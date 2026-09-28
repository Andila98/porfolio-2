<?php
/** @var \App\Core\View $view @var list<int> $presets */
$old = $old ?? [];
?>
<form class="tea" method="post" action="<?= $view->url('support') ?>" data-tea-form novalidate>
    <?= $view->csrfField() ?>
    <div class="tea__request mono" aria-hidden="true">POST /api/tips</div>
    <fieldset class="tea__amounts">
        <legend>Amount (KES)</legend>
        <?php foreach ($presets as $i => $preset): ?>
            <label class="chip">
                <input type="radio" name="amount" value="<?= (int) $preset ?>"<?= $i === 1 ? ' checked' : '' ?>>
                <span><?= (int) $preset ?></span>
            </label>
        <?php endforeach; ?>
        <label class="chip chip--custom">
            <input type="radio" name="amount" value="">
            <span>Other</span>
        </label>
        <label class="tea__custom">
            <span class="visually-hidden">Custom amount in KES</span>
            <input type="number" name="custom_amount" min="10" max="10000" step="1" inputmode="numeric" placeholder="10–10,000">
        </label>
    </fieldset>
    <label class="field">
        <span class="field__label">M-Pesa phone number</span>
        <input type="tel" name="phone" required autocomplete="tel" inputmode="tel" placeholder="0712 345 678" value="<?= $view->e($old['phone'] ?? '') ?>">
    </label>
    <button class="btn btn--primary" type="submit">Send STK Push</button>
    <p class="tea__status" data-tea-status role="status" aria-live="polite"></p>
    <p class="field__hint">You'll get an M-Pesa prompt on your phone. Your number is never shown publicly.</p>
</form>
