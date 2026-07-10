<?php

namespace Core;

/**
 * JSON Prompt Generator untuk Deterministic Question Generation
 * 
 * Menghasilkan prompt JSON yang konsisten untuk AI/Claude
 * Output bisa langsung di-paste ke AI untuk generate soal dengan format terstandardisasi
 */
class PromptGenerator
{
    /**
     * Kurikulum yang didukung
     */
    const KURIKULUM = [
        'K13' => 'Kurikulum 2013',
        'KURIKULUM_MERDEKA' => 'Kurikulum Merdeka',
        'AADC' => 'Adjusted And Developed Curriculum',
        'CUSTOM' => 'Custom Curriculum'
    ];

    /**
     * Tingkat kesulitan
     */
    const TINGKAT_KESULITAN = [
        'MUDAH' => 'Mudah (Basic Recall)',
        'SEDANG' => 'Sedang (Understanding & Application)',
        'SULIT' => 'Sulit (Analysis & Evaluation)',
        'CAMPURAN' => 'Campuran (30% Mudah, 50% Sedang, 20% Sulit)'
    ];

    /**
     * Mode ujian
     */
    const MODE_UJIAN = [
        'LATIHAN_HARIAN' => 'Latihan Harian (Formative)',
        'TRY_OUT' => 'Try Out (Simulation)',
        'ULANGAN_HARIAN' => 'Ulangan Harian (Assessment)',
        'MID_TERM' => 'Mid Term Exam',
        'FINAL_EXAM' => 'Final Exam',
        'KOMPREHENSIF' => 'Komprehensif (All Topics)'
    ];

    /**
     * ✅ NEW: Kelas yang diizinkan untuk TRY_OUT (UNAS/TKA)
     * Hanya kelas 6 (UNAS SD), 9 (UNAS SMP), 12 (TKA SMA)
     */
    const TRYOUT_ALLOWED_KELAS = ['6 SD', '9 SMP', '12 SMA'];

    /**
     * ✅ NEW: Mata Pelajaran yang diizinkan untuk setiap TRY_OUT
     */
    const TRYOUT_MAPEL = [
        '6 SD' => ['Matematika', 'Bahasa Indonesia'],  // UNAS SD
        '9 SMP' => ['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS'],  // UNAS SMP
        '12 SMA' => ['Matematika', 'Bahasa Indonesia', 'Bahasa Inggris', 'Geografi']  // TKA SMA (sampel)
    ];

    /**
     * Kelas yang didukung dengan referensi materi
     */
    const KELAS = [
        'SD' => ['1', '2', '3', '4', '5', '6'],
        'SMP' => ['7', '8', '9'],
        'SMA' => ['10', '11', '12'],
        'SMK' => ['10', '11', '12']
    ];

