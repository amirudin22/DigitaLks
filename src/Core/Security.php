<?php

namespace Core;

class Security
{
    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Memulai sesi
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    /**
     * Generate 4 digit random PIN
     */
    public function generatePin(): string
    {
        return str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate URL slug unik
     */
    public function generateSlug(string $mapel): string
    {
        $cleanMapel = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $mapel));
        if (empty($cleanMapel))
            $cleanMapel = 'soal';

        $dateStr = date('Ymd');
        $randomHex = bin2hex(random_bytes(3)); // 6 chars
        return "{$cleanMapel}-{$dateStr}-{$randomHex}";
    }

    /**
     * Rate Limiting sederhana pakai session (bisa diganti file jika perlu persisten lintas tab)
     */
    public function checkRateLimit(string $key, int $maxAttempts = 3, int $lockoutSeconds = 600): bool
    {
        $this->startSession();
        $attempts = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];

        $now = time();

        if ($attempts['locked_until'] > $now) {
            return false; // Masih dalam lockout
        }

        // Jika sudah melewati lockout, reset counter
        if ($attempts['locked_until'] > 0 && $attempts['locked_until'] <= $now) {
            $attempts['count'] = 0;
            $attempts['locked_until'] = 0;
            $_SESSION['rate_limit'][$key] = $attempts;
        }

        // Cek apakah sudah melebihi batas (lockout belum aktif, tapi count sudah >= max)
        if ($attempts['count'] >= $maxAttempts) {
            $attempts['locked_until'] = $now + $lockoutSeconds;
            $attempts['count'] = 0;
            $_SESSION['rate_limit'][$key] = $attempts;
            return false;
        }

        return true;
    }

    /**
     * Catat percobaan gagal
     */
    public function recordFailedAttempt(string $key, int $maxAttempts = 3, int $lockoutSeconds = 600): void
    {
        $this->startSession();
        $attempts = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'last_attempt' => time(), 'locked_until' => 0];

        $attempts['count']++;
        $attempts['last_attempt'] = time();

        if ($attempts['count'] >= $maxAttempts) {
            $attempts['locked_until'] = time() + $lockoutSeconds;
            $attempts['count'] = 0; // Reset counter
        }

        $_SESSION['rate_limit'][$key] = $attempts;
    }

    /**
     * Reset rate limit (setelah login sukses)
     */
    public function resetRateLimit(string $key): void
    {
        $this->startSession();
        unset($_SESSION['rate_limit'][$key]);
    }

    /**
     * Sanitizes strings
     */
    public function sanitize(string $input): string
    {
        return htmlspecialchars(strip_tags($input, '<b><i><u><strong><em>'), ENT_QUOTES, 'UTF-8');
    }
}
