<?php

namespace Core;

class Auth
{
    private string $usersPath;
    private array $users = [];

    private const ROLES = ['guru' => 1, 'administrator' => 2, 'kepala_sekolah' => 3];

    public function __construct(string $storageDir)
    {
        $this->usersPath = rtrim($storageDir, '/') . '/config/users.json';
        $this->load();
    }

    private function load(): void
    {
        if (file_exists($this->usersPath)) {
            $data = json_decode(file_get_contents($this->usersPath), true);
            if (is_array($data)) $this->users = $data;
        }
    }

    private function save(): bool
    {
        $dir = dirname($this->usersPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $tmp = $this->usersPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, json_encode($this->users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($written === false) return false;
        if (PHP_OS_FAMILY === 'Windows') @unlink($this->usersPath);
        return rename($tmp, $this->usersPath);
    }

    // ── Login ──

    public function login(string $username, string $password): ?array
    {
        $user = $this->users[$username] ?? null;
        if (!$user) return null;

        if (password_verify($password, $user['hash'] ?? '')) {
            return [
                'username' => $username,
                'role'     => $user['role'] ?? 'guru',
                'nama'     => $user['nama'] ?? $username,
            ];
        }
        return null;
    }

    /**
     * Fallback untuk user dari .env (plaintext comparison)
     */
    public function loginPlain(string $username, string $password, string $defaultRole = 'administrator'): ?array
    {
        $user = $this->users[$username] ?? null;
        if (!$user) return null;

        // .env users stored with 'password' key (plain), not 'hash' (bcrypt)
        if (isset($user['password']) && $user['password'] === $password) {
            return [
                'username' => $username,
                'role'     => $user['role'] ?? $defaultRole,
                'nama'     => $user['nama'] ?? $username,
            ];
        }
        return null;
    }

    // ── Role checks ──

    public static function roleWeight(string $role): int
    {
        return self::ROLES[$role] ?? 0;
    }

    public static function can(string $userRole, string $minRole): bool
    {
        return self::roleWeight($userRole) >= self::roleWeight($minRole);
    }

    public static function requireRole(string $minRole): void
    {
        $role = $_SESSION['admin_role'] ?? '';
        if (!self::can($role, $minRole)) {
            http_response_code(403);
            die('Akses ditolak.');
        }
    }

    // ── User CRUD (kepala_sekolah only) ──

    public function getUsers(): array
    {
        $list = [];
        foreach ($this->users as $username => $data) {
            $list[] = [
                'username' => $username,
                'role'     => $data['role'] ?? 'guru',
                'nama'     => $data['nama'] ?? $username,
            ];
        }
        return $list;
    }

    public function addUser(string $username, string $password, string $role, string $nama): array
    {
        if (isset($this->users[$username])) {
            return ['ok' => false, 'error' => 'Username sudah terdaftar.'];
        }
        if (!isset(self::ROLES[$role])) {
            return ['ok' => false, 'error' => 'Role tidak valid.'];
        }
        $this->users[$username] = [
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'nama' => $nama,
        ];
        return $this->save()
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Gagal menyimpan.'];
    }

    public function updateUser(string $username, ?string $password, ?string $role, ?string $nama): array
    {
        if (!isset($this->users[$username])) {
            return ['ok' => false, 'error' => 'User tidak ditemukan.'];
        }
        if ($password !== null) {
            $this->users[$username]['hash'] = password_hash($password, PASSWORD_BCRYPT);
        }
        if ($role !== null) {
            if (!isset(self::ROLES[$role])) return ['ok' => false, 'error' => 'Role tidak valid.'];
            $this->users[$username]['role'] = $role;
        }
        if ($nama !== null) {
            $this->users[$username]['nama'] = $nama;
        }
        return $this->save()
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Gagal menyimpan.'];
    }

    public function deleteUser(string $username): array
    {
        if (!isset($this->users[$username])) {
            return ['ok' => false, 'error' => 'User tidak ditemukan.'];
        }
        unset($this->users[$username]);
        return $this->save()
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Gagal menyimpan.'];
    }

    public function userExists(): bool
    {
        return !empty($this->users);
    }
}