    /**
     * Referensi Materi Berdasarkan Kelas (untuk panduan orang tua/guru)
     */
    const MATERI_REFERENCE = [
        '1 SD' => [
            'Matematika' => ['Bilangan 1-10', 'Penjumlahan Dasar', 'Pengurangan Dasar', 'Bentuk Geometri', 'Pengukuran Panjang'],
            'Bahasa Indonesia' => ['Huruf dan Suara', 'Kata-kata Sederhana', 'Membaca Nyaring', 'Menulis Nama', 'Kalimat Sederhana'],
            'IPA' => ['Makhluk Hidup', 'Bagian Tubuh', 'Kebutuhan Hidup', 'Cuaca dan Musim', 'Lingkungan Sekitar'],
            'IPS' => ['Keluarga', 'Rumah dan Sekolah', 'Lingkungan Rumah', 'Kenampakan Alam', 'Budaya Lokal']
        ],
        '2 SD' => [
            'Matematika' => ['Bilangan 1-100', 'Penjumlahan Dua Digit', 'Pengurangan Dua Digit', 'Perkalian Dasar', 'Pengukuran Berat'],
            'Bahasa Indonesia' => ['Membaca Pemahaman', 'Menulis Kalimat', 'Tata Bahasa Dasar', 'Cerita Pendek', 'Puisi Sederhana'],
            'IPA' => ['Tumbuhan', 'Hewan', 'Kesehatan Tubuh', 'Sifat Benda', 'Musim dan Cuaca'],
            'IPS' => ['Identitas Diri', 'Kehidupan Keluarga', 'Pekerjaan', 'Alat Transportasi', 'Hari Raya']
        ],
        '3 SD' => [
            'Matematika' => ['Bilangan 1-1000', 'Operasi Hitung Tiga Digit', 'Uang', 'Waktu', 'Perkalian dan Pembagian'],
            'Bahasa Indonesia' => ['Teks Narasi', 'Deskripsi', 'Dialog', 'Puisi', 'Surat'],
            'IPA' => ['Sifat Benda', 'Energi', 'Gerak dan Gaya', 'Makanan dan Rantai Makanan', 'Mata Air dan Sumber Daya Alam'],
            'IPS' => ['Provinsi Indonesia', 'Budaya dan Tradisi', 'Pemimpin', 'Sejarah Lokal', 'Ekonomi Sederhana']
        ],
        '4 SD' => [
            'Matematika' => ['Bilangan Bulat', 'Operasi Pecahan', 'Kelipatan dan Faktor', 'Geometri Bangun Datar', 'Luas dan Keliling'],
            'Bahasa Indonesia' => ['Teks Eksplanasi', 'Teks Prosedur', 'Puisi Rakyat', 'Berita', 'Iklan dan Slogan'],
            'IPA' => ['Sistem Pencernaan', 'Daur Air', 'Ekosistem', 'Benda dan Sifatnya', 'Energi Alternatif'],
            'IPS' => ['Peta Indonesia', 'Suku Bangsa', 'Keragaman Budaya', 'Sejarah Kerajaan', 'Jenis Usaha']
        ],
        '5 SD' => [
            'Matematika' => ['Bilangan Desimal', 'Operasi Pecahan Kompleks', 'Persentase', 'Bangun Ruang', 'Volume dan Luas Permukaan'],
            'Bahasa Indonesia' => ['Cerita Fantasi', 'Teks Persuasi', 'Laporan Hasil Observasi', 'Pantun', 'Struktur Teks'],
            'IPA' => ['Sistem Gerak', 'Sistem Peredaran Darah', 'Fotosintesis', 'Cahaya dan Optik', 'Gelombang dan Bunyi'],
            'IPS' => ['Pemerintahan Indonesia', 'Hak dan Kewajiban', 'Peristiwa Bersejarah', 'Letak Geografis', 'Perdagangan']
        ],
        '6 SD' => [
            'Matematika' => ['FPB dan KPK', 'Operasi Bilangan Bulat', 'Skala dan Perbandingan', 'Statistika Dasar', 'Peluang Sederhana'],
            'Bahasa Indonesia' => ['Teks Fiksi dan Non-Fiksi', 'Analisis Cerita', 'Drama', 'Artikel', 'Membuat Glosarium'],
            'IPA' => ['Sistem Ekskresi', 'Reproduksi', 'Bumi dan Tata Surya', 'Perubahan Lingkungan', 'Teknologi'],
            'IPS' => ['Dinamika Kehidupan Masyarakat', 'Interaksi Antar Ruang', 'Sejarah Nasional', 'Perjuangan Kemerdekaan', 'Globalisasi']
        ],
        '7 SMP' => [
            'Matematika' => ['Bilangan Bulat dan Pecahan', 'Himpunan', 'Aljabar Dasar', 'Persamaan Linear', 'Pertidaksamaan Linear'],
            'Bahasa Indonesia' => ['Teks Laporan Hasil Observasi', 'Teks Eksplanasi', 'Teks Prosedur', 'Puisi Modernyang Teks Deskripsi'],
            'IPA' => ['Zat dan Wujudnya', 'Kalor', 'Tekanan', 'Getaran dan Gelombang', 'Cahaya dan Penglihatan'],
            'IPS' => ['Manusia, Tempat, Lingkungan', 'Interaksi Sosial', 'Sosialisasi dan Kepribadian', 'Dinamika Kelompok', 'Mobilitas Sosial']
        ],
        '8 SMP' => [
            'Matematika' => ['Relasi dan Fungsi', 'Persamaan Garis Lurus', 'Sistem Persamaan Linear', 'Teorema Pythagoras', 'Lingkaran'],
            'Bahasa Indonesia' => ['Teks Berita', 'Iklan, Slogan, Poster', 'Cerpen', 'Biografi', 'Pidato'],
            'IPA' => ['Struktur dan Fungsi Jaringan Tumbuhan', 'Struktur Jaringan Hewan', 'Organisasi Kehidupan', 'Fotosintesis', 'Gerak dan Gaya'],
            'IPS' => ['Kondisi Alam Indonesia', 'Kehidupan Masyarakat', 'Ekonomi dan Pemanfaatan Sumber Daya', 'Transportasi dan Komunikasi', 'Keanekaragaman Budaya']
        ],
        '9 SMP' => [
            'Matematika' => ['Bilangan Berpangkat', 'Barisan dan Deret', 'Persamaan Kuadrat', 'Fungsi Kuadrat', 'Transformasi Geometri'],
            'Bahasa Indonesia' => ['Teks Eksposisi', 'Teks Persuasi', 'Teks Deskripsi Lanjut', 'Analisis Karya Sastra', 'Resensi'],
            'IPA' => ['Sistem Pernapasan', 'Sistem Pencernaan', 'Sistem Ekskresi', 'Reproduksi Manusia', 'Konsep Energi'],
            'IPS' => ['Sejarah Kerajaan Nusantara', 'Masa Penjajahan', 'Pergerakan Nasional', 'Proklamasi Kemerdekaan', 'Sistem Pemerintahan']
        ],
        '10 SMA' => [
            'Matematika' => ['Fungsi, Eksponen, Logaritma', 'Pertidaksamaan Kuadrat', 'Program Linear', 'Trigonometri Dasar', 'Dimensi Tiga'],
            'Bahasa Indonesia' => ['Teks Deskripsi dan Eksposisi', 'Argumentasi', 'Resensi Buku dan Film', 'Cerpen Analisis', 'Drama Modern'],
            'IPA' => ['Kinematika', 'Dinamika', 'Kerja dan Energi', 'Elastisitas Bahan', 'Gelombang Mekanik'],
            'IPS' => ['Pengetahuan Sosial', 'Interaksi Sosial', 'Lembaga Sosial', 'Struktur dan Diferensiasi Sosial', 'Konlik dan Integrasi']
        ],
        '11 SMA' => [
            'Matematika' => ['Sukses dan Komposisi Fungsi', 'Limit Fungsi', 'Turunan Fungsi', 'Integral Tak Tentu', 'Statistik Lanjutan'],
            'Bahasa Indonesia' => ['Teks Editorial', 'Opini Publik', 'Analisis Wacana', 'Sastra Kontemporer', 'Karya Ilmiah'],
            'IPA' => ['Getaran dan Gelombang Lanjut', 'Gelombang Bunyi', 'Optik Geometrik', 'Cahaya Sebagai Gelombang', 'Termodinamika'],
            'IPS' => ['Stratifikasi Sosial', 'Mobilitas Sosial Lanjut', 'Sosialisasi Lanjut', 'Deviasi Sosial', 'Lembaga Pemerintahan']
        ],
        '12 SMA' => [
            'Matematika' => ['Integral Tentu', 'Aplikasi Integral', 'Program Linear Lanjutan', 'Peluang Lanjutan', 'Statistik Deskriptif'],
            'Bahasa Indonesia' => ['Apresiasi Sastra', 'Kritik Sastra', 'Puitika', 'Prosa Kontemporer', 'Teori Bahasa'],
            'IPA' => ['Medan Gravitasi', 'Medan Listrik', 'Medan Magnet', 'Gelombang Elektromagnetik', 'Fisika Inti'],
            'IPS' => ['Pertumbuhan Ekonomi', 'Pembangunan Ekonomi', 'Sistem Ekonomi Internasional', 'Globalisasi Ekonomi', 'Pemberdayaan Masyarakat']
        ]
    ];

