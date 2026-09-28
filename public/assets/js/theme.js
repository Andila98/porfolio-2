// Light/dark toggle. The initial theme is set by an inline script in <head>
// (before first paint); this module only handles switching and remembering it.

const root = document.documentElement;

export function setTheme(theme) {
    const next = theme === 'light' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('theme', next); } catch { /* private mode: not remembered */ }
    return next;
}

export function currentTheme() {
    return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

for (const button of document.querySelectorAll('[data-theme-toggle]')) {
    button.addEventListener('click', () => setTheme(currentTheme() === 'dark' ? 'light' : 'dark'));
}
