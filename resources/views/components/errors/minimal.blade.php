@props(['code', 'title', 'message'])
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('seo.name', config('app.name')) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|plus-jakarta-sans:700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    @include('partials.google-analytics')
</head>
<body class="font-sans antialiased min-h-screen flex flex-col bg-white text-gray-900">
    <header class="border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center">
            <a href="{{ url('/') }}" class="inline-flex" aria-label="Apna Invoice home">
                <x-brand-logo class="h-9 w-auto" />
            </a>
        </div>
    </header>

    {{-- Calm and left-aligned: the error code as a small label, a plain
         title, what happened, and the ways back. --}}
    <main id="main-content" class="flex-1 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
            <div class="max-w-xl">
                <p class="flex items-center gap-2.5 text-sm font-semibold text-brand-700">
                    <span class="w-6 h-0.5 rounded-full bg-accent-500" aria-hidden="true"></span>Error {{ $code }}
                </p>
                <h1 class="mt-3 font-display text-4xl md:text-5xl font-extrabold leading-[1.12] tracking-tight text-gray-900">{{ $title }}</h1>
                <p class="mt-4 text-lg text-gray-600 leading-relaxed">{{ $message }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ url('/') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white font-semibold shadow-sm transition-colors">Go to home page</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-white ring-1 ring-gray-300 hover:ring-gray-400 text-gray-800 font-semibold transition">Go to dashboard</a>
                    @elseif (Route::has('pages.contact'))
                        <a href="{{ route('pages.contact') }}" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-white ring-1 ring-gray-300 hover:ring-gray-400 text-gray-800 font-semibold transition">Contact support</a>
                    @endauth
                </div>

                @if ((string) $code === '404')
                    <div class="mt-12 pt-6 border-t border-gray-200">
                        <p class="text-sm font-semibold text-gray-900">Popular pages</p>
                        <ul class="mt-3 grid grid-cols-2 gap-x-6 text-[15px]">
                            @foreach ([
                                ['pages.features', 'Features'],
                                ['pages.pricing', 'Pricing'],
                                ['pages.gst-calculator', 'GST calculator'],
                                ['pages.gst-invoice-format', 'GST invoice format'],
                                ['blog.index', 'Blog'],
                                ['pages.faq', 'FAQ'],
                            ] as [$route, $label])
                                @if (Route::has($route))
                                    <li><a href="{{ route($route) }}" class="inline-flex items-center min-h-[40px] text-brand-700 hover:text-brand-800 hover:underline underline-offset-2">{{ $label }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </main>

    <footer class="border-t border-gray-200 py-5 text-center text-sm text-gray-500">
        © {{ date('Y') }} {{ config('seo.organization.legal_name', 'Datasoft Technologies') }}. All rights reserved.
    </footer>
</body>
</html>
