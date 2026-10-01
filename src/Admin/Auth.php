<?php

declare(strict_types=1);

namespace App\Admin;

use App\Core\Env;

/** Single-user session login for /admin. */
final class Auth
{
    private const SESSION_KEY = '_admin';
    private const IDLE_SECONDS = 7200;

    public static function configured(): bool
    {
        return Env::get('ADMIN_PASSWORD_HASH') !== null;
    }

    public static function attempt(string $user, string $password): bool
    {
        $hash = (string) Env::get('ADMIN_PASSWORD_HASH', '');
        $expectedUser = (string) Env::get('ADMIN_USER', 'admin');
        // Always run password_verify so timing does not reveal whether the user matched.
        $passwordOk = $hash !== '' && password_verify($password, $hash);
        if (!$passwordOk || !hash_equals($expectedUser, $user)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = ['user' => $user, 'seen' => time()];
        return true;
    }

    public static function check(): bool
    {
        $session = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($session) || time() - (int) $session['seen'] > self::IDLE_SECONDS) {
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }
        $_SESSION[self::SESSION_KEY]['seen'] = time();
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }
}
