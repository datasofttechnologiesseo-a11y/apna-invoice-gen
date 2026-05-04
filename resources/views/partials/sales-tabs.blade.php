{{--
    Sales section tabs — Indian invoicing tools (Vyapar, Tally Prime, Zoho)
    group sales documents (Tax Invoices, Quotations, Credit Notes…) under one
    navigation umbrella with internal tabs.

    Pass `$active` as one of: 'invoices' | 'quotations'.
    Optionally pass `$counts` as an associative array, e.g.
        ['invoices' => 42, 'quotations' => 7]
    The count chip will auto-render only for tabs whose key is present.
--}}
@php
    $counts ??= [];
    $tabs = [
        [
            'key' => 'invoices',
            'label' => 'Tax Invoices',
            'href' => route('invoices.index'),
            'desc' => 'GSTR-1 reportable · official sale documents',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'key' => 'quotations',
            'label' => 'Quotations',
            'href' => route('quotations.index'),
            'desc' => 'Pre-sale proposals · convert to invoice on accept',
            'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        ],
    ];
@endphp

{{-- Outer wrap: subtle gradient + ring to give the strip a card-like presence
     so it reads as a major section divider, not a thin accessory. --}}
<div class="relative bg-gradient-to-br from-white via-gray-50 to-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
    {{-- Decorative top stripe — saffron→brand gradient. Pure ornament; gives
         the section a tricolor "spine" feel matching the nav bar. --}}
    <div class="h-1 bg-gradient-to-r from-saffron-400 via-saffron-500 to-brand-700"></div>

    <div class="grid grid-cols-2 divide-x divide-gray-200" role="tablist" aria-label="Sales documents">
        @foreach ($tabs as $tab)
            @php
                $isActive = ($active ?? null) === $tab['key'];
                $count = $counts[$tab['key']] ?? null;
            @endphp
            <a href="{{ $tab['href'] }}" role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}"
               @class([
                   'group relative min-h-[56px] px-3 sm:px-5 py-3 sm:py-4 flex items-center gap-3 sm:gap-4 transition-all',
                   'bg-gradient-to-br from-brand-50 via-white to-saffron-50/40' => $isActive,
                   'hover:bg-gray-50' => ! $isActive,
               ])>
                {{-- Icon tile — smaller on mobile (40px) to keep both tabs on
                     one row; full-size on sm+ for visual presence. --}}
                <span @class([
                    'shrink-0 w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center shadow-sm transition-all',
                    'bg-gradient-to-br from-brand-700 to-brand-900 text-white shadow-brand-900/20' => $isActive,
                    'bg-gray-100 text-gray-500 group-hover:bg-brand-100 group-hover:text-brand-700' => ! $isActive,
                ])>
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/></svg>
                </span>

                <span class="flex-1 min-w-0 text-left">
                    <span class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                        <span @class([
                            'block font-display font-bold text-sm sm:text-base leading-tight',
                            'text-brand-900' => $isActive,
                            'text-gray-700 group-hover:text-gray-900' => ! $isActive,
                        ])>{{ $tab['label'] }}</span>
                        @if ($count !== null)
                            <span @class([
                                'inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 text-[10px] font-bold rounded-full',
                                'bg-saffron-500 text-brand-900' => $isActive,
                                'bg-gray-200 text-gray-700' => ! $isActive,
                            ])>{{ $count }}</span>
                        @endif
                        @if ($isActive)
                            <span class="hidden md:inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-saffron-700 bg-saffron-100 ring-1 ring-saffron-200 rounded-full px-1.5 py-0.5">
                                <span class="w-1 h-1 rounded-full bg-saffron-600 animate-pulse"></span>
                                Open
                            </span>
                        @endif
                    </span>
                    {{-- Description hidden below sm; takes too much horizontal
                         room for two tabs to share at 320px. --}}
                    <span @class([
                        'hidden sm:block text-[11px] mt-0.5 leading-snug',
                        'text-gray-600' => $isActive,
                        'text-gray-500' => ! $isActive,
                    ])>{{ $tab['desc'] }}</span>
                </span>

                @if ($isActive)
                    <span class="absolute bottom-0 inset-x-0 h-[3px] bg-gradient-to-r from-saffron-400 via-saffron-500 to-brand-700"></span>
                @endif
            </a>
        @endforeach
    </div>
</div>
