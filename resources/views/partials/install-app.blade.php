{{--
    "Install Apna Invoice" bar for phones and tablets, on the public site and
    in the app. Apna Invoice is an installable web app (public/manifest.json
    + public/service-worker.js): installed, it opens from the home screen in
    its own window, like an app from the Play Store.

    - Android (Chrome, Edge, Samsung Internet): the browser fires
      beforeinstallprompt when the site can be installed; Install opens the
      browser's own install dialog.
    - iPhone and iPad (Safari): there is no install prompt, so Install shows
      the two steps: Share, then Add to Home Screen.
    - Never shown when already opened as the installed app, on desktop
      (lg and up), while the cookie banner is still waiting for an answer,
      or for 14 days after "Not now".
--}}
<div x-data="installApp()" x-init="init()" x-cloak x-show="visible"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="lg:hidden fixed inset-x-0 bottom-0 z-40 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pointer-events-none"
     role="region" aria-label="Install Apna Invoice">
    <div class="pointer-events-auto mx-auto max-w-md bg-white rounded-xl shadow-2xl ring-1 ring-gray-200 p-3">
        <div class="flex items-center gap-3">
            <img src="/brand/icon-192.png?v=3" alt="" width="40" height="40" class="w-10 h-10 rounded-lg shrink-0">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900">Install Apna Invoice</p>
                <p class="text-xs text-gray-600">Opens from your home screen like an app. Free.</p>
            </div>
            <button type="button" @click="install()"
                    class="shrink-0 inline-flex items-center justify-center h-9 px-4 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold">Install</button>
            <button type="button" @click="dismiss()" aria-label="Not now"
                    class="shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <ol x-show="iosSteps" x-cloak class="mt-3 pt-3 border-t border-gray-100 text-sm text-gray-700 list-decimal pl-5 space-y-1">
            <li>Tap the Share button
                <svg class="inline w-4 h-4 -mt-0.5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12M8 7l4-4 4 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                in Safari's toolbar.</li>
            <li>Choose <strong>Add to Home Screen</strong>, then <strong>Add</strong>.</li>
        </ol>
    </div>
</div>

<script>
    // Chromium fires this once the page can be installed, often before Alpine
    // starts: keep the event so the bar can use it whenever it is ready.
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.__apnaInstallPrompt = e;
        window.dispatchEvent(new CustomEvent('apna-install-available'));
    });

    function installApp() {
        return {
            visible: false,
            iosSteps: false,
            canPrompt: false,
            isIos: false,
            DISMISS_KEY: 'installAppDismissedAt',
            DISMISS_DAYS: 14,
            init() {
                if (this.isInstalled() || this.recentlyDismissed()) {
                    return;
                }
                const ua = navigator.userAgent || '';
                // iPadOS reports itself as a Mac: tell it apart by touch.
                this.isIos = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
                // Only Safari can add to the home screen on iOS.
                const iosSafari = this.isIos && /Safari/.test(ua) && ! /CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(ua);

                this.canPrompt = !! window.__apnaInstallPrompt;
                window.addEventListener('apna-install-available', () => { this.canPrompt = true; this.maybeShow(iosSafari); });
                window.addEventListener('appinstalled', () => { this.visible = false; window.__apnaInstallPrompt = null; });
                window.addEventListener('cookie-choice-made', () => setTimeout(() => this.maybeShow(iosSafari), 1500));
                setTimeout(() => this.maybeShow(iosSafari), 2500);
            },
            maybeShow(iosSafari) {
                if (this.isInstalled() || this.recentlyDismissed() || this.cookieBannerOpen()) {
                    return;
                }
                if (this.canPrompt || iosSafari) {
                    this.visible = true;
                }
            },
            async install() {
                const prompt = window.__apnaInstallPrompt;
                if (prompt) {
                    prompt.prompt();
                    try {
                        const choice = await prompt.userChoice;
                        if (choice && choice.outcome === 'accepted') {
                            this.visible = false;
                        }
                    } catch (e) { /* the dialog was closed */ }
                    // A prompt can be used once; the browser offers a new one later.
                    window.__apnaInstallPrompt = null;
                    this.canPrompt = false;
                    return;
                }
                if (this.isIos) {
                    this.iosSteps = ! this.iosSteps;
                }
            },
            dismiss() {
                this.visible = false;
                try { localStorage.setItem(this.DISMISS_KEY, String(Date.now())); } catch (e) { /* private mode */ }
            },
            isInstalled() {
                return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            },
            recentlyDismissed() {
                try {
                    const at = Number(localStorage.getItem(this.DISMISS_KEY) || 0);
                    return at > 0 && Date.now() - at < this.DISMISS_DAYS * 86400000;
                } catch (e) {
                    return false;
                }
            },
            cookieBannerOpen() {
                try {
                    return ! localStorage.getItem('cookiePrefs');
                } catch (e) {
                    return false;
                }
            },
        };
    }
</script>
