<?php

declare(strict_types=1);

// Generates the ADMIN_PASSWORD_HASH value for .env.
// Usage: php bin/hash-password.php            (prompts)
//        php bin/hash-password.php 'secret'   (argument; avoid on shared machines)

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$password = $argv[1] ?? null;
if ($password === null) {
    fwrite(STDOUT, 'Admin password: ');
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Use at least 12 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
// Single quotes keep the $ signs literal when the .env file is read.
fwrite(STDOUT, "ADMIN_PASSWORD_HASH='{$hash}'\n");
