// Buy Me a Tea: submits the STK Push request as JSON and polls for the result.
// Without JavaScript the same form posts normally and a status page refreshes itself.
import { api } from './util.js';

const POLL_MS = 3000;
const TIMEOUT_MS = 90000;

for (const form of document.querySelectorAll('[data-tea-form]')) {
    const status = form.querySelector('[data-tea-status]');
    const button = form.querySelector('button[type="submit"]');
    const custom = form.querySelector('input[name="custom_amount"]');
    const other = form.querySelector('.chip--custom input');

    const say = (text, kind = 'info') => {
        status.textContent = text;
        status.className = 'tea__status is-' + kind;
    };

    // Typing a custom amount selects "Other"; picking a preset clears it.
    custom?.addEventListener('input', () => { if (other) other.checked = true; });
    for (const radio of form.querySelectorAll('input[name="amount"]')) {
        radio.addEventListener('change', () => {
            if (radio !== other && custom) custom.value = '';
            if (radio === other) custom?.focus();
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const picked = form.querySelector('input[name="amount"]:checked');
        const amount = picked && picked.value !== '' ? picked.value : (custom?.value ?? '');
        const phone = form.querySelector('input[name="phone"]').value;

        button.disabled = true;
        say('Sending payment prompt…');
        try {
            const { ok, data } = await api('api/tips', { method: 'POST', body: JSON.stringify({ amount, phone }) });
            if (!ok) {
                say(data?.error ?? 'Something went wrong. Please try again.', 'err');
                return;
            }
            say('Check your phone and enter your M-Pesa PIN…');
            await poll(data.tipId, say);
        } catch {
            say('Network error. Please check your connection and try again.', 'err');
        } finally {
            button.disabled = false;
        }
    });
}

async function poll(tipId, say) {
    const started = Date.now();
    while (Date.now() - started < TIMEOUT_MS) {
        await new Promise((resolve) => setTimeout(resolve, POLL_MS));
        const { ok, data } = await api('api/tips/' + encodeURIComponent(tipId));
        if (!ok || !data) continue;
        if (data.status === 'completed') {
            say(data.message + (data.receipt ? ' Receipt ' + data.receipt + '.' : ''), 'ok');
            return;
        }
        if (data.status !== 'pending') {
            say(data.message, 'err');
            return;
        }
    }
    say('Still waiting on M-Pesa. If you paid, it will be recorded; you can close this page.', 'info');
}
