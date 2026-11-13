<?php

namespace App\Services;

use App\Models\Company;

/**
 * Typeahead behind the search boxes: the web dropdown (SearchController) and
 * the mobile app's search (Api\SearchController) get the same rows.
 *
 * Matching is deliberately NOT written here. Invoices go through
 * Invoice::scopeSearch and customers through Customer::scopeSearch, the
 * same scopes the list pages filter with, so the dropdown can never offer
 * a row that pressing Enter then fails to find.
 */
class SearchSuggestions
{
    /**
     * 80 chars is longer than any invoice number or customer name in the
     * data; past that it is a paste accident, and a LIKE on it is wasted.
     */
    public static function normalise(mixed $q): string
    {
        return trim(mb_substr(is_string($q) ? $q : '', 0, 80));
    }

    /**
     * Groups of suggestions. Each item carries its record type and id
     * ("invoice" opens the invoice, "customer" the customer's ledger).
     *
     * @param  'invoices'|'customers'  $scope
     * @return array<int, array{label: string, items: array<int, array{type: string, id: int, title: string, subtitle: string, meta: string, badge: ?string, tone: ?string}>}>
     */
    public function groups(Company $company, string $q, string $scope = 'invoices'): array
    {
        if ($q === '') {
            return [];
        }
        $groups = [];

        if ($scope === 'invoices') {
            $invoices = $company->invoices()->with('customer')->search($q)
                ->orderByDesc('invoice_date')->orderByDesc('id')
                ->limit(6)->get();

            if ($invoices->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Invoices',
                    'items' => $invoices->map(fn ($inv) => [
                        'type' => 'invoice',
                        'id' => $inv->id,
                        'title' => $inv->displayNumber(),
                        'subtitle' => trim(($inv->customer->name ?? 'No customer')
                            .' · '.$inv->invoice_date?->format('d M Y')),
                        'meta' => '₹'.rupees((float) $inv->grand_total),
                        'badge' => $this->statusLabel($inv),
                        'tone' => $this->statusTone($inv),
                    ])->all(),
                ];
            }
        }

        // Customers appear under both scopes and always open the customer's
        // ledger: every bill, payment and credit note for that person.
        //
        // Customer::scopeSearch also matches the digits of a phone number, so
        // "INV-0002" found a customer whose mobile contains 0002. A query only
        // counts as a phone number when it is mostly digits and has at least
        // five; anything else must match the name, email, GSTIN or the phone
        // as typed.
        $phoneLike = self::looksLikePhone($q);
        $needle = mb_strtolower($q);
        $customers = $company->customers()->withCount('invoices')->search($q)
            ->orderBy('name')->limit(25)->get()
            ->filter(fn ($c) => $phoneLike
                || str_contains(mb_strtolower((string) $c->name), $needle)
                || str_contains(mb_strtolower((string) $c->email), $needle)
                || str_contains(mb_strtolower((string) $c->gstin), $needle)
                || str_contains(mb_strtolower((string) $c->phone), $needle))
            ->take(5)->values();

        if ($customers->isNotEmpty()) {
            $groups[] = [
                'label' => 'Customers',
                'items' => $customers->map(fn ($c) => [
                    'type' => 'customer',
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => collect([$c->phone, $c->gstin, $c->email])
                        ->filter()->take(2)->join(' · ') ?: 'No contact details saved',
                    'meta' => $c->invoices_count === 1 ? '1 invoice' : $c->invoices_count.' invoices',
                    'badge' => null,
                    'tone' => null,
                ])->all(),
            ];
        }

        return $groups;
    }

    /**
     * "98765 43210", "+91-98765-43210": at least five digits, and digits make
     * up most of what was typed. "INV-0002" (4 of 8) is not a phone number.
     */
    public static function looksLikePhone(string $q): bool
    {
        $compact = preg_replace('/\s+/', '', $q);
        $digits = strlen(preg_replace('/\D/', '', $compact));

        return $digits >= 5 && $compact !== '' && $digits / strlen($compact) >= 0.7;
    }

    /**
     * The pill on an invoice suggestion. Mirrors the wording on the list so a
     * bill does not read "Final" in the dropdown and "Unpaid" one click later.
     */
    private function statusLabel($invoice): string
    {
        if ($invoice->isDraft()) {
            return 'Draft';
        }
        if ($invoice->isCancelled()) {
            return 'Cancelled';
        }
        if ((float) $invoice->balance <= 0) {
            return 'Paid';
        }
        // Strictly before today, which is how the invoice list, the dashboard
        // ring and the notification bell all define it. isPast() would not
        // agree: due_date is cast to a date, so "due today" is midnight this
        // morning and already in the past by breakfast.
        if ($invoice->due_date && $invoice->due_date->lt(today())) {
            return 'Overdue';
        }

        return (float) $invoice->paid_amount > 0 ? 'Part paid' : 'Unpaid';
    }

    private function statusTone($invoice): string
    {
        return match ($this->statusLabel($invoice)) {
            'Paid' => 'paid',
            'Overdue', 'Cancelled' => 'danger',
            'Draft' => 'muted',
            default => 'due',
        };
    }
}
