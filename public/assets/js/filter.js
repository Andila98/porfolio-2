// Client-side tag filter for /projects. Without JS the chips are plain ?tag= links.
const nav = document.querySelector('[data-filter]');
const list = document.querySelector('[data-filter-list]');

if (nav && list) {
    const empty = list.querySelector('[data-filter-empty]');

    const apply = (tag) => {
        let shown = 0;
        for (const item of list.querySelectorAll('.release')) {
            const tags = (item.dataset.tags ?? '').split('|');
            const visible = tag === '' || tags.includes(tag);
            item.hidden = !visible;
            if (visible) shown++;
        }
        if (empty) empty.hidden = shown > 0;
        for (const chip of nav.querySelectorAll('[data-tag]')) {
            const active = chip.dataset.tag === tag;
            chip.classList.toggle('is-active', active);
            if (active) chip.setAttribute('aria-current', 'true'); else chip.removeAttribute('aria-current');
        }
    };

    nav.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-tag]');
        if (!chip) return;
        event.preventDefault();
        apply(chip.dataset.tag);
        history.replaceState(null, '', chip.href);
    });
}
