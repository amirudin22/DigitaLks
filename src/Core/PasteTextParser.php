<?php

namespace Core;

/**
 * Parser untuk text plain yang di-paste oleh user
 * Mendukung format:
 * 1. Pertanyaan di sini?
 * A. Opsi A
 * B. Opsi B
 * C. Opsi C
 * D. Opsi D
 * Jawaban: A
 * 
 * Atau variasi lainnya
 * 
 * ✅ INTEGRATED: Mendukung parsing dari file contohsoal.md
 * Dapat membaca langsung dari file atau dari teks yang di-paste
 */
class PasteTextParser
{
    private $exampleFilePath = '';
    
    /**
     * Constructor - set path untuk contohsoal.md
     * @param string $exampleFilePath Path ke contohsoal.md (optional)
     */
    public function __construct(string $exampleFilePath = '')
    {
        if (empty($exampleFilePath)) {
            // Default: cari file di root project
            $this->exampleFilePath = dirname(__DIR__, 2) . '/contohsoal.md';
        } else {
            $this->exampleFilePath = $exampleFilePath;
        }
    }
    /**
     * Parse dari file contohsoal.md
     * 
     * @return array Result parsing
     */
    public function parseFromFile(): array
    {
        if (!file_exists($this->exampleFilePath)) {
            return [
                'metadata' => [
                    'judul' => 'Latihan Soal',
                    'mata_pelajaran' => 'Umum',
                    'kelas_target' => '-',
                    'status' => 'draft',
                    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ],
                'soal' => [],
                'errors' => ["File tidak ditemukan: {$this->exampleFilePath}"]
            ];
        }

        $text = file_get_contents($this->exampleFilePath);
        if ($text === false) {
            return [
                'metadata' => [
                    'judul' => 'Latihan Soal',
                    'mata_pelajaran' => 'Umum',
                    'kelas_target' => '-',
                    'status' => 'draft',
                    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ],
                'soal' => [],
                'errors' => ["Gagal membaca file: {$this->exampleFilePath}"]
            ];
        }

        // Parse dengan metadata dari file
        return $this->parse($text, [
            'judul' => 'Contoh Soal dari File',
            'source' => 'contohsoal.md'
        ]);
    }

