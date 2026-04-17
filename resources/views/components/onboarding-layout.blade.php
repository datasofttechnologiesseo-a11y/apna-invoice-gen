@props(['step'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set up · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gradient-to-br from-brand-50 via-white to-accent-50 min-h-screen">

<header class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between py-3">
        <a href="/" class="flex items-center">
            <x-brand-logo class="h-20 md:h-24 w-auto" />
        </a>
        <div class="flex items-center gap-4 text-sm">
            <span class="hidden md:inline text-gray-500">Hi, {{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button class="text-gray-500 hover:text-gray-700 underline">Log out</button>
            </form>
        </div>
    </div>
</header>

@php
    $steps = [
        ['key' => 'business', 'label' => 'Your business', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1'],
        ['key' => 'customer', 'label' => 'First customer', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['key' => 'done', 'label' => 'Ready to invoice', 'icon' => 'M5 13l4 4L19 7'],
    ];
    $currentIndex = collect($steps)->pluck('key')->search($step);
@endphp

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
    <div class="flex items-center justify-between mb-2 text-sm text-gray-500">
        <div>Step <strong class="text-gray-900">{{ $currentIndex + 1 }}</strong> of {{ count($steps) }}</div>
        <div>{{ round((($currentIndex + 1) / count($steps)) * 100) }}% complete</div>
    </div>
    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-full bg-gradient-to-r from-brand-500 to-accent-500 transition-all duration-500" style="width: {{ (($currentIndex + 1) / count($steps)) * 100 }}%"></div>
    </div>

    <div class="mt-6 hidden md:flex items-center justify-between">
        @foreach ($steps as $i => $s)
            @php
                $done = $i < $currentIndex;
                $active = $i === $currentIndex;
            @endphp
            <div class="flex items-center gap-3 flex-1 {{ $i < count($steps) - 1 ? '' : '' }}">
                <div @class([
                    'w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0 ring-4',
                    'bg-money-500 text-white ring-money-100' => $done,
                    'bg-brand-700 text-white ring-brand-100' => $active,
                    'bg-gray-200 text-gray-500 ring-transparent' => ! $done && ! $active,
                ])>
                    @if ($done)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                <div class="flex-1">
                    <div @class([
                        'text-sm font-semibold',
                        'text-gray-900' => $active,
                        'text-gray-500' => ! $active,
                    ])>{{ $s['label'] }}</div>
                </div>
                @if ($i < count($steps) - 1)
                    <div class="h-px w-full bg-gray-200 hidden lg:block"></div>
                @endif
            </div>
        @endforeach
    </div>
</div>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{ $slot }}
</main>

<footer class="text-center py-6 text-xs text-gray-500">
    © 2026 Datasoft Technologies · All rights reserved.
</footer>

</body>
</html>
