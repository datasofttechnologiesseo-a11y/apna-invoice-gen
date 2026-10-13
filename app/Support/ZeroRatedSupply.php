<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Input rules for export and SEZ invoices, shared by the web form and the API.
 */
final class ZeroRatedSupply
{
    /** Validation rules for the extra fields. */
    public static function rules(): array
    {
        return [
            'supply_type' => ['nullable', Rule::in(array_keys(Invoice::SUPPLY_TYPES))],
            'export_port_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'shipping_bill_number' => ['nullable', 'string', 'max:20'],
            'shipping_bill_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Combinations that would produce a wrong return: reverse charge on a
     * zero-rated supply, an SEZ recipient without a GSTIN (SEZ units are
     * registered), an export to a customer in India (GSTR-1 Table 6A needs
     * place of supply 96 - "Foreign Country").
     */
    public static function guard(string $supplyType, array $data, Customer $customer): void
    {
        if ($supplyType === 'regular') {
            return;
        }
        $errors = [];
        if (! empty($data['reverse_charge'])) {
            $errors['reverse_charge'] = 'Reverse charge does not apply to exports or SEZ supplies. Untick it.';
        }
        if (str_starts_with($supplyType, 'sez_') && empty($customer->gstin)) {
            $errors['supply_type'] = 'SEZ units and developers are GST-registered. Add the customer\'s GSTIN before billing an SEZ supply.';
        }
        if (str_starts_with($supplyType, 'export_') && $customer->state?->gst_code !== '96') {
            $errors['supply_type'] = 'For an export, the customer\'s state must be "Foreign Country" (place of supply 96). Edit the customer first.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** The values to store on the invoice. */
    public static function attributes(string $supplyType, array $data): array
    {
        $export = str_starts_with($supplyType, 'export_');

        return [
            'supply_type' => $supplyType,
            'export_port_code' => $export ? (strtoupper(trim((string) ($data['export_port_code'] ?? ''))) ?: null) : null,
            'shipping_bill_number' => $export ? (trim((string) ($data['shipping_bill_number'] ?? '')) ?: null) : null,
            'shipping_bill_date' => $export ? ($data['shipping_bill_date'] ?? null) : null,
        ];
    }
}
