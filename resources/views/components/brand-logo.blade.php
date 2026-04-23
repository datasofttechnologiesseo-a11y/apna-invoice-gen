@props(['class' => 'h-10 w-auto', 'variant' => 'light'])

<img src="{{ asset('brand/apna-invoice-logo-sm.jpg') }}"
     alt="Apna Invoice"
     width="680" height="139"
     decoding="async"
     {{ $attributes->merge(['class' => $class]) }}>
