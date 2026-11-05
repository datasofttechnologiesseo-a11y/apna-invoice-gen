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

    The chips floating over the screens describe what the sample business
    (Sharma Electricals) did with that bill. They are not figures about
    Apna Invoice users.
--}}
@php
    $h1 = $h1 ?? 'Free GST billing software for Indian businesses';
    $sub = $sub ?? 'GST worked out from the place of supply. Bills shared on WhatsApp with a UPI QR to pay. GSTR-1 ready for the portal. Free during beta, no card needed.';
    $formPrefix = $formPrefix ?? 'hero-';
    // [icon, tint, text]
    $points = [
        ['tax', 'bg-brand-100 text-brand-800', 'CGST and SGST, or IGST, from the place of supply'],
        ['doc', 'bg-accent-100 text-accent-800', 'HSN/SAC on every line, five PDF styles with your logo'],
        ['chat', 'bg-money-100 text-money-800', 'WhatsApp, email or a link, with a UPI QR to pay'],
        ['upload', 'bg-sky-100 text-sky-800', 'GSTR-1 JSON, GSTR-2B match and GSTR-3B figures'],
    ];
    $icons = [
        'tax' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
        'doc' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'chat' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        'upload' => 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
    ];
@endphp
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
    {{-- Soft colour and a faint grid, behind everything. --}}
    <div class="pointer-events-none absolute inset-0 bg-hero-mesh" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-[520px] bg-grid-soft bg-grid-soft [mask-image:linear-gradient(to_bottom,black,transparent)]" aria-hidden="true"></div>

    <div class="relative mx-auto grid max-w-6xl gap-x-12 gap-y-7 px-4 pb-10 pt-7 sm:px-6 md:pt-12 lg:grid-cols-12 lg:grid-rows-[auto_1fr] lg:px-8 lg:pb-14">
        {{-- Headline --}}
        <div class="lg:col-span-7 lg:row-start-1 lg:pt-2">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1.5 text-sm font-semibold text-brand-800 shadow-sm ring-1 ring-brand-200 backdrop-blur">
                {{-- The tricolour, as three bars. --}}
                <span class="flex h-3.5 w-5 shrink-0 flex-col overflow-hidden rounded-[2px] ring-1 ring-gray-300" aria-hidden="true">
                    <span class="flex-1 bg-[#FF9933]"></span><span class="flex-1 bg-white"></span><span class="flex-1 bg-[#138808]"></span>
                </span>
                <span lang="hi">आपका अपना GST बिलिंग साथी</span>
            </p>
            <h1 class="mt-4 font-display text-[2.1rem] font-extrabold leading-[1.1] tracking-tight text-gray-900 sm:text-[2.75rem] lg:text-[3.25rem]">{{ $h1 }}</h1>
            <div class="mt-4 h-1.5 w-20 rounded-full bg-gradient-to-r from-accent-500 via-brand-600 to-money-600" aria-hidden="true"></div>
            <p class="mt-5 max-w-xl text-[1.05rem] leading-relaxed text-gray-700 sm:text-lg">{{ $sub }}</p>
        </div>

        {{-- Sign-up card (or the way back in, when signed in) --}}
        <div id="signup" data-has-errors="{{ $errors->any() ? '1' : '' }}" class="scroll-mt-24 lg:col-span-5 lg:col-start-8 lg:row-span-2 lg:row-start-1">
            <div id="lp-hero-cta" class="relative rounded-2xl bg-white p-5 shadow-2xl shadow-brand-900/10 ring-1 ring-gray-900/10 sm:p-7">
                @auth
                    <h2 class="font-display text-xl font-bold text-gray-900">You are signed in</h2>
                    <p class="mt-1 text-sm text-gray-600">Your invoices, returns and reports are on the dashboard.</p>
                    <a href="{{ route('dashboard') }}" class="mt-5 flex min-h-[52px] w-full items-center justify-center rounded-lg bg-brand-700 px-5 text-base font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Go to dashboard</a>
                @else
                    {{-- The price, where the decision is made. --}}
                    <div class="absolute -top-3.5 right-5 inline-flex items-center gap-1.5 rounded-full bg-accent-500 px-3 py-1 text-sm font-bold text-accent-950 shadow-md">
                        <span class="font-display">₹0</span><span class="font-semibold">during beta</span>
                    </div>
                    <h2 class="font-display text-xl font-bold text-gray-900">Create your free account</h2>
                    <p class="mt-1 text-sm font-medium text-gray-600">Takes about a minute · No card</p>
                    <x-signup-form :compact="true" :id-prefix="$formPrefix" submit-label="Create free account" class="mt-5" />
                @endauth
            </div>
        </div>

        {{-- Points and trust line --}}
        <div class="lg:col-span-7 lg:row-start-2 lg:self-start">
            <ul class="grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
                @foreach ($points as [$icon, $tint, $point])
                    <li class="flex items-start gap-3 rounded-xl bg-white/80 p-3 text-[15px] leading-snug text-gray-800 shadow-sm ring-1 ring-gray-200 backdrop-blur">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tint }}" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] }}"/></svg>
                        </span>
                        <span class="pt-1">{{ $point }}</span>
                    </li>
                @endforeach
            </ul>
            <ul class="mt-6 flex flex-wrap gap-2 text-sm font-medium text-gray-800" aria-label="What you get">
                <li class="inline-flex items-center gap-1.5 rounded-full bg-money-50 px-3 py-1.5 ring-1 ring-money-200">
                    <svg class="h-4 w-4 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>No card
                </li>
                <li class="inline-flex items-center gap-1.5 rounded-full bg-money-50 px-3 py-1.5 ring-1 ring-money-200">
                    <svg class="h-4 w-4 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>No limit on invoices
                </li>
                <li class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 ring-1 ring-gray-200">
                    <span class="flex h-3 w-4 shrink-0 flex-col overflow-hidden rounded-[2px] ring-1 ring-gray-300" aria-hidden="true">
                        <span class="flex-1 bg-[#FF9933]"></span><span class="flex-1 bg-white"></span><span class="flex-1 bg-[#138808]"></span>
                    </span>Made in India
                </li>
                <li class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 ring-1 ring-gray-200">
                    <svg class="h-4 w-4 shrink-0 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Download your data any time
                </li>
            </ul>
        </div>
    </div>

    {{-- Real screens, below the form so the form stays on the first screen. --}}
    <div class="relative mx-auto max-w-5xl px-4 pb-14 sm:px-6 md:pb-20 lg:px-8">
        <div class="relative sm:pb-10 sm:pr-[16%] lg:pr-[12%]">
            <x-screen src="invoice-view.webp" :eager="true" url="apnainvoice.com/invoices/SE-26-27-0001"
                      alt="An issued GST tax invoice with CGST and SGST, the amount received and share buttons" />
            <x-screen src="m-invoice-form.webp" :mobile="true"
                      alt="New invoice on a phone with the customer chosen and Save and issue at the bottom"
                      class="absolute bottom-0 right-0 hidden sm:block !w-[30%] !max-w-[220px]" />

            {{-- What happened to this sample bill. Decorative: the alt text above carries it. --}}
            <div class="pointer-events-none absolute -left-3 top-[18%] hidden md:block motion-safe:animate-float" aria-hidden="true">
                <div class="flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5 shadow-xl ring-1 ring-gray-900/10">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#25D366] text-white">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.48-1.75-1.65-2.05-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.5h-.01a9.45 9.45 0 01-4.82-1.32l-.35-.2-3.58.93.96-3.49-.23-.36A9.43 9.43 0 012.6 12C2.6 6.8 6.84 2.56 12.06 2.56c2.52 0 4.9.98 6.68 2.77a9.4 9.4 0 012.76 6.68c0 5.22-4.24 9.46-9.45 9.46"/></svg>
                    </span>
                    <span class="text-left">
                        <span class="block text-xs text-gray-500">Shared on WhatsApp</span>
                        <span class="block text-sm font-semibold text-gray-900">SE/26-27/0001</span>
                    </span>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-2 top-[6%] hidden md:block motion-safe:animate-float [animation-delay:1.5s]" aria-hidden="true">
                <div class="flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5 shadow-xl ring-1 ring-gray-900/10">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-money-100 font-display text-sm font-extrabold text-money-800">₹</span>
                    <span class="text-left">
                        <span class="block text-xs text-gray-500">Payment received</span>
                        <span class="block text-sm font-semibold tabular-nums text-gray-900">₹43,872 · Paid</span>
                    </span>
                </div>
            </div>
            <div class="pointer-events-none absolute bottom-[14%] left-[8%] hidden lg:block motion-safe:animate-float [animation-delay:3s]" aria-hidden="true">
                <div class="flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5 shadow-xl ring-1 ring-gray-900/10">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-800">
                        <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="text-left">
                        <span class="block text-xs text-gray-500">GSTR-1 for September</span>
                        <span class="block text-sm font-semibold text-gray-900">JSON ready for the portal</span>
                    </span>
                </div>
            </div>
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
