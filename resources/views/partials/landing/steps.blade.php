{{--
    Three steps to the first invoice, and a plain list of what is not in the
    product yet. The button scrolls to the sign-up form in the hero.
--}}
@php
    $steps = [
        ['Sign up', 'Continue with Google, or use your name, mobile and email. A one-time code sent by email confirms it.'],
        ['Add your business', 'Business name and state are enough. GSTIN, logo, bank and UPI details can be added now or later.'],
        ['Save and issue, then share', 'Pick the customer, add items and press Save and issue. Send it on WhatsApp, by email or as a link.'],
    ];
@endphp
<section id="how-it-works" aria-labelledby="lp-steps-title" class="scroll-mt-20 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20 lg:px-8">
        <h2 id="lp-steps-title" class="font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Your first invoice in three steps</h2>
        <ol class="mt-8 grid gap-6 md:grid-cols-3 md:gap-8">
            @foreach ($steps as $i => [$title, $text])
                <li class="flex gap-4 md:block">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-700 font-display text-sm font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <div class="md:mt-4">
                        <h3 class="font-display text-lg font-bold text-gray-900"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $title }}</h3>
                        <p class="mt-1.5 text-[15px] leading-relaxed text-gray-600">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        @guest
            <a href="#signup" data-lp-signup class="mt-10 inline-flex min-h-[48px] items-center justify-center rounded-lg bg-brand-700 px-6 font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Create free account</a>
        @endguest

        <p class="mt-10 max-w-3xl rounded-lg bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 ring-1 ring-gray-200">
            <strong class="font-semibold text-gray-900">Not yet in Apna Invoice:</strong>
            e-invoice (IRN) generation, Tally export and Hindi screens. E-way bills are made on the free government portal and the number is typed on the invoice.
        </p>
    </div>
</section>
