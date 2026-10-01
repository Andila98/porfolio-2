<?php /** @var \App\Core\View $view @var int $status @var string $title @var string|null $detail */ ?>
<section class="node node--narrow error-page" aria-labelledby="err-title">
    <header class="node__head">
        <span class="node__id">HTTP <?= (int) $status ?></span>
        <h1 class="node__title" id="err-title"><?= $view->e($title) ?></h1>
    </header>
    <div class="node__body">
        <pre class="mono"><?= $status === 404 ? 'curl: (22) The requested URL returned error: 404' : 'process exited with status ' . (int) $status ?></pre>
        <?php if ($detail): ?><p><?= $view->e($detail) ?></p><?php endif; ?>
        <p><a class="btn btn--primary" href="<?= $view->url('/') ?>">cd ~</a> <a class="btn" href="<?= $view->url('projects') ?>">ls deployments</a></p>
    </div>
</section>
