@props(['status', 'label' => null])
{{--
    One status badge for every document: pill, sentence case, 12px. The
    colour follows the status key, so an invoice, a purchase invoice and a
    quotation in the same state look the same everywhere.
    Use with the model's own label: <x-status-badge :status="$inv->status" :label="$inv->statusLabel()" />
--}}
@php
    $tones = [
        'draft' => 'bg-gray-100 text-gray-700 ring-gray-200',
        'final' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'issued' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'unpaid' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'sent' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'partially_paid' => 'bg-accent-50 text-accent-800 ring-accent-200',
        'partial' => 'bg-accent-50 text-accent-800 ring-accent-200',
        'paid' => 'bg-money-50 text-money-800 ring-money-200',
        'accepted' => 'bg-money-50 text-money-800 ring-money-200',
        'converted' => 'bg-money-50 text-money-800 ring-money-200',
        'overdue' => 'bg-danger-50 text-danger-700 ring-danger-200',
        'declined' => 'bg-danger-50 text-danger-700 ring-danger-200',
        'cancelled' => 'bg-gray-100 text-gray-500 ring-gray-200 line-through decoration-gray-400',
        'expired' => 'bg-gray-100 text-gray-500 ring-gray-200',
    ];
    $key = strtolower((string) $status);
    $text = $label ?? ucfirst(str_replace('_', ' ', $key));
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ' . ($tones[$key] ?? 'bg-gray-100 text-gray-700 ring-gray-200')]) }}>{{ $text }}</span>
