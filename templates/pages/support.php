<?php
/** @var \App\Core\View $view @var list<int> $presets @var string|null $error @var array<string, string> $old */
$view->share('scripts', ['tea.js']);
?>
<section class="page-head">
    <p class="mono crumbs"><a href="<?= $view->url('/') ?>">~</a> / sponsor</p>
    <h1>Buy Me a Tea</h1>
    <p class="lead">If my work helped you, a cup of tea keeps the builds green. Payments go through M-Pesa and stay private.</p>
</section>
<section class="node node--narrow" id="sponsor" aria-labelledby="sponsor-title">
    <?= $view->partial('partials/node-head', ['num' => '06', 'title' => 'Sponsor Endpoint', 'id' => 'sponsor', 'meta' => 'STK Push']) ?>
    <div class="node__body">
        <?php if ($error): ?><p class="notice notice--err" role="alert"><?= $view->e($error) ?></p><?php endif; ?>
        <?= $view->partial('partials/tea-form', ['presets' => $presets, 'old' => $old]) ?>
    </div>
</section>
