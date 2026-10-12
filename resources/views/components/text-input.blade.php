@props(['disabled' => false])

@php
    // A field with a validation error says so to screen readers and points
    // at its message (<x-input-error for="name">).
    $field = $attributes->get('name');
    if (is_string($field) && $field !== '' && isset($errors) && $errors->has($field)) {
        $attributes = $attributes->except('aria-describedby')->merge([
            'aria-invalid' => 'true',
            'aria-describedby' => trim($attributes->get('aria-describedby', '').' '.$field.'-error'),
        ]);
    }
@endphp

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm']) }}>
