<?php

declare(strict_types=1);

// Settles tips that never received a Daraja callback by querying their status.
// Run every 5 minutes from cron (see README).

if (PHP_SAPI !== 'cli') {
    exit(1);
}

/** @var \App\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $settled = $app->tips()->reconcile(300);
    $message = "reconcile-tips: settled {$settled} tip(s)";
    $app->log('info', $message);
    fwrite(STDOUT, $message . PHP_EOL);
} catch (Throwable $e) {
    $app->log('error', 'reconcile-tips failed: ' . $e->getMessage());
    fwrite(STDERR, 'reconcile-tips failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
