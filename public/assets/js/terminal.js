// NODE 03 · CLI console. Progressive enhancement: without JS the command chips
// are ordinary links to the matching sections.
import { api, url } from './util.js';
import { setTheme } from './theme.js';

const root = document.querySelector('[data-terminal]');
if (root) init(root);

function init(root) {
    const out = root.querySelector('[data-terminal-out]');
    const form = root.querySelector('[data-terminal-form]');
    const input = form.querySelector('input');
    const history = [];
    let cursor = 0;
    let data = null;

    const line = (text = '', cls = '') => {
        const p = document.createElement('p');
        if (cls) p.className = cls;
        p.textContent = text;
        out.append(p);
        return p;
    };
    const link = (label, href) => {
        const p = line();
        const a = document.createElement('a');
        a.href = href;
        a.textContent = label;
        p.append(a);
        return p;
    };
    const load = async () => data ??= (await api('api/content')).data ?? {};
    const projects = () => (data?.projects ?? []).filter((p) => p.url);

    const commands = {
        help: {
            about: 'list commands',
            run() {
                for (const [name, cmd] of Object.entries(commands)) {
                    if (!cmd.hidden) line(name.padEnd(18) + cmd.about, 'dim');
                }
                line('Tip: Tab completes, ↑/↓ browse history.', 'dim');
            },
        },
        whoami: {
            about: 'who is this?',
            run() {
                const s = data.site ?? {};
                line(`${s.name} · ${s.title}`, 'hl');
                if (s.location) line('region: ' + s.location);
                if (s.availability) line('status: ' + s.availability, 'ok');
                if (s.tagline) line(s.tagline);
            },
        },
        neofetch: {
            about: 'system profile',
            run() {
                const s = data.site ?? {};
                const stack = (data.skills ?? []).slice(0, 2).flatMap((g) => g.items).slice(0, 5).join(', ');
                const rows = [
                    ['user', s.name], ['role', s.title], ['region', s.location],
                    ['uptime', s.years_coding && !/todo/i.test(s.years_coding) ? s.years_coding + ' years coding' : 'since first commit'],
                    ['stack', stack], ['releases', projects().length + ' deployed'],
                    ['shell', 'php-8'], ['theme', document.documentElement.dataset.theme],
                ];
                const art = ['   /\\    ', '  /  \\   ', ' / /\\ \\  ', '/ ____ \\ ', '/_/  \\_\\ '];
                line(`${' '.repeat(10)}guest@${(s.name ?? 'portfolio').toLowerCase()}`, 'hl');
                rows.forEach(([k, v], i) => {
                    const p = line((art[i] ?? '').padEnd(10), 'hl');
                    const span = document.createElement('span');
                    span.className = 'dim';
                    span.textContent = ` ${k}: `;
                    p.append(span, document.createTextNode(v ?? ''));
                });
            },
        },
        skills: {
            about: 'stack (skills --backend)',
            run(args) {
                const flag = (args[0] ?? '').replace(/^--/, '');
                const groups = (data.skills ?? []).filter((g) => !flag || g.id === flag);
                if (!groups.length) return line(`skills: unknown group "${flag}". Try: ${(data.skills ?? []).map((g) => '--' + g.id).join(' ')}`, 'err');
                for (const g of groups) line(`[${g.label}] ${g.items.join(' · ')}`);
            },
            complete: () => (data?.skills ?? []).map((g) => '--' + g.id),
        },
        git: {
            about: 'git log --oneline',
            run(args) {
                if (args.join(' ') !== 'log --oneline' && args[0] !== 'log') return line('usage: git log --oneline', 'err');
                for (const p of data.projects ?? []) {
                    const hash = [...p.title].reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 7).toString(16).slice(0, 7);
                    const row = line(hash + ' ', 'hl');
                    row.append(document.createTextNode(`${p.version ? p.version + ' ' : ''}${p.title}: ${p.summary}`));
                }
            },
            complete: () => ['log --oneline'],
        },
        open: {
            about: 'open <project>',
            run(args) {
                const p = projects().find((x) => x.slug === args[0]);
                if (!p) return line(`open: no such project. Try: ${projects().map((x) => x.slug).join(', ')}`, 'err');
                line(`opening ${p.slug}…`, 'ok');
                window.location.href = p.url;
            },
            complete: () => projects().map((p) => p.slug),
        },
        cat: {
            about: 'cat achievements.log',
            run(args) {
                if (args[0] !== 'achievements.log') return line(`cat: ${args[0] ?? ''}: No such file. Try achievements.log`, 'err');
                const list = data.achievements ?? [];
                if (!list.length) line('(empty)', 'dim');
                for (const a of list) line(`${a.date} [${a.category}] ${a.title}${a.issuer ? ' · ' + a.issuer : ''}`);
            },
            complete: () => ['achievements.log'],
        },
        cv: {
            about: 'cv --list | cv <version>',
            run(args) {
                const cvs = data.cvs ?? [];
                if (!args[0] || args[0] === '--list') {
                    for (const c of cvs) line(`${c.key.padEnd(10)} ${c.label}${c.available ? '' : ' (coming soon)'}`, c.available ? '' : 'dim');
                    return;
                }
                const cv = cvs.find((c) => c.key === args[0]);
                if (!cv) return line(`cv: unknown version "${args[0]}"`, 'err');
                if (!cv.available) return line(`${cv.label} is not published yet.`, 'dim');
                line(`downloading ${cv.label}…`, 'ok');
                window.location.href = cv.url;
            },
            complete: () => ['--list', ...(data?.cvs ?? []).map((c) => c.key)],
        },
        curl: {
            about: 'curl /health',
            async run(args) {
                if (args[0] !== '/health') return line('usage: curl /health', 'err');
                const { data: health } = await api('healthz');
                line(JSON.stringify(health), 'ok');
                if (data.site?.availability) line('open_to_work: ' + JSON.stringify(data.site.availability), 'dim');
            },
            complete: () => ['/health'],
        },
        ping: {
            about: 'is anyone there?',
            run() {
                line('PONG: usually replies within 24h', 'ok');
                commands.contact.run();
            },
        },
        contact: {
            about: 'how to reach me',
            run() {
                const s = data.site ?? {};
                if (s.email) link('email    ' + s.email, 'mailto:' + s.email);
                if (s.github) link('github   ' + s.github, s.github);
                if (s.linkedin) link('linkedin ' + s.linkedin, s.linkedin);
                if (s.whatsapp) link('whatsapp ' + s.whatsapp, 'https://wa.me/' + s.whatsapp.replace(/\D/g, ''));
                link('form     #contact', '#contact');
            },
        },
        brew: {
            about: 'brew tea',
            run(args) {
                if (args[0] !== 'tea') return line('brew: only tea is available ☕', 'err');
                line('Brewing… redirecting to the sponsor endpoint.', 'ok');
                const node = document.getElementById('sponsor');
                if (node) node.scrollIntoView(); else window.location.href = url('support');
            },
            complete: () => ['tea'],
        },
        sudo: {
            about: 'sudo hire-me',
            run(args) {
                if (args[0] !== 'hire-me') return line('guest is not in the sudoers file. This incident will be reported.', 'err');
                line('[sudo] access granted. Opening the contact form…', 'ok');
                const subject = document.querySelector('#contact input[name="subject"]');
                if (!subject) { window.location.href = url('/#contact'); return; }
                subject.value = 'Hiring enquiry';
                document.getElementById('contact').scrollIntoView();
                document.querySelector('#contact input[name="name"]')?.focus({ preventScroll: true });
            },
            complete: () => ['hire-me'],
        },
        theme: {
            about: 'theme dark | light',
            run(args) {
                if (!['dark', 'light'].includes(args[0])) return line('usage: theme dark | light', 'err');
                line('theme set to ' + setTheme(args[0]), 'ok');
            },
            complete: () => ['dark', 'light'],
        },
        history: { about: 'previous commands', run: () => history.forEach((h, i) => line(`${String(i + 1).padStart(3)}  ${h}`, 'dim')) },
        clear: { about: 'clear the screen', run: () => out.replaceChildren() },
        exit: {
            about: 'close the session',
            run() {
                out.replaceChildren();
                line('Session closed. Tap a command to start a new one.', 'dim');
                input.blur();
            },
        },
        rm: { hidden: true, run: (args) => line(args.join(' ') === '-rf /' ? 'Permission denied: tenant isolation enforced.' : 'rm: refusing to remove anything here.', 'err') },
    };

    async function run(raw) {
        const text = raw.trim();
        if (!text) return;
        history.push(text);
        cursor = history.length;
        line(text, 'cmd');
        const [name, ...args] = text.split(/\s+/);
        const cmd = commands[name.toLowerCase()];
        try {
            await load();
            if (cmd) await cmd.run(args);
            else line(`command not found: ${name}${suggest(name)}`, 'err');
        } catch {
            line('error: could not reach the server. Try again.', 'err');
        }
        out.scrollTop = out.scrollHeight;
    }

    function suggest(name) {
        let best = null;
        let score = 3;
        for (const candidate of Object.keys(commands)) {
            const d = distance(name.toLowerCase(), candidate);
            if (d < score) { score = d; best = candidate; }
        }
        return best ? `. Did you mean "${best}"?` : '. Type "help".';
    }

    function complete() {
        const value = input.value;
        const parts = value.split(/\s+/);
        let options;
        if (parts.length === 1) {
            options = Object.keys(commands).filter((c) => !commands[c].hidden);
        } else {
            options = commands[parts[0]]?.complete?.() ?? [];
        }
        const prefix = parts.length === 1 ? parts[0] : parts.slice(1).join(' ');
        const matches = options.filter((o) => o.startsWith(prefix));
        if (matches.length === 1) {
            input.value = (parts.length === 1 ? '' : parts[0] + ' ') + matches[0] + (parts.length === 1 ? ' ' : '');
        } else if (matches.length > 1) {
            line(matches.join('  '), 'dim');
            out.scrollTop = out.scrollHeight;
        }
    }

    form.hidden = false;
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        run(input.value);
        input.value = '';
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Tab') {
            event.preventDefault();
            load().then(complete);
        } else if (event.key === 'ArrowUp' && cursor > 0) {
            event.preventDefault();
            input.value = history[--cursor];
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            cursor = Math.min(history.length, cursor + 1);
            input.value = history[cursor] ?? '';
        }
    });
    out.addEventListener('click', () => { if (!window.getSelection()?.toString()) input.focus({ preventScroll: true }); });
    for (const chip of root.querySelectorAll('[data-cmd]')) {
        chip.addEventListener('click', (event) => {
            event.preventDefault();
            run(chip.dataset.cmd);
        });
    }
}

function distance(a, b) {
    const row = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        let prev = row[0];
        row[0] = i;
        for (let j = 1; j <= b.length; j++) {
            const temp = row[j];
            row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + (a[i - 1] === b[j - 1] ? 0 : 1));
            prev = temp;
        }
    }
    return row[b.length];
}
