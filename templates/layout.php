<?php
/**
 * @var \App\Core\View $view
 * @var array<string, mixed> $site
 * @var string $content
 * @var string $nonce
 */
$name = $site['name'] ?? 'Portfolio';
$fullTitle = isset($pageTitle) ? $pageTitle . ' · ' . $name : $name . ' · ' . ($site['title'] ?? '');
$description = $metaDescription ?? $site['meta_description'] ?? '';
$scripts = $scripts ?? [];
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $view->e($fullTitle) ?></title>
    <meta name="description" content="<?= $view->e($description) ?>">
    <meta name="csrf-token" content="<?= $view->e(\App\Core\Csrf::token()) ?>">
    <meta name="base-path" content="<?= $view->e(rtrim($view->url(''), '/')) ?>">
    <meta name="theme-color" content="#0b1622">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $view->e($fullTitle) ?>">
    <meta property="og:description" content="<?= $view->e($description) ?>">
    <link rel="icon" href="<?= $view->url('favicon.svg') ?>" type="image/svg+xml">
    <script nonce="<?= $view->e($nonce) ?>">
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (!t) { t = window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <link rel="preload" href="<?= $view->url('assets/fonts/plex-sans-400.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $view->asset('css/main.css') ?>">
    <script type="module" src="<?= $view->asset('js/theme.js') ?>"></script>
    <?php foreach ($scripts as $script): ?>
    <script type="module" src="<?= $view->asset('js/' . $script) ?>"></script>
    <?php endforeach; ?>
    <?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json" nonce="<?= $view->e($nonce) ?>"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
    <?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <?= $view->partial('partials/icons') ?>
    <?= $view->partial('partials/header') ?>
    <main id="main" class="shell">
        <?= $content ?>
    </main>
    <?= $view->partial('partials/footer') ?>
    <?= $view->partial('partials/tabbar') ?>
</body>
</html>
