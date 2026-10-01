<?php
/**
 * @var \App\Core\View $view @var string $name @var array<string, mixed> $schema
 * @var array<string, mixed> $record @var string|null $originalKey @var array<string, string> $errors
 */
$isSingleton = !empty($schema['singleton']);
$toText = static function (mixed $value, string $type): string {
    return match ($type) {
        'list' => implode("\n", (array) $value),
        'links' => implode("\n", array_map(static fn (array $l): string => $l['label'] . ' | ' . $l['url'], (array) $value)),
        default => is_scalar($value) ? (string) $value : '',
    };
};
?>
<div class="admin__head">
    <h1><?= $view->e($schema['label']) ?>: <?= $originalKey !== null && !$isSingleton ? 'edit' : ($isSingleton ? 'edit' : 'new') ?></h1>
    <?php if (!$isSingleton): ?><a class="btn" href="<?= $view->url('admin/collections/' . $name) ?>">&larr; Back</a><?php endif; ?>
</div>
<?php if (!empty($errors['_form'])): ?><p class="notice notice--err" role="alert"><?= $view->e($errors['_form']) ?></p><?php endif; ?>
<?php if ($errors && empty($errors['_form'])): ?><p class="notice notice--err" role="alert">Please fix the highlighted fields.</p><?php endif; ?>

<form class="form admin-form" method="post" action="<?= $view->url('admin/collections/' . $name . '/save') ?>">
    <?= $view->csrfField() ?>
    <input type="hidden" name="_original_key" value="<?= $view->e($originalKey ?? '') ?>">
    <?php foreach ($schema['fields'] as $field => $def): ?>
        <?php
        $value = $record[$field] ?? ($def['type'] === 'bool' ? false : '');
        $id = 'f-' . $field;
        $invalid = isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : '';
        ?>
        <?php if ($def['type'] === 'bool'): ?>
            <label class="check" for="<?= $id ?>">
                <input type="checkbox" id="<?= $id ?>" name="<?= $field ?>" value="1"<?= $value ? ' checked' : '' ?>>
                <span><?= $view->e($def['label']) ?></span>
            </label>
        <?php else: ?>
            <div class="field">
                <label class="field__label" for="<?= $id ?>"><?= $view->e($def['label']) ?><?= !empty($def['required']) ? ' *' : '' ?></label>
                <?php if ($def['type'] === 'select'): ?>
                    <select id="<?= $id ?>" name="<?= $field ?>"<?= $invalid ?>>
                        <?php foreach ($def['options'] as $option): ?>
                            <option value="<?= $view->e($option) ?>"<?= $value === $option ? ' selected' : '' ?>><?= $view->e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (in_array($def['type'], ['textarea', 'list', 'links'], true)): ?>
                    <textarea id="<?= $id ?>" name="<?= $field ?>" rows="<?= $def['type'] === 'textarea' ? 4 : 5 ?>"<?= $invalid ?>><?= $view->e($toText($value, $def['type'])) ?></textarea>
                    <?php if ($def['type'] === 'list'): ?><span class="field__hint">One item per line.</span><?php endif; ?>
                    <?php if ($def['type'] === 'links'): ?><span class="field__hint">One per line: <code>Label | https://…</code></span><?php endif; ?>
                <?php else: ?>
                    <input type="<?= $def['type'] === 'url' ? 'url' : 'text' ?>" id="<?= $id ?>" name="<?= $field ?>" value="<?= $view->e($toText($value, $def['type'])) ?>"<?= $def['type'] === 'date' ? ' placeholder="YYYY-MM or YYYY-MM-DD"' : '' ?><?= $invalid ?>>
                <?php endif; ?>
                <?php if (isset($errors[$field])): ?><span class="field__error" id="<?= $id ?>-err"><?= $view->e($errors[$field]) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="admin-form__actions">
        <button class="btn btn--primary" type="submit">Save</button>
    </div>
</form>
