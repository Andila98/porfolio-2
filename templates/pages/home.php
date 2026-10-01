<?php
/**
 * @var \App\Core\View $view
 * @var array<string, mixed> $site
 * @var list<array<string, mixed>> $skills
 * @var list<array<string, mixed>> $projects
 * @var list<array<string, mixed>> $achievements
 * @var list<array<string, mixed>> $experience
 * @var list<array<string, mixed>> $cvs
 * @var list<int> $presets
 * @var bool $contactSent
 * @var array<string, string> $contactErrors
 * @var array<string, string> $contactOld
 */
$primarySkills = array_filter($skills, static fn (array $s): bool => !empty($s['primary']));
$primaryCv = null;
foreach ($cvs as $cv) {
    if (!empty($cv['primary'])) {
        $primaryCv = $cv;
    }
}
$showcase = array_values(array_filter($projects, static fn (array $p): bool => empty($p['work_project'])));
$workProjects = array_values(array_filter($projects, static fn (array $p): bool => !empty($p['work_project'])));
$view->share('scripts', ['terminal.js', 'tea.js']);
?>
<?php
$real = static fn (mixed $v): bool => is_string($v) && trim($v) !== '' && !str_starts_with($v, 'TODO');
$currentRole = null;
foreach ($experience as $item) {
    if (($item['kind'] ?? '') === 'work' && empty($item['end'])) {
        $currentRole = $item;
        break;
    }
}
$stackLine = [];
foreach ($primarySkills as $group) {
    foreach ($group['items'] as $item) {
        $stackLine[] = preg_replace('/\s*\/.*$/', '', (string) $item);
    }
}
$stackLine = array_slice(array_values(array_unique($stackLine)), 0, 4);
$when = static function (array $item) use ($view): string {
    $start = $view->date($item['start'] ?? '');
    $end = !empty($item['end']) ? $view->date($item['end']) : '';
    if ($start !== '') {
        return $start . ' – ' . ($end !== '' ? $end : 'present');
    }
    return $end;
};
?>
<section class="node node--hero" id="top" aria-labelledby="hero-title">
    <?= $view->partial('partials/node-head', ['num' => '00', 'title' => 'Ingress', 'id' => 'hero-head', 'meta' => 'port 443', 'tag' => 'span']) ?>
    <div class="hero">
        <div class="hero__copy">
            <p class="hero__prompt mono"><span class="prompt">$</span> whoami</p>
            <h1 class="hero__title" id="hero-title"><?= $view->e($site['name'] ?? '') ?></h1>
            <p class="hero__role"><?= $view->e($site['title'] ?? '') ?></p>
            <?php if ($real($site['tagline'] ?? null)): ?><p class="hero__tagline"><?= $view->e($site['tagline']) ?></p><?php endif; ?>
            <dl class="spec">
                <?php if ($real($site['location'] ?? null)): ?><div><dt>Location</dt><dd><?= $view->e($site['location']) ?></dd></div><?php endif; ?>
                <?php if ($currentRole !== null): ?><div><dt>Current role</dt><dd><?= $view->e($currentRole['role']) ?><?= $real($currentRole['org'] ?? null) ? ', ' . $view->e($currentRole['org']) : '' ?></dd></div><?php endif; ?>
                <?php if ($real($site['availability'] ?? null)): ?><div><dt>Status</dt><dd class="spec__ok"><?= $view->e($site['availability']) ?></dd></div><?php endif; ?>
                <?php if ($stackLine): ?><div><dt>Core stack</dt><dd><?= $view->e(implode(', ', $stackLine)) ?></dd></div><?php endif; ?>
            </dl>
            <div class="hero__cta">
                <a class="btn btn--primary" href="<?= $view->url('projects') ?>">View projects</a>
                <?php if ($primaryCv !== null && $primaryCv['available']): ?>
                    <a class="btn" href="<?= $view->url('cv/' . $primaryCv['key']) ?>"><svg class="icon" aria-hidden="true"><use href="#i-download"/></svg>Download CV</a>
                <?php else: ?>
                    <a class="btn" href="#artifacts">CV versions</a>
                <?php endif; ?>
                <a class="btn btn--ghost" href="#console"><svg class="icon" aria-hidden="true"><use href="#i-terminal"/></svg>Open console</a>
            </div>
        </div>
        <?= $view->partial('partials/pipeline') ?>
    </div>
