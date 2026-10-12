@props(['messages', 'for' => null])

{{-- Pass for="field_name" so the field can point at this message with
     aria-describedby (x-text-input and x-password-input do that by name). --}}
@if ($messages)
    <ul {{ $attributes->merge(array_filter(['class' => 'text-sm text-danger-600 space-y-1', 'id' => $for ? $for.'-error' : null])) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
