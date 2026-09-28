<?php /** @var \App\Core\View $view @var list<array<string, mixed>> $messages */ ?>
<h1>Messages</h1>
<?php if (!$messages): ?><p class="muted">No messages yet.</p><?php endif; ?>
<div class="messages">
    <?php foreach ($messages as $message): ?>
        <article class="node message<?= $message['read_at'] === null ? ' message--unread' : '' ?>">
            <header class="node__head">
                <span class="node__id"><?= $message['read_at'] === null ? 'NEW' : 'READ' ?></span>
                <h2 class="node__title"><?= $view->e($message['subject'] !== '' ? $message['subject'] : '(no subject)') ?></h2>
                <span class="node__meta"><?= $view->e($message['created_at']) ?></span>
            </header>
            <div class="node__body">
                <p class="mono"><?= $view->e($message['name']) ?> &lt;<a href="mailto:<?= $view->e($message['email']) ?>"><?= $view->e($message['email']) ?></a>&gt;<?php if ($message['project']): ?> · project: <?= $view->e($message['project']) ?><?php endif; ?><?= $message['mail_sent'] ? '' : ' · <span class="muted">not emailed</span>' ?></p>
                <p><?= nl2br($view->e($message['body'])) ?></p>
                <?php if ($message['read_at'] === null): ?>
                    <form method="post" action="<?= $view->url('admin/messages/read') ?>">
                        <?= $view->csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                        <button class="btn btn--sm" type="submit">Mark as read</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
