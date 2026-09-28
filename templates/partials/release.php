<?php
/** @var \App\Core\View $view @var array<string, mixed> $project */
$isWork = !empty($project['work_project']);
$detailUrl = $view->url('projects/' . $project['slug']);
?>
<article class="release<?= $isWork ? ' release--work' : '' ?>" data-tags="<?= $view->e(implode('|', $project['tags'] ?? [])) ?>">
    <div class="release__rail" aria-hidden="true"></div>
    <header class="release__head">
        <?php if (!empty($project['version'])): ?>
            <span class="tag tag--version"><?= $view->e($project['version']) ?></span>
        <?php endif; ?>
        <h3 class="release__title">
            <?php if ($isWork): ?>
                <?= $view->e($project['title']) ?>
            <?php else: ?>
                <a href="<?= $view->e($detailUrl) ?>"><?= $view->e($project['title']) ?></a>
            <?php endif; ?>
        </h3>
        <span class="release__type"><?= $view->e($project['type'] ?? '') ?></span>
        <?php if (!empty($project['released'])): ?>
            <time class="release__date" datetime="<?= $view->e($project['released']) ?>"><?= $view->e($view->date($project['released'])) ?></time>
        <?php endif; ?>
    </header>
    <p class="release__summary"><?= $view->e($project['summary'] ?? '') ?></p>
    <?php if (!empty($project['stack'])): ?>
        <ul class="badges" aria-label="Stack">
            <?php foreach (array_slice($project['stack'], 0, 8) as $item): ?><li class="badge"><?= $view->e($item) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!$isWork): ?>
        <?= $view->partial('partials/project-links', ['project' => $project, 'compact' => true]) ?>
    <?php endif; ?>
</article>
