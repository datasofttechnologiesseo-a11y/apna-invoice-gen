{{--
    Landing hero, shared by the home page and the ads page (/start): the
    headline, the sign-up form itself, and real screens below.

    Takes: $h1, $sub (message-matched on /start), $formPrefix (id prefix for
    the form fields, unique per page copy).

    Layout: on desktop the headline and points sit left and the form card
    right. On phones the order is headline, form, points, so the first field
    is on the first screen. The card is #signup: every other "Create free
    account" link on the page scrolls here and focuses the name field (script
    at the end), and the phone sticky bar appears once the card has scrolled
    away.
--}}
@php
    $h1 = $h1 ?? 'Free GST billing software for Indian businesses';
    $sub = $sub ?? 'GST worked out from the place of supply. Bills shared on WhatsApp with a UPI QR to pay. GSTR-1 ready for the portal. Free during beta, no card needed.';
    $formPrefix = $formPrefix ?? 'hero-';
    $points = [
        'CGST and SGST, or IGST, from the place of supply',
        'HSN/SAC on every line, five PDF styles with your logo',
        'WhatsApp, email or a link, with a UPI QR to pay',
        'GSTR-1 JSON, GSTR-2B match and GSTR-3B figures',
    ];
    $trust = ['No card', 'No limit on invoices', 'Made in India', 'Download your data any time'];
@endphp
<section class="bg-gradient-to-b from-brand-50 to-white">
    <div class="mx-auto grid max-w-6xl gap-x-12 gap-y-7 px-4 pb-10 pt-7 sm:px-6 md:pt-12 lg:grid-cols-12 lg:grid-rows-[auto_1fr] lg:px-8 lg:pb-14">
        {{-- Headline --}}
        <div class="lg:col-span-7 lg:row-start-1 lg:pt-4">
            <p lang="hi" class="text-sm font-semibold text-brand-800">आपका अपना GST बिलिंग साथी</p>
            <h1 class="mt-2 font-display text-[2rem] font-extrabold leading-[1.12] tracking-tight text-gray-900 sm:text-[2.6rem] lg:text-5xl">{{ $h1 }}</h1>
            <p class="mt-4 max-w-xl text-[1.05rem] leading-relaxed text-gray-700 sm:text-lg">{{ $sub }}</p>
        </div>

        {{-- Sign-up card (or the way back in, when signed in) --}}
        <div id="signup" data-has-errors="{{ $errors->any() ? '1' : '' }}" class="scroll-mt-24 lg:col-span-5 lg:col-start-8 lg:row-span-2 lg:row-start-1">
            <div id="lp-hero-cta" class="rounded-2xl bg-white p-5 shadow-xl ring-1 ring-gray-900/10 sm:p-7">
                @auth
                    <h2 class="font-display text-xl font-bold text-gray-900">You are signed in</h2>
                    <p class="mt-1 text-sm text-gray-600">Your invoices, returns and reports are on the dashboard.</p>
                    <a href="{{ route('dashboard') }}" class="mt-5 flex min-h-[52px] w-full items-center justify-center rounded-lg bg-brand-700 px-5 text-base font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Go to dashboard</a>
                @else
                    <h2 class="font-display text-xl font-bold text-gray-900">Create your free account</h2>
                    <p class="mt-1 text-sm font-medium text-gray-600">Free during beta · No card</p>
                    <x-signup-form :compact="true" :id-prefix="$formPrefix" submit-label="Create free account" class="mt-5" />
                @endauth
            </div>
        </div>

        {{-- Points and trust line --}}
        <div class="lg:col-span-7 lg:row-start-2 lg:self-start">
            <ul class="grid gap-2.5 text-[15px] text-gray-800 sm:grid-cols-2 lg:max-w-xl">
                @foreach ($points as $point)
                    <li class="flex gap-2.5">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ $point }}</span>
                    </li>
                @endforeach
            </ul>
            <ul class="mt-6 flex flex-wrap gap-x-4 gap-y-1.5 border-t border-gray-200 pt-5 text-sm text-gray-700" aria-label="What you get">
                @foreach ($trust as $item)
                    <li class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Real screens, below the form so the form stays on the first screen. --}}
    <div class="mx-auto max-w-5xl px-4 pb-14 sm:px-6 md:pb-20 lg:px-8">
        <div class="relative sm:pb-10 sm:pr-[16%] lg:pr-[12%]">
            <x-screen src="invoice-view.webp" :eager="true" url="apnainvoice.com/invoices/SE-26-27-0001"
                      alt="An issued GST tax invoice with CGST and SGST, the amount received and share buttons" />
            <x-screen src="m-invoice-form.webp" :mobile="true"
                      alt="New invoice on a phone with the customer chosen and Save and issue at the bottom"
                      class="absolute bottom-0 right-0 hidden sm:block !w-[30%] !max-w-[220px]" />
        </div>
        <p class="mt-3 text-xs text-gray-500">Screens from a sample business, Sharma Electricals in Pune.</p>
    </div>
</section>

@guest
<script>
    // Every [data-lp-signup] link ("Start free", the sticky bar, the closing
    // section) brings the sign-up card into view and puts the cursor in the
    // name field. Without JavaScript the href="#signup" jump still works.
    (function () {
        var card = document.getElementById('signup');
        if (!card) {
            return;
        }
        var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function focusForm(smooth) {
            card.scrollIntoView({ behavior: smooth && !still ? 'smooth' : 'auto', block: 'start' });
            var field = card.querySelector('input[name="name"]');
            if (field) {
                field.focus({ preventScroll: true });
            }
        }
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('[data-lp-signup]');
            if (!link) {
                return;
            }
            e.preventDefault();
            focusForm(true);
        });
        // Back from a failed sign-up: show the form with its messages.
        if (card.dataset.hasErrors) {
            var bad = card.querySelector('[aria-invalid="true"]');
            card.scrollIntoView({ block: 'start' });
            if (bad) { bad.focus({ preventScroll: true }); }
        }
    })();
</script>
@endguest