    /**
     * Template prompt berdasarkan preset
     */
    private array $templates = [];

    public function __construct()
    {
        $this->initializeTemplates();
    }

    /**
     * Initialize prompt templates untuk berbagai mata pelajaran
     */
    private function initializeTemplates(): void
    {
        $this->templates = [
            'matematika' => [
                'description' => 'Soal Matematika',
                'tips' => [
                    'Fokus pada konsep dan aplikasi',
                    'Sertakan langkah-langkah penyelesaian',
                    'Variasikan tipe soal (hitungan, logika, permasalahan)',
                    'Gunakan angka yang realistis'
                ],
                'format_khusus' => 'Setiap soal harus memiliki 4 opsi jawaban (A, B, C, D)'
            ],
            'bahasa_indonesia' => [
                'description' => 'Soal Bahasa Indonesia',
                'tips' => [
                    'Gunakan teks atau kutipan dari literatur',
                    'Variasikan tipe soal (pemahaman, analisis, aplikasi)',
                    'Perhatikan ejaan dan tata bahasa yang benar',
                    'Berikan konteks yang jelas'
                ],
                'format_khusus' => 'Soal dapat berbentuk pertanyaan pemahaman, analisis, atau aplikasi'
            ],
            'ipa' => [
                'description' => 'Soal IPA (Sains)',
                'tips' => [
                    'Berdasarkan konsep ilmiah yang akurat',
                    'Gunakan terminologi yang tepat',
                    'Variasikan: teori, praktik, aplikasi',
                    'Sesuaikan dengan tingkat kognitif'
                ],
                'format_khusus' => 'Setiap soal harus scientifically accurate'
            ],
            'ips' => [
                'description' => 'Soal IPS (Sosial)',
                'tips' => [
                    'Berdasarkan fakta historis dan geografis',
                    'Gunakan data terkini',
                    'Variasikan perspektif dan interpretasi',
                    'Hindari bias ideologi'
                ],
                'format_khusus' => 'Soal dapat merangkum berbagai aspek sosial'
            ],
            'bahasa_inggris' => [
                'description' => 'Soal Bahasa Inggris',
                'tips' => [
                    'Gunakan grammar yang benar',
                    'Variasikan skill: reading, vocabulary, grammar',
                    'Gunakan konteks yang realistis',
                    'Sesuaikan dengan level proficiency'
                ],
                'format_khusus' => 'Soal dapat berbentuk pilihan ganda atau short answer'
            ],
            'pkn' => [
                'description' => 'Soal PKn (Civics)',
                'tips' => [
                    'Berdasarkan konstitusi dan hukum berlaku',
                    'Ajarkan nilai-nilai kebangsaan',
                    'Gunakan contoh kontekstual',
                    'Hindari propaganda'
                ],
                'format_khusus' => 'Soal harus mendidik tentang kewajiban dan hak'
            ]
        ];
    }

