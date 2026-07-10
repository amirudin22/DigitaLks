<?php

namespace Core;

class FileManager
{
    private string $storageDir;
    private ?Database $db = null;

    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim($storageDir, '/');
    }

    public function setDatabase(?Database $db): void
    {
        $this->db = $db;
    }

    // ============================================================
    // CORE JSON OPERATIONS
    // ============================================================

    public function readJson(string $path): ?array
    {
        $fullPath = $this->resolve($path);
        if (!file_exists($fullPath)) return null;
        $content = file_get_contents($fullPath);
        if ($content === false) return null;
        $data = json_decode($content, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }

    public function writeJson(string $path, array $data): bool
    {
        $fullPath = $this->resolve($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $jsonStr = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tmpPath = $fullPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, $jsonStr, LOCK_EX);
        if ($written === false) return false;
        if (PHP_OS_FAMILY === 'Windows') {
            @unlink($fullPath);
        }
        return rename($tmpPath, $fullPath);
    }

    public function deleteJson(string $path): bool
    {
        $fullPath = $this->resolve($path);
        if (!file_exists($fullPath)) return false;
        return @unlink($fullPath);
    }

    // ============================================================
    // INDEX.JSON — LOCAL FAST INDEX (PRIMARY)
    // ============================================================

    private function resolve(string $path): string
    {
        return $this->storageDir . '/' . ltrim($path, '/');
    }

    private function getIndexPath(string $guru): string
    {
        return $this->storageDir . '/accounts/' . $guru . '/index.json';
    }

    private function readIndex(string $guru): array
    {
        $path = $this->getIndexPath($guru);
        if (!file_exists($path)) return [];
        $content = @file_get_contents($path);
        if ($content === false) return [];
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function writeIndex(string $guru, array $index): bool
    {
        $path = $this->getIndexPath($guru);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $jsonStr = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tmpPath = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmpPath, $jsonStr, LOCK_EX);
        if ($written === false) return false;
        if (PHP_OS_FAMILY === 'Windows') {
            @unlink($path);
        }
        return rename($tmpPath, $path);
    }

    private function extractGuruFromPath(string $path): string
    {
        $parts = explode('/', ltrim($path, '/'));
        return $parts[1] ?? '';
    }

    private function buildIndexEntry(string $path, array $data): ?array
    {
        $meta = $data['metadata'] ?? [];
        $slug = $meta['slug'] ?? '';
        if (empty($slug)) return null;

        return [
            'filename'       => $path,
            'judul'          => $meta['judul'] ?? '',
            'mata_pelajaran' => $meta['mata_pelajaran'] ?? '',
            'kelas_target'   => $meta['kelas_target'] ?? '',
            'status'         => $meta['status'] ?? 'draft',
            'pin'            => $meta['pin'] ?? '',
            'timer_menit'    => $meta['timer_menit'] ?? 20,
            'created_at'     => $meta['created_at'] ?? date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Update index.json dengan entry baru (add/update)
     */
    private function updateIndex(string $path, array $data): void
    {
        $guru = $this->extractGuruFromPath($path);
        if (empty($guru)) return;

        $entry = $this->buildIndexEntry($path, $data);
        if ($entry === null) return;

        $index = $this->readIndex($guru);
        $slug = $data['metadata']['slug'] ?? '';
        $index[$slug] = $entry;
        $this->writeIndex($guru, $index);
    }

    /**
     * Hapus entry dari index.json
     */
    private function removeFromIndex(string $path): void
    {
        $guru = $this->extractGuruFromPath($path);
        if (empty($guru)) return;

        // Baca slug dari file dulu
        $data = $this->readJson($path);
        $slug = $data['metadata']['slug'] ?? '';

        $index = $this->readIndex($guru);

        if (!empty($slug) && isset($index[$slug])) {
            unset($index[$slug]);
        } else {
            // Fallback: cari berdasarkan filename
            foreach ($index as $key => $entry) {
                if (($entry['filename'] ?? '') === $path) {
                    unset($index[$key]);
                    break;
                }
            }
        }

        $this->writeIndex($guru, $index);
    }

    /**
     * Rebuild index.json dari scan semua file (jika korup/hilang)
     */
    public function rebuildIndex(string $guru): array
    {
        $soalDir = $this->storageDir . '/accounts/' . $guru . '/soal';
        $index = [];

        if (!is_dir($soalDir)) return $index;

        $files = glob($soalDir . '/*.json');
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            $data = json_decode($content, true);
            if (!is_array($data)) continue;

            $path = 'accounts/' . $guru . '/soal/' . basename($file);
            $entry = $this->buildIndexEntry($path, $data);
            if ($entry === null) continue;

            $slug = $data['metadata']['slug'] ?? basename($file);
            $index[$slug] = $entry;
        }

        $this->writeIndex($guru, $index);
        return $index;
    }

    // ============================================================
    // LOOKUP: index.json → MySQL → scan folder
    // ============================================================

    /**
     * Cari soal berdasarkan slug.
     * Prioritas: index.json (O(1)) → MySQL → scan folder (O(N))
     *
     * @return array{data: array, path: string, guru: string}|null
     */
    public function findBySlug(string $slug): ?array
    {
        // 1. Cari lewat index.json per-user
        $accountsPath = $this->storageDir . '/accounts';
        if (is_dir($accountsPath)) {
            $accounts = scandir($accountsPath);
            foreach ($accounts as $acc) {
                if ($acc === '.' || $acc === '..') continue;
                $index = $this->readIndex($acc);
                if (isset($index[$slug])) {
                    $data = $this->readJson($index[$slug]['filename']);
                    if ($data) {
                        return [
                            'data' => $data,
                            'path' => $index[$slug]['filename'],
                            'guru' => $acc,
                        ];
                    }
                }
            }
        }

        // 2. Fallback: MySQL
        if ($this->db && $this->db->isConnected()) {
            $row = $this->db->findQuizBySlug($slug);
            if ($row && !empty($row['filename'])) {
                $data = $this->readJson($row['filename']);
                if ($data) {
                    $guru = $row['guru_username'] ?? '';
                    if ($guru) {
                        $index = $this->readIndex($guru);
                        $index[$slug] = $this->buildIndexEntry($row['filename'], $data);
                        $this->writeIndex($guru, $index);
                    }
                    return [
                        'data' => $data,
                        'path' => $row['filename'],
                        'guru' => $guru,
                    ];
                }
            }
        }

        // 3. Fallback terakhir: scan semua file (rebuild index)
        if (is_dir($accountsPath)) {
            $accounts = scandir($accountsPath);
            foreach ($accounts as $acc) {
                if ($acc === '.' || $acc === '..') continue;
                $index = $this->rebuildIndex($acc);
                if (isset($index[$slug])) {
                    $data = $this->readJson($index[$slug]['filename']);
                    if ($data) {
                        return [
                            'data' => $data,
                            'path' => $index[$slug]['filename'],
                            'guru' => $acc,
                        ];
                    }
                }
            }
        }

        return null;
    }

    // ============================================================
    // WRITE/DELETE + AUTO-SYNC (index.json + MySQL)
    // ============================================================

    /**
     * writeJson + auto-update index.json + sync MySQL
     */
    public function writeJsonAndSync(string $path, array $data): bool
    {
        $result = $this->writeJson($path, $data);
        if (!$result) return false;

        $this->updateIndex($path, $data);
        $this->syncToMySql($path, $data);

        return true;
    }

    /**
     * deleteJson + auto-remove from index.json + MySQL
     */
    public function deleteJsonAndSync(string $path): bool
    {
        $this->removeFromIndex($path);
        $this->deleteFromMySql($path);

        return $this->deleteJson($path);
    }

    // ============================================================
    // MySQL SYNC (SECONDARY — optional, silent fail)
    // ============================================================

    private function syncToMySql(string $path, array $data): void
    {
        if (!$this->db || !$this->db->isConnected()) return;

        try {
            $meta = $data['metadata'] ?? [];
            $slug = $meta['slug'] ?? '';
            if (empty($slug)) return;

            $this->db->saveQuizIndex([
                'slug'           => $slug,
                'guru_username'  => $this->extractGuruFromPath($path),
                'filename'       => $path,
                'judul'          => $meta['judul'] ?? '',
                'mata_pelajaran' => $meta['mata_pelajaran'] ?? '',
                'kelas_target'   => $meta['kelas_target'] ?? '',
                'status'         => $meta['status'] ?? 'draft',
                'pin'            => $meta['pin'] ?? '',
                'timer_menit'    => $meta['timer_menit'] ?? 20,
                'created_at'     => $meta['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[FileManager] MySQL sync error (non-fatal): ' . $e->getMessage());
        }
    }

    private function deleteFromMySql(string $path): void
    {
        if (!$this->db || !$this->db->isConnected()) return;

        try {
            $data = $this->readJson($path);
            $slug = $data['metadata']['slug'] ?? '';
            if (empty($slug)) {
                $slug = pathinfo($path, PATHINFO_FILENAME);
            }
            $this->db->deleteQuizIndex($slug);
        } catch (\Throwable $e) {
            error_log('[FileManager] MySQL delete sync error (non-fatal): ' . $e->getMessage());
        }
    }

    // ============================================================
    // LISTING
    // ============================================================

    /**
     * List soal — pakai index.json dulu, fallback scan folder
     */
    public function listSoalFiles(string $userDir): array
    {
        // Coba dari index.json dulu (lebih cepat)
        $index = $this->readIndex($userDir);
        if (!empty($index)) {
            $result = [];
            foreach ($index as $slug => $entry) {
                $result[] = [
                    'filename' => basename($entry['filename']),
                    'metadata' => [
                        'slug'           => $slug,
                        'judul'          => $entry['judul'],
                        'mata_pelajaran' => $entry['mata_pelajaran'],
                        'kelas_target'   => $entry['kelas_target'],
                        'status'         => $entry['status'],
                        'pin'            => $entry['pin'],
                        'timer_menit'    => $entry['timer_menit'],
                        'created_at'     => $entry['created_at'],
                    ],
                ];
            }
            usort($result, function ($a, $b) {
                return strtotime($b['metadata']['created_at'] ?? '0') - strtotime($a['metadata']['created_at'] ?? '0');
            });
            return $result;
        }

        // Fallback: scan folder (dan rebuild index)
        $this->rebuildIndex($userDir);

        $dirPath = $this->storageDir . '/accounts/' . $userDir . '/soal';
        if (!is_dir($dirPath)) return [];

        $files = glob($dirPath . '/*.json');
        if (!$files) return [];

        $result = [];
        foreach ($files as $file) {
            $data = $this->readJson('accounts/' . $userDir . '/soal/' . basename($file));
            if ($data && isset($data['metadata'])) {
                $result[] = [
                    'filename' => basename($file),
                    'metadata' => $data['metadata'],
                ];
            }
        }

        usort($result, function ($a, $b) {
            return strtotime($b['metadata']['created_at'] ?? '0') - strtotime($a['metadata']['created_at'] ?? '0');
        });

        return $result;
    }

    // ============================================================
    // BULK OPERATIONS
    // ============================================================

    /**
     * Sync semua index.json ke MySQL (untuk satu guru atau semua)
     */
    public function syncAllToMySql(?string $guru = null): array
    {
        if (!$this->db || !$this->db->isConnected()) {
            return ['ok' => false, 'error' => 'Database tidak terhubung'];
        }

        $accountsPath = $this->storageDir . '/accounts';
        if (!is_dir($accountsPath)) {
            return ['ok' => false, 'error' => 'Folder accounts tidak ditemukan'];
        }

        $gurus = $guru
            ? [$guru]
            : array_values(array_diff(scandir($accountsPath) ?: [], ['.', '..']));

        $synced = 0;
        $errors = 0;

        foreach ($gurus as $acc) {
            if (!is_dir($accountsPath . '/' . $acc)) continue;
            $index = $this->readIndex($acc);
            foreach ($index as $slug => $entry) {
                $data = $this->readJson($entry['filename']);
                if ($data) {
                    $this->syncToMySql($entry['filename'], $data);
                    $synced++;
                } else {
                    $errors++;
                }
            }
        }

        return ['ok' => true, 'synced' => $synced, 'errors' => $errors, 'total_guru' => count($gurus)];
    }

    /**
     * Rebuild index.json untuk semua akun guru
     */
    public function rebuildAllIndexes(): array
    {
        $accountsPath = $this->storageDir . '/accounts';
        if (!is_dir($accountsPath)) {
            return ['ok' => false, 'error' => 'Folder accounts tidak ditemukan'];
        }

        $gurus = array_values(array_diff(scandir($accountsPath) ?: [], ['.', '..']));
        $rebuilt = 0;

        foreach ($gurus as $acc) {
            if (!is_dir($accountsPath . '/' . $acc)) continue;
            $this->rebuildIndex($acc);
            $rebuilt++;
        }

        return ['ok' => true, 'rebuilt' => $rebuilt];
    }
}
