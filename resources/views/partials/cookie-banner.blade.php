{{-- Cookie banner — shown on first visit across marketing, auth, and app layouts.
     Once a choice is made the banner hides for 12 months (stored in localStorage).
     For authenticated users, the choice is also POSTed to /cookie-consent so it lands
     in the user_consents audit log. --}}
<div x-data="cookieBanner()" x-init="init()" x-cloak x-show="visible"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4 pointer-events-none"
    role="dialog" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-body">
    {{-- max-h + overflow so the banner can't cover form fields when the mobile keyboard pushes it up --}}
    <div class="pointer-events-auto mx-auto max-w-3xl bg-white rounded-xl shadow-2xl ring-1 ring-gray-200 p-4 sm:p-5 max-h-[50vh] overflow-y-auto">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="flex-1 min-w-0 text-sm leading-relaxed">
                <p id="cookie-banner-title" class="font-display font-bold text-gray-900">We value your privacy</p>
                <p id="cookie-banner-body" class="mt-1 text-gray-600">
                    Apna Invoice uses essential cookies to keep you signed in and, with your permission, a small set of analytics cookies to improve the product. You can change this any time on the
                    <a href="{{ route('pages.cookies') }}" class="underline hover:text-brand-700">Cookie Settings</a> page.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 sm:flex-col sm:shrink-0">
                <button type="button" @click="acceptAll()" class="px-4 py-2 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold shadow-sm">Accept all</button>
                <button type="button" @click="rejectNonEssential()" class="px-4 py-2 rounded-lg ring-1 ring-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold">Reject non-essential</button>
                <a href="{{ route('pages.cookies') }}" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold text-center">Customize</a>
            </div>
        </div>
    </div>
</div>

<script>
function cookieBanner() {
    return {
        visible: false,
        STORAGE_KEY: 'cookiePrefs',
        VERSION_KEY: 'cookiePrefsVersion',
        // Bump when Cookie Policy substance changes — forces a re-prompt.
        CURRENT_VERSION: '2026-04-24',
        init() {
            try {
                const storedVer = localStorage.getItem(this.VERSION_KEY);
                const stored = localStorage.getItem(this.STORAGE_KEY);
                if (!stored || storedVer !== this.CURRENT_VERSION) {
                    this.visible = true;
                }
            } catch (e) {
                this.visible = true;
            }
        },
        persist(prefs) {
            try {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(prefs));
                localStorage.setItem(this.VERSION_KEY, this.CURRENT_VERSION);
            } catch (e) { /* private mode */ }
            this.visible = false;
            this.syncToServer(prefs);
        },
        acceptAll() {
            this.persist({ analytics: true, marketing: true });
        },
        rejectNonEssential() {
            this.persist({ analytics: false, marketing: false });
        },
        syncToServer(prefs) {
            // Only logged-in users — guest preferences stay client-side.
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!token) return;
            fetch('{{ route('cookie-consent.store') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(prefs),
            }).catch(() => { /* best-effort; localStorage is source of truth */ });
        },
    };
}
</script>
