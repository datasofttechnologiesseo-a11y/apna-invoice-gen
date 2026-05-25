{{--
    Sales section tabs — established Indian invoicing tools
    group sales documents under a single section. Each tab gets its own
    color theme, distinct icon, a live stat (Outstanding / Awaiting), and
    a quick "+ New" CTA on the active tab.

    Required:
        $active = 'invoices' | 'quotations'

    Optional:
        $stats = [
            'invoices'   => ['total' => 12, 'open' => 3],          // open = outstanding (unpaid)
            'quotations' => ['total' => 5,  'awaiting' => 2],       // awaiting = sent but not yet accepted
        ]
        Backwards-compat: if a legacy `$counts` array is passed
        (`['invoices' => 12, ...]`) it's normalised into the new shape so
        existing callers don't break.
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
            'label' => 'Tax Invoices',
            'href' => route('invoices.index'),
            'desc' => 'GSTR-1 reportable · official sale documents',
            // Document with embedded check — distinct from quotation envelope
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            'theme' => [
                'pill_bg'         => 'bg-brand-700',
                'pill_to'         => 'to-brand-900',
                'icon_active'     => 'bg-gradient-to-br from-brand-700 to-brand-900 text-white shadow-brand-900/20',
                'icon_idle'       => 'bg-brand-50 text-brand-700 group-hover:bg-brand-100',
                'label_active'    => 'text-brand-900',
                'count_active'    => 'bg-saffron-500 text-brand-900',
                'count_idle'      => 'bg-gray-200 text-gray-700',
                'stripe'          => 'bg-gradient-to-r from-saffron-400 via-saffron-500 to-brand-700',
                'cta_bg'          => 'bg-brand-700 hover:bg-brand-800',
                'bg_active'       => 'bg-gradient-to-br from-brand-50/80 via-white to-saffron-50/30',
            ],
            'create_route' => route('invoices.create'),
            'create_label' => 'New invoice',
            // Stat sub-label
            'stat_key'   => 'open',
            'stat_label' => 'outstanding',
            'stat_zero'  => 'All paid',
            'stat_tone'  => 'amber',
        ],
        [
            'key' => 'quotations',
            'label' => 'Quotations',
            'href' => route('quotations.index'),
            'desc' => 'Pre-sale proposals · convert to invoice on accept',
            // Receipt-with-arrow / "send proposal" icon
            'icon' => 'M9 17h6l-3-3m0 0l3-3m-3 3H3m18 0a9 9 0 11-18 0 9 9 0 0118 0z',
            'theme' => [
                'pill_bg'         => 'bg-purple-700',
                'pill_to'         => 'to-purple-900',
                'icon_active'     => 'bg-gradient-to-br from-purple-700 to-purple-900 text-white shadow-purple-900/20',
                'icon_idle'       => 'bg-purple-50 text-purple-700 group-hover:bg-purple-100',
                'label_active'    => 'text-purple-900',
                'count_active'    => 'bg-saffron-500 text-purple-900',
                'count_idle'      => 'bg-gray-200 text-gray-700',
                'stripe'          => 'bg-gradient-to-r from-saffron-400 via-saffron-500 to-purple-700',
                'cta_bg'          => 'bg-purple-700 hover:bg-purple-800',
                'bg_active'       => 'bg-gradient-to-br from-purple-50/80 via-white to-saffron-50/30',
            ],
            'create_route' => route('quotations.create'),
            'create_label' => 'New quote',
            'stat_key'   => 'awaiting',
            'stat_label' => 'awaiting reply',
            'stat_zero'  => 'No pending',
            'stat_tone'  => 'amber',
        ],
    ];

    // Resolve the active tab's accent stripe for the top of the card.
    $activeStripe = collect($tabs)->firstWhere('key', $active)['theme']['stripe']
        ?? 'bg-gradient-to-r from-saffron-400 via-saffron-500 to-brand-700';
@endphp

