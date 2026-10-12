{{--
    Application sidebar - the "software" layout: a fixed left menu grouped the
    way Indian accounting tools are (Sales / Purchases / Parties / Items /
    Reports / GST / Settings). Collapses to icons on desktop (remembered per
    browser) and slides in as a drawer on phones and tablets.
--}}
@php
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns);
    $report = fn (string $key) => request()->routeIs('reports.show') && request()->route('report') === $key;
    $i = [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'invoice' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'quote' => 'M9 17h6l-3-3m0 0l3-3m-3 3H3m18 0a9 9 0 11-18 0 9 9 0 0118 0z',
        'credit' => 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z',
        'receipt' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        'inbox' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
        'pay' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'expense' => 'M19 14l-7 7m0 0l-7-7m7 7V3',
        'memo' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'truck' => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
        'box' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'reports' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'gst' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
        'download' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
        'hash' => 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
        'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'shield' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        // One icon per destination, so the collapsed (icons only) menu can
        // be read without hovering: returns, banks and the two aging
        // reports each have their own.
        'bank' => 'M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3',
        'upload' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        'match' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'stack' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
        'team' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
        'grid' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    ];
    $monthFrom = now()->startOfMonth()->toDateString();
    $monthTo = now()->endOfMonth()->toDateString();

    // Arranged the way the business runs: sell, buy, the people and goods
    // involved, the bank, then the returns (in filing-calendar order:
    // GSTR-1 by the 11th, GSTR-2B from the 14th, GSTR-3B by the 20th),
    // reports and settings. Labels say what a shopkeeper calls the thing.
    $groups = [
        [null, [
            ['Dashboard', route('dashboard'), $i['home'], $is('dashboard')],
        ]],
        ['Sales', [
            ['Invoices', route('invoices.index'), $i['invoice'], $is('invoices.*', 'credit-notes.create')],
            ['Quotations', route('quotations.index'), $i['quote'], $is('quotations.*')],
            ['Credit notes', route('reports.show', ['report' => 'credit-notes']), $i['credit'], $report('credit-notes')],
            ['Payments received', route('reports.show', ['report' => 'receipts']), $i['receipt'], $report('receipts')],
        ]],
        ['Purchases', [
            ['Purchase invoices', route('purchases.index'), $i['inbox'], $is('purchases.*')],
            ['Payments made', route('reports.show', ['report' => 'vendor-payments']), $i['pay'], $report('vendor-payments')],
            ['Expenses', route('finance.expenses'), $i['expense'], $is('finance.expenses*')],
            ['Cash memos', route('finance.cash-memos.index'), $i['memo'], $is('finance.cash-memos.*')],
        ]],
        ['Parties', [
            ['Customers', route('customers.index'), $i['users'], $is('customers.*') || (request()->routeIs('imports.*') && request()->route('type') === 'customers')],
            ['Vendors', route('vendors.index'), $i['truck'], $is('vendors.*') || (request()->routeIs('imports.*') && request()->route('type') === 'vendors')],
        ]],
        ['Inventory', [
            ['Items', route('products.index'), $i['box'], ($is('products.*') && ! $is('products.stock')) || (request()->routeIs('imports.*') && request()->route('type') === 'products')],
            ['Stock summary', route('reports.show', ['report' => 'stock-summary']), $i['stack'], $report('stock-summary') || $is('products.stock')],
        ]],
        ['Banking', [
            ['Bank reconciliation', route('bank.index'), $i['bank'], $is('bank.*')],
        ]],
        ['GST returns', [
            ['GSTR-1', route('finance.gstr1'), $i['upload'], $is('finance.gstr1*')],
            ['GSTR-2B match', route('finance.gstr2b'), $i['match'], $is('finance.gstr2b*')],
            ['GSTR-3B', route('finance.gstr3b'), $i['gst'], $is('finance.gstr3b*')],
            ['HSN summary', route('reports.show', ['report' => 'hsn-summary', 'from' => $monthFrom, 'to' => $monthTo]), $i['hash'], $report('hsn-summary')],
        ]],
        ['Reports', [
            ['Profit & loss', route('finance.index'), $i['chart'], $is('finance.index')],
            ['Receivables aging', route('finance.aging'), $i['clock'], $is('finance.aging*') || $report('receivables-aging')],
            ['Payables aging', route('reports.show', ['report' => 'payables-aging']), $i['calendar'], $report('payables-aging')],
            ['All reports', route('reports.index'), $i['grid'], $is('reports.index') || ($is('reports.show') && in_array(request()->route('report'), ['sales-register', 'purchase-register', 'quotations', 'customers', 'products', 'vendors'], true))],
        ]],
        ['Settings', [
            ['Company settings', route('company.edit'), $i['building'], $is('companies.*', 'company.*')],
            ['Team', route('team.index'), $i['team'], $is('team.*')],
            ['All settings', route('settings.index'), $i['cog'], $is('settings.*', 'profile.*', 'referrals.*')],
            ['Backups', route('backup.index'), $i['download'], $is('backup.*')],
        ]],
    ];
    // A team member sees only what their role opens.
    if (\App\Support\Team::isMember()) {
        $routes = app('router')->getRoutes();
        foreach ($groups as $gi => $group) {
            $groups[$gi][1] = array_values(array_filter($group[1], function ($item) use ($routes) {
                try {
                    $name = $routes->match(\Illuminate\Http\Request::create($item[1], 'GET'))->getName();
                } catch (\Throwable) {
                    return false;
                }

                return $name === 'companies.index' || \App\Support\Team::can((string) $name);
            }));
        }
        $groups = array_values(array_filter($groups, fn ($g) => count($g[1]) > 0));
    }
    if (\App\Support\Team::realUser()?->isSuperAdmin()) {
        $groups[] = ['Admin', [['Admin panel', route('admin.dashboard'), $i['shield'], $is('admin.*')]]];
    }
