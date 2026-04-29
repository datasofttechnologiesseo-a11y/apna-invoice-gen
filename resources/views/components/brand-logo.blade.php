@props(['class' => 'h-10 w-auto', 'variant' => 'light'])

{{-- HD logo: native 1939×454 (aspect ratio ~4.27:1). Browsers downscale via the
     class height while preserving sharpness on retina displays. --}}
<img src="{{ asset('brand/apna-invoice-logo-sm.jpg') }}"
     alt="Apna Invoice"
     width="1939" height="454"
     decoding="async"
     {{ $attributes->merge(['class' => $class]) }}>
