@props(['messages', 'for' => null, 'id' => null])

{{-- Pass for="field_name" (id becomes "field_name-error") or an explicit
     id="…", so the field can point at this message with aria-describedby.
     x-text-input and x-password-input do that by name. --}}
@php $errorId = $id ?? ($for ? $for . '-error' : null); @endphp
@if ($messages)
    <ul {{ $attributes->merge(array_filter(['class' => 'text-sm text-danger-600 space-y-1', 'id' => $errorId])) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
