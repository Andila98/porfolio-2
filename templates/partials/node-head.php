<?php /** @var \App\Core\View $view @var string $num @var string $title @var string $id @var string $meta */
$tag = ($tag ?? 'h2') === 'span' ? 'span' : 'h2';
?>
<header class="node__head">
    <span class="node__id">node <?= $view->e($num) ?></span>
    <<?= $tag ?> class="node__title" id="<?= $view->e($id) ?>-title"><?= $view->e($title) ?></<?= $tag ?>>
    <?php if (!empty($meta)): ?><span class="node__meta"><?= $view->e($meta) ?></span><?php endif; ?>
</header>
