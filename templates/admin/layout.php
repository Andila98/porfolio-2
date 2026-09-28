<?php /** @var \App\Core\View $view @var string $content @var array<string, mixed> $site */ ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin · <?= $view->e($site['name'] ?? '') ?></title>
    <link rel="stylesheet" href="<?= $view->asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
    <script type="module" src="<?= $view->asset('js/admin.js') ?>"></script>
</head>
<body class="admin">
    <header class="topbar">
        <div class="topbar__inner shell">
            <a class="brand" href="<?= $view->url('admin') ?>"><span class="brand__mark">#</span><span>admin</span></a>
            <nav class="topnav" aria-label="Admin">
                <a href="<?= $view->url('admin') ?>">Dashboard</a>
                <?php foreach (\App\Content\CollectionSchema::all() as $key => $schema): ?>
                    <a href="<?= $view->url('admin/collections/' . $key) ?>"><?= $view->e($schema['label']) ?></a>
                <?php endforeach; ?>
                <a href="<?= $view->url('admin/uploads') ?>">Uploads</a>
                <a href="<?= $view->url('admin/ledger') ?>">Ledger</a>
                <a href="<?= $view->url('admin/messages') ?>">Messages</a>
            </nav>
            <div class="topbar__actions">
                <a class="btn btn--sm" href="<?= $view->url('/') ?>">View site</a>
                <form method="post" action="<?= $view->url('admin/logout') ?>"><?= $view->csrfField() ?><button class="btn btn--sm" type="submit">Log out</button></form>
            </div>
        </div>
    </header>
    <main class="shell admin__main"><?= $content ?></main>
</body>
</html>
