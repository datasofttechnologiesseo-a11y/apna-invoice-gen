{{--
    Short answers to what a visitor from an ad asks before signing up. Used on
    /start; the home page has its own, longer FAQ. Plain <details>, so it
    works without JavaScript.
--}}
@php
    $faqs = [
        ['Is it really free?', 'Yes. Apna Invoice is free during beta, with no card and no limit on invoices, customers or users. When paid plans come for larger businesses, everyone who joined during beta keeps the free plan.'],
        ['Do I need a GSTIN to use it?', 'No. Without a GSTIN your bills are titled Invoice. Add your GSTIN later and new bills become tax invoices. A composition dealer gets a bill of supply.'],
        ['Can I bill from my phone?', 'Yes. It works in the browser on any phone or computer, with the same account on both. On a phone you can add it to the home screen and open it like an app.'],
        ['Does it file my GST return?', 'It prepares the return from your books: the GSTR-1 JSON file for the portal and the GSTR-3B figures. You or your CA upload and file it on the GST portal.'],
        ['Can my CA or staff use my account?', 'Yes. Add them under Team, each with their own login and a role: Billing staff, Accountant / CA or Admin. You can remove access at any time.'],
        ['Can I bring my data from Excel?', 'Yes. Customers, vendors and items can be imported from an Excel or CSV file. Your own data can be downloaded as Excel at any time.'],
    ];
@endphp
<section id="questions" aria-labelledby="lp-faq-title" class="scroll-mt-20 border-t border-gray-200 bg-gray-50">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:py-20 lg:grid-cols-12 lg:px-8">
        <div class="lg:col-span-4">
            <p class="text-sm font-semibold text-brand-700">Questions</p>
            <h2 id="lp-faq-title" class="mt-2 font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Before you sign up</h2>
        </div>
        <div class="space-y-3 lg:col-span-8">
            @foreach ($faqs as $i => [$q, $a])
                <details class="group rounded-xl bg-white shadow-sm ring-1 ring-gray-200 open:ring-brand-200" @if ($i === 0) open @endif>
                    <summary class="flex min-h-[52px] cursor-pointer list-none items-center justify-between gap-4 px-5 py-3 font-semibold text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden">
                        {{ $q }}
                        <svg class="h-5 w-5 shrink-0 text-brand-700 transition group-open:rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
                    </summary>
                    <p class="px-5 pb-5 text-[15px] leading-relaxed text-gray-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