</section>

<div class="grid">

<section class="node node--wide" id="core" aria-labelledby="core-title">
    <?= $view->partial('partials/node-head', ['num' => '01', 'title' => 'Core Services', 'id' => 'core', 'meta' => 'service.yml']) ?>
    <div class="node__body core">
        <div class="core__main">
            <div class="config">
                <p class="config__file mono">/etc/andila/service.yml</p>
                <dl class="config__block">
                    <dt>service</dt><dd class="mono"><?= $view->e(strtolower((string) ($site['name'] ?? ''))) ?>-engineering</dd>
                    <dt>role</dt><dd><?= $view->e($site['title'] ?? '') ?></dd>
                    <?php if ($real($site['bio'] ?? null)): ?><dt>about</dt><dd><?= $view->e($site['bio']) ?></dd><?php endif; ?>
                    <?php if (!empty($site['philosophy'])): ?>
                        <dt>principles</dt>
                        <dd><ol class="config__list"><?php foreach ($site['philosophy'] as $line): ?><li><?= $view->e($line) ?></li><?php endforeach; ?></ol></dd>
                    <?php endif; ?>
                </dl>
            </div>
            <?php if ($primarySkills): ?>
                <h3 class="subhead">Primary stack</h3>
                <ul class="badges badges--lg" aria-label="Primary stack">
                    <?php foreach ($primarySkills as $group): ?>
                        <?php foreach ($group['items'] as $item): ?><li class="badge badge--accent"><?= $view->e($item) ?></li><?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php $secondary = array_filter($skills, static fn (array $s): bool => empty($s['primary'])); ?>
            <?php if ($secondary): ?>
                <h3 class="subhead">Also works with</h3>
                <div class="skills">
                    <?php foreach ($secondary as $group): ?>
                        <div class="skills__group">
                            <h4 class="skills__label"><?= $view->e($group['label']) ?></h4>
                            <p class="skills__items"><?= $view->e(implode(', ', $group['items'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($experience): ?>
            <aside class="core__history" aria-labelledby="history-title">
                <h3 class="subhead" id="history-title">Runtime history</h3>
                <ol class="timeline">
                    <?php foreach ($experience as $item): ?>
                        <li class="timeline__item<?= empty($item['end']) && ($item['kind'] ?? '') === 'work' ? ' is-current' : '' ?>">
                            <?php if ($when($item) !== ''): ?><span class="timeline__when mono"><?= $view->e($when($item)) ?></span><?php endif; ?>
                            <strong class="timeline__role"><?= $view->e($real($item['role'] ?? null) ? $item['role'] : '') ?></strong>
                            <?php if ($real($item['org'] ?? null)): ?><span class="timeline__org"><?= $view->e($item['org']) ?></span><?php endif; ?>
                            <?php if ($real($item['summary'] ?? null)): ?><p><?= $view->e($item['summary']) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </aside>
        <?php endif; ?>
    </div>
</section>

<section class="node node--wide" id="deployments" aria-labelledby="deployments-title">
    <?= $view->partial('partials/node-head', ['num' => '02', 'title' => 'Production Deployments', 'id' => 'deployments', 'meta' => 'CHANGELOG.md']) ?>
    <div class="node__body">
        <div class="changelog">
            <?php foreach ($showcase as $project): ?>
                <?= $view->partial('partials/release', ['project' => $project]) ?>
            <?php endforeach; ?>
            <?php foreach ($workProjects as $project): ?>
                <?= $view->partial('partials/release', ['project' => $project]) ?>
            <?php endforeach; ?>
        </div>
        <p class="node__foot"><a class="link" href="<?= $view->url('projects') ?>">All projects, filterable by stack</a></p>
    </div>
</section>

<section class="node node--wide" id="console" aria-labelledby="console-title">
    <?= $view->partial('partials/node-head', ['num' => '03', 'title' => 'CLI Console', 'id' => 'console', 'meta' => 'tty1']) ?>
    <div class="node__body">
        <div class="terminal" data-terminal>
            <div class="terminal__bar" aria-hidden="true"><span></span><span></span><span></span><em>guest@<?= $view->e(strtolower((string) ($site['name'] ?? 'portfolio'))) ?>: ~</em></div>
            <div class="terminal__out" data-terminal-out role="log" aria-live="polite" aria-label="Console output">
                <p>Welcome. Type <kbd>help</kbd> or tap a command below.</p>
            </div>
            <form class="terminal__in" data-terminal-form hidden>
                <label for="terminal-input" class="prompt">guest:~$</label>
                <input id="terminal-input" name="cmd" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Type a command">
            </form>
            <nav class="terminal__chips" aria-label="Quick commands">
                <span class="terminal__chips-label">Try</span>
                <a class="chip" href="#console" data-cmd="help">help</a>
                <a class="chip" href="#core" data-cmd="skills">skills</a>
                <a class="chip" href="#deployments" data-cmd="git log --oneline">git log</a>
                <a class="chip" href="#core" data-cmd="neofetch">neofetch</a>
                <a class="chip" href="#contact" data-cmd="contact">contact</a>
                <a class="chip" href="<?= $view->url('support') ?>" data-cmd="brew tea">brew tea</a>
            </nav>
        </div>
    </div>
</section>

<section class="node" id="milestones" aria-labelledby="milestones-title">
    <?= $view->partial('partials/node-head', ['num' => '04', 'title' => 'Release Milestones', 'id' => 'milestones', 'meta' => 'achievements.log']) ?>
    <div class="node__body">
        <?php if ($achievements): ?>
            <ol class="log">
                <?php foreach ($achievements as $item): ?>
                    <li class="log__line">
                        <time class="mono" datetime="<?= $view->e($item['date']) ?>"><?= $view->e($item['date']) ?></time>
                        <span class="tag tag--<?= $view->e($item['category'] ?? 'milestone') ?>"><?= $view->e($item['category'] ?? '') ?></span>
                        <span class="log__text">
                            <strong><?= $view->e($item['title']) ?></strong><?php if (!empty($item['issuer'])): ?> · <?= $view->e($item['issuer']) ?><?php endif; ?>
                            <?php if (!empty($item['proof_url'])): ?> <a class="link" href="<?= $view->e($item['proof_url']) ?>" rel="noopener">proof</a><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="empty">No milestones published yet. Certifications, awards and launches appear here as they land.</p>
        <?php endif; ?>
    </div>
</section>

<section class="node" id="artifacts" aria-labelledby="artifacts-title">
    <?= $view->partial('partials/node-head', ['num' => '05', 'title' => 'Build Artifacts', 'id' => 'artifacts', 'meta' => 'dist/'] ) ?>
    <div class="node__body">
        <ul class="artifacts">
            <?php foreach ($cvs as $cv): ?>
                <li class="artifact">
                    <span class="artifact__name mono"><?= $view->e($cv['label']) ?><?= !empty($cv['primary']) ? ' <em>(master)</em>' : '' ?></span>
                    <?php if ($cv['available']): ?>
                        <?php if (!empty($cv['updated'])): ?><span class="artifact__meta muted">updated <?= $view->e($view->date($cv['updated'], 'j M Y')) ?></span><?php endif; ?>
                        <a class="btn btn--sm" href="<?= $view->url('cv/' . $cv['key']) ?>"><svg class="icon" aria-hidden="true"><use href="#i-download"/></svg>PDF</a>
                    <?php else: ?>
                        <span class="artifact__meta muted">coming soon</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="node" id="sponsor" aria-labelledby="sponsor-title">
    <?= $view->partial('partials/node-head', ['num' => '06', 'title' => 'Sponsor Endpoint', 'id' => 'sponsor', 'meta' => 'POST /api/tips']) ?>
    <div class="node__body">
        <p class="node__intro">Enjoyed something here? Buy me a cup of tea through M-Pesa.</p>
        <?= $view->partial('partials/tea-form', ['presets' => $presets]) ?>
    </div>
</section>

<section class="node" id="contact" aria-labelledby="contact-title">
    <?= $view->partial('partials/node-head', ['num' => '07', 'title' => 'Contact Gateway', 'id' => 'contact', 'meta' => 'POST /contact']) ?>
    <div class="node__body">
        <?php if ($contactSent): ?>
            <p class="notice notice--ok" role="status"><span class="mono">200 OK</span> Message received. I'll reply within 24 hours.</p>
        <?php endif; ?>
        <?php if (!empty($contactErrors['form'])): ?><p class="notice notice--err" role="alert"><?= $view->e($contactErrors['form']) ?></p><?php endif; ?>
        <form class="form" method="post" action="<?= $view->url('contact') ?>" novalidate>
            <?= $view->csrfField() ?>
            <input type="hidden" name="project" value="<?= $view->e($contactOld['project'] ?? '') ?>">
            <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <?php foreach (['name' => ['Name', 'text', 'name'], 'email' => ['Email', 'email', 'email']] as $field => [$label, $type, $auto]): ?>
                <label class="field">
                    <span class="field__label"><?= $label ?></span>
                    <input type="<?= $type ?>" name="<?= $field ?>" required autocomplete="<?= $auto ?>" value="<?= $view->e($contactOld[$field] ?? '') ?>"<?= isset($contactErrors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . $field . '"' : '' ?>>
                    <?php if (isset($contactErrors[$field])): ?><span class="field__error" id="err-<?= $field ?>"><?= $view->e($contactErrors[$field]) ?></span><?php endif; ?>
                </label>
            <?php endforeach; ?>
            <label class="field">
                <span class="field__label">Subject</span>
                <input type="text" name="subject" value="<?= $view->e($contactOld['subject'] ?? (!empty($contactOld['project']) ? 'Walkthrough request: ' . $contactOld['project'] : '')) ?>">
            </label>
            <label class="field">
                <span class="field__label">Message</span>
                <textarea name="message" rows="5" required<?= isset($contactErrors['message']) ? ' aria-invalid="true" aria-describedby="err-message"' : '' ?>><?= $view->e($contactOld['message'] ?? '') ?></textarea>
                <?php if (isset($contactErrors['message'])): ?><span class="field__error" id="err-message"><?= $view->e($contactErrors['message']) ?></span><?php endif; ?>
            </label>
            <button class="btn btn--primary" type="submit">Send message</button>
        </form>
        <ul class="direct">
            <?php if (!empty($site['email'])): ?><li><a class="link" href="mailto:<?= $view->e($site['email']) ?>"><svg class="icon" aria-hidden="true"><use href="#i-mail"/></svg><?= $view->e($site['email']) ?></a></li><?php endif; ?>
            <?php if (!empty($site['github'])): ?><li><a class="link" href="<?= $view->e($site['github']) ?>" rel="me noopener"><svg class="icon" aria-hidden="true"><use href="#i-github"/></svg>GitHub</a></li><?php endif; ?>
            <?php if (!empty($site['linkedin'])): ?><li><a class="link" href="<?= $view->e($site['linkedin']) ?>" rel="me noopener"><svg class="icon" aria-hidden="true"><use href="#i-in"/></svg>LinkedIn</a></li><?php endif; ?>
            <?php if (!empty($site['whatsapp'])): ?><li><a class="link" href="https://wa.me/<?= $view->e(preg_replace('/\D/', '', $site['whatsapp'])) ?>" rel="noopener"><svg class="icon" aria-hidden="true"><use href="#i-chat"/></svg>WhatsApp</a></li><?php endif; ?>
        </ul>
    </div>
</section>

</div>
