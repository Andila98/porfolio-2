<?php /** @var \App\Core\View $view @var list<array<string, mixed>> $tips @var list<array<string, mixed>> $totals @var int $page */ ?>
<div class="admin__head">
    <h1>Tip ledger</h1>
    <a class="btn" href="<?= $view->url('admin/ledger?format=csv') ?>">Export CSV</a>
</div>
<p class="muted">Append-only: every request, callback and reconciliation is a separate row. Click a tip to see its full history.</p>

<?php if ($totals): ?>
    <h2 class="subhead mono">completed per month</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Month</th><th>Tips</th><th>KES</th></tr></thead><tbody>
        <?php foreach ($totals as $row): ?><tr><td class="mono"><?= $view->e($row['month']) ?></td><td><?= (int) $row['tips'] ?></td><td><?= number_format((int) $row['total']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<h2 class="subhead mono">tips (latest status)</h2>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Started</th><th>Tip</th><th>KES</th><th>Phone</th><th>Status</th><th>Receipt</th><th>Last update</th></tr></thead>
    <tbody>
    <?php foreach ($tips as $tip): ?>
        <tr>
            <td class="mono nowrap"><?= $view->e(substr((string) $tip['started_at'], 0, 16)) ?></td>
            <td class="mono"><a href="<?= $view->url('admin/ledger?tip=' . $tip['tip_id']) ?>"><?= $view->e(substr((string) $tip['tip_id'], 0, 8)) ?></a></td>
            <td><?= number_format((int) $tip['amount']) ?></td>
            <td class="mono">•••<?= $view->e($tip['phone_last3']) ?></td>
            <td><span class="tag tag--<?= $view->e(strtolower((string) $tip['event'])) ?>"><?= $view->e($tip['event']) ?></span></td>
            <td class="mono"><?= $view->e($tip['mpesa_receipt'] ?? '') ?></td>
            <td class="mono nowrap"><?= $view->e(substr((string) $tip['created_at'], 0, 16)) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$tips): ?><tr><td colspan="7" class="muted">No tips yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<p class="pager">
    <?php if ($page > 1): ?><a class="link" href="<?= $view->url('admin/ledger?page=' . ($page - 1)) ?>">&larr; Newer</a><?php endif; ?>
    <?php if (count($tips) === 50): ?><a class="link" href="<?= $view->url('admin/ledger?page=' . ($page + 1)) ?>">Older &rarr;</a><?php endif; ?>
</p>
