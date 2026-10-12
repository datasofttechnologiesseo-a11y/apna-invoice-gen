@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-money-700', 'role' => 'status']) }}>
        {{ $status }}
    </div>
@endif
