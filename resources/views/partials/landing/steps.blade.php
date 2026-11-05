{{--
    Three steps to the first invoice, and a plain list of what is not in the
    product yet. The button scrolls to the sign-up form in the hero.
--}}
@php
    // [icon path, title, text]
    $steps = [
        ['M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
            'Sign up', 'Continue with Google, or use your name, mobile and email. A one-time code sent by email confirms it.'],
        ['M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            'Add your business', 'Business name and state are enough. GSTIN, logo, bank and UPI details can be added now or later.'],
        ['M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
            'Save and issue, then share', 'Pick the customer, add items and press Save and issue. Send it on WhatsApp, by email or as a link.'],
    ];
@endphp
<section id="how-it-works" aria-labelledby="lp-steps-title" class="scroll-mt-20 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand-700">How it works</p>
            <h2 id="lp-steps-title" class="mt-2 font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Your first invoice in three steps</h2>
        </div>
        <ol class="relative mt-10 grid gap-8 md:grid-cols-3 md:gap-8">
            {{-- The line joining the steps, behind the numbers (desktop). --}}
            <span class="pointer-events-none absolute left-[16%] right-[16%] top-7 hidden h-0.5 bg-gradient-to-r from-brand-200 via-brand-400 to-brand-200 md:block" aria-hidden="true"></span>
            @foreach ($steps as $i => [$icon, $title, $text])
                <li class="relative flex gap-4 md:block md:text-center">
                    <span class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-700 text-white shadow-brand md:mx-auto" aria-hidden="true">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        <span class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-accent-500 font-display text-xs font-bold text-accent-950 ring-2 ring-white">{{ $i + 1 }}</span>
                    </span>
                    <div class="md:mt-5">
                        <h3 class="font-display text-lg font-bold text-gray-900"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $title }}</h3>
                        <p class="mt-1.5 text-[15px] leading-relaxed text-gray-600 md:mx-auto md:max-w-xs">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        @guest
            <div class="mt-10 md:text-center">
                <a href="#signup" data-lp-signup class="inline-flex min-h-[48px] items-center justify-center rounded-lg bg-brand-700 px-6 font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Create free account</a>
            </div>
        @endguest

        <p class="mx-auto mt-10 max-w-3xl rounded-lg bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 ring-1 ring-gray-200">
            <strong class="font-semibold text-gray-900">Not yet in Apna Invoice:</strong>
            e-invoice (IRN) generation, Tally export and Hindi screens. E-way bills are made on the free government portal and the number is typed on the invoice.
        </p>
    </div>
</section>