<div class="relative bg-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
    {{-- Decorative top stripe — colour follows the ACTIVE tab so users can
         glance at the strip and instantly see which doc type they're in. --}}
    <div class="h-1 {{ $activeStripe }}"></div>

    <div class="grid grid-cols-2 divide-x divide-gray-200" role="tablist" aria-label="Sales documents">
        @foreach ($tabs as $tab)
            @php
                $isActive = ($active ?? null) === $tab['key'];
                $tabStats = $stats[$tab['key']] ?? [];
                $count = $tabStats['total'] ?? null;
                $statValue = $tabStats[$tab['stat_key']] ?? null;
                $t = $tab['theme'];
            @endphp
            <div @class([
                'group relative min-h-[64px] transition-all',
                $t['bg_active'] => $isActive,
                'hover:bg-gray-50' => ! $isActive,
            ])>
                <a href="{{ $tab['href'] }}" role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}"
                   class="block px-3 sm:px-5 py-3 sm:py-4 flex items-center gap-3 sm:gap-4">
                    {{-- Icon tile — themed per tab --}}
                    <span @class([
                        'shrink-0 w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center shadow-sm transition-all',
                        $t['icon_active'] => $isActive,
                        $t['icon_idle'] => ! $isActive,
                    ])>
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/></svg>
                    </span>

                    <span class="flex-1 min-w-0 text-left">
                        <span class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                            <span @class([
                                'block font-display font-bold text-sm sm:text-base leading-tight',
                                $t['label_active'] => $isActive,
                                'text-gray-700 group-hover:text-gray-900' => ! $isActive,
                            ])>{{ $tab['label'] }}</span>
                            @if ($count !== null)
                                <span @class([
                                    'inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 text-[10px] font-bold rounded-full tabular-nums',
                                    $t['count_active'] => $isActive,
                                    $t['count_idle'] => ! $isActive,
                                ])>{{ $count }}</span>
                            @endif
                            @if ($isActive)
                                <span class="hidden md:inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-saffron-700 bg-saffron-100 ring-1 ring-saffron-200 rounded-full px-1.5 py-0.5">
                                    <span class="w-1 h-1 rounded-full bg-saffron-600 animate-pulse"></span>
                                    Open
                                </span>
                            @endif
                        </span>
                        <span @class([
                            'hidden sm:block text-[11px] mt-0.5 leading-snug',
                            'text-gray-600' => $isActive,
                            'text-gray-500' => ! $isActive,
                        ])>{{ $tab['desc'] }}</span>

                        {{-- Live status stat — shown when controller passes a value.
                             Friendly empty-state message ("All paid") when value is 0. --}}
                        @if ($statValue !== null)
                            <span class="hidden sm:flex items-center gap-1.5 mt-1.5 text-[11px] font-medium">
                                @if ((int) $statValue > 0)
                                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold tabular-nums">{{ $statValue }}</span>
                                    <span class="text-amber-700">{{ $tab['stat_label'] }}</span>
                                @else
                                    <span class="inline-flex items-center justify-center w-[18px] h-[18px] rounded-full bg-emerald-100 text-emerald-700">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    <span class="text-emerald-700">{{ $tab['stat_zero'] }}</span>
                                @endif
                            </span>
                        @endif
                    </span>
                </a>

                {{-- Quick "+ New" CTA — only on the active tab. Sits absolutely
                     positioned so the row click target stays as one big link. --}}
                @if ($isActive)
                    <a href="{{ $tab['create_route'] }}"
                       class="absolute right-3 sm:right-4 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 px-2.5 sm:px-3 py-1.5 {{ $t['cta_bg'] }} text-white text-xs font-semibold rounded-md shadow-sm transition"
                       title="{{ $tab['create_label'] }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline">{{ $tab['create_label'] }}</span>
                    </a>
                    <span class="absolute bottom-0 inset-x-0 h-[3px] {{ $t['stripe'] }}"></span>
                @endif
            </div>
        @endforeach
    </div>
</div>
