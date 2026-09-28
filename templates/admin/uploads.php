<?php
/** @var \App\Core\View $view @var list<array<string, mixed>> $cvs @var list<array<string, mixed>> $projects @var string|null $notice @var string|null $error */
?>
<h1>Uploads</h1>
<?php if ($notice): ?><p class="notice notice--ok" role="status"><?= $view->e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice notice--err" role="alert"><?= $view->e($error) ?></p><?php endif; ?>
<div class="grid">
    <section class="node">
        <header class="node__head"><span class="node__id">DIST</span><h2 class="node__title">CV PDFs</h2><span class="node__meta">max 5 MB</span></header>
        <div class="node__body">
            <ul class="plain">
                <?php foreach ($cvs as $cv): ?>
                    <li><span class="mono"><?= $view->e($cv['key']) ?></span>: <?= $view->e($cv['label']) ?> · <?= $cv['available'] ? 'uploaded' . (!empty($cv['updated']) ? ' ' . $view->e($cv['updated']) : '') : '<span class="muted">missing</span>' ?></li>
                <?php endforeach; ?>
            </ul>
            <form class="form" method="post" action="<?= $view->url('admin/uploads/cv') ?>" enctype="multipart/form-data">
                <?= $view->csrfField() ?>
                <label class="field"><span class="field__label">Version</span>
                    <select name="key"><?php foreach ($cvs as $cv): ?><option value="<?= $view->e($cv['key']) ?>"><?= $view->e($cv['label']) ?></option><?php endforeach; ?></select>
                </label>
                <label class="field"><span class="field__label">PDF file</span><input type="file" name="file" accept="application/pdf" required></label>
                <button class="btn btn--primary" type="submit">Upload CV</button>
            </form>
        </div>
    </section>
    <section class="node">
        <header class="node__head"><span class="node__id">IMG</span><h2 class="node__title">Project screenshots</h2><span class="node__meta">JPG/PNG/WebP, max 2 MB</span></header>
        <div class="node__body">
            <form class="form" method="post" action="<?= $view->url('admin/uploads/image') ?>" enctype="multipart/form-data">
                <?= $view->csrfField() ?>
                <label class="field"><span class="field__label">Project</span>
                    <select name="slug"><?php foreach ($projects as $project): ?><option value="<?= $view->e($project['slug']) ?>"><?= $view->e($project['title']) ?></option><?php endforeach; ?></select>
                </label>
                <label class="field"><span class="field__label">Image</span><input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
                <p class="field__hint">The file name becomes the caption, e.g. <code>tenant-dashboard.webp</code>. Prefer WebP under 200 KB.</p>
                <button class="btn btn--primary" type="submit">Upload image</button>
            </form>
        </div>
    </section>
</div>