    /**
     * Generate deterministic JSON prompt
     */
    public function generatePrompt(array $params): array
    {
        // Validasi input
        $validation = $this->validateInput($params);
        if (!empty($validation['errors'])) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Extract parameters
        $kelas = $params['kelas'] ?? '';
        $mapel = strtolower(str_replace(' ', '_', $params['mata_pelajaran'] ?? ''));
        $kurikulum = $params['kurikulum'] ?? 'K13';
        $materi = $params['materi'] ?? '';
        $jumlahSoal = (int)($params['jumlah_soal'] ?? 10);
        $tingkat = $params['tingkat_kesulitan'] ?? 'SEDANG';
        $mode = $params['mode_ujian'] ?? 'LATIHAN_HARIAN';

        // Handle TRY_OUT mode: materi otomatis mixed dari semua kelas
        if ($mode === 'TRY_OUT') {
            $materi = '[AUTO-MIX] Semua materi kelas ' . $kelas . ' (Try Out - Simulasi UN)';
        }

        // Tentukan template tips berdasarkan mapel
        $templateTips = $this->templates[$mapel] ?? [
            'description' => ucfirst(str_replace('_', ' ', $mapel)),
            'tips' => ['Buat soal yang sesuai dengan kurikulum berlaku'],
            'format_khusus' => 'Setiap soal harus memiliki 4 opsi jawaban (A, B, C, D)'
        ];

        // Hitung distribusi tingkat kesulitan
        $distribusi = $this->getDistribusiKesulitan($tingkat, $jumlahSoal);

        // Build prompt
        $prompt = [
            'version' => '1.0',
            'type' => 'question_generation',
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            
            'metadata' => [
                'kelas' => $kelas,
                'mata_pelajaran' => $params['mata_pelajaran'],
                'kurikulum' => self::KURIKULUM[$kurikulum] ?? $kurikulum,
                'materi' => $materi,
                'mode_ujian' => self::MODE_UJIAN[$mode] ?? $mode,
                'tingkat_kesulitan' => self::TINGKAT_KESULITAN[$tingkat] ?? $tingkat,
            ],

            'requirements' => [
                'jumlah_soal' => $jumlahSoal,
                'format' => 'Pilihan Ganda (Multiple Choice)',
                'opsi_per_soal' => 4,
                'opsi_label' => ['A', 'B', 'C', 'D'],
                'distribusi_kesulitan' => $distribusi,
                'include_jawaban_kunci' => true,
            ],

            'content_guidelines' => [
                'kurikulum_acuan' => self::KURIKULUM[$kurikulum] ?? $kurikulum,
                'materi_utama' => $materi,
                'tips_konten' => $templateTips['tips'],
                'format_khusus' => $templateTips['format_khusus'],
                'perhatian_khusus' => $this->getPerhatianKhusus($mapel, $tingkat, $mode)
            ],

            'format_output' => [
                'struktur_soal' => 'Untuk SETIAP soal, gunakan format EXACT ini:

1. [Pertanyaan soal di sini?]
A. [Opsi jawaban A]
B. [Opsi jawaban B]
C. [Opsi jawaban C]
D. [Opsi jawaban D]
Jawaban: [A/B/C/D]

2. [Pertanyaan soal berikutnya?]
...',
                'catatan_penting' => [
                    'Setiap soal HARUS dipisahkan dengan blank line kosong',
                    'Jawaban harus jelas dan unik (tidak ambiguous)',
                    'Hindari "none of the above" atau "all of the above"',
                    'Opsi yang salah harus plausibel (distractor yang baik)',
                    'Numbering: 1, 2, 3, ... (NOT a, b, c, ...)'
                ],
                'example_format' => $this->getExampleFormat($mapel)
            ],

            'quality_control' => [
                'checklist' => [
                    'Soal sesuai dengan kurikulum ' . (self::KURIKULUM[$kurikulum] ?? $kurikulum),
                    'Soal sesuai dengan level kelas ' . $kelas,
                    'Soal sesuai dengan materi: ' . $materi,
                    'Tingkat kesulitan sesuai: ' . (self::TINGKAT_KESULITAN[$tingkat] ?? $tingkat),
                    'Setiap soal memiliki jawaban yang clear dan correct',
                    'Opsi jawaban yang salah adalah distractor yang plausibel',
                    'Tidak ada typo atau grammar error',
                    'Format output EXACT sesuai dengan contoh di atas'
                ],
                'validation_rules' => [
                    'rule_1' => 'Minimal 3 opsi harus plausibel (tidak obvious)',
                    'rule_2' => 'Jawaban benar harus RANDOM (tidak selalu A, B, C, atau D)',
                    'rule_3' => 'Variasi panjang pertanyaan dan opsi (jangan semua panjang atau semua pendek)',
                    'rule_4' => 'Hindari negative phrasing kecuali perlu',
                    'rule_5' => 'Setiap soal independent (tidak dependent pada soal lain)'
                ]
            ],

            'context_dan_tips' => [
                'konteks_pembelajaran' => $this->getKonteksJam($mode, $kelas, $materi),
                'tips_khusus' => $this->getTipsKhusus($mode, $tingkat),
                'motivasi' => 'Hasilkan soal yang BERKUALITAS, BERAGAM, dan EDUCATIVE. Setiap soal harus menguji pemahaman siswa, bukan hanya hafalan.'
            ],

            'instruksi_final' => [
                'do' => [
                    '✓ Generate EXACTLY ' . $jumlahSoal . ' soal',
                    '✓ Gunakan format EXACT seperti di atas',
                    '✓ Pisahkan setiap soal dengan 1 blank line',
                    '✓ Berikan Jawaban: X untuk setiap soal',
                    '✓ Review kualitas sebelum output'
                ],
                'dont' => [
                    '✗ Jangan generate kurang atau lebih dari ' . $jumlahSoal . ' soal',
                    '✗ Jangan ubah format',
                    '✗ Jangan lupa jawaban kunci',
                    '✗ Jangan sertakan penjelasan (explanation)',
                    '✗ Jangan sertakan pembahasan panjang'
                ]
            ]
        ];

        return [
            'success' => true,
            'prompt' => $prompt,
            'prompt_text' => $this->formatPromptAsText($prompt),
            'copy_paste_ready' => true,
            'instruction' => 'Silakan copy seluruh prompt di bawah ini dan paste ke Claude/ChatGPT untuk generate soal'
        ];
    }

