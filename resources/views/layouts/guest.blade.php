@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'noindex' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo
            :title="$title ?? 'Log in'"
            :description="$description ?? 'Sign in to Apna Invoice — the free GST invoice generator for Indian SMEs, startups, freelancers, and CAs.'"
            :keywords="$keywords ?? 'Apna Invoice login, free GST invoice generator India, invoice software signup, online invoice tool India'"
            :noindex="$noindex" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-brand-50 via-white to-accent-50">
            <div class="mb-6">
                <a href="{{ url('/') }}" aria-label="Apna Invoice home" class="inline-block bg-white rounded">
                    <x-brand-logo class="h-14 w-auto block" />
                </a>
            </div>

            <div class="w-full sm:max-w-md px-6 py-6 bg-white shadow-brand ring-1 ring-gray-100 overflow-hidden sm:rounded-xl">
                {{ $slot }}
            </div>

            <p class="mt-6 text-xs text-gray-500">
                Powered by <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="font-semibold text-gray-700 hover:text-brand-700 transition">Datasoft Technologies</a>
            </p>
        </div>
        @stack('scripts')
    </body>
</html>
