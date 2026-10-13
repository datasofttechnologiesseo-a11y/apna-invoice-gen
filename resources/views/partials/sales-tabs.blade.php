{{--
    Sales / purchase switch: the two sides of the GST ledger side by side,
    bills you issued (GSTR-1) and bills you received (ITC in GSTR-3B), each
    with a live count and what needs attention. The page header carries the
    "New …" button, so the strip does not repeat it.

    Required:
        $active = 'invoices' | 'purchases'

    Optional:
        $stats = [
            'invoices'  => ['total' => 12, 'open' => 3],         // open = outstanding (unpaid)
            'purchases' => ['total' => 40, 'unpaid' => 4],       // unpaid = still owed to vendors
        ]
        Backwards-compat: a legacy `$counts` array (`['invoices' => 12, ...]`)
        is normalised into the new shape so existing callers don't break.
--}}
@php
    // Backwards-compat shim: accept the old `$counts` shape.
    if (! isset($stats) && isset($counts)) {
        $stats = collect($counts)->mapWithKeys(fn ($v, $k) => [$k => ['total' => (int) $v]])->all();
    }
    $stats ??= [];

    $tabs = [
        [
            'key' => 'invoices',
            'label' => 'Sales invoices',
            'href' => route('invoices.index'),
            'desc' => 'Bills you issued · reported in GSTR-1',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'stat_key' => 'open',
            'stat_label' => 'outstanding',
            'stat_zero' => 'All paid',
        ],
        [
            'key' => 'purchases',
            'label' => 'Purchase invoices',
            'href' => route('purchases.index'),
            'desc' => 'Bills you received · input tax credit for GSTR-3B',
            'icon' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
            'stat_key' => 'unpaid',
            'stat_label' => 'unpaid',
            'stat_zero' => 'All paid',
        ],
    ];
    // Billing staff don't see purchases.
    $tabs = array_values(array_filter($tabs, fn ($t) => $t['key'] !== 'purchases' || \App\Support\Team::can('purchases.index')));
@endphp

<nav class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden grid {{ count($tabs) > 1 ? 'grid-cols-2 divide-x divide-gray-200' : 'grid-cols-1' }}" aria-label="Sales and purchase documents">
    @foreach ($tabs as $tab)
        @php
            $isActive = ($active ?? null) === $tab['key'];
            $tabStats = $stats[$tab['key']] ?? [];
            $count = $tabStats['total'] ?? null;
            $statValue = $tabStats[$tab['stat_key']] ?? null;
        @endphp
        <a href="{{ $tab['href'] }}" @if ($isActive) aria-current="page" @endif
           @class([
               'flex items-center gap-3 px-3 sm:px-5 py-3 min-h-[64px] border-b-2 transition',
               'bg-brand-50/60 border-brand-700' => $isActive,
               'border-transparent hover:bg-gray-50' => ! $isActive,
           ])>
            <span @class([
                'shrink-0 w-10 h-10 rounded-lg flex items-center justify-center',
                'bg-brand-700 text-white' => $isActive,
                'bg-gray-100 text-gray-600' => ! $isActive,
            ])>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/></svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2 flex-wrap">
                    <span @class(['font-semibold text-sm sm:text-base leading-tight', 'text-gray-900' => $isActive, 'text-gray-700' => ! $isActive])>{{ $tab['label'] }}</span>
                    @if ($count !== null)
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 text-xs font-semibold rounded-full tabular-nums bg-gray-100 text-gray-700">{{ $count }}</span>
                    @endif
                </span>
                <span class="hidden sm:block text-xs text-gray-600 mt-0.5 leading-snug">{{ $tab['desc'] }}</span>
                @if ($statValue !== null)
                    <span class="block text-xs font-medium mt-0.5 {{ (int) $statValue > 0 ? 'text-accent-800' : 'text-money-700' }}">
                        {{ (int) $statValue > 0 ? $statValue . ' ' . $tab['stat_label'] : $tab['stat_zero'] }}
                    </span>
                @endif
            </span>
        </a>
    @endforeach
</nav>
