@props(['newMenu' => []])
{{--
    Phone and tablet tab bar (below lg): Home, Invoices, New, Parties, Menu,
    the way phone billing apps lay out their main screens. "New" opens the
    top bar's create list as a bottom sheet; "Menu" opens the side menu
    drawer (the layout's `drawer` state). Not rendered on screens that keep
    their own bar at the bottom (see layouts/app).
--}}
@php
    $tabs = [
        ['Home', 'dashboard', request()->routeIs('dashboard'), 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['Invoices', 'invoices.index', request()->routeIs('invoices.*', 'credit-notes.*'), 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['new'],
        ['Parties', 'customers.index', request()->routeIs('customers.*', 'vendors.*'), 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['menu'],
    ];
    $tabs = array_values(array_filter($tabs, fn ($t) => count($t) === 1 ? ($t[0] !== 'new' || count($newMenu) > 0) : \App\Support\Team::can($t[1])));
    $tab = 'flex flex-col items-center justify-center gap-0.5 min-h-[3.5rem] text-[11px] font-medium rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600';
@endphp

<nav class="lg:hidden fixed inset-x-0 bottom-0 z-30 bg-white/95 backdrop-blur border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.05)] pb-[env(safe-area-inset-bottom)] print:hidden"
     aria-label="Main sections">
    <div class="grid px-1 pt-1" style="grid-template-columns: repeat({{ count($tabs) }}, minmax(0, 1fr));">
        @foreach ($tabs as $t)
            @if ($t[0] === 'new')
                <button type="button" @click="newSheet = true" aria-haspopup="dialog" :aria-expanded="newSheet.toString()" aria-controls="new-sheet"
                        class="{{ $tab }} text-brand-800">
                    <span class="inline-flex items-center justify-center w-10 h-10 -mt-3 rounded-full bg-brand-700 text-white shadow-md ring-4 ring-white">
                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    New
                </button>
            @elseif ($t[0] === 'menu')
                <button type="button" @click="drawer = true" aria-controls="app-sidebar" :aria-expanded="drawer.toString()"
                        class="{{ $tab }} text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    Menu
                </button>
            @else
                @php [$label, $routeName, $active, $icon] = $t; @endphp
                <a href="{{ route($routeName) }}" @if ($active) aria-current="page" @endif
                   @class([$tab, 'text-brand-700' => $active, 'text-gray-600 hover:text-gray-900' => ! $active])>
                    <span @class(['inline-flex items-center justify-center w-12 h-7 rounded-full', 'bg-brand-50' => $active])>
                        <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $active ? '2.2' : '1.8' }}"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <span @class(['font-semibold' => $active])>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </div>
</nav>

{{-- "New" bottom sheet: the same list as the top bar's + New. --}}
@if (count($newMenu) > 0)
    <div x-show="newSheet" x-cloak class="lg:hidden fixed inset-0 z-50 print:hidden">
        <div x-show="newSheet" x-transition.opacity @click="newSheet = false" class="absolute inset-0 bg-gray-900/40" aria-hidden="true"></div>
        <div id="new-sheet" x-show="newSheet"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             role="dialog" aria-modal="true" aria-labelledby="new-sheet-title"
             class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto overscroll-contain bg-white rounded-t-2xl shadow-2xl pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="sticky top-0 bg-white flex items-center justify-between gap-2 px-4 pt-3 pb-2 border-b border-gray-100">
                <h2 id="new-sheet-title" class="font-semibold text-gray-900">Create new</h2>
                <button type="button" @click="newSheet = false" aria-label="Close"
                        class="inline-flex items-center justify-center w-10 h-10 -mr-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-800">
                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @foreach ($newMenu as [$heading, $items])
                <div class="px-4 pt-3">
                    <h3 class="text-xs font-semibold text-gray-500">{{ $heading }}</h3>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        @foreach ($items as [$label, $routeName, $params])
                            <a href="{{ route($routeName, $params) }}"
                               class="flex items-center min-h-[44px] px-3 py-2 rounded-lg ring-1 ring-gray-200 text-sm font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
