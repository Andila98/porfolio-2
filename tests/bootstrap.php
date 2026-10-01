<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

\App\Core\Env::set('APP_SECRET', 'test-secret');
date_default_timezone_set('Africa/Nairobi');
