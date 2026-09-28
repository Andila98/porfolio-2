<?php /** @var \App\Core\View $view @var array<string, mixed> $site */ ?>
<span class="status-badge" title="Site release <?= $view->e($site['version'] ?? '') ?>">
    <span class="status-badge__dot" aria-hidden="true"></span>
    System Status: <?= $view->e($site['status'] ?? 'Online') ?> <span class="status-badge__ver">v<?= $view->e($site['version'] ?? '1.0.0') ?></span>
</span>
