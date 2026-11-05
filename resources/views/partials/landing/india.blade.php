{{--
    Made for how India bills: four things an Indian business owner checks
    first, each a plain fact about the product.
--}}
@php
    $facts = [
        ['₹1,18,000', 'Lakhs and crores', 'Amounts written the Indian way, and in words on the bill.'],
        ['26-27', 'April to March', 'Numbers run by financial year, like SE/26-27/0001.'],
        ['CGST + SGST', 'Or IGST, worked out', 'The tax split follows the customer’s state.'],
        ['80 mm', 'Counter or A4', 'Thermal counter bills and A4 invoices from the same entry.'],
    ];
@endphp
<section aria-labelledby="lp-india-title" class="bg-brand-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <h2 id="lp-india-title" class="text-sm font-semibold text-brand-200">Made for how India bills</h2>
        <ul class="mt-5 grid grid-cols-2 gap-x-6 gap-y-7 lg:grid-cols-4">
            @foreach ($facts as [$big, $title, $text])
                <li>
                    <p class="font-display text-2xl font-extrabold tracking-tight text-accent-300 sm:text-3xl">{{ $big }}</p>
                    <p class="mt-1 font-semibold">{{ $title }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-brand-100">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
