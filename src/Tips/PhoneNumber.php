<?php

declare(strict_types=1);

namespace App\Tips;

/** Kenyan mobile numbers → the 2547XXXXXXXX / 2541XXXXXXXX form Daraja expects. */
final class PhoneNumber
{
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $input) ?? '';
        $digits = ltrim($digits, '+');
        if (!ctype_digit($digits)) {
            return null;
        }
        if (preg_match('/^0([17]\d{8})$/', $digits, $m)) {
            return '254' . $m[1];
        }
        if (preg_match('/^([17]\d{8})$/', $digits, $m)) {
            return '254' . $m[1];
        }
        if (preg_match('/^254[17]\d{8}$/', $digits)) {
            return $digits;
        }
        return null;
    }

    public static function last3(string $normalized): string
    {
        return substr($normalized, -3);
    }
}
