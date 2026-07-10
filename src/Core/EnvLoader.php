<?php

namespace Core;

class EnvLoader
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path = null): void
    {
        if (self::$loaded) return;

        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }

        if (!file_exists($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '') continue;

            self::$vars[$key] = $value;

            if (!array_key_exists($key, $_SERVER)) {
                putenv("{$key}={$value}");
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) self::load();
        return self::$vars[$key] ?? $default;
    }

    public static function all(): array
    {
        if (!self::$loaded) self::load();
        return self::$vars;
    }

    public static function parseAuthUsers(string $value): array
    {
        $users = [];
        $pairs = explode(',', $value);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $users[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $users;
    }
}
