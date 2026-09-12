<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Typeahead behind the search boxes on the invoice and customer lists.
     *
     * The complaint this answers is that the box behaved like a form field and
     * not like a search bar: you typed, guessed, pressed Enter, and waited for
     * a page load to find out whether you had guessed right. Suggestions turn
     * that into a lookup - three letters and the bill is on screen.
     *
     * Matching is deliberately NOT written here. Invoices go through
     * Invoice::scopeSearch and customers through Customer::scopeSearch, the
     * same scopes the list pages filter with, so the dropdown can never offer
     * a row that pressing Enter then fails to find.
     */
    public function suggest(Request $request): JsonResponse
    {
        // 80 chars is longer than any invoice number or customer name in the
        // data; past that it is a paste accident, and a LIKE on it is wasted.
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $scope = $request->query('scope') === 'customers' ? 'customers' : 'invoices';

        if ($q === '') {
            return response()->json(['q' => '', 'groups' => []]);
        }

        $company = $request->user()->ensureCompany();
        $groups = [];

        if ($scope === 'invoices') {
            $invoices = $company->invoices()->with('customer')->search($q)
                ->orderByDesc('invoice_date')->orderByDesc('id')
                ->limit(6)->get();

            if ($invoices->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Invoices',
                    'items' => $invoices->map(fn ($inv) => [
                        'title' => $inv->displayNumber(),
                        'subtitle' => trim(($inv->customer->name ?? 'No customer')
                            . ' · ' . $inv->invoice_date?->format('d M Y')),
                        'meta' => '₹' . number_format((float) $inv->grand_total, 2),
                        'url' => route('invoices.show', $inv),
                        'badge' => $this->statusLabel($inv),
                        'tone' => $this->statusTone($inv),
                    ])->all(),
                ];
            }
        }

        // Customers appear under both scopes. On the invoice list they are the
        // faster route to "everything I have billed this person", which is what
        // someone typing a name usually wants - not one particular bill.
        $customers = $company->customers()->withCount('invoices')->search($q)
            ->orderBy('name')->limit(5)->get();

        if ($customers->isNotEmpty()) {
            $groups[] = [
                'label' => 'Customers',
                'items' => $customers->map(fn ($c) => [
                    'title' => $c->name,
                    'subtitle' => collect([$c->phone, $c->gstin, $c->email])
                        ->filter()->take(2)->join(' · ') ?: 'No contact details saved',
                    'meta' => $c->invoices_count === 1 ? '1 invoice' : $c->invoices_count . ' invoices',
                    'url' => $scope === 'customers'
                        ? route('customers.ledger', $c)
                        : route('invoices.index', ['search' => $c->name]),
                    'badge' => null,
                    'tone' => null,
                ])->all(),
            ];
        }

        return response()->json(['q' => $q, 'groups' => $groups]);
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
