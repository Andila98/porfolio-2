<?php /** @var \App\Core\View $view @var array<string, mixed> $site */ ?>
<footer class="footer">
    <div class="shell footer__inner">
        <p class="mono">&copy; <?= date('Y') ?> <?= $view->e($site['name'] ?? '') ?> · build v<?= $view->e($site['version'] ?? '') ?></p>
        <p class="footer__links">
            <?php if (!empty($site['github'])): ?><a href="<?= $view->e($site['github']) ?>" rel="me noopener">GitHub</a><?php endif; ?>
            <?php if (!empty($site['linkedin'])): ?><a href="<?= $view->e($site['linkedin']) ?>" rel="me noopener">LinkedIn</a><?php endif; ?>
            <a href="<?= $view->url('support') ?>">Buy me a tea</a>
            <a href="https://github.com/Andila98/porfolio-2" rel="noopener">Source</a>
        </p>
    </div>
</footer>