@endphp

{{-- Phone / tablet backdrop --}}
<div x-show="drawer" x-cloak x-transition.opacity @click="drawer = false" class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden" aria-hidden="true"></div>

<aside id="app-sidebar"
    :inert="!drawer && !desktop"
    :class="[drawer ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-16' : 'lg:w-60']"
    class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full lg:translate-x-0 bg-[#0E2843] text-gray-300 flex flex-col transition-all duration-200 print:hidden"
    aria-label="Main menu">
    {{-- The original logo, untouched, on a white plate. Collapsed, the plate
         shrinks to the logo mark alone. --}}
    <div class="h-14 shrink-0 flex items-center gap-2 px-2 border-b border-white/10">
        <a href="{{ route('dashboard') }}" class="flex-1 min-w-0 flex items-center justify-center rounded-md bg-white h-11 px-2 shadow-sm" aria-label="Apna Invoice dashboard">
            <x-brand-logo x-show="!collapsed || drawer" class="h-9 w-auto max-w-full" />
            <img x-show="collapsed && !drawer" x-cloak src="{{ asset('brand/favicon-96.png') }}" alt="Apna Invoice" class="w-8 h-8" width="32" height="32">
        </a>
        <button type="button" @click="drawer = false" class="ml-auto lg:hidden p-2 rounded hover:bg-white/10" aria-label="Close menu">
            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto overscroll-contain py-2 sidebar-scroll" aria-label="Sections">
        @foreach ($groups as [$heading, $items])
            <div class="px-2 {{ $heading ? 'mt-3' : '' }}" @if ($heading) data-tour="nav-{{ \Illuminate\Support\Str::slug($heading) }}" @endif>
                @if ($heading)
                    <div x-show="!collapsed || drawer" class="px-2 pb-1 text-xs font-semibold text-gray-400">{{ $heading }}</div>
                    <div x-show="collapsed && !drawer" x-cloak class="mx-2 mb-1 border-t border-white/10" aria-hidden="true"></div>
                @endif
                @foreach ($items as [$label, $href, $icon, $active])
                    <a href="{{ $href }}" title="{{ $label }}"
                       @if ($active) aria-current="page" @endif
                       @class([
                           'group flex items-center gap-3 rounded-md px-2 py-2.5 lg:py-1.5 text-sm font-medium transition-colors',
                           'bg-[#167C88] text-white' => $active,
                           'text-gray-300 hover:bg-white/10 hover:text-white' => ! $active,
                       ])>
                        <svg class="w-[18px] h-[18px] shrink-0 {{ $active ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        <span x-show="!collapsed || drawer" class="truncate">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Phones: the top bar has no room for the WhatsApp button, so support
         sits at the foot of the menu instead of floating over the page. --}}
    @if ($wa = config('seo.contact.whatsapp_url'))
        <div class="shrink-0 border-t border-white/10 p-2 lg:hidden">
            <a href="{{ $wa }}" target="_blank" rel="noopener"
               class="w-full flex items-center gap-3 rounded-md px-2 py-2.5 text-sm font-semibold text-emerald-300 hover:bg-white/10 hover:text-emerald-200">
                <svg class="w-[18px] h-[18px] shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                Chat with us on WhatsApp
            </a>
        </div>
    @endif

    <div class="shrink-0 border-t border-white/10 p-2 hidden lg:block">
        <button type="button" @click="toggleCollapsed()" class="w-full flex items-center gap-3 rounded-md px-2 py-1.5 text-xs text-gray-400 hover:bg-white/10 hover:text-white" :title="collapsed ? 'Expand menu' : 'Collapse menu'">
            <svg class="w-[18px] h-[18px] shrink-0 transition-transform" :class="collapsed && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
            <span x-show="!collapsed">Collapse</span>
        </button>
    </div>
</aside>
