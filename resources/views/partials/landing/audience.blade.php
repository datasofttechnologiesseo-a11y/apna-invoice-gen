{{--
    Who it is for, as one compact row under the hero. $activeAudience (set by
    /start from ?for=) marks the visitor's own kind of business.
--}}
@php
    $activeAudience = $activeAudience ?? null;
    $audiences = [
        'shop' => ['Shops and showrooms', 'Counter bills on 80 mm paper, UPI at the counter'],
        'wholesale' => ['Wholesalers and distributors', 'IGST across states, e-way bill number on the bill'],
        'services' => ['Service businesses and freelancers', 'SAC codes, TDS, bills sent on WhatsApp'],
        'traders' => ['Traders and manufacturers', 'Purchase bills, input tax credit and stock'],
        'ca' => ['CAs and accountants', 'GSTR-1 JSON, 2B match and your own login'],
    ];
@endphp
<section aria-labelledby="lp-who-title" class="border-y border-gray-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h2 id="lp-who-title" class="text-sm font-semibold text-gray-700">Who it is for</h2>
        <ul class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach ($audiences as $key => [$who, $what])
                @php $on = $activeAudience === $key; @endphp
                <li @class([
                        'rounded-lg p-3.5 ring-1',
                        'col-span-2 lg:col-span-1' => $loop->last,
                        'bg-brand-50 ring-brand-600' => $on,
                        'bg-gray-50 ring-gray-200' => ! $on,
                    ])>
                    <p class="text-sm font-semibold leading-snug text-gray-900">{{ $who }}</p>
                    <p class="mt-1 text-[13px] leading-snug text-gray-600">{{ $what }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
