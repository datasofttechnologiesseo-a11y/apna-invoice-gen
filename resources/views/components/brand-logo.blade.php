@props(['class' => 'h-10 w-auto', 'variant' => 'light'])

{{-- WebP logo, 960×225 (~13 KB vs the old 39 KB JPEG). Renders ~40px tall, so
     960w still covers 2x retina. Browsers downscale via the class height. --}}
<img src="{{ asset('brand/apna-invoice-logo-sm.webp') }}"
     alt="Apna Invoice"
     width="960" height="225"
     decoding="async"
     {{ $attributes->merge(['class' => $class]) }}>
