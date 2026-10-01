<?php
/**
 * @var \App\Core\View $view @var string $name @var array<string, mixed> $schema
 * @var list<array<string, mixed>> $records @var list<string> $history
 * @var string|null $notice @var string|null $error
 */
$key = $schema['key'];
$title = $schema['title'];
?>
<div class="admin__head">
    <h1><?= $view->e($schema['label']) ?></h1>
    <a class="btn btn--primary" href="<?= $view->url('admin/collections/' . $name . '/new') ?>">+ Add</a>
</div>
<?php if ($notice): ?><p class="notice notice--ok" role="status"><?= $view->e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice notice--err" role="alert"><?= $view->e($error) ?></p><?php endif; ?>

<div class="table-wrap">
<table class="table">
    <thead><tr><th>Order</th><th><?= $view->e($schema['fields'][$title]['label']) ?></th><th><?= $view->e($schema['fields'][$key]['label']) ?></th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($records as $i => $record): ?>
        <tr>
            <td class="nowrap">
                <?php foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow): ?>
                    <form class="inline" method="post" action="<?= $view->url('admin/collections/' . $name . '/move') ?>">
                        <?= $view->csrfField() ?>
                        <input type="hidden" name="key" value="<?= $view->e($record[$key]) ?>">
                        <input type="hidden" name="direction" value="<?= $dir ?>">
                        <button class="icon-btn icon-btn--sm" type="submit" aria-label="Move <?= $dir ?>"<?= ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($records) - 1) ? ' disabled' : '' ?>><?= $arrow ?></button>
                    </form>
                <?php endforeach; ?>
            </td>
            <td><a href="<?= $view->url('admin/collections/' . $name . '/edit/' . rawurlencode((string) $record[$key])) ?>"><?= $view->e($record[$title] ?? '') ?></a></td>
            <td class="mono"><?= $view->e($record[$key]) ?></td>
            <td><?= !empty($record['hidden']) ? '<span class="tag">hidden</span>' : '<span class="tag tag--milestone">live</span>' ?></td>
            <td class="nowrap">
                <form class="inline" method="post" action="<?= $view->url('admin/collections/' . $name . '/delete') ?>" data-confirm="Delete &quot;<?= $view->e($record[$title] ?? '') ?>&quot;? You can restore it from history.">
                    <?= $view->csrfField() ?>
                    <input type="hidden" name="key" value="<?= $view->e($record[$key]) ?>">
                    <button class="btn btn--sm btn--danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($history): ?>
    <details class="history">
        <summary>History (<?= count($history) ?> earlier versions)</summary>
        <ul class="plain">
            <?php foreach ($history as $file): ?>
                <li>
                    <form class="inline" method="post" action="<?= $view->url('admin/collections/' . $name . '/restore') ?>" data-confirm="Restore <?= $view->e($file) ?>? The current version is kept in history too.">
                        <?= $view->csrfField() ?>
                        <input type="hidden" name="file" value="<?= $view->e($file) ?>">
                        <span class="mono"><?= $view->e($file) ?></span>
                        <button class="btn btn--sm" type="submit">Restore</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endif; ?>