    /**
     * Validasi input
     */
    private function validateInput(array $params): array
    {
        $errors = [];

        if (empty($params['kelas'])) {
            $errors[] = 'Kelas harus dipilih';
        }

        if (empty($params['mata_pelajaran'])) {
            $errors[] = 'Mata pelajaran harus dipilih';
        }

        // ✅ NEW: Validasi khusus untuk TRY_OUT mode
        $mode = $params['mode_ujian'] ?? 'LATIHAN_HARIAN';
        if ($mode === 'TRY_OUT') {
            $kelas = $params['kelas'] ?? '';
            
            // Cek apakah kelas diizinkan untuk TRY_OUT
            if (!in_array($kelas, self::TRYOUT_ALLOWED_KELAS)) {
                $errors[] = "❌ Mode TRY_OUT hanya tersedia untuk kelas: " . implode(', ', self::TRYOUT_ALLOWED_KELAS) . ". Anda memilih kelas: $kelas";
            }
            
            // Cek apakah mata pelajaran valid untuk TRY_OUT
            $mapel = $params['mata_pelajaran'] ?? '';
            if (isset(self::TRYOUT_MAPEL[$kelas])) {
                if (!in_array($mapel, self::TRYOUT_MAPEL[$kelas])) {
                    $validMapel = implode(', ', self::TRYOUT_MAPEL[$kelas]);
                    $errors[] = "❌ Untuk $kelas di TRY_OUT, mata pelajaran yang tersedia: $validMapel. Anda memilih: $mapel";
                }
            }
            
            // TRY_OUT tidak perlu input materi (akan auto-mixed)
            // Jadi abaikan validasi materi untuk TRY_OUT
        } else {
            // Untuk non-TRY_OUT: materi harus diisi
            if (empty($params['materi'])) {
                $errors[] = 'Materi harus diisi';
            }
        }

        $jumlahSoal = (int)($params['jumlah_soal'] ?? 0);
        if ($jumlahSoal < 1 || $jumlahSoal > 100) {
            $errors[] = 'Jumlah soal harus antara 1-100';
        }

        return ['errors' => $errors];
    }

