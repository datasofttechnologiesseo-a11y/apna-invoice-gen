<x-layouts.marketing
    title="Cookie Settings"
    eyebrow="Preferences"
    lead="Control which cookies Apna Invoice uses. Essential cookies are always on because the product can't function without them."
    description="Manage your Apna Invoice cookie preferences — essential, analytics, and marketing. Settings are stored locally and respected across the site."
    keywords="Apna Invoice cookies, cookie preferences India, GDPR cookies, DPDP cookies, SaaS cookie settings"
    type="article">

    <div class="not-prose mt-4 space-y-4" x-data="cookiePrefs()" x-init="load()">

        <div class="p-5 rounded-xl ring-1 ring-gray-200 bg-gray-50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-display font-bold text-gray-900">Essential</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-gray-200 text-gray-600">Always on</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">Authentication, CSRF protection, and security. The product cannot function without these.</p>
                </div>
                <span class="inline-flex items-center h-6 w-11 rounded-full bg-brand-600 flex-shrink-0">
                    <span class="ml-5 h-5 w-5 rounded-full bg-white shadow"></span>
                </span>
            </div>
        </div>

        <div class="p-5 rounded-xl ring-1 ring-gray-200 bg-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-display font-bold text-gray-900">Analytics</h3>
                    <p class="mt-1 text-sm text-gray-600">Helps us understand which features are used so we can improve the product. Aggregated, never sold.</p>
                </div>
                <button type="button" @click="prefs.analytics = !prefs.analytics"
                    :class="prefs.analytics ? 'bg-brand-600' : 'bg-gray-300'"
                    class="inline-flex items-center h-6 w-11 rounded-full transition flex-shrink-0">
                    <span :class="prefs.analytics ? 'ml-5' : 'ml-0.5'" class="h-5 w-5 rounded-full bg-white shadow transition-all"></span>
                </button>
            </div>
        </div>

        <div class="p-5 rounded-xl ring-1 ring-gray-200 bg-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-display font-bold text-gray-900">Marketing</h3>
                    <p class="mt-1 text-sm text-gray-600">Measure effectiveness of campaigns and show relevant content on other sites.</p>
                </div>
                <button type="button" @click="prefs.marketing = !prefs.marketing"
                    :class="prefs.marketing ? 'bg-brand-600' : 'bg-gray-300'"
                    class="inline-flex items-center h-6 w-11 rounded-full transition flex-shrink-0">
                    <span :class="prefs.marketing ? 'ml-5' : 'ml-0.5'" class="h-5 w-5 rounded-full bg-white shadow transition-all"></span>
                </button>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="button" @click="save()" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">Save preferences</button>
            <button type="button" @click="acceptAll()" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold transition">Accept all</button>
            <button type="button" @click="rejectAll()" class="px-5 py-2.5 rounded-lg ring-1 ring-gray-300 hover:bg-gray-50 text-gray-700 font-semibold transition">Reject non-essential</button>
            <span x-show="saved" x-transition class="self-center text-sm text-money-700 font-medium">✓ Saved</span>
        </div>
    </div>

    <h2>What we use</h2>
    <ul>
        <li><strong>Session cookies</strong> (essential) — keep you logged in.</li>
        <li><strong>XSRF-TOKEN</strong> (essential) — protects against cross-site request forgery.</li>
        <li><strong>Product analytics</strong> (optional) — anonymous feature-usage metrics.</li>
        <li><strong>Marketing</strong> (optional) — ad measurement pixels for campaigns.</li>
    </ul>

    <p>
        You can also manage cookies directly in your browser. For details on the data we process, see our
        <a href="{{ route('pages.privacy') }}">Privacy Policy</a>.
    </p>

    <script>
        function cookiePrefs() {
            return {
                prefs: { analytics: false, marketing: false },
                saved: false,
                load() {
                    try {
                        const raw = localStorage.getItem('cookiePrefs');
                        if (raw) this.prefs = { ...this.prefs, ...JSON.parse(raw) };
                    } catch (e) { /* ignore */ }
                },
                persist() {
                    localStorage.setItem('cookiePrefs', JSON.stringify(this.prefs));
                    this.saved = true;
                    setTimeout(() => this.saved = false, 2500);
                },
                save() { this.persist(); },
                acceptAll() { this.prefs = { analytics: true, marketing: true }; this.persist(); },
                rejectAll() { this.prefs = { analytics: false, marketing: false }; this.persist(); },
            };
        }
    </script>
</x-layouts.marketing>
