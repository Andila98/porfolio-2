<?php
/** @var \App\Core\View $view @var array<string, mixed> $site @var array<string, mixed>|null $primaryCv */
$cvHref = $primaryCv !== null && !empty($primaryCv['available']) ? $view->url('cv/' . $primaryCv['key']) : $view->url('/#artifacts');
?>
<header class="topbar">
    <div class="topbar__inner shell">
        <a class="brand" href="<?= $view->url('/') ?>" aria-label="<?= $view->e($site['name'] ?? '') ?>, home">
            <span class="brand__mark" aria-hidden="true">&gt;_</span>
            <span class="brand__name"><?= $view->e($site['name'] ?? '') ?></span>
        </a>
        <?= $view->partial('partials/status-badge') ?>
        <nav class="topnav" aria-label="Main">
            <a href="<?= $view->url('/#core') ?>">About</a>
            <a href="<?= $view->url('projects') ?>">Projects</a>
            <a href="<?= $view->url('/#console') ?>">Console</a>
            <a href="<?= $view->url('support') ?>">Tea</a>
            <a href="<?= $view->url('/#contact') ?>">Contact</a>
        </nav>
        <div class="topbar__actions">
            <a class="btn btn--primary btn--sm" href="<?= $view->e($cvHref) ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-download"/></svg><span>CV</span>
            </a>
            <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch colour theme">
                <svg class="icon icon--sun" aria-hidden="true"><use href="#i-sun"/></svg>
                <svg class="icon icon--moon" aria-hidden="true"><use href="#i-moon"/></svg>
            </button>
        </div>
    </div>
</header>
