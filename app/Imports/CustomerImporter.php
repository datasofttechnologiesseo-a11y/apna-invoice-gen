<?php

namespace App\Imports;

use Illuminate\Support\Str;

class CustomerImporter extends Importer
{
    private ?array $existing = null;

    public function key(): string
    {
        return 'customers';
    }

    public function title(): string
    {
        return 'Customers';
    }

    public function listRoute(): string
    {
        return route('customers.index');
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'Customer Name', 'required' => true, 'help' => 'Legal name as it should print on invoices', 'example' => 'Sharma Traders', 'aliases' => ['name', 'party name', 'customer', 'business name', 'company name']],
            'gstin' => ['label' => 'GSTIN', 'help' => '15 characters; leave blank for unregistered (B2C) customers', 'example' => '29ABCDE1234F1ZW', 'aliases' => ['gst no', 'gst number', 'gstin/uin', 'gstin no']],
            'state' => ['label' => 'State', 'required' => true, 'help' => 'State name or GST code (27). Filled from the GSTIN if left blank', 'example' => 'Karnataka', 'aliases' => ['state name', 'place of supply', 'state code']],
            'phone' => ['label' => 'Phone', 'help' => 'Mobile for WhatsApp invoices', 'example' => '9876543210', 'aliases' => ['mobile', 'mobile no', 'phone no', 'contact', 'contact no', 'whatsapp']],
            'email' => ['label' => 'Email', 'help' => 'For emailing invoices and reminders', 'example' => 'accounts@sharmatraders.in', 'aliases' => ['email id', 'e-mail', 'mail']],
            'address_line1' => ['label' => 'Address Line 1', 'help' => 'Building, street', 'example' => '14 Brigade Road', 'aliases' => ['address', 'address 1', 'billing address']],
            'address_line2' => ['label' => 'Address Line 2', 'help' => 'Area, landmark (optional)', 'example' => 'Near MG Road Metro', 'aliases' => ['address 2', 'area']],
            'city' => ['label' => 'City', 'help' => '', 'example' => 'Bengaluru', 'aliases' => ['town', 'district']],
            'postal_code' => ['label' => 'PIN Code', 'help' => '6 digits', 'example' => '560001', 'aliases' => ['pin', 'pincode', 'postal code', 'zip']],
        ];
    }

    public function prepare(array $row): array
    {
        $errors = [];
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Customer Name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Customer Name is longer than 255 characters.';
        }

        [$gstin, $gstinError] = $this->checkGstin($row['gstin'] ?? null);
        if ($gstinError) {
            $errors[] = $gstinError;
        }
        [$state, $stateError] = $this->resolveState($row['state'] ?? null, $gstin);
        if ($stateError) {
            $errors[] = $stateError;
        } elseif (! $state) {
            $errors[] = 'State is required (or give a GSTIN and it is filled in).';
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
            'state_id' => $state->id,
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
        return $data['gstin'] ?: Str::lower($data['name']) . '|' . self::digits($data['phone']);
    }

    /** An existing customer with the same GSTIN, or the same name and phone. */
    private function duplicateOf(array $data): ?string
    {
        if ($this->existing === null) {
            $this->existing = ['gstin' => [], 'namephone' => []];
            foreach ($this->company->customers()->get(['name', 'gstin', 'phone']) as $c) {
                if ($c->gstin) {
                    $this->existing['gstin'][$c->gstin] = $c->name;
                }
                $this->existing['namephone'][Str::lower(trim($c->name)) . '|' . self::digits($c->phone)] = $c->name;
            }
        }
        if ($data['gstin'] && isset($this->existing['gstin'][$data['gstin']])) {
            return 'Already a customer with GSTIN ' . $data['gstin'] . ' (' . $this->existing['gstin'][$data['gstin']] . ')';
        }
        $k = Str::lower($data['name']) . '|' . self::digits($data['phone']);
        if (isset($this->existing['namephone'][$k])) {
            return 'Already a customer named ' . $this->existing['namephone'][$k];
        }

        return null;
    }

    public function create(array $data): void
    {
        // user_id is not mass-assignable on Customer; set it explicitly.
        $customer = new \App\Models\Customer($data);
        $customer->forceFill(['user_id' => $this->user->id, 'company_id' => $this->company->id])->save();
    }
}
