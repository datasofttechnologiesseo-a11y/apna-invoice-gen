import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/* ------------------------------------------------------------------
 * Global form-submit UX
 *
 * Every non-GET form automatically:
 *   - disables its submit button(s) on submit
 *   - sets [data-submitting] on the form (for CSS spinner hooks)
 *   - restores state if the browser cancels the navigation (bfcache)
 *
 * Opt out with: <form data-no-submit-state>
 * ---------------------------------------------------------------- */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-submit-state')) return;
    if ((form.method || 'get').toLowerCase() === 'get') return;

    // HTML5 validation failed — do not lock the button.
    if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

    form.setAttribute('data-submitting', '');

    // Disable every submit control, but keep it in the form so the server
    // still receives its name=value when the last-clicked button has one.
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = true;
        btn.setAttribute('data-was-enabled', '1');
    });
});

/* If the user navigates back (bfcache) and sees the same page, restore
 * the button states so they're not stuck. */
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        form.removeAttribute('data-submitting');
        form.querySelectorAll('[data-was-enabled]').forEach((btn) => {
            btn.disabled = false;
            btn.removeAttribute('data-was-enabled');
        });
    });
});
