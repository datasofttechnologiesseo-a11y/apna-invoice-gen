<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo
            :title="$title ?? 'Dashboard'"
            :noindex="true" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.google-analytics')
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="skip-link">Skip to main content</a>
        @if (session('impersonator_id'))
            <div class="bg-amber-500 text-amber-950 px-4 py-2 flex items-center justify-between text-sm font-semibold">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                    <span>You're viewing as <strong>{{ auth()->user()->name }}</strong> — super-admin impersonation active</span>
                </div>
                <form method="POST" action="{{ route('admin.impersonation.stop') }}">
                    @csrf
                    <button class="px-3 py-1 bg-amber-950 text-amber-100 rounded text-xs font-bold uppercase tracking-wider hover:bg-amber-900">Stop impersonating</button>
                </form>
            </div>
        @endif
        <div class="min-h-screen flex flex-col bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Content (heading slot renders in-flow above the body) -->
            <main id="main-content" class="flex-1" tabindex="-1">
                @isset($header)
                    <div class="max-w-7xl mx-auto w-full pt-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                @endisset
                {{ $slot }}
            </main>

            <x-site-footer variant="minimal" />
        </div>

        @include('partials.cookie-banner')
        @stack('scripts')
    </body>
</html>
