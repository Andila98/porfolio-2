<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Security;

// Built-in dev server: let it serve real files (CSS, JS, images) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

/** @var \App\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$request = Request::fromGlobals($app->basePath);
Security::startSession($request->isHttps());
$app->handle($request)->send();
