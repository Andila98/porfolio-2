<?php /** @var \App\Core\View $view @var string $content */ ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin login</title>
    <link rel="stylesheet" href="<?= $view->asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
</head>
<body class="admin">
    <main class="shell admin__main"><?= $content ?></main>
</body>
</html>
