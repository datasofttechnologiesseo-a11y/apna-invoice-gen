{{--
    The bill book and the spreadsheet most visitors use today, beside Apna
    Invoice, task by task. No other product is named.
--}}
@php
    $rows = [
        ['GST on each bill', 'Worked out by hand, line by line', 'CGST and SGST, or IGST, from the customer’s state'],
        ['Sending the bill', 'A photo of the page on WhatsApp', 'A PDF or link on WhatsApp, with a UPI QR to pay'],
        ['GSTR-1', 'Typed into the portal one invoice at a time', 'One JSON file uploaded on the GST portal'],
        ['Who owes you', 'Turning the pages of the khata', 'Unpaid bills by 30, 60 and 90 days, with reminders'],
        ['Stock', 'Counted at the end of the month', 'Updated with every sale and purchase'],
        ['If the book or laptop is lost', 'The records go with it', 'Your books are online. Download them any time'],
    ];
@endphp
<section aria-labelledby="lp-compare-title" class="bg-white">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 md:py-20 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand-700">Why switch</p>
            <h2 id="lp-compare-title" class="mt-2 font-display text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">From the bill book and Excel to Apna Invoice</h2>
        </div>

        {{-- Desktop: a table. --}}
        <div class="mt-9 hidden overflow-hidden rounded-2xl ring-1 ring-gray-200 md:block">
            <table class="w-full text-left text-[15px]">
                <thead>
                    <tr class="bg-gray-50 text-sm">
                        <th scope="col" class="w-1/4 px-5 py-4 font-semibold text-gray-700">Task</th>
                        <th scope="col" class="w-[37.5%] px-5 py-4 font-semibold text-gray-700">Bill book or Excel</th>
                        <th scope="col" class="w-[37.5%] bg-brand-700 px-5 py-4 font-semibold text-white">With Apna Invoice</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($rows as [$task, $before, $after])
                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-gray-900">{{ $task }}</th>
                            <td class="px-5 py-4 text-gray-600">
                                <span class="flex gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ $before }}
                                </span>
                            </td>
                            <td class="bg-brand-50/60 px-5 py-4 font-medium text-gray-900">
                                <span class="flex gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    {{ $after }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Phones: one card per task. --}}
        <ul class="mt-7 grid gap-3 md:hidden">
            @foreach ($rows as [$task, $before, $after])
                <li class="rounded-xl ring-1 ring-gray-200">
                    <p class="rounded-t-xl bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-900">{{ $task }}</p>
                    <p class="flex gap-2.5 px-4 pt-3 text-sm text-gray-600">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span><span class="sr-only">Bill book or Excel: </span>{{ $before }}</span>
                    </p>
                    <p class="flex gap-2.5 px-4 pb-3 pt-2 text-[15px] font-medium text-gray-900">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-money-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span><span class="sr-only">With Apna Invoice: </span>{{ $after }}</span>
                    </p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
