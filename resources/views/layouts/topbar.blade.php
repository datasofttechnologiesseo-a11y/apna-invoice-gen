{{--
    Slim application top bar: menu toggle, global search, active firm, the
    "+ New" create menu, notifications and the account menu. Everything that
    navigates lives in the sidebar; this bar only holds tools.
--}}
@php
    $activeCompany = Auth::user()->ensureCompany();
    // The firms the person at the keyboard can open: their own, and the ones
    // they joined as a team member (with their role).
    $realUser = \App\Support\Team::realUser();
    $myCompanies = $realUser->companies()->orderBy('name')->get();
    $joinedCompanies = $realUser->memberships()->with('company')->get()->filter(fn ($m) => $m->company);
    $teamRole = \App\Support\Team::membership()?->roleLabel();
@endphp
<header class="sticky top-0 z-30 h-14 bg-white/95 backdrop-blur border-b border-gray-200 flex items-center gap-2 px-3 sm:px-4 print:hidden">
    <button type="button" @click="drawer = true" class="lg:hidden inline-flex items-center justify-center w-10 h-10 -ml-1 rounded-md text-gray-600 hover:bg-gray-100" aria-label="Open menu" aria-controls="app-sidebar" :aria-expanded="drawer.toString()">
        <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    {{-- Search: "/" focuses it from anywhere, like desktop accounting tools.
         It searches the section you are in - quotations on Quotations,
         vendors on Vendors - and invoices everywhere else. --}}
    @php
        $searchTargets = [
            ['quotations.*', 'quotations.index', 'quotations', 'Search quotations by number, customer…'],
            ['purchases.*', 'purchases.index', 'purchase invoices', 'Search purchase invoices by bill no., vendor…'],
            ['customers.*', 'customers.index', 'customers', 'Search customers by name, GSTIN, phone…'],
            ['vendors.*', 'vendors.index', 'vendors', 'Search vendors by name, GSTIN, phone…'],
            ['products.*', 'products.index', 'items', 'Search items by name, SKU, HSN/SAC…'],
            ['finance.cash-memos.*', 'finance.cash-memos.index', 'cash memos', 'Search cash memos by number, seller…'],
            ['finance.expenses*', 'finance.expenses', 'expenses', 'Search expenses by description, vendor, reference…'],
        ];
        $search = ['invoices.index', 'invoices', 'Search invoices by number, customer, GSTIN…'];
        foreach ($searchTargets as [$pattern, $route, $noun, $hint]) {
            if (request()->routeIs($pattern)) {
                $search = [$route, $noun, $hint];
                break;
            }
        }
    @endphp
    <x-command-search :action="route($search[0])" :placeholder="'Search ' . $search[1] . ' or a page, e.g. GSTR-1'" short-placeholder="Search" :noun="$search[1]"
                      :value="request()->routeIs($search[0]) ? (string) request('search') : ''" />

    <div class="ml-auto flex items-center gap-1.5">
        {{-- Active firm --}}
        <x-dropdown align="right" width="w-64">
            <x-slot name="trigger">
                <button type="button" data-tour="company" title="Active firm: {{ $activeCompany->name }}" class="hidden sm:inline-flex items-center gap-2 h-9 px-2.5 text-sm rounded-md text-gray-700 border border-gray-300 hover:bg-gray-50 max-w-[14rem]">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span class="truncate font-semibold text-xs">{{ $activeCompany->name }}</span>
                    @if ($teamRole)<span class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-accent-100 text-accent-800 font-semibold">{{ $teamRole }}</span>@endif
                    <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="px-4 py-2 text-xs text-gray-500 font-semibold border-b border-gray-100">Switch firm</div>
                @foreach ($myCompanies as $c)
                    @if ($c->id === $activeCompany->id)
                        <div class="flex items-center gap-2 px-4 py-2 text-sm text-brand-700 font-semibold bg-brand-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>{{ $c->name }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('companies.switch', $c) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">{{ $c->name }}</button>
                        </form>
                    @endif
                @endforeach
                @if ($joinedCompanies->isNotEmpty())
                    <div class="px-4 py-2 text-xs text-gray-500 font-semibold border-y border-gray-100">As a team member</div>
                    @foreach ($joinedCompanies as $m)
                        @if ($m->company->id === $activeCompany->id)
                            <div class="flex items-center gap-2 px-4 py-2 text-sm text-brand-700 font-semibold bg-brand-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span class="truncate">{{ $m->company->name }}</span><span class="ml-auto text-xs text-gray-500">{{ $m->roleLabel() }}</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('companies.switch', $m->company) }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                    <span class="truncate">{{ $m->company->name }}</span><span class="ml-auto text-xs text-gray-500">{{ $m->roleLabel() }}</span>
                                </button>
                            </form>
                        @endif
                    @endforeach
                @endif
                <div class="border-t border-gray-100">
                    <a href="{{ route('companies.index') }}" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Manage companies</a>
                    @unless (\App\Support\Team::isMember())
                        <a href="{{ route('companies.create') }}" class="block px-4 py-2 text-sm text-brand-700 font-semibold hover:bg-brand-50">+ Add company</a>
                    @endunless
                </div>
            </x-slot>
        </x-dropdown>

        {{-- + New --}}
        <x-dropdown align="right" width="w-56">
            <x-slot name="trigger">
                <button type="button" aria-label="New" data-tour="new" class="inline-flex items-center justify-center gap-1.5 h-10 min-w-[2.5rem] sm:h-9 px-2.5 sm:px-3 rounded-md text-sm font-semibold text-white bg-brand-700 hover:bg-brand-800 shadow-sm">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">New</span>
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="px-4 py-1.5 text-xs text-gray-500 font-semibold">Sales</div>
                @if (\App\Support\Team::can('invoices.create'))<x-dropdown-link :href="route('invoices.create')">Invoice <span class="float-right text-[10px] text-gray-500 font-mono" aria-hidden="true">Alt+N</span></x-dropdown-link>@endif
                @if (\App\Support\Team::can('quotations.create'))<x-dropdown-link :href="route('quotations.create')">Quotation</x-dropdown-link>@endif
                <div class="px-4 py-1.5 text-xs text-gray-500 font-semibold border-t border-gray-100">Purchases</div>
                @if (\App\Support\Team::can('purchases.create'))<x-dropdown-link :href="route('purchases.create')">Purchase invoice</x-dropdown-link>@endif
                @if (\App\Support\Team::can('finance.expenses.create'))<x-dropdown-link :href="route('finance.expenses.create')">Expense</x-dropdown-link>@endif
                @if (\App\Support\Team::can('finance.cash-memos.create'))<x-dropdown-link :href="route('finance.cash-memos.create')">Cash memo</x-dropdown-link>@endif
                <div class="px-4 py-1.5 text-xs text-gray-500 font-semibold border-t border-gray-100">Parties &amp; items</div>
                @if (\App\Support\Team::can('customers.create'))<x-dropdown-link :href="route('customers.create')">Customer</x-dropdown-link>@endif
                @if (\App\Support\Team::can('vendors.create'))<x-dropdown-link :href="route('vendors.create')">Vendor</x-dropdown-link>@endif
                @if (\App\Support\Team::can('products.create'))<x-dropdown-link :href="route('products.create')">Item</x-dropdown-link>@endif
                <div class="px-4 py-1.5 text-xs text-gray-500 font-semibold border-t border-gray-100">Banking &amp; GST</div>
                @if (\App\Support\Team::can('bank.index'))<x-dropdown-link :href="route('bank.index')">Upload bank statement</x-dropdown-link>@endif
                @if (\App\Support\Team::can('finance.gstr2b'))<x-dropdown-link :href="route('finance.gstr2b')">Upload GSTR-2B</x-dropdown-link>@endif
            </x-slot>
        </x-dropdown>

        {{-- Help with the page you are on: opens that section of the guide. --}}
        @if (\App\Support\Team::can('help'))
            @php $helpAnchor = \App\Support\AppDestinations::helpAnchor(); @endphp
            <a href="{{ route('help') }}{{ $helpAnchor ? '#' . $helpAnchor : '' }}" data-tour="help"
               title="Help with this page" aria-label="Help with this page"
               class="inline-flex items-center justify-center w-10 h-10 sm:w-9 sm:h-9 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-brand-700 transition">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </a>
        @endif

        {{-- Support on WhatsApp, where established tools keep help: in the
             header, always one click away, never covering the page. --}}
        @if ($wa = config('seo.contact.whatsapp_url'))
            <a href="{{ $wa }}" target="_blank" rel="noopener"
               title="Chat with us on WhatsApp" aria-label="Chat with us on WhatsApp"
               class="hidden sm:inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 text-[#0f7540] hover:bg-[#0f7540]/[0.07] hover:border-[#0f7540]/40 transition">
                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            </a>
        @endif
        <x-notification-bell />

        {{-- Account --}}
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-md hover:bg-gray-100" aria-label="Account menu" title="{{ $realUser->name }}">
                    <span class="w-8 h-8 rounded-full bg-brand-100 text-brand-800 font-bold flex items-center justify-center text-sm">{{ strtoupper(substr($realUser->name, 0, 1)) }}</span>
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $realUser->name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $realUser->email }}</div>
                </div>
                @if (\App\Support\Team::can('profile.edit'))<x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>@endif
                @if (\App\Support\Team::can('company.edit'))<x-dropdown-link :href="route('company.edit')">Company settings</x-dropdown-link>@endif
                @if (\App\Support\Team::can('profile.activity'))<x-dropdown-link :href="route('profile.activity')">Activity log</x-dropdown-link>@endif
                @if (\App\Support\Team::can('referrals.index'))<x-dropdown-link :href="route('referrals.index')">Refer a friend</x-dropdown-link>@endif
                <div class="border-t border-gray-100"></div>
                @if (\App\Support\Team::can('help'))<x-dropdown-link :href="route('help')">Help</x-dropdown-link>@endif
                <x-dropdown-link :href="\App\Support\Team::can('dashboard') ? route('dashboard', ['tour' => 1]) : route('invoices.index', ['tour' => 1])">Take the tour</x-dropdown-link>
                <div class="border-t border-gray-100">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log out</x-dropdown-link>
                    </form>
                </div>
            </x-slot>
        </x-dropdown>
    </div>
</header>
