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
