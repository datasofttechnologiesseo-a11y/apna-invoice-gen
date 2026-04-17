@props(['class' => 'h-10 w-auto'])

@if (file_exists(public_path('brand/dst-logo.png')))
    <img src="{{ asset('brand/dst-logo.png') }}" alt="DST Datasoft Technologies" {{ $attributes->merge(['class' => $class]) }}>
@else
    <img src="{{ asset('brand/dst-logo.svg') }}" alt="DST Datasoft Technologies" {{ $attributes->merge(['class' => $class]) }}>
@endif
