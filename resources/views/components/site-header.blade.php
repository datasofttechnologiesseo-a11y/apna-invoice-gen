{{-- Public site header: marketing pages, guides and the blog.
     One copy so the three templates that used to carry their own header
     cannot drift apart. Not sticky: /features pins its own category bar to
     the top of the viewport. Below lg the links fold into a menu, which the
     old header never had (phones saw only "Log in" and "Start free"). --}}
@php
    $links = [
        ['route' => 'pages.features', 'label' => 'Features', 'match' => 'pages.features'],
        ['route' => 'pages.how-to-use', 'label' => 'How to use', 'match' => 'pages.how-to-use'],
        ['route' => 'pages.pricing', 'label' => 'Pricing', 'match' => 'pages.pricing'],
        ['route' => 'blog.index', 'label' => 'Blog', 'match' => 'blog.*'],
        ['route' => 'pages.faq', 'label' => 'FAQ', 'match' => 'pages.faq'],
    ];
@endphp
<header class="relative z-30 bg-white border-b border-gray-200" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 md:h-[4.5rem] flex items-center justify-between gap-4">
        <a href="{{ url('/') }}" class="shrink-0 flex items-center" aria-label="Apna Invoice home">
            <x-brand-logo class="h-9 md:h-10 w-auto block" />
        </a>

        <nav aria-label="Main" class="hidden lg:flex items-center gap-1">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['match']); @endphp
                <a href="{{ route($link['route']) }}"
                   @if ($active) aria-current="page" @endif
                   class="px-3 py-2 rounded-md text-[15px] font-medium transition-colors {{ $active ? 'text-brand-800 bg-brand-50' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50' }}">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-1.5 sm:gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-[44px] px-4 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-[15px] font-semibold shadow-sm transition-colors whitespace-nowrap">
                    <span class="sm:hidden">Dashboard</span><span class="hidden sm:inline">Go to dashboard</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center min-h-[44px] px-3 text-[15px] font-semibold text-gray-800 hover:text-brand-700 transition-colors">Log in</a>
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-4 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-[15px] font-semibold shadow-sm transition-colors whitespace-nowrap">Start free</a>
            @endauth
            <button type="button"
                    class="lg:hidden inline-flex items-center justify-center w-[44px] h-[44px] -mr-2 rounded-lg text-gray-700 hover:bg-gray-100"
                    @click="open = !open"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="site-menu">
                <span class="sr-only">Menu</span>
                <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>

    <div id="site-menu" x-show="open" x-cloak @click.outside="open = false"
         class="lg:hidden absolute inset-x-0 top-full bg-white border-b border-gray-200 shadow-lg">
        <nav aria-label="Main" class="max-w-6xl mx-auto px-4 sm:px-6 py-2">
            <ul class="divide-y divide-gray-100">
                @foreach ($links as $link)
                    @php $active = request()->routeIs($link['match']); @endphp
                    <li>
                        <a href="{{ route($link['route']) }}" @if ($active) aria-current="page" @endif
                           class="flex items-center min-h-[48px] text-base font-medium {{ $active ? 'text-brand-800' : 'text-gray-800 hover:text-brand-700' }}">{{ $link['label'] }}</a>
                    </li>
                @endforeach
                @guest
                    <li class="sm:hidden">
                        <a href="{{ route('login') }}" class="flex items-center min-h-[48px] text-base font-medium text-gray-800 hover:text-brand-700">Log in</a>
                    </li>
                @endguest
            </ul>
        </nav>
    </div>
</header>
