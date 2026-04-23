/* ------------------------------------------------------------------
 * Landing-page JS bundle.
 *
 * Intentionally excludes Alpine.js and axios — the marketing pages
 * have no interactive components or XHR, so pulling in the full app
 * bundle wastes ~26KB gzipped on the first page most visitors see.
 * ---------------------------------------------------------------- */

/* Global form-submit UX — only the newsletter form uses this on landing. */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-submit-state')) return;
    if ((form.method || 'get').toLowerCase() === 'get') return;
    if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

    form.setAttribute('data-submitting', '');
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = true;
        btn.setAttribute('data-was-enabled', '1');
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        form.removeAttribute('data-submitting');
        form.querySelectorAll('[data-was-enabled]').forEach((btn) => {
            btn.disabled = false;
            btn.removeAttribute('data-was-enabled');
        });
    });
});

/* Count-up for marketing stats. Animates 0 → N when scrolled into view. */
(() => {
    const formatInr = (n) => {
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