    /**
     * Parse text plain yang di-paste menjadi array soal terstruktur
     * Optimized untuk format Cici AI
     * 
     * @param string $text Text yang di-paste atau dari file
     * @param array $metadata Metadata soal (judul, mapel, kelas)
     * @return array Array terstruktur dengan soal dan metadata
     */
    public function parse(string $text, array $metadata = []): array
    {
        $result = [
            'metadata' => array_merge([
                'judul' => 'Latihan Soal (Paste)',
                'mata_pelajaran' => 'Umum',
                'kelas_target' => '-',
                'status' => 'draft',
                'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ], $metadata),
            'soal' => [],
            'errors' => []
        ];

        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // ✅ TRY PATTERN 0 FIRST: Optimized Cici AI Format (Flexible)
        // Format:
        // 1. Question text?
        // A. Option A (atau A. Option A atau A) Option A)
        // B. Option B
        // C. Option C
        // D. Option D
        // Jawaban: A (atau Kunci: A)
        
        // Pattern dengan fleksibilitas untuk berbagai format opsi dan jawaban
        // Menggunakan .*? untuk pertanyaan agar bisa membaca multi-line (seperti teks panjang atau kutipan)
        $pattern0 = '/(\d+)\.\s+(.*?)\n' .               // Nomor dan pertanyaan (multiline)
                    '\s*([A-D])[.)]\s*([^\n]+)\n' .      // Opsi A (fleksibel)
                    '\s*([A-D])[.)]\s*([^\n]+)\n' .      // Opsi B
                    '\s*([A-D])[.)]\s*([^\n]+)\n' .      // Opsi C
                    '\s*([A-D])[.)]\s*([^\n]+)\n' .      // Opsi D
                    '\s*(?:Jawaban|Kunci|JAWABAN|KUNCI)\s*:\s*([A-D])/is';  // Jawaban (fleksibel)
        
        if (preg_match_all($pattern0, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result['soal'][] = [
                    'id' => count($result['soal']) + 1,
                    'tag' => "Soal " . trim($match[1]),
                    'pertanyaan' => trim($match[2]),
                    'opsi' => [
                        strtoupper($match[3]) => trim($match[4]),
                        strtoupper($match[5]) => trim($match[6]),
                        strtoupper($match[7]) => trim($match[8]),
                        strtoupper($match[9]) => trim($match[10])
                    ],
                    'jawaban_benar' => strtoupper(trim($match[11])),
                    'pembahasan' => ''
                ];
            }
            
            // Early exit jika pattern 0 berhasil
            if (!empty($result['soal'])) {
                return $result;
            }
        }

        // ✅ FALLBACK: Split by double newline untuk soal blocks
        $blocks = preg_split('/\n\s*\n/', trim($text));

        $soalId = 1;

        foreach ($blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }

            $soal = $this->parseBlock($block, $soalId);
            if ($soal !== null) {
                $result['soal'][] = $soal;
                $soalId++;
            } else {
                // Jika parsing gagal, simpan error tapi lanjut
                $lines = explode("\n", trim($block));
                if (!empty($lines[0])) {
                    $result['errors'][] = "Gagal parse: " . substr($lines[0], 0, 50) . "...";
                }
            }
        }

        return $result;
    }

    /**
     * Parse satu block soal
     */
    private function parseBlock(string $block, int $id): ?array
    {
        $lines = array_filter(array_map('trim', explode("\n", $block)));
        if (count($lines) < 6) {
            return null; // Minimal: pertanyaan + 4 opsi + jawaban
        }

        $lines = array_values($lines); // Re-index array

        $pertanyaanLines = [];
        $opsi = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
        $jawabanLine = '';
        $currentSegment = 'pertanyaan'; // 'pertanyaan', 'opsi', 'jawaban'
        $currentOpsi = '';

        foreach ($lines as $line) {
            // Check apakah line adalah opsi dengan berbagai format...
            if (preg_match('/^([A-D])[.)]\s*(.+)$/i', $line, $match)) {
                $key = strtoupper($match[1]);
                $opsi[$key] = trim($match[2]);
                $currentSegment = 'opsi';
                $currentOpsi = $key;
                continue;
            }
            // Check apakah line adalah jawaban marker dengan berbagai format...
            elseif (preg_match('/^(?:Jawaban|jawaban|Kunci|kunci|Ans|ans|JAWABAN|KUNCI)\s*:\s*([A-D])/i', $line, $match)) {
                $jawabanLine = strtoupper($match[1]);
                $currentSegment = 'jawaban';
                continue;
            }
            elseif (preg_match('/^(?:Jawaban|jawaban|JAWABAN)\s*:\s*([A-D])[)\s.]*$/i', $line, $match)) {
                $jawabanLine = strtoupper($match[1]);
                $currentSegment = 'jawaban';
                continue;
            }

            if ($currentSegment === 'pertanyaan') {
                $pertanyaanLines[] = $line;
            } elseif ($currentSegment === 'opsi' && $currentOpsi) {
                $opsi[$currentOpsi] .= "\n" . trim($line);
            }
        }

        $pertanyaan = implode("\n", $pertanyaanLines);
        $pertanyaan = preg_replace('/^\d+\.\s+/', '', $pertanyaan);
        $pertanyaan = trim($pertanyaan);

        if (empty($pertanyaan)) {
            return null;
        }

        // Validasi: semua opsi harus terisi
        if (empty($opsi['A']) || empty($opsi['B']) || empty($opsi['C']) || empty($opsi['D'])) {
            return null;
        }

        // Validasi: harus ada jawaban
        if (empty($jawabanLine) || !in_array($jawabanLine, ['A', 'B', 'C', 'D'])) {
            return null;
        }

        return [
            'id' => $id,
            'tag' => "Soal $id",
            'pertanyaan' => $pertanyaan,
            'opsi' => $opsi,
            'jawaban_benar' => $jawabanLine,
            'pembahasan' => ''
        ];
    }

    /**
     * Validate hasil parsing
     */
    public function validateResult(array $result): array
    {
        $issues = [];

        if (empty($result['soal'])) {
            $issues[] = "Tidak ada soal yang berhasil di-parse. Periksa format teks Anda.";
            return $issues;
        }

        $soalCount = count($result['soal']);
        $errorCount = count($result['errors'] ?? []);

        if ($errorCount > 0 && $errorCount >= $soalCount / 2) {
            $issues[] = "Tingkat kesalahan parsing tinggi ($errorCount/$soalCount). Format mungkin tidak sesuai.";
        }

        // Validasi setiap soal
        foreach ($result['soal'] as $idx => $soal) {
            $soalNo = $idx + 1;

            if (empty($soal['pertanyaan'])) {
                $issues[] = "Soal $soalNo: Pertanyaan kosong";
            }

            if (empty($soal['jawaban_benar'])) {
                $issues[] = "Soal $soalNo: Jawaban tidak ditemukan";
            }
        }

        return $issues;
    }

    /**
     * ✅ NEW: Get contohsoal.md content untuk ditampilkan di UI
     * Gunakan ini untuk menampilkan example format
     * 
     * @return string Content dari contohsoal.md atau template default
     */
    public function getExampleContent(): string
    {
        if (file_exists($this->exampleFilePath)) {
            $content = file_get_contents($this->exampleFilePath);
            if ($content !== false && !empty($content)) {
                return $content;
            }
        }

        // Return default template jika file tidak ada/kosong
        return $this->getDefaultTemplate();
    }

    /**
     * ✅ NEW: Get default template untuk user
     */
    public function getDefaultTemplate(): string
    {
        return <<<'TEMPLATE'
1. Bangun ruang yang memiliki 6 sisi persegi adalah...
A. Kubus
B. Balok
C. Tabung
D. Kerucut
Jawaban: A

2. Berapa jumlah rusuk pada balok?
A. 8
B. 10
C. 12
D. 14
Jawaban: C

3. Bangun ruang yang memiliki alas berbentuk lingkaran dan puncak meruncing adalah...
A. Kerucut
B. Bola
C. Prisma segitiga
D. Tabung
Jawaban: A
TEMPLATE;
    }

    /**
     * ✅ NEW: Get file info
     */
    public function getFileInfo(): array
    {
        $exists = file_exists($this->exampleFilePath);
        $size = 0;
        $lines = 0;
        
        if ($exists) {
            $content = file_get_contents($this->exampleFilePath);
            $size = strlen($content);
            $lines = substr_count($content, "\n") + 1;
        }

        return [
            'path' => $this->exampleFilePath,
            'exists' => $exists,
            'size' => $size,
            'lines' => $lines,
            'readable' => $exists && is_readable($this->exampleFilePath)
        ];
    }
}
?>
