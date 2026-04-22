@props(['code', 'title', 'message'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('seo.name', config('app.name')) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|plus-jakarta-sans:700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased min-h-screen flex flex-col bg-gradient-to-br from-brand-50 via-white to-accent-50">
    <header class="py-6">
        <div class="max-w-6xl mx-auto px-6 flex items-center">
            <a href="{{ url('/') }}" class="inline-block bg-white rounded" aria-label="Apna Invoice">
                <x-brand-logo class="h-10 w-auto" />
            </a>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="max-w-xl text-center">
            <div class="font-display font-extrabold text-brand-700 text-[128px] leading-none tracking-tighter select-none">{{ $code }}</div>
            <h1 class="mt-2 font-display text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">{{ $title }}</h1>
            <p class="mt-4 text-gray-600 leading-relaxed">{{ $message }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition">← Go home</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white hover:bg-gray-50 text-gray-700 font-semibold ring-1 ring-gray-200 transition">Go to dashboard</a>
                @endauth
            </div>
        </div>
    </main>

    <footer class="py-6 text-center text-xs text-gray-500">
        © {{ date('Y') }} {{ config('seo.organization.legal_name', 'Datasoft Technologies') }}
    </footer>
</body>
</html>
