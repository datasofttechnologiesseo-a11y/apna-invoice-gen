<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ url('/') }}" aria-label="Apna Invoice home" class="inline-block bg-white rounded">
                        <x-brand-logo class="block h-10 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                @php
                    $navItems = [
                        ['href' => route('dashboard'), 'label' => 'Dashboard', 'active' => request()->routeIs('dashboard'), 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['href' => route('invoices.index'), 'label' => 'Invoices', 'active' => request()->routeIs('invoices.*'), 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['href' => route('customers.index'), 'label' => 'Customers', 'active' => request()->routeIs('customers.*'), 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                        ['href' => route('companies.index'), 'label' => 'Companies', 'active' => request()->routeIs('companies.*') || request()->routeIs('company.*'), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                        ['href' => route('finance.index'), 'label' => 'Finance', 'active' => request()->routeIs('finance.*'), 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ];
                    if (Auth::user()?->isSuperAdmin()) {
                        $navItems[] = ['href' => route('admin.dashboard'), 'label' => 'Admin', 'active' => request()->routeIs('admin.*'), 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'];
                    }
                @endphp
                <div class="hidden sm:ms-6 sm:flex sm:items-center gap-0.5">
                    @foreach ($navItems as $item)
                        <a href="{{ $item['href'] }}" @class([
                            'group inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap',
                            'bg-gradient-to-br from-brand-700 to-brand-800 text-white shadow-sm ring-1 ring-brand-600' => $item['active'],
                            'text-gray-600 hover:text-brand-700 hover:bg-brand-50' => ! $item['active'],
                        ])>
                            <svg class="w-4 h-4 {{ $item['active'] ? 'text-accent-300' : 'text-gray-400 group-hover:text-brand-600' }} transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Company switcher -->
            @auth
                @php
                    $activeCompany = Auth::user()->ensureCompany();
                    $myCompanies = Auth::user()->companies()->orderBy('name')->get();
                @endphp
                @if ($myCompanies->count() > 1 || ! request()->routeIs('onboarding.*'))
                    <div class="hidden lg:flex lg:items-center lg:ms-3 flex-shrink min-w-0">
                        <x-dropdown align="right" width="64">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-2 px-2.5 py-1.5 border border-gray-200 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 hover:border-brand-300 focus:outline-none transition w-44">
                                    <svg class="w-4 h-4 text-brand-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <div class="text-left min-w-0 flex-1">
                                        <div class="text-[9px] text-gray-400 leading-tight uppercase tracking-wider">Active</div>
                                        <div class="leading-tight truncate text-xs font-semibold">{{ $activeCompany->name }}</div>
                                    </div>
                                    <svg class="fill-current h-3.5 w-3.5 text-gray-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="px-3 py-2 text-xs text-gray-500 uppercase tracking-wider font-bold border-b">Switch company</div>
                                @foreach ($myCompanies as $c)
                                    @if ($c->id === $activeCompany->id)
                                        <div class="block w-full px-4 py-2 text-left text-sm leading-5 text-brand-700 font-semibold bg-brand-50">
                                            ✓ {{ $c->name }}
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('companies.switch', $c) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 hover:bg-gray-100 transition">
                                                {{ $c->name }}
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                                <div class="border-t">
                                    <a href="{{ route('companies.index') }}" class="block px-4 py-2 text-sm leading-5 text-gray-600 hover:bg-gray-100 transition">Manage companies</a>
                                    <a href="{{ route('companies.create') }}" class="block px-4 py-2 text-sm leading-5 text-brand-700 font-semibold hover:bg-brand-50 transition">+ Add new company</a>
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @endif
            @endauth

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-3 flex-shrink-0">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-2 py-1.5 border border-gray-200 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 hover:border-brand-300 focus:outline-none transition">
                            <span class="w-7 h-7 rounded-full bg-gradient-to-br from-brand-600 to-accent-500 text-white font-bold flex items-center justify-center text-xs flex-shrink-0">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                            <div class="text-left hidden xl:block">
                                <div class="text-[9px] text-gray-400 leading-tight uppercase tracking-wider">Hi,</div>
                                <div class="leading-tight text-xs font-semibold">{{ Str::limit(Auth::user()->name, 12) }}</div>
                            </div>
                            <svg class="fill-current h-3.5 w-3.5 text-gray-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-100">
        <div class="px-2 py-3 space-y-1">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" @class([
                    'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition',
                    'bg-gradient-to-br from-brand-700 to-brand-800 text-white' => $item['active'],
                    'text-gray-700 hover:bg-brand-50 hover:text-brand-700' => ! $item['active'],
                ])>
                    <svg class="w-5 h-5 {{ $item['active'] ? 'text-accent-300' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <!-- Responsive company switcher -->
        @auth
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4 text-[10px] uppercase tracking-wider font-bold text-gray-500">Active company</div>
                <div class="px-4 pt-1 pb-2 text-sm font-semibold text-brand-700">{{ $activeCompany->name }}</div>
                @if ($myCompanies->count() > 1)
                    <div class="mt-1 space-y-1">
                        @foreach ($myCompanies as $c)
                            @if ($c->id !== $activeCompany->id)
                                <form method="POST" action="{{ route('companies.switch', $c) }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-brand-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        Switch to {{ $c->name }}
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                @endif
                <a href="{{ route('companies.index') }}" class="block px-4 py-2 text-sm text-gray-600 hover:bg-brand-50">Manage companies</a>
                <a href="{{ route('companies.create') }}" class="block px-4 py-2 text-sm text-brand-700 font-semibold hover:bg-brand-50">+ Add new company</a>
            </div>
        @endauth

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
