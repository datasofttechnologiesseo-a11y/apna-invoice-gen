<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ __('Settings') }}</h2>
            <p class="text-sm text-gray-500 mt-1">Manage your business details, account, branding and preferences in one place.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <x-flash />

            @php
                $user = auth()->user();
                $company = $user->companies()->first();

                $sections = [
                    [
                        'group' => 'Account',
                        'cards' => [
                            [
                                'title' => 'Profile',
                                'sub' => 'Name, email and password',
                                'href' => route('profile.edit'),
                                'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                                'tone' => 'sky',
                                'badge' => null,
                            ],
                            [
                                'title' => 'Sign-in activity',
                                'sub' => 'Recent logins and sessions',
                                'href' => route('profile.activity'),
                                'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                                'tone' => 'indigo',
                                'badge' => null,
                            ],
                        ],
                    ],
                    [
                        'group' => 'Business',
                        'cards' => array_filter([
                            [
                                'title' => 'Company profile',
                                'sub' => 'Name, GSTIN, address, state, logo, signature',
                                'href' => route('company.edit') . '?from=settings',
                                'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                                'tone' => 'brand',
                                'badge' => $company && $company->gstin ? null : 'Action needed',
                            ],
                            [
                                'title' => 'Invoice templates',
                                'sub' => 'Pick a layout for new invoices',
                                'href' => route('invoices.templates'),
                                'icon' => 'M4 6a2 2 0 012-2h12a2 2 0 012 2v2H4V6zM4 10h16v8a2 2 0 01-2 2H6a2 2 0 01-2-2v-8z',
                                'tone' => 'saffron',
                                'badge' => null,
                            ],
                            [
                                'title' => 'Customers',
                                'sub' => 'Manage saved customers and contacts',
                                'href' => route('customers.index'),
                                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                                'tone' => 'emerald',
                                'badge' => null,
                            ],
                            \Illuminate\Support\Facades\Route::has('products.index') ? [
                                'title' => 'Products & services',
                                'sub' => 'Reusable items with HSN/SAC and rates',
                                'href' => route('products.index'),
                                'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                                'tone' => 'amber',
                                'badge' => null,
                            ] : null,
                        ]),
                    ],
                    [
                        'group' => 'Help',
                        'cards' => [
                            [
                                'title' => 'Help & support',
                                'sub' => 'Documentation and contact us',
                                'href' => route('pages.contact'),
                                'icon' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',
                                'tone' => 'gray',
                                'badge' => null,
                            ],
                            [
                                'title' => 'Privacy &amp; security',
                                'sub' => 'How we protect your data',
                                'href' => route('pages.security'),
                                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                                'tone' => 'gray',
                                'badge' => null,
                            ],
                        ],
                    ],
                ];

                $tones = [
                    'sky'     => ['bg' => 'bg-sky-50',     'text' => 'text-sky-700',     'ring' => 'ring-sky-100'],
                    'indigo'  => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-700',  'ring' => 'ring-indigo-100'],
                    'brand'   => ['bg' => 'bg-brand-50',   'text' => 'text-brand-700',   'ring' => 'ring-brand-100'],
                    'saffron' => ['bg' => 'bg-saffron-50', 'text' => 'text-saffron-700', 'ring' => 'ring-saffron-100'],
                    'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-100'],
                    'amber'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'ring' => 'ring-amber-100'],
                    'gray'    => ['bg' => 'bg-gray-100',   'text' => 'text-gray-700',    'ring' => 'ring-gray-200'],
                ];
            @endphp

            @foreach ($sections as $section)
                <section>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3 px-1">{{ $section['group'] }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($section['cards'] as $card)
                            @php $t = $tones[$card['tone']]; @endphp
                            <a href="{{ $card['href'] }}"
                               class="group relative bg-white rounded-2xl shadow-card ring-1 ring-gray-200 hover:ring-brand-300 hover:shadow-md transition p-5 flex items-start gap-4">
                                <div class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center {{ $t['bg'] }} ring-1 {{ $t['ring'] }}">
                                    <svg class="w-6 h-6 {{ $t['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="font-semibold text-gray-900 group-hover:text-brand-700 transition">{!! $card['title'] !!}</div>
                                        @if (! empty($card['badge']))
                                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 ring-1 ring-amber-200">{{ $card['badge'] }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 mt-0.5">{{ $card['sub'] }}</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-brand-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
