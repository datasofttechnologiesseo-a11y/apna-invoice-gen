@props([
    'src',                // file name in public/brand/screens, e.g. 'dashboard.webp'
    'alt',
    'mobile' => false,    // phone frame instead of a browser window
    'url' => null,        // address shown in the browser bar, e.g. 'apnainvoice.com/invoices'
    'caption' => null,
    'eager' => false,     // true only for the first image on a page
])

{{--
    A real screen of the product, in a browser window or a phone frame.
    Screens are taken from a sample business (Sharma Electricals, Pune), not
    a customer's books. Width and height are set so the page does not jump
    while the image loads.
--}}
@php
    $path = public_path('brand/screens/' . $src);
    [$w, $h] = is_file($path) ? (getimagesize($path) ?: [1280, 800]) : [1280, 800];
    $img = asset('brand/screens/' . $src) . (is_file($path) ? '?v=' . filemtime($path) : '');
@endphp

<figure {{ $attributes->merge(['class' => $mobile ? 'mx-auto w-full max-w-[280px]' : 'w-full']) }}>
    @if ($mobile)
        <div class="rounded-[2rem] bg-gray-900 p-2 shadow-xl ring-1 ring-black/10">
            <img src="{{ $img }}" alt="{{ $alt }}" width="{{ $w }}" height="{{ $h }}"
                 class="block w-full h-auto rounded-[1.6rem]"
                 loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async">
        </div>
    @else
        <div class="overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-gray-900/10">
            <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2" aria-hidden="true">
                <span class="flex gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                </span>
                @if ($url)
                    <span class="ml-2 truncate rounded-md bg-white px-2.5 py-0.5 text-[11px] text-gray-500 ring-1 ring-gray-200">{{ $url }}</span>
                @endif
            </div>
            <img src="{{ $img }}" alt="{{ $alt }}" width="{{ $w }}" height="{{ $h }}"
                 class="block w-full h-auto"
                 loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async">
        </div>
    @endif
    @if ($caption)
        <figcaption class="mt-2.5 text-center text-sm text-gray-500">{{ $caption }}</figcaption>
    @endif
</figure>
