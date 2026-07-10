<?php

namespace Core;

/**
 * Database Helper — Singleton MySQL PDO wrapper
 * Digunakan untuk menyimpan & mengambil data hasil evaluasi siswa
 */
class Database
{
    private static ?Database $instance = null;
    private ?\PDO $pdo = null;
    private bool $connected = false;

    private function __construct(array $config)
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host']     ?? 'localhost',
                $config['port']     ?? '3306',
                $config['dbname']   ?? 'portalsoal'
            );

            $this->pdo = new \PDO($dsn, $config['user'] ?? 'root', $config['pass'] ?? '', [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            $this->connected = true;
            $this->migrate();
        } catch (\PDOException $e) {
            // Gagal koneksi tidak boleh crash aplikasi — laporan saja tidak tersedia
            $this->connected = false;
            error_log('[Database] Koneksi MySQL gagal: ' . $e->getMessage());
        }
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Buat tabel jika belum ada (auto-migrate)
     */
    private function migrate(): void
    {
        // Tabel hasil evaluasi siswa
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `student_results` (
                `id`             INT AUTO_INCREMENT PRIMARY KEY,
                `guru_username`  VARCHAR(100)   NOT NULL,
                `soal_id`        VARCHAR(255)   NOT NULL,
                `soal_judul`     VARCHAR(255)   NOT NULL DEFAULT '',
                `mata_pelajaran` VARCHAR(100)   NOT NULL DEFAULT '',
                -- Identitas siswa
                `nama_siswa`     VARCHAR(255)   NOT NULL,
                `nis`            VARCHAR(50)    NOT NULL DEFAULT '',
                -- Hasil
                `skor`           DECIMAL(5,2)   NOT NULL DEFAULT 0,
                `total_soal`     INT            NOT NULL DEFAULT 0,
                `jumlah_benar`   INT            NOT NULL DEFAULT 0,
                `jumlah_salah`   INT            NOT NULL DEFAULT 0,
                -- Waktu
                `waktu_kumpul`   DATETIME       NOT NULL,
                `created_at`     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
                -- Cegah duplikasi data jika reload
                UNIQUE KEY `uq_result` (`soal_id`, `nama_siswa`, `nis`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Tabel data siswa terdaftar (master data)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `students` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `guru_username` VARCHAR(100) NOT NULL,
                `nis`           VARCHAR(50)  NOT NULL,
                `nama`          VARCHAR(255) NOT NULL,
                `kelas`         VARCHAR(50)  NOT NULL DEFAULT '',
                `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_siswa` (`guru_username`, `nis`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Tabel index soal — untuk lookup cepat (hybrid JSON + MySQL)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `quiz_index` (
                `slug`          VARCHAR(100) PRIMARY KEY,
                `guru_username` VARCHAR(100) NOT NULL,
                `filename`      VARCHAR(255) NOT NULL,
                `judul`         VARCHAR(255) NOT NULL DEFAULT '',
                `mata_pelajaran` VARCHAR(100) NOT NULL DEFAULT '',
                `kelas_target`  VARCHAR(50)  NOT NULL DEFAULT '',
                `status`        VARCHAR(20)  NOT NULL DEFAULT 'draft',
                `pin`           VARCHAR(4)   NOT NULL DEFAULT '',
                `timer_menit`   INT          NOT NULL DEFAULT 20,
                `created_at`    DATETIME     NOT NULL,
                `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_guru`  (`guru_username`),
                INDEX `idx_status` (`status`),
                INDEX `idx_mapel` (`mata_pelajaran`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }


    /**
     * Simpan atau perbarui hasil evaluasi siswa
     */
    public function saveResult(array $data): bool
    {
        if (!$this->connected) return false;

        $sql = "
            INSERT INTO `student_results`
                (`guru_username`, `soal_id`, `soal_judul`, `mata_pelajaran`,
                 `nama_siswa`, `nis`, `skor`, `total_soal`, `jumlah_benar`, `jumlah_salah`, `waktu_kumpul`)
            VALUES
                (:guru_username, :soal_id, :soal_judul, :mata_pelajaran,
                 :nama_siswa, :nis, :skor, :total_soal, :jumlah_benar, :jumlah_salah, :waktu_kumpul)
            ON DUPLICATE KEY UPDATE
                `skor`          = VALUES(`skor`),
                `total_soal`    = VALUES(`total_soal`),
                `jumlah_benar`  = VALUES(`jumlah_benar`),
                `jumlah_salah`  = VALUES(`jumlah_salah`),
                `waktu_kumpul`  = VALUES(`waktu_kumpul`)
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':guru_username'  => $data['guru_username']  ?? '',
                ':soal_id'        => $data['soal_id']        ?? '',
                ':soal_judul'     => $data['soal_judul']     ?? '',
                ':mata_pelajaran' => $data['mata_pelajaran'] ?? '',
                ':nama_siswa'     => $data['nama_siswa']     ?? '',
                ':nis'            => $data['nis']            ?? '',
                ':skor'           => $data['skor']           ?? 0,
                ':total_soal'     => $data['total_soal']     ?? 0,
                ':jumlah_benar'   => $data['jumlah_benar']  ?? 0,
                ':jumlah_salah'   => $data['jumlah_salah']  ?? 0,
                ':waktu_kumpul'   => $data['waktu_kumpul']  ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            error_log('[Database] saveResult error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cari siswa berdasarkan nama atau NIS (untuk halaman laporan)
     * Hanya tampilkan hasil milik guru yang login
     *
     * @param string $guruUsername  Username guru
     * @param string $keyword       Kata kunci pencarian (nama atau NIS)
     * @return array
     */
    /**
     * Ambil daftar mata pelajaran unik yang pernah diujikan oleh guru
     */
    public function getMapelList(string $guruUsername): array
    {
        if (!$this->connected) return [];
        $sql = "SELECT DISTINCT `mata_pelajaran` FROM `student_results`
                WHERE `guru_username` = :guru AND `mata_pelajaran` != ''
                ORDER BY `mata_pelajaran` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':guru' => $guruUsername]);
        return array_column($stmt->fetchAll(), 'mata_pelajaran');
    }

    /**
     * Cari daftar siswa unik, bisa difilter per kata kunci dan/atau mata pelajaran
     */
    public function searchStudents(string $guruUsername, string $keyword = '', string $mapel = ''): array
    {
        if (!$this->connected) return [];

        $keyword = trim($keyword);
        $mapel   = trim($mapel);

        $conditions = ['`guru_username` = :guru'];
        $params     = [':guru' => $guruUsername];

        if ($keyword !== '') {
            $conditions[] = '(`nama_siswa` LIKE :keyword OR `nis` LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        if ($mapel !== '') {
            $conditions[] = '`mata_pelajaran` = :mapel';
            $params[':mapel'] = $mapel;
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT
                `nama_siswa`,
                `nis`,
                COUNT(*)            AS total_ujian,
                AVG(`skor`)         AS rata_skor,
                MAX(`waktu_kumpul`) AS terakhir_ujian
            FROM `student_results`
            WHERE {$where}
            GROUP BY `nama_siswa`, `nis`
            ORDER BY `nama_siswa` ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ambil riwayat ujian detail satu siswa (untuk line chart)
     */
    /**
     * Ambil riwayat ujian detail satu siswa, bisa difilter per mata pelajaran
     */
    public function getStudentHistory(string $guruUsername, string $nis, string $nama, string $mapel = ''): array
    {
        if (!$this->connected) return [];

        $params = [':guru' => $guruUsername, ':nis' => $nis, ':nama' => $nama];
        $mapelClause = '';
        if ($mapel !== '') {
            $mapelClause = 'AND `mata_pelajaran` = :mapel';
            $params[':mapel'] = $mapel;
        }

        $sql = "
            SELECT
                `soal_judul`,
                `mata_pelajaran`,
                `skor`,
                `total_soal`,
                `jumlah_benar`,
                `jumlah_salah`,
                `waktu_kumpul`
            FROM `student_results`
            WHERE `guru_username` = :guru
              AND (`nis` = :nis OR `nama_siswa` = :nama)
              {$mapelClause}
            ORDER BY `waktu_kumpul` ASC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('[Database] getStudentHistory error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Statistik ringkas semua siswa guru (untuk overview dashboard laporan)
     */
    public function getOverviewStats(string $guruUsername): array
    {
        if (!$this->connected) return [];

        $sql = "
            SELECT
                COUNT(DISTINCT CONCAT(`nama_siswa`, '_', `nis`)) AS total_siswa,
                COUNT(*) AS total_ujian,
                ROUND(AVG(`skor`), 1) AS rata_skor,
                SUM(`jumlah_benar`) AS total_benar,
                SUM(`jumlah_salah`) AS total_salah
            FROM `student_results`
            WHERE `guru_username` = :guru
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':guru' => $guruUsername]);
        return $stmt->fetch() ?: [];
    }

    // ================================================================
    // STUDENT MASTER DATA — CRUD
    // ================================================================

    /**
     * Cari siswa berdasarkan NIS (dipakai untuk auto-fill nama saat onboarding)
     */
    public function getSiswaByNis(string $guruUsername, string $nis): ?array
    {
        if (!$this->connected) return null;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT `id`, `nis`, `nama`, `kelas` FROM `students`
                 WHERE `guru_username` = :guru AND `nis` = :nis LIMIT 1"
            );
            $stmt->execute([':guru' => $guruUsername, ':nis' => $nis]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log('[Database] getSiswaByNis error: ' . $e->getMessage());
            return null;
        }
    }

    /** Ambil semua siswa milik guru, bisa difilter keyword */
    public function getAllSiswa(string $guruUsername, string $keyword = ''): array
    {
        if (!$this->connected) return [];
        $params = [':guru' => $guruUsername];
        $where  = '`guru_username` = :guru';
        if ($keyword !== '') {
            $where .= ' AND (`nama` LIKE :kw OR `nis` LIKE :kw OR `kelas` LIKE :kw)';
            $params[':kw'] = '%' . $keyword . '%';
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `students` WHERE {$where} ORDER BY `kelas` ASC, `nama` ASC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('[Database] getAllSiswa error: ' . $e->getMessage());
            return [];
        }
    }

    /** Tambah atau perbarui siswa (upsert by NIS) */
    public function addSiswa(string $guruUsername, string $nis, string $nama, string $kelas): bool
    {
        if (!$this->connected) return false;
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `students` (`guru_username`, `nis`, `nama`, `kelas`)
                 VALUES (:guru, :nis, :nama, :kelas)
                 ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `kelas` = VALUES(`kelas`)"
            );
            return $stmt->execute([':guru' => $guruUsername, ':nis' => $nis, ':nama' => $nama, ':kelas' => $kelas]);
        } catch (\PDOException $e) {
            error_log('[Database] addSiswa error: ' . $e->getMessage());
            return false;
        }
    }

    /** Update data siswa berdasarkan ID */
    public function updateSiswa(int $id, string $guruUsername, string $nis, string $nama, string $kelas): bool
    {
        if (!$this->connected) return false;
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `students` SET `nis` = :nis, `nama` = :nama, `kelas` = :kelas
                 WHERE `id` = :id AND `guru_username` = :guru"
            );
            return $stmt->execute([':id' => $id, ':guru' => $guruUsername, ':nis' => $nis, ':nama' => $nama, ':kelas' => $kelas]);
        } catch (\PDOException $e) {
            error_log('[Database] updateSiswa error: ' . $e->getMessage());
            return false;
        }
    }

    /** Hapus siswa */
    public function deleteSiswa(int $id, string $guruUsername): bool
    {
        if (!$this->connected) return false;
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM `students` WHERE `id` = :id AND `guru_username` = :guru"
            );
            return $stmt->execute([':id' => $id, ':guru' => $guruUsername]);
        } catch (\PDOException $e) {
            error_log('[Database] deleteSiswa error: ' . $e->getMessage());
            return false;
        }
    }

    // ================================================================
    // QUIZ INDEX — Hybrid JSON + MySQL lookup
    // ================================================================

    public function saveQuizIndex(array $data): bool
    {
        if (!$this->connected) return false;
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `quiz_index`
                    (`slug`, `guru_username`, `filename`, `judul`, `mata_pelajaran`,
                     `kelas_target`, `status`, `pin`, `timer_menit`, `created_at`)
                 VALUES
                    (:slug, :guru, :filename, :judul, :mapel,
                     :kelas, :status, :pin, :timer, :created)
                 ON DUPLICATE KEY UPDATE
                    `filename`      = VALUES(`filename`),
                    `judul`         = VALUES(`judul`),
                    `mata_pelajaran` = VALUES(`mata_pelajaran`),
                    `kelas_target`  = VALUES(`kelas_target`),
                    `status`        = VALUES(`status`),
                    `pin`           = VALUES(`pin`),
                    `timer_menit`   = VALUES(`timer_menit`)"
            );
            return $stmt->execute([
                ':slug'   => $data['slug'],
                ':guru'   => $data['guru_username'],
                ':filename' => $data['filename'],
                ':judul'  => $data['judul'],
                ':mapel'  => $data['mata_pelajaran'],
                ':kelas'  => $data['kelas_target'],
                ':status' => $data['status'],
                ':pin'    => $data['pin'],
                ':timer'  => (int)($data['timer_menit'] ?? 20),
                ':created' => $data['created_at'],
            ]);
        } catch (\PDOException $e) {
            error_log('[Database] saveQuizIndex error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteQuizIndex(string $slug): bool
    {
        if (!$this->connected) return false;
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `quiz_index` WHERE `slug` = :slug");
            return $stmt->execute([':slug' => $slug]);
        } catch (\PDOException $e) {
            error_log('[Database] deleteQuizIndex error: ' . $e->getMessage());
            return false;
        }
    }

    public function findQuizBySlug(string $slug): ?array
    {
        if (!$this->connected) return null;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `quiz_index` WHERE `slug` = :slug LIMIT 1"
            );
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log('[Database] findQuizBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    public function listQuizIndex(string $guruUsername, array $filters = []): array
    {
        if (!$this->connected) return [];

        $conditions = ['`guru_username` = :guru'];
        $params     = [':guru' => $guruUsername];

        if (!empty($filters['status'])) {
            $conditions[] = '`status` = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['mapel'])) {
            $conditions[] = '`mata_pelajaran` = :mapel';
            $params[':mapel'] = $filters['mapel'];
        }
        if (!empty($filters['keyword'])) {
            $conditions[] = '(`judul` LIKE :kw OR `kelas_target` LIKE :kw)';
            $params[':kw'] = '%' . $filters['keyword'] . '%';
        }

        $where = implode(' AND ', $conditions);
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `quiz_index` WHERE {$where} ORDER BY `updated_at` DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('[Database] listQuizIndex error: ' . $e->getMessage());
            return [];
        }
    }
}
