// Small shared helpers for the page scripts.

const base = document.querySelector('meta[name="base-path"]')?.content ?? '';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Internal URL that respects the base path (XAMPP subfolders). */
export const url = (path) => base + '/' + String(path).replace(/^\//, '');

/** JSON request with the CSRF header. Resolves to { ok, status, data }. */
export async function api(path, options = {}) {
    const response = await fetch(url(path), {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            'X-CSRF-Token': csrf,
            ...(options.headers ?? {}),
        },
    });
    let data = null;
    try { data = await response.json(); } catch { /* empty body */ }
    return { ok: response.ok, status: response.status, data };
}
