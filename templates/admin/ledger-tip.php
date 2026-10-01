<?php /** @var \App\Core\View $view @var string $tipId @var list<array<string, mixed>> $events */ ?>
<div class="admin__head">
    <h1>Tip <span class="mono"><?= $view->e(substr($tipId, 0, 8)) ?></span></h1>
    <a class="btn" href="<?= $view->url('admin/ledger') ?>">&larr; Ledger</a>
</div>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>#</th><th>Time</th><th>Event</th><th>Env</th><th>Source</th><th>KES</th><th>Checkout</th><th>Receipt</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($events as $event): ?>
        <tr>
            <td class="mono"><?= (int) $event['id'] ?></td>
            <td class="mono nowrap"><?= $view->e($event['created_at']) ?></td>
            <td><span class="tag tag--<?= $view->e(strtolower((string) $event['event'])) ?>"><?= $view->e($event['event']) ?></span></td>
            <td class="mono"><?= $view->e($event['environment']) ?></td>
            <td class="mono"><?= $view->e($event['source']) ?></td>
            <td><?= (int) $event['amount'] ?></td>
            <td class="mono"><?= $view->e($event['checkout_request_id'] ?? '') ?></td>
            <td class="mono"><?= $view->e($event['mpesa_receipt'] ?? '') ?></td>
            <td><?= $event['result_code'] !== null ? '<span class="mono">' . (int) $event['result_code'] . '</span> ' : '' ?><?= $view->e($event['result_desc'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
