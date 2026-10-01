<?php
/** @var \App\Core\View $view @var array<string, mixed> $project */
$slug = (string) $project['slug'];
$images = array_filter(
    glob(dirname(__DIR__, 2) . '/public/assets/img/projects/' . $slug . '/*') ?: [],
    static fn (string $f): bool => (bool) preg_match('/\.(jpe?g|png|webp)$/i', $f),
);
$sections = [
    'problem' => 'Problem',
    'solution' => 'Approach',
    'challenges' => 'Challenges',
    'results' => 'Results',
];
?>
<article class="project">
    <header class="page-head">
        <p class="mono crumbs"><a href="<?= $view->url('/') ?>">~</a> / <a href="<?= $view->url('projects') ?>">deployments</a> / <?= $view->e($slug) ?></p>
        <h1>
            <?php if (!empty($project['version'])): ?><span class="tag tag--version"><?= $view->e($project['version']) ?></span><?php endif; ?>
            <?= $view->e($project['title']) ?>
        </h1>
        <p class="lead"><?= $view->e($project['summary']) ?></p>
        <p class="mono muted">
            <?= $view->e($project['type'] ?? '') ?>
            <?php if (!empty($project['role'])): ?> · role: <?= $view->e($project['role']) ?><?php endif; ?>
            <?php if (!empty($project['released'])): ?> · released <?= $view->e($view->date($project['released'])) ?><?php endif; ?>
        </p>
        <?= $view->partial('partials/project-links', ['project' => $project]) ?>
    </header>

    <div class="grid">
        <section class="node node--wide" aria-labelledby="notes-title">
            <?= $view->partial('partials/node-head', ['num' => '02.' . $slug, 'title' => 'Release notes', 'id' => 'notes', 'meta' => 'README.md']) ?>
            <div class="node__body prose">
                <?php foreach ($sections as $field => $label): ?>
                    <?php if (!empty($project[$field]) && !str_starts_with((string) $project[$field], 'TODO')): ?>
                        <h2><?= $label ?></h2>
                        <p><?= nl2br($view->e($project[$field])) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($project['highlights'])): ?>
                    <h2>Highlights</h2>
                    <ul class="checklist">
                        <?php foreach ($project['highlights'] as $item): ?><li><?= $view->e($item) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($project['stack'])): ?>
            <section class="node node--wide" aria-labelledby="stack-title">
                <?= $view->partial('partials/node-head', ['num' => '01', 'title' => 'Stack', 'id' => 'stack', 'meta' => 'dependencies']) ?>
                <div class="node__body">
                    <ul class="badges badges--lg"><?php foreach ($project['stack'] as $item): ?><li class="badge badge--accent"><?= $view->e($item) ?></li><?php endforeach; ?></ul>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($images): ?>
            <section class="node node--wide" aria-labelledby="shots-title">
                <?= $view->partial('partials/node-head', ['num' => '03', 'title' => 'Screenshots', 'id' => 'shots', 'meta' => count($images) . ' files']) ?>
                <div class="node__body shots">
                    <?php foreach ($images as $image): ?>
                        <?php $name = basename($image); ?>
                        <figure>
                            <img src="<?= $view->url('assets/img/projects/' . $slug . '/' . $name) ?>" alt="<?= $view->e($project['title'] . ': ' . str_replace('-', ' ', pathinfo($name, PATHINFO_FILENAME))) ?>" loading="lazy" decoding="async">
                        </figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <p><a class="link" href="<?= $view->url('projects') ?>">&larr; All releases</a></p>
</article>
