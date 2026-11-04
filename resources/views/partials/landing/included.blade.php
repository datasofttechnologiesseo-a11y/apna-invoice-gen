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
<section id="pricing" aria-labelledby="lp-free-title" class="scroll-mt-20 bg-brand-50">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20 lg:px-8">
        <div class="max-w-2xl">
            <h2 id="lp-free-title" class="font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Included in the free plan</h2>
            <p class="mt-3 leading-relaxed text-gray-700">Free during beta. No card, and no limit on invoices, customers or users. When paid plans come for larger businesses, everyone who joined during beta keeps the free plan.</p>
        </div>
        <ul class="mt-8 grid gap-px overflow-hidden rounded-xl bg-brand-200 ring-1 ring-brand-200 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($included as [$what, $detail])
                <li class="flex gap-3 bg-white p-5">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $what }}</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-gray-600">{{ $detail }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mt-6 max-w-2xl text-sm leading-relaxed text-gray-700"><strong class="font-semibold text-gray-900">Refer a friend.</strong> Share your code from the app. When a friend signs up with it and issues their first invoice, your account is free for life.</p>
    </div>
</section>