    /**
     * Hitung distribusi kesulitan berdasarkan tingkat
     */
    private function getDistribusiKesulitan(string $tingkat, int $jumlahSoal): array
    {
        switch ($tingkat) {
            case 'MUDAH':
                return [
                    'mudah' => $jumlahSoal,
                    'sedang' => 0,
                    'sulit' => 0,
                    'persentase' => '100% Mudah'
                ];
            case 'SEDANG':
                return [
                    'mudah' => (int)($jumlahSoal * 0.2),
                    'sedang' => (int)($jumlahSoal * 0.6),
                    'sulit' => $jumlahSoal - (int)($jumlahSoal * 0.8),
                    'persentase' => '20% Mudah, 60% Sedang, 20% Sulit'
                ];
            case 'SULIT':
                return [
                    'mudah' => 0,
                    'sedang' => (int)($jumlahSoal * 0.3),
                    'sulit' => (int)($jumlahSoal * 0.7),
                    'persentase' => '30% Sedang, 70% Sulit'
                ];
            case 'CAMPURAN':
            default:
                return [
                    'mudah' => (int)($jumlahSoal * 0.3),
                    'sedang' => (int)($jumlahSoal * 0.5),
                    'sulit' => $jumlahSoal - (int)($jumlahSoal * 0.8),
                    'persentase' => '30% Mudah, 50% Sedang, 20% Sulit'
                ];
        }
    }

