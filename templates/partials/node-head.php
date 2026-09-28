<?php /** @var \App\Core\View $view @var string $num @var string $title @var string $id @var string $meta */ ?>
<header class="node__head">
    <span class="node__id">NODE <?= $view->e($num) ?></span>
    <h2 class="node__title" id="<?= $view->e($id) ?>-title"><?= $view->e($title) ?></h2>
    <?php if (!empty($meta)): ?><span class="node__meta"><?= $view->e($meta) ?></span><?php endif; ?>
</header>
