@props(['title' => null])
<!DOCTYPE html>
<html lang="en-IN">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo
            :title="$title ?? 'Dashboard'"
            :noindex="true" />

        {{-- PWA manifest now comes from <x-seo>. --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Apna Invoice">


        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.google-analytics')
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="skip-link">Skip to main content</a>
        @if (session('impersonator_id'))
            <div class="bg-accent-500 text-accent-950 px-4 py-2 flex items-center justify-between text-sm font-semibold">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                    <span>Viewing as <strong>{{ auth()->user()->name }}</strong> (super-admin impersonation)</span>
                </div>
                <form method="POST" action="{{ route('admin.impersonation.stop') }}">
                    @csrf
                    <button class="px-3 py-1 bg-accent-950 text-accent-100 rounded text-xs font-bold uppercase tracking-wider hover:bg-accent-900">Stop impersonating</button>
                </form>
            </div>
        @endif
        @php
            // The "+ New" menu: one list for the top bar and the phone tab bar.
            // [heading, [[label, route name, route params, shortcut], ...]]
            $newMenu = [
                ['Sales', [
                    ['Invoice', 'invoices.create', [], 'Alt+N'],
                    ['Quotation', 'quotations.create', [], null],
                    // No separate payment form: a payment is recorded on its invoice.
                    ['Payment received', 'invoices.index', ['status' => 'outstanding'], 'Alt+R'],
                ]],
                ['Purchases', [
                    ['Purchase invoice', 'purchases.create', [], 'Alt+P'],
                    ['Expense', 'finance.expenses.create', [], null],
                    ['Cash memo', 'finance.cash-memos.create', [], null],
                ]],
                ['Parties & items', [
                    ['Customer', 'customers.create', [], null],
                    ['Vendor', 'vendors.create', [], null],
                    ['Item', 'products.create', [], null],
                ]],
                ['Banking & GST', [
                    ['Upload bank statement', 'bank.index', [], null],
                    ['Upload GSTR-2B', 'finance.gstr2b', [], null],
                ]],
            ];
            foreach ($newMenu as $gi => $group) {
                $newMenu[$gi][1] = array_values(array_filter($group[1], fn ($item) => \App\Support\Team::can($item[1])));
            }
            $newMenu = array_values(array_filter($newMenu, fn ($g) => count($g[1]) > 0));

            // Keyboard shortcuts that open a page (null when the role cannot).
            $shortcutUrls = [
                'n' => route('invoices.create'),
                'p' => \App\Support\Team::can('purchases.create') ? route('purchases.create') : null,
                'r' => \App\Support\Team::can('invoices.index') ? route('invoices.index', ['status' => 'outstanding']) : null,
            ];

            // Phone tab bar: not on screens that keep their own bar at the bottom
            // (document forms, the invoice page's action bar, company settings).
            $tabBarRoute = request()->route('invoice');
            $showTabBar = ! request()->routeIs(
                    'invoices.create', 'invoices.edit', 'quotations.create', 'quotations.edit',
                    'purchases.create', 'purchases.edit', 'finance.cash-memos.create', 'finance.cash-memos.edit',
                    'finance.expenses.create', 'finance.expenses.edit', 'credit-notes.create',
                    'company.edit', 'companies.create', 'companies.edit',
                )
                && ! (request()->routeIs('invoices.show') && ! ($tabBarRoute instanceof \App\Models\Invoice && $tabBarRoute->isCancelled()));
        @endphp
        {{-- Software-style frame: fixed sidebar + slim top bar. The sidebar
             collapses to icons on desktop (remembered in this browser) and
             is a drawer on phones. Keyboard: "/" or Ctrl+K search, Alt+N new
             invoice, Alt+P new purchase invoice, Alt+R payment received,
             "?" the list of shortcuts. None fire while typing in a field. --}}
        <div class="min-h-screen bg-gray-50"
             x-data="{
                drawer: false,
                collapsed: false,
                shortcuts: false,
                newSheet: false,
                desktop: window.matchMedia('(min-width: 1024px)').matches,
                urls: {{ \Illuminate\Support\Js::from($shortcutUrls) }},
                init() {
                    try { this.collapsed = localStorage.getItem('sidebarCollapsed') === '1'; } catch (e) {}
                    // The phone drawer sits off-screen when shut; keep its links
                    // out of the Tab order until it is opened.
                    const mq = window.matchMedia('(min-width: 1024px)');
                    mq.addEventListener && mq.addEventListener('change', (e) => { this.desktop = e.matches; });
                },
                toggleCollapsed() {
                    this.collapsed = !this.collapsed;
                    try { localStorage.setItem('sidebarCollapsed', this.collapsed ? '1' : '0'); } catch (e) {}
                },
                onKey(e) {
                    if (e.key === 'Escape') { this.drawer = false; this.shortcuts = false; this.newSheet = false; return; }
                    const t = e.target;
                    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || t.isContentEditable;
                    if (typing || e.defaultPrevented) return;
                    const key = (e.key || '').toLowerCase();
                    if ((key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) || ((e.ctrlKey || e.metaKey) && !e.altKey && key === 'k')) {
                        e.preventDefault();
                        this.shortcuts = false;
                        document.getElementById('global-search')?.focus();
                        return;
                    }
                    if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); this.shortcuts = !this.shortcuts; return; }
                    if (e.altKey && !e.ctrlKey && !e.metaKey) {
                        // e.code too: on a Mac, Option+letter types a symbol.
                        const letter = (e.code || '').startsWith('Key') ? e.code.slice(3).toLowerCase() : key;
                        if (this.urls[letter]) { e.preventDefault(); window.location = this.urls[letter]; }
                    }
                },
             }"
             @keydown.window="onKey($event)">
            @include('layouts.sidebar')
            <div :class="collapsed ? 'lg:pl-16' : 'lg:pl-60'" @class([
                'app-shell min-h-screen flex flex-col lg:pl-60 transition-[padding] duration-200',
                // Room for the phone tab bar, so the footer and the last row scroll clear of it.
                'max-lg:pb-[calc(4rem+env(safe-area-inset-bottom))]' => $showTabBar,
            ])>
            @include('layouts.topbar', ['newMenu' => $newMenu, 'showTabBar' => $showTabBar])
            @if (\App\Support\Team::isMember())
                <div class="bg-accent-50 border-b border-accent-200 text-accent-900 text-xs px-4 py-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 print:hidden">
                    <span>You are working in <strong>{{ auth()->user()->ensureCompany()->name }}</strong> as <strong>{{ \App\Support\Team::membership()->roleLabel() }}</strong>.</span>
                    <a href="{{ route('companies.index') }}" class="underline font-semibold">Switch firm</a>
                </div>
            @endif

            <!-- Page Content (heading slot renders in-flow above the body) -->
            <main id="main-content" class="flex-1" tabindex="-1">
                @isset($header)
                    <div class="max-w-7xl mx-auto w-full pt-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                @endisset
                {{-- The result of the last action ("5 lines read, 2 matched", "Books
                     are locked...") on pages that don't show it themselves. The page
                     renders first, so a message it already shows is not repeated.
                     Breeze's internal codes ("profile-updated") are not messages. --}}
                @php
                    $pendingFlash = collect(['error', 'warning', 'info', 'status'])->map(fn ($k) => session($k))->first(fn ($m) => is_string($m) && $m !== '');
                    $pendingFlash = $pendingFlash && ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)+$/', $pendingFlash)
                        && ! str_contains((string) $slot . (isset($header) ? (string) $header : ''), e($pendingFlash));
                @endphp
                @if ($pendingFlash)
                    <div class="max-w-7xl mx-auto w-full pt-4 px-4 sm:px-6 lg:px-8 print:hidden">
                        <x-flash />
                    </div>
                @endif
                {{ $slot }}
            </main>

            <x-site-footer variant="minimal" />
            <x-app-tour />
            </div>

            @if ($showTabBar)
                <x-mobile-tab-bar :new-menu="$newMenu" />
            @endif
            <x-shortcuts-help />
        </div>

        {{-- No floating chat bubble inside the app: WhatsApp support is in the
             top bar, the footer ribbon and (on phones) the menu. --}}
        @include('partials.cookie-banner')
        {{-- Inside the app only on the dashboard: other screens have their own bottom bars. --}}
        @if (request()->routeIs('dashboard'))
            @include('partials.install-app', ['aboveTabBar' => $showTabBar])
        @endif
        @stack('scripts')
    </body>
</html>
