<?php

namespace App\Imports;

use Illuminate\Support\Str;

class VendorImporter extends Importer
{
    private ?array $existing = null;

    public function key(): string
    {
        return 'vendors';
    }

    public function title(): string
    {
        return 'Vendors';
    }

    public function listRoute(): string
    {
        return route('vendors.index');
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'Vendor Name', 'required' => true, 'help' => 'Supplier name as on their invoices', 'example' => 'Deepcool India', 'aliases' => ['name', 'party name', 'supplier', 'supplier name', 'vendor']],
            'gstin' => ['label' => 'GSTIN', 'help' => 'Needed to claim input tax credit', 'example' => '27AAPFU0939F1ZV', 'aliases' => ['gst no', 'gst number', 'gstin/uin']],
            'state' => ['label' => 'State', 'help' => 'State name or GST code. Filled from the GSTIN if blank', 'example' => 'Maharashtra', 'aliases' => ['state name', 'state code']],
            'phone' => ['label' => 'Phone', 'help' => '', 'example' => '9822012345', 'aliases' => ['mobile', 'mobile no', 'phone no', 'contact']],
            'email' => ['label' => 'Email', 'help' => '', 'example' => 'sales@deepcool.in', 'aliases' => ['email id', 'e-mail']],
            'address_line1' => ['label' => 'Address Line 1', 'help' => '', 'example' => 'Plot 21, MIDC', 'aliases' => ['address', 'address 1']],
            'address_line2' => ['label' => 'Address Line 2', 'help' => '', 'example' => 'Bhosari', 'aliases' => ['address 2', 'area']],
            'city' => ['label' => 'City', 'help' => '', 'example' => 'Pune', 'aliases' => ['town']],
            'postal_code' => ['label' => 'PIN Code', 'help' => '6 digits', 'example' => '411026', 'aliases' => ['pin', 'pincode', 'postal code']],
        ];
    }

    public function prepare(array $row): array
    {
        $errors = [];
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Vendor Name is required.';
        } elseif (mb_strlen($name) > 160) {
            $errors[] = 'Vendor Name is longer than 160 characters.';
        }
        [$gstin, $gstinError] = $this->checkGstin($row['gstin'] ?? null);
        if ($gstinError) {
            $errors[] = $gstinError;
        }
        [$state, $stateError] = $this->resolveState($row['state'] ?? null, $gstin);
        if ($stateError) {
            $errors[] = $stateError;
        }
        $email = trim($row['email'] ?? '');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email \"{$email}\" is not valid.";
        }
        $pin = self::digits($row['postal_code'] ?? '');
        if ($pin !== '' && strlen($pin) !== 6) {
            $errors[] = "PIN Code \"{$row['postal_code']}\" should be 6 digits.";
        }
        if ($errors) {
            return ['data' => null, 'errors' => $errors, 'duplicate' => null];
        }

        $data = [
            'name' => $name,
            'gstin' => $gstin,
            'state_id' => $state?->id,
            'phone' => trim($row['phone'] ?? '') ?: null,
            'email' => $email ?: null,
            'address_line1' => trim($row['address_line1'] ?? '') ?: null,
            'address_line2' => trim($row['address_line2'] ?? '') ?: null,
            'city' => trim($row['city'] ?? '') ?: null,
            'postal_code' => $pin ?: null,
            'country' => 'India',
        ];

        return ['data' => $data, 'errors' => [], 'duplicate' => $this->duplicateOf($data)];
    }

    public function fileKey(array $data): string
    {
        return $data['gstin'] ?: Str::lower($data['name']);
    }

    private function duplicateOf(array $data): ?string
    {
        if ($this->existing === null) {
            $this->existing = ['gstin' => [], 'name' => []];
            foreach ($this->company->vendors()->get(['name', 'gstin']) as $v) {
                if ($v->gstin) {
                    $this->existing['gstin'][$v->gstin] = $v->name;
                }
                $this->existing['name'][Str::lower(trim($v->name))] = $v->name;
            }
        }
        if ($data['gstin'] && isset($this->existing['gstin'][$data['gstin']])) {
            return 'Already a vendor with GSTIN ' . $data['gstin'] . ' (' . $this->existing['gstin'][$data['gstin']] . ')';
        }
        if (isset($this->existing['name'][Str::lower($data['name'])])) {
            return 'Already a vendor named ' . $this->existing['name'][Str::lower($data['name'])];
        }

        return null;
    }

    public function create(array $data): void
    {
        $this->company->vendors()->create(array_merge($data, ['user_id' => $this->user->id]));
    }
}