    /**
     * Get perhatian khusus berdasarkan mapel dan mode
     */
    private function getPerhatianKhusus(string $mapel, string $tingkat, string $mode): array
    {
        $tips = [];

        // Tips berdasarkan mapel
        if (strpos($mapel, 'matematika') !== false) {
            $tips[] = 'Gunakan bilangan bulat atau desimal yang realistis';
            $tips[] = 'Variasikan tipe soal: operasi dasar, problem solving, reasoning';
        }

        if (strpos($mapel, 'bahasa') !== false) {
            $tips[] = 'Pastikan grammar dan ejaan benar';
            $tips[] = 'Variasikan jenis soal: vocab, comprehension, analysis';
        }

        if (strpos($mapel, 'ipa') !== false) {
            $tips[] = 'Semua informasi harus scientifically accurate';
            $tips[] = 'Gunakan istilah ilmiah yang tepat';
        }

        // Tips berdasarkan mode ujian
        if ($mode === 'LATIHAN_HARIAN') {
            $tips[] = 'Fokus pada pembelajaran harian, bukan cumulative';
        } else if ($mode === 'TRY_OUT') {
            $tips[] = 'Soal lebih challenging, simulasi ujian sebenarnya';
        } else if ($mode === 'FINAL_EXAM') {
            $tips[] = 'Semua materi semester harus tercakup (comprehensive)';
        }

        return $tips;
    }

    /**
     * Get konteks pembelajaran berdasarkan jam/mode
     */
    private function getKonteksJam(string $mode, string $kelas, string $materi): string
    {
        $konteks = "Mode: $mode, Kelas: $kelas, Materi: $materi";

        if ($mode === 'LATIHAN_HARIAN') {
            $konteks .= ". Ini adalah latihan rutin untuk siswa memahami materi harian.";
        } else if ($mode === 'TRY_OUT') {
            $konteks .= ". Ini adalah simulasi ujian untuk mempersiapkan siswa.";
        } else if ($mode === 'FINAL_EXAM') {
            $konteks .= ". Ini adalah ujian akhir yang komprehensif.";
        }

        return $konteks;
    }

    /**
     * Get tips khusus berdasarkan mode dan tingkat
     */
    private function getTipsKhusus(string $mode, string $tingkat): array
    {
        $tips = [];

        if ($mode === 'LATIHAN_HARIAN' && $tingkat === 'MUDAH') {
            $tips[] = 'Focus on building confidence dan basic understanding';
        } else if ($mode === 'TRY_OUT' && $tingkat === 'SULIT') {
            $tips[] = 'Challenge students dengan soal-soal yang memerlukan deeper analysis';
        }

        return $tips;
    }

    /**
     * Get example format soal berdasarkan mapel
     */
    private function getExampleFormat(string $mapel): string
    {
        if (strpos($mapel, 'matematika') !== false) {
            return <<<'EXAMPLE'
1. Berapa hasil dari 25 + 17?
A. 32
B. 42
C. 52
D. 62
Jawaban: B

2. Sebuah persegi panjang memiliki panjang 10 cm dan lebar 5 cm. Berapa luasnya?
A. 30 cm²
B. 50 cm²
C. 40 cm²
D. 60 cm²
Jawaban: B
EXAMPLE;
        } else if (strpos($mapel, 'bahasa') !== false) {
            return <<<'EXAMPLE'
1. Apa arti dari kata "kontemplasi"?
A. Memikirkan dengan mendalam
B. Berlari cepat
C. Menulis dengan rapi
D. Berbicara dengan keras
Jawaban: A

2. Dalam kalimat "Dia adalah orang yang jujur", kata "jujur" merupakan...?
A. Kata benda
B. Kata sifat
C. Kata kerja
D. Kata keterangan
Jawaban: B
EXAMPLE;
        } else {
            return <<<'EXAMPLE'
1. Apa yang dimaksud dengan...?
A. Penjelasan A
B. Penjelasan B
C. Penjelasan C
D. Penjelasan D
Jawaban: A

2. Mengapa ...?
A. Alasan A
B. Alasan B
C. Alasan C
D. Alasan D
Jawaban: B
EXAMPLE;
        }
    }

