@props(['class' => 'h-10 w-auto', 'variant' => 'light'])

<img src="{{ asset('brand/apna-invoice-logo.png') }}"
     alt="Apna Invoice"
     {{ $attributes->merge(['class' => $class]) }}>
