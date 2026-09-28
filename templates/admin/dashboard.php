<?php /** @var \App\Core\View $view @var array<string, mixed> $stats @var array<string, array<string, mixed>> $collections */ ?>
<h1>Dashboard</h1>
<?php if (!empty($stats['dbError'])): ?>
    <p class="notice notice--err">Database unavailable: <?= $view->e($stats['dbError']) ?>. Did you import <code>database/schema.sql</code>?</p>
<?php endif; ?>
<div class="grid">
    <section class="node">
        <header class="node__head"><span class="node__id">INBOX</span><h2 class="node__title">Messages</h2></header>
        <div class="node__body">
            <p class="stat"><?= $stats['unread'] === null ? '–' : (int) $stats['unread'] ?> <span>unread</span></p>
            <a class="link" href="<?= $view->url('admin/messages') ?>">Open messages &rarr;</a>
        </div>
    </section>
    <section class="node">
        <header class="node__head"><span class="node__id">LEDGER</span><h2 class="node__title">Tips received</h2></header>
        <div class="node__body">
            <?php if ($stats['totals']): ?>
                <table class="table"><thead><tr><th>Month</th><th>Tips</th><th>KES</th></tr></thead><tbody>
                <?php foreach (array_slice($stats['totals'], 0, 6) as $row): ?>
                    <tr><td class="mono"><?= $view->e($row['month']) ?></td><td><?= (int) $row['tips'] ?></td><td><?= number_format((int) $row['total']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php else: ?><p class="muted">No completed tips yet.</p><?php endif; ?>
            <a class="link" href="<?= $view->url('admin/ledger') ?>">Open ledger &rarr;</a>
        </div>
    </section>
    <section class="node">
        <header class="node__head"><span class="node__id">DIST</span><h2 class="node__title">CV downloads</h2></header>
        <div class="node__body">
            <?php if ($stats['downloads']): ?>
                <ul class="plain"><?php foreach ($stats['downloads'] as $row): ?><li><span class="mono"><?= $view->e($row['cv_key']) ?></span>: <?= (int) $row['n'] ?></li><?php endforeach; ?></ul>
            <?php else: ?><p class="muted">No downloads yet.</p><?php endif; ?>
            <a class="link" href="<?= $view->url('admin/uploads') ?>">Upload CVs &rarr;</a>
        </div>
    </section>
    <section class="node">
        <header class="node__head"><span class="node__id">DATA</span><h2 class="node__title">Content</h2></header>
        <div class="node__body">
            <ul class="plain">
                <?php foreach ($collections as $key => $schema): ?>
                    <li><a class="link" href="<?= $view->url('admin/collections/' . $key) ?>"><?= $view->e($schema['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
</div>