    /**
     * Format prompt sebagai text untuk copy-paste
     */
    private function formatPromptAsText(array $prompt): string
    {
        $text = "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        $text .= "║                        JSON PROMPT - QUESTION GENERATION                   ║\n";
        $text .= "║                          (Ready for AI/Claude/ChatGPT)                     ║\n";
        $text .= "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

        $text .= "📋 METADATA:\n";
        $text .= "─────────────────────────────────────────────────────────────────────────────\n";
        foreach ($prompt['metadata'] as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', $key));
            $text .= "  • $label: $value\n";
        }
        $text .= "\n";

        $text .= "📊 REQUIREMENTS:\n";
        $text .= "─────────────────────────────────────────────────────────────────────────────\n";
        $text .= "  • Jumlah Soal: " . $prompt['requirements']['jumlah_soal'] . "\n";
        $text .= "  • Format: " . $prompt['requirements']['format'] . "\n";
        $text .= "  • Opsi per Soal: " . $prompt['requirements']['opsi_per_soal'] . "\n";
        $text .= "  • Distribusi Kesulitan: " . $prompt['requirements']['distribusi_kesulitan']['persentase'] . "\n";
        $text .= "\n";

        $text .= "📝 FORMAT OUTPUT (EXACT FORMAT - DO NOT DEVIATE):\n";
        $text .= "─────────────────────────────────────────────────────────────────────────────\n";
        $text .= $prompt['format_output']['struktur_soal'] . "\n\n";

        $text .= "⚠️ PENTING - Catatan Format:\n";
        foreach ($prompt['format_output']['catatan_penting'] as $catatan) {
            $text .= "  ✓ $catatan\n";
        }
        $text .= "\n";

        $text .= "✅ QUALITY CONTROL CHECKLIST:\n";
        $text .= "─────────────────────────────────────────────────────────────────────────────\n";
        foreach ($prompt['quality_control']['checklist'] as $check) {
            $text .= "  [ ] $check\n";
        }
        $text .= "\n";

        $text .= "🎯 INSTRUKSI FINAL:\n";
        $text .= "─────────────────────────────────────────────────────────────────────────────\n";
        $text .= "DO:\n";
        foreach ($prompt['instruksi_final']['do'] as $do) {
            $text .= "  $do\n";
        }
        $text .= "\nDON'T:\n";
        foreach ($prompt['instruksi_final']['dont'] as $dont) {
            $text .= "  $dont\n";
        }
        $text .= "\n\n";

        $text .= "═══════════════════════════════════════════════════════════════════════════════\n";
        $text .= "                         SIAP UNTUK COPY-PASTE KE AI\n";
        $text .= "═══════════════════════════════════════════════════════════════════════════════\n\n";

        $text .= "Full JSON untuk reference:\n";
        $text .= json_encode($prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        return $text;
    }

    /**
     * Export prompt sebagai JSON pure
     */
    public function exportAsJson(array $prompt): string
    {
        return json_encode($prompt['prompt'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export prompt sebagai text plain
     */
    public function exportAsText(array $prompt): string
    {
        return $prompt['prompt_text'] ?? '';
    }

    /**
     * Get referensi materi untuk kelas tertentu
     * Membantu orang tua/guru memilih materi yang sesuai
     */
    public function getMateriReference(string $kelas, string $mapel = ''): array
    {
        // Normalize kelas format
        $klsRef = $kelas . ' ' . (strlen($kelas) == 1 && is_numeric($kelas) ? 'SD' : 'SMA');
        
        // Jika kelas belum ada referensinya, format ulang
        if (!isset(self::MATERI_REFERENCE[$kelas])) {
            // Cari dengan pattern yang lebih fleksibel
            foreach (self::MATERI_REFERENCE as $key => $materiList) {
                if (str_contains($key, $kelas) || str_contains($kelas, $key)) {
                    $kelas = $key;
                    break;
                }
            }
        }

        if (!isset(self::MATERI_REFERENCE[$kelas])) {
            return ['error' => 'Kelas tidak ditemukan'];
        }

        $allMateri = self::MATERI_REFERENCE[$kelas];

        if (empty($mapel)) {
            // Return semua materi untuk kelas ini
            return [
                'kelas' => $kelas,
                'materi_by_subject' => $allMateri
            ];
        }

        // Return materi spesifik untuk mapel tertentu
        $mapelNormalized = $this->normalizeMapel($mapel);
        foreach ($allMateri as $subject => $topics) {
            if (strtolower(str_replace(' ', '_', $subject)) === $mapelNormalized) {
                return [
                    'kelas' => $kelas,
                    'mata_pelajaran' => $subject,
                    'materi_topik' => $topics
                ];
            }
        }

        return ['error' => 'Mata pelajaran tidak ditemukan untuk kelas ini'];
    }

    /**
     * Normalize nama mata pelajaran
     */
    private function normalizeMapel(string $mapel): string
    {
        return strtolower(str_replace(' ', '_', $mapel));
    }
}
?>
