@props([
    'title' => 'Admin',
    'subtitle' => null,
    'action' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo :title="'Admin · ' . $title" :noindex="true" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">

@php
    $impersonatorId = session('impersonator_id');
    $navItems = [
        ['label' => 'Overview',  'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard'),       'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['label' => 'Users',     'href' => route('admin.users'),     'active' => request()->routeIs('admin.users*'),          'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['label' => 'Invoices',  'href' => route('admin.invoices'),  'active' => request()->routeIs('admin.invoices'),         'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['label' => 'Companies', 'href' => route('admin.companies'), 'active' => request()->routeIs('admin.companies'),        'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['label' => 'Customers', 'href' => route('admin.customers'), 'active' => request()->routeIs('admin.customers'),        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
@endphp

{{-- Impersonation banner --}}
@if ($impersonatorId)
    <div class="bg-amber-500 text-amber-950 px-4 py-2 flex items-center justify-between text-sm font-semibold">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
            <span>You are impersonating <strong>{{ auth()->user()->name }}</strong></span>
        </div>
        <form method="POST" action="{{ route('admin.impersonation.stop') }}">
            @csrf
            <button class="px-3 py-1 bg-amber-950 text-amber-100 rounded text-xs font-bold uppercase tracking-wider hover:bg-amber-900">Stop impersonating</button>
        </form>
    </div>
@endif

<div class="flex min-h-screen" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         x-transition.opacity
         class="fixed inset-0 bg-slate-900/50 z-30 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        class="w-64 bg-slate-900 text-slate-300 flex flex-col flex-shrink-0 fixed inset-y-0 left-0 z-40 transform transition-transform duration-200 lg:static lg:transform-none"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-500 to-amber-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="font-display font-extrabold text-white leading-tight truncate">Apna Invoice</div>
                    <div class="text-[10px] uppercase tracking-widest text-slate-500 leading-tight">Admin Console</div>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden p-1 text-slate-400 hover:text-white" aria-label="Close menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 p-3 space-y-0.5">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" @click="sidebarOpen = false" @class([
                    'group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                    'bg-slate-800 text-white shadow-inner' => $item['active'],
                    'text-slate-400 hover:bg-slate-800 hover:text-white' => ! $item['active'],
                ])>
                    <svg class="w-5 h-5 flex-shrink-0 {{ $item['active'] ? 'text-amber-400' : 'text-slate-500 group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="p-3 border-t border-slate-800 space-y-1">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:bg-slate-800 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to app
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:bg-slate-800 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Log out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main area --}}
    <div class="flex-1 flex flex-col min-w-0">

        {{-- Top bar --}}
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3 sm:py-4 sticky top-0 z-10">
            <div class="flex items-center gap-3">
                {{-- Hamburger (mobile only) --}}
                <button @click="sidebarOpen = true" class="lg:hidden p-2 -ml-2 text-slate-600 hover:text-slate-900" aria-label="Open menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="font-display font-extrabold text-lg sm:text-xl md:text-2xl text-slate-900 truncate">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5 truncate hidden sm:block">{{ $subtitle }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                    @if (isset($action))
                        <div class="hidden md:flex md:items-center md:gap-2">
                            {{ $action }}
                        </div>
                    @endif
                    <div class="flex items-center gap-2 sm:gap-3 sm:pl-3 sm:border-l sm:border-slate-200">
                        <div class="text-right hidden xl:block">
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Signed in</div>
                            <div class="text-sm font-semibold text-slate-900 leading-tight">{{ Str::limit(auth()->user()->name, 14) }}</div>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-red-500 to-amber-500 text-white font-bold flex items-center justify-center text-sm flex-shrink-0">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    </div>
                </div>
            </div>
            {{-- Mobile: action row below the title --}}
            @if (isset($action))
                <div class="mt-3 flex items-center gap-2 md:hidden">
                    {{ $action }}
                </div>
            @endif
        </header>

        {{-- Session status --}}
        @if (session('status'))
            <div class="mx-4 sm:mx-6 lg:mx-8 mt-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{{ session('status') }}</div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
