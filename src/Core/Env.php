<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Real environment variables always win over the file,
 * so Docker/production can inject values without a .env present.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            // Strip inline comments on unquoted values and surrounding quotes.
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strpos($value, $quote, 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        $value = self::$values[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        return $value === null ? $default : in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null ? $default : (int) $value;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV') === 'production';
    }

    /**
     * APP_SECRET, which keys the callback token, phone hashes and rate-limit
     * buckets. Outside production a fixed dev value is used when it is unset.
     */
    public static function secret(): string
    {
        $secret = self::get('APP_SECRET');
        if ($secret === null) {
            if (self::isProduction()) {
                throw new \RuntimeException('APP_SECRET is not set.');
            }
            return 'dev-secret-not-for-production';
        }
        return $secret;
    }

    /**
     * Refuses to boot in production with settings that are only safe locally:
     * a weak or placeholder secret, or the fake M-Pesa driver (which would
     * write fake COMPLETED rows into the permanent ledger).
     */
    public static function assertProductionConfig(): void
    {
        if (!self::isProduction()) {
            return;
        }
        $secret = (string) self::get('APP_SECRET', '');
        if (strlen($secret) < 32 || in_array($secret, ['change-me', 'dev-secret', 'dev-secret-not-for-production'], true)) {
            throw new \RuntimeException('APP_SECRET must be a random value of at least 32 characters in production.');
        }
        if (!in_array(self::get('MPESA_ENV'), ['sandbox', 'production'], true)) {
            throw new \RuntimeException('MPESA_ENV must be "sandbox" or "production" in production.');
        }
    }

    /** Test helpers. */
    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$values[$key]);
    }
}
