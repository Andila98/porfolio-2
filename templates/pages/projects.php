<?php
/**
 * @var \App\Core\View $view
 * @var list<array<string, mixed>> $projects
 * @var list<string> $tags
 * @var string $activeTag
 */
$view->share('scripts', ['filter.js']);
?>
<section class="page-head">
    <p class="mono crumbs"><a href="<?= $view->url('/') ?>">~</a> / deployments</p>
    <h1>Production Deployments</h1>
    <p class="lead">Every project as a release: what it handles, what it's built with, and where the code lives.</p>
</section>

<nav class="filter" aria-label="Filter by stack" data-filter>
    <a class="chip<?= $activeTag === '' ? ' is-active' : '' ?>" href="<?= $view->url('projects') ?>" data-tag=""<?= $activeTag === '' ? ' aria-current="true"' : '' ?>>all</a>
    <?php foreach ($tags as $tag): ?>
        <a class="chip<?= $activeTag === $tag ? ' is-active' : '' ?>" href="<?= $view->url('projects?tag=' . rawurlencode($tag)) ?>" data-tag="<?= $view->e($tag) ?>"<?= $activeTag === $tag ? ' aria-current="true"' : '' ?>><?= $view->e($tag) ?></a>
    <?php endforeach; ?>
</nav>

<section class="node node--wide" aria-label="Releases">
    <div class="node__body changelog" data-filter-list>
        <?php foreach ($projects as $project): ?>
            <?= $view->partial('partials/release', ['project' => $project]) ?>
        <?php endforeach; ?>
        <p class="muted" data-filter-empty<?= $projects ? ' hidden' : '' ?>>No releases with that tag yet.</p>
    </div>
</section>
