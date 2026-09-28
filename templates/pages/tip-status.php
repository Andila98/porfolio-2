<?php
/** @var \App\Core\View $view @var array{status: string, amount: int, receipt: ?string, message: string} $tip @var string $tipId */
$pending = $tip['status'] === 'pending';
?>
<section class="node node--narrow" aria-labelledby="tip-title">
    <?= $view->partial('partials/node-head', ['num' => '06', 'title' => 'Sponsor Endpoint', 'id' => 'tip', 'meta' => 'GET /api/tips/' . substr($tipId, 0, 8)]) ?>
    <div class="node__body">
        <p class="notice notice--<?= $tip['status'] === 'completed' ? 'ok' : ($pending ? 'info' : 'err') ?>" role="status">
            <?= $view->e($tip['message']) ?>
        </p>
        <p class="mono">amount: KES <?= (int) $tip['amount'] ?><?php if ($tip['receipt']): ?> · receipt: <?= $view->e($tip['receipt']) ?><?php endif; ?></p>
        <?php if (!$pending): ?>
            <p><a class="link" href="<?= $view->url('/') ?>">&larr; Back home</a><?php if ($tip['status'] !== 'completed'): ?> · <a class="link" href="<?= $view->url('support') ?>">Try again</a><?php endif; ?></p>
        <?php endif; ?>
    </div>
</section>
