<?php /** @var \App\Core\View $view @var string|null $error @var bool $configured */ ?>
<section class="node node--narrow admin-login">
    <header class="node__head"><span class="node__id">AUTH</span><h1 class="node__title">Admin login</h1></header>
    <div class="node__body">
        <?php if (!$configured): ?>
            <p class="notice notice--err">No admin password is set. Run <code>php bin/hash-password.php</code> and put the result in <code>ADMIN_PASSWORD_HASH</code> in <code>.env</code>.</p>
        <?php endif; ?>
        <?php if ($error): ?><p class="notice notice--err" role="alert"><?= $view->e($error) ?></p><?php endif; ?>
        <form class="form" method="post" action="<?= $view->url('admin/login') ?>">
            <?= $view->csrfField() ?>
            <label class="field"><span class="field__label">Username</span><input type="text" name="user" autocomplete="username" required autofocus></label>
            <label class="field"><span class="field__label">Password</span><input type="password" name="password" autocomplete="current-password" required></label>
            <button class="btn btn--primary" type="submit">Log in</button>
        </form>
    </div>
</section>
