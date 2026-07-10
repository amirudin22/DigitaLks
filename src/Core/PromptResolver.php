<?php
/**
 * PromptResolver — Shared prompt resolution logic.
 *
 * normalizeMapel() menggunakan peta nama sendiri (hardcoded),
 * TIDAK bergantung pada $systemRoles keys.
 * resolveRole() tetap menggunakan $systemRoles untuk lookup prompt.
 */
namespace Core;

class PromptResolver
{
    /**
     * Peta nama mata pelajaran → canonical casing.
     * Digunakan untuk normalisasi input user/dropdown.
     * Key = lowercase, Value = casing resmi.
     */
    private static $subjectMap = [
        // Eksakta
        'matematika'             => 'Matematika',
        'matematika peminatan'   => 'Matematika Peminatan',
        'matematika wajib'       => 'Matematika Wajib',
        'fisika'                 => 'Fisika',
        'kimia'                  => 'Kimia',

        // Sains
        'biologi'                => 'Biologi',
        'ipa'                    => 'IPA',
        'ipas'                   => 'IPAS',
        'prakarya'               => 'Prakarya',
        'informatika'            => 'Informatika',
        'tik'                    => 'TIK',

        // Sosial
        'ips'                    => 'IPS',
        'sejarah'                => 'Sejarah',
        'sejarah indonesia'      => 'Sejarah Indonesia',
        'geografi'               => 'Geografi',
        'sosiologi'              => 'Sosiologi',
        'ekonomi'                => 'Ekonomi',
        'antropologi'            => 'Antropologi',

        // Bahasa
        'bahasa indonesia'       => 'Bahasa Indonesia',
        'bahasa inggris'         => 'Bahasa Inggris',
        'bahasa daerah'          => 'Bahasa Daerah',
        'bahasa asing'           => 'Bahasa Asing',

        // Lainnya
        'pkn'                    => 'PKn',
        'seni'                   => 'Seni',
        'seni budaya'            => 'Seni Budaya',
        'olahraga'               => 'Olahraga',
        'pjok'                   => 'PJOK',
        'pendidikan agama islam'      => 'Pendidikan Agama Islam',
        'pendidikan agama kristen'    => 'Pendidikan Agama Kristen',
        'pendidikan agama katolik'    => 'Pendidikan Agama Katolik',
        'pendidikan agama hindu'      => 'Pendidikan Agama Hindu',
        'pendidikan agama buddha'     => 'Pendidikan Agama Buddha',
        'pendidikan agama khonghucu'  => 'Pendidikan Agama Khonghucu',
        'muatan lokal'            => 'Muatan Lokal',
        'umum'                   => 'Umum',

    ];

    /**
     * Normalisasi nama mata pelajaran ke canonical casing.
     * Menggunakan peta internal, TIDAK bergantung $systemRoles.
     *
     * @param string $input  Nama mata pelajaran dari user/dropdown
     * @return string Canonical casing, atau input asli jika tidak ada di peta
     */
    public static function normalizeMapel($input)
    {
        $clean = strtolower(trim($input));

        // 1. Exact lookup di peta internal
        if (isset(self::$subjectMap[$clean])) {
            return self::$subjectMap[$clean];
        }

        // 2. Substring match — cari key terpanjang yang cocok
        $bestKey = '';
        $bestLen = 0;
        foreach (self::$subjectMap as $key => $canonical) {
            $len = strlen($key);
            if ($len <= $bestLen) continue;
            if (strpos($clean, $key) !== false || strpos($key, $clean) !== false) {
                $bestKey = $key;
                $bestLen = $len;
            }
        }

        if ($bestKey !== '') {
            return self::$subjectMap[$bestKey];
        }

        // 3. Tidak ditemukan — kembalikan input asli
        return $input;
    }

    /**
     * Resolve role prompt dari mata_pelajaran.
     * Menggunakan $systemRoles dari prompts.php untuk lookup prompt.
     *
     * @param array   $cpConfig      $config['copy_protection'] dari prompts.php
     * @param string  $mataPelajaran Nama mata pelajaran (bisa belum dinormalisasi)
     * @return array ['role' => [...], 'key' => string, 'method' => string]
     */
    public static function resolveRole(array $cpConfig, $mataPelajaran)
    {
        $systemRoles = $cpConfig['system_roles'] ?? [];

        if (empty($systemRoles)) {
            return [
                'role'    => ['label' => 'Tutor', 'persona' => '', 'prompt' => '', 'rules' => [], 'levels' => []],
                'key'     => 'Umum',
                'method'  => 'no_system_roles',
            ];
        }

        // Normalisasi dulu via peta internal
        $canonical = self::normalizeMapel($mataPelajaran);

        // 1. Exact match (case-insensitive) di $systemRoles
        $cleanMapel = strtolower($canonical);
        foreach ($systemRoles as $key => $roleData) {
            if (strtolower($key) === $cleanMapel) {
                return [
                    'role'   => $roleData,
                    'key'    => $key,
                    'method' => 'exact_match',
                ];
            }
        }

        // 2. Substring match di $systemRoles keys
        $roleKeys = array_keys($systemRoles);
        usort($roleKeys, function ($a, $b) { return strlen($b) - strlen($a); });

        foreach ($roleKeys as $key) {
            $cleanKey = strtolower(trim($key));
            // Skip fallback key
            if ($cleanKey === 'umum') {
                continue;
            }
            if (strpos($cleanMapel, $cleanKey) !== false || strpos($cleanKey, $cleanMapel) !== false) {
                return [
                    'role'   => $systemRoles[$key],
                    'key'    => $key,
                    'method' => 'substring_match',
                ];
            }
        }

        // 3. Fallback ke Umum
        $fallback = isset($systemRoles['Umum']) ? $systemRoles['Umum'] : [
            'label' => 'Tutor', 'persona' => '', 'prompt' => '', 'rules' => [], 'levels' => [],
        ];

        return [
            'role'   => $fallback,
            'key'    => 'Umum',
            'method' => 'fallback_umum',
        ];
    }
}
