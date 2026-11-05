{{--
    Phone-only sign-up bar, for signed-out visitors. Include it AFTER the
    cookie banner and the install bar: it looks both up by their labels.

    Shown only when all of these hold:
    - the sign-up card in the hero (#lp-hero-cta) has scrolled up out of view;
    - the closing call to action (#lp-final-cta) is not near or above the
      viewport, so the footer is never covered;
    - the cookie banner is closed (it owns the bottom edge until answered);
    - no form field has focus (the phone keyboard is up).
    When the install bar is open, this bar sits on top of it, and the
    WhatsApp button (.wa-float) is lifted above both.

    "Create free account" scrolls back to that card and focuses the name
    field (the [data-lp-signup] handler lives in the hero partial).
--}}
@guest
<div id="lp-sticky" inert aria-hidden="true"
     class="md:hidden fixed inset-x-0 bottom-0 z-40 translate-y-[120%] opacity-0 pointer-events-none transition duration-300 ease-out"
     role="region" aria-label="Sign up">
    <div class="border-t border-gray-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-6px_20px_rgba(15,23,42,0.08)]">
        <div class="mx-auto flex max-w-md gap-2">
            <a href="#signup" data-lp-signup class="flex min-h-[48px] flex-1 items-center justify-center rounded-lg bg-brand-700 px-4 font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Create free account</a>
            @if (filled(config('services.google.client_id')))
                <a href="{{ route('social.redirect', 'google') }}" aria-label="Continue with Google"
                   class="inline-flex min-h-[48px] shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 font-semibold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">
                    <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                        <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                        <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571.001-.001.002-.001.003-.002l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                    </svg>
                    <span>Google</span>
                </a>
            @endif
        </div>
    </div>
</div>
<style>
    body.lp-sticky-on .wa-float { bottom: calc(var(--lp-sticky-h, 0px) + 0.75rem); transition: bottom .3s ease-out; }
</style>
<script>
    (function () {
        var bar = document.getElementById('lp-sticky');
        var hero = document.getElementById('lp-hero-cta');
        var end = document.getElementById('lp-final-cta');
        if (!bar || !hero || !('IntersectionObserver' in window)) {
            return;
        }
        var cookieEl = document.querySelector('[aria-labelledby="cookie-banner-title"]');
        var installEl = document.querySelector('[aria-label="Install Apna Invoice"]');
        var hiddenClasses = ['translate-y-[120%]', 'opacity-0', 'pointer-events-none'];
        var state = { pastHero: false, nearEnd: false, typing: false };

        function shown(el) {
            return !!el && el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none';
        }
        function cookieOpen() {
            if (cookieEl) {
                return shown(cookieEl);
            }
            try { return !localStorage.getItem('cookiePrefs'); } catch (e) { return false; }
        }
        function update() {
            var lift = shown(installEl) ? installEl.offsetHeight : 0;
            bar.style.bottom = lift + 'px';
            // offsetHeight is 0 at md and up, where the bar is display:none.
            var on = bar.offsetHeight > 0 && state.pastHero && !state.nearEnd && !state.typing && !cookieOpen();
            hiddenClasses.forEach(function (c) { bar.classList.toggle(c, !on); });
            if (on) {
                bar.removeAttribute('inert');
                bar.removeAttribute('aria-hidden');
            } else {
                bar.setAttribute('inert', '');
                bar.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.toggle('lp-sticky-on', on);
            document.body.style.setProperty('--lp-sticky-h', (on ? bar.offsetHeight + lift : 0) + 'px');
        }

        new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                state.pastHero = !e.isIntersecting && e.boundingClientRect.bottom < 0;
            });
            update();
        }).observe(hero);

        if (end) {
            new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    state.nearEnd = e.isIntersecting || e.boundingClientRect.top < 0;
                });
                update();
            }, { rootMargin: '0px 0px 160px 0px' }).observe(end);
        }

        document.addEventListener('focusin', function (e) {
            state.typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
            update();
        });
        document.addEventListener('focusout', function () {
            state.typing = false;
            setTimeout(update, 50);
        });
        window.addEventListener('cookie-choice-made', function () { setTimeout(update, 350); });
        window.addEventListener('resize', update);
        if ('ResizeObserver' in window) {
            var ro = new ResizeObserver(update);
            if (installEl) { ro.observe(installEl); }
            if (cookieEl) { ro.observe(cookieEl); }
        }
    })();
</script>
@endguest
