<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Env;

$root = dirname(__DIR__);

// Composer autoloader when available (PHPMailer, PHPUnit); otherwise a tiny
// PSR-4 fallback so the site still runs on a fresh XAMPP checkout.
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        if (str_starts_with($class, 'App\\')) {
            $file = $root . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

Env::load($root . '/.env');

date_default_timezone_set('Africa/Nairobi');
error_reporting(E_ALL);
ini_set('display_errors', Env::bool('APP_DEBUG') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $root . '/storage/logs/php-error.log');

/**
 * Base path of the site, e.g. "/porfolio-2/public" on XAMPP or "" on a vhost.
 * If the request came through the repository-root .htaccess, "/public" is not
 * part of the visible URL, so it is stripped.
 */
function detect_base_path(): string
{
    $configured = Env::get('APP_BASE_PATH');
    if ($configured !== null) {
        return rtrim($configured, '/');
    }
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $scriptDir = rtrim($scriptDir, '/');
    $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($scriptDir !== '' && !str_starts_with($uri, $scriptDir) && str_ends_with($scriptDir, '/public')) {
        return substr($scriptDir, 0, -strlen('/public'));
    }
    return $scriptDir;
}

return new App($root, PHP_SAPI === 'cli' ? rtrim((string) Env::get('APP_BASE_PATH', ''), '/') : detect_base_path());
