<?php /** @var \App\Core\View $view */ ?>
<nav class="tabbar" aria-label="Sections">
    <a href="<?= $view->url('/#core') ?>"><svg class="icon" aria-hidden="true"><use href="#i-core"/></svg><span>About</span></a>
    <a href="<?= $view->url('projects') ?>"><svg class="icon" aria-hidden="true"><use href="#i-stack"/></svg><span>Projects</span></a>
    <a href="<?= $view->url('/#console') ?>"><svg class="icon" aria-hidden="true"><use href="#i-terminal"/></svg><span>Console</span></a>
    <a href="<?= $view->url('support') ?>"><svg class="icon" aria-hidden="true"><use href="#i-cup"/></svg><span>Tea</span></a>
    <a href="<?= $view->url('/#contact') ?>"><svg class="icon" aria-hidden="true"><use href="#i-mail"/></svg><span>Contact</span></a>
</nav>
