{{--
    What the free plan includes, stated as facts about Apna Invoice only.
    id="pricing" keeps the old home page anchor working.
--}}
@php
    $included = [
        ['Unlimited invoices', 'Also quotations, credit notes, customers and items.'],
        ['GSTR-1 JSON and Excel', 'JSON for the GST portal, Excel for the offline tool.'],
        ['GSTR-2B match', 'Each supplier bill checked against your 2B.'],
        ['Bank matching', 'Statement lines matched to receipts and payments.'],
        ['Team access', 'Logins for billing staff and your CA, each with its own role.'],
        ['Backups', 'Download all your data any time, or get it by email every week.'],
    ];
@endphp
<section id="pricing" aria-labelledby="lp-free-title" class="scroll-mt-20 bg-gradient-to-b from-brand-50 to-white">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:py-20 lg:grid-cols-12 lg:gap-12 lg:px-8">
        {{-- The price --}}
        <div class="lg:col-span-4">
            <div class="relative overflow-hidden rounded-2xl bg-brand-800 p-7 text-white shadow-brand">
                <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-white/10" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-16 -left-10 h-40 w-40 rounded-full bg-accent-400/15" aria-hidden="true"></div>
                <p class="relative text-sm font-semibold text-brand-200">Free plan</p>
                <p class="relative mt-2 flex items-baseline gap-2">
                    <span class="font-display text-6xl font-extrabold tracking-tight">₹0</span>
                    <span class="text-brand-100">per month</span>
                </p>
                <p class="relative mt-3 text-sm leading-relaxed text-brand-100">Free during beta. No card, and no limit on invoices, customers or users.</p>
                @guest
                    <a href="#signup" data-lp-signup class="relative mt-6 flex min-h-[48px] items-center justify-center rounded-lg bg-white px-5 font-semibold text-brand-800 shadow-sm transition hover:bg-brand-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Create free account</a>
                @endguest
            </div>
            <div class="mt-4 flex gap-3 rounded-2xl bg-accent-50 p-4 ring-1 ring-accent-200">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent-500 text-accent-950" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                </span>
                <p class="text-sm leading-relaxed text-gray-800"><strong class="font-semibold text-gray-900">Refer a friend.</strong> Share your code from the app. When a friend signs up with it and issues their first invoice, your account is free for life.</p>
            </div>
        </div>

        {{-- What is in it --}}
        <div class="lg:col-span-8">
            <h2 id="lp-free-title" class="font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Included in the free plan</h2>
            <p class="mt-3 max-w-2xl leading-relaxed text-gray-700">When paid plans come for larger businesses, everyone who joined during beta keeps the free plan.</p>
            <ul class="mt-7 grid gap-3 sm:grid-cols-2">
                @foreach ($included as [$what, $detail])
                    <li class="flex gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-money-100 text-money-700" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="font-semibold text-gray-900">{{ $what }}</p>
                            <p class="mt-0.5 text-sm leading-relaxed text-gray-600">{{ $detail }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
