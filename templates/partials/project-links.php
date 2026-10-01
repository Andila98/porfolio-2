<?php
/** @var \App\Core\View $view @var array<string, mixed> $project @var bool $compact */
$compact = $compact ?? false;
?>
<ul class="links">
    <?php if ($compact): ?>
        <li><a class="link" href="<?= $view->url('projects/' . $project['slug']) ?>">Release notes</a></li>
    <?php endif; ?>
    <?php if (!empty($project['private_repo'])): ?>
        <li><a class="link link--private" href="<?= $view->url('/?project=' . rawurlencode($project['slug']) . '#contact') ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>Private repo: walkthrough on request</a></li>
    <?php else: ?>
        <?php foreach ($project['repos'] ?? [] as $repo): ?>
            <li><a class="link" href="<?= $view->e($repo['url']) ?>" rel="noopener">
                <svg class="icon" aria-hidden="true"><use href="#i-github"/></svg><?= $view->e(count($project['repos']) > 1 ? $repo['label'] : 'Repository') ?></a></li>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($project['live_url'])): ?>
        <li><a class="link" href="<?= $view->e($project['live_url']) ?>" rel="noopener"><svg class="icon" aria-hidden="true"><use href="#i-link"/></svg>Live demo</a></li>
    <?php endif; ?>
    <?php if (!empty($project['docs_url'])): ?>
        <li><a class="link" href="<?= $view->e($project['docs_url']) ?>" rel="noopener"><svg class="icon" aria-hidden="true"><use href="#i-doc"/></svg>Docs</a></li>
    <?php endif; ?>
</ul>
