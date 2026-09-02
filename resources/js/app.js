import './bootstrap';
import './html-to-markdown';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/* ------------------------------------------------------------------
 * Modal focus management.
 *
 * Our dialogs handled Escape and carried aria-modal, but focus stayed on
 * whatever was behind them: opening "Delete this customer?" with a keyboard
 * left you tabbing through the page underneath, unable to reach the two
 * buttons you were being asked to choose between.
 *
 * This keys off the semantics that are already in the markup -
 * [role="dialog"][aria-modal="true"] - rather than a custom directive, so it
 * works no matter how a dialog is shown (Alpine x-show, a class toggle, or
 * plain JS) and covers dialogs added later without anyone remembering to opt
 * in. A first attempt used an Alpine directive and silently never fired;
 * observing the DOM is harder to get subtly wrong.
 * ---------------------------------------------------------------- */
const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

const isVisible = (n) => n.offsetWidth > 0 || n.offsetHeight > 0 || n.getClientRects().length > 0;
// A Set as well as the WeakMap, because a dialog can leave by being REMOVED
// from the DOM rather than hidden. querySelectorAll cannot see those, so
// without a list of what is currently open the listener leaks and focus is
// never handed back.
const trapped = new WeakMap();
const openDialogs = new Set();

function focusablesIn(dialog) {
    return Array.from(dialog.querySelectorAll(FOCUSABLE)).filter(isVisible);
}

function openTrap(dialog) {
    if (trapped.has(dialog)) return;

    const onKeydown = (e) => {
        if (e.key !== 'Tab') return;
        const items = focusablesIn(dialog);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        // Wrap both ways so focus can never escape behind the dialog.
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    };

    trapped.set(dialog, { onKeydown, previous: document.activeElement });
    openDialogs.add(dialog);
    document.addEventListener('keydown', onKeydown, true);

    // Move focus in. A dialog may still be mid-transition, so retry for a few
    // frames rather than guessing a delay.
    const grab = (tries = 0) => {
        if (!trapped.has(dialog)) return;
        const first = focusablesIn(dialog)[0];
        if (first) { first.focus(); return; }
        if (tries < 30) requestAnimationFrame(() => grab(tries + 1));
    };
    grab();
}

function closeTrap(dialog) {
    const state = trapped.get(dialog);
    if (!state) return;
    document.removeEventListener('keydown', state.onKeydown, true);
    trapped.delete(dialog);
    openDialogs.delete(dialog);
    // Send focus back to whatever opened it, rather than dumping the user at
    // the top of the document.
    if (state.previous && document.contains(state.previous) && isVisible(state.previous)) {
        state.previous.focus();
    }
}

function syncTraps() {
    // Close any dialog that was detached from the document entirely.
    openDialogs.forEach((dialog) => {
        if (!document.contains(dialog)) closeTrap(dialog);
    });

    document.querySelectorAll('[role="dialog"][aria-modal="true"]').forEach((dialog) => {
        isVisible(dialog) ? openTrap(dialog) : closeTrap(dialog);
    });
}

const dialogObserver = new MutationObserver(syncTraps);
document.addEventListener('DOMContentLoaded', () => {
    dialogObserver.observe(document.body, {
        subtree: true, childList: true,
        attributes: true, attributeFilter: ['style', 'class', 'hidden', 'aria-modal', 'role'],
    });
    syncTraps();
});

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

/* ------------------------------------------------------------------
 * PWA — register service worker for offline asset caching + install prompt.
 *
 * The worker (public/service-worker.js) is conservative: it caches only
 * /build/* (Vite-hashed) and /brand/* assets. HTML, POSTs, and dynamic
 * routes always hit the network so CSRF/sessions can never go stale.
 *
 * Skipped on localhost without HTTPS would normally fail, but localhost
 * is treated as a secure context by browsers, so SW registers there too.
 * ---------------------------------------------------------------- */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .catch(() => { /* fail silently — SW is progressive enhancement */ });
    });
}

/* ------------------------------------------------------------------
 * Count-up animation for marketing-page stats.
 *
 * Any element with [data-countup="N"] animates 0 → N when it scrolls
 * into view. Add data-format="inr" for Indian number system (₹12,50,000).
 * Respects prefers-reduced-motion: snaps straight to final value.
 * ---------------------------------------------------------------- */
(() => {
    const formatInr = (n) => {
        // xx,xx,xxx layout — lakhs/crores
        const s = Math.round(n).toString();
        if (s.length <= 3) return s;
        const last3 = s.slice(-3);
        const rest = s.slice(0, -3);
        return `${rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',')},${last3}`;
    };

    const animateOne = (el) => {
        const target = parseFloat(el.dataset.countup);
        if (!Number.isFinite(target)) return;
        const useInr = el.dataset.format === 'inr';
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            el.textContent = useInr ? formatInr(target) : Math.round(target).toLocaleString('en-IN');
            return;
        }
        const duration = 1400;
        const start = performance.now();
        const easeOut = (t) => 1 - Math.pow(1 - t, 3);
        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const v = target * easeOut(t);
            el.textContent = useInr ? formatInr(v) : Math.round(v).toLocaleString('en-IN');
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    const start = () => {
        const targets = document.querySelectorAll('[data-countup]:not([data-countup-done])');
        if (!targets.length) return;
        if (!('IntersectionObserver' in window)) {
            targets.forEach((el) => { animateOne(el); el.setAttribute('data-countup-done', ''); });
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                animateOne(entry.target);
                entry.target.setAttribute('data-countup-done', '');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.4 });
        targets.forEach((el) => io.observe(el));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
