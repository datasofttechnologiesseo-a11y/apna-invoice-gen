<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Generates a ZIP of a user's business data — invoices, customers, products,
 * payments, expenses — as CSV files. Used both for manual downloads and for
 * the scheduled weekly email backup.
 *
 * Everything stays in RAM + a single temp file — no persistent storage, and
 * the temp file is cleaned up by the caller after streaming.
 */
class BackupService
{
    /**
     * Build the backup zip. Returns the absolute path to a temp file.
     * The caller is responsible for reading/streaming and then unlinking it.
     */
    public function buildZipForUser(User $user): string
    {
        $user->loadMissing(['companies']);
        $tmp = tempnam(sys_get_temp_dir(), 'apna-backup-') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create backup zip file');
        }

        // README in the zip root so the user understands the structure.
        $zip->addFromString('README.txt', $this->readme($user));

        // Per-company data. Most users have one company; a few have several.
        foreach ($user->companies as $company) {
            $folder = Str::slug($company->name) . '-' . $company->id;

            $zip->addFromString(
                "{$folder}/company.csv",
                $this->csv(['field', 'value'], [
                    ['name', $company->name],
                    ['gstin', $company->gstin],
                    ['pan', $company->pan],
                    ['address', trim(($company->address_line1 ?? '') . ' ' . ($company->address_line2 ?? ''))],
                    ['city', $company->city],
                    ['state', $company->state?->name],
                    ['postal_code', $company->postal_code],
                    ['country', $company->country],
                    ['phone', $company->phone],
                    ['email', $company->email],
                    ['website', $company->website],
                    ['bank_name', $company->bank_name],
                    ['bank_account_number', $company->bank_account_number],
                    ['bank_ifsc', $company->bank_ifsc],
                    ['upi_id', $company->upi_id],
                    ['invoice_prefix', $company->invoice_prefix],
                    ['invoice_counter', $company->invoice_counter],
                ])
            );

            // Customers
            $customers = $company->customers()->with('state')->get();
            $zip->addFromString(
                "{$folder}/customers.csv",
                $this->csv(
                    ['id', 'name', 'gstin', 'phone', 'email', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country', 'created_at'],
                    $customers->map(fn ($c) => [
                        $c->id, $c->name, $c->gstin, $c->phone, $c->email,
                        $c->address_line1, $c->address_line2, $c->city,
                        $c->state?->name, $c->postal_code, $c->country,
                        $c->created_at?->toDateTimeString(),
                    ])->all()
                )
            );

            // Products
            $products = $company->products()->get();
            $zip->addFromString(
                "{$folder}/products.csv",
                $this->csv(
                    ['id', 'name', 'sku', 'kind', 'hsn_sac', 'unit', 'rate', 'gst_rate', 'is_active', 'description'],
                    $products->map(fn ($p) => [
                        $p->id, $p->name, $p->sku, $p->kind, $p->hsn_sac, $p->unit,
                        $p->rate, $p->gst_rate, $p->is_active ? 1 : 0, $p->description,
                    ])->all()
                )
            );

            // Invoices — eager-load items so the inner loop isn't N+1.
            $invoices = $company->invoices()->with(['customer', 'items'])->get();
            $zip->addFromString(
                "{$folder}/invoices.csv",
                $this->csv(
                    ['id', 'invoice_number', 'status', 'customer', 'invoice_date', 'due_date', 'currency',
                     'subtotal', 'cgst', 'sgst', 'igst', 'total_tax', 'grand_total', 'paid_amount', 'balance',
                     'finalized_at', 'cancelled_at', 'cancellation_reason'],
                    $invoices->map(fn ($i) => [
                        $i->id, $i->invoice_number, $i->status, $i->customer?->name,
                        $i->invoice_date?->toDateString(), $i->due_date?->toDateString(),
                        $i->currency, $i->subtotal, $i->total_cgst, $i->total_sgst, $i->total_igst,
                        $i->total_tax, $i->grand_total, $i->paid_amount, $i->balance,
                        $i->finalized_at?->toDateTimeString(), $i->cancelled_at?->toDateTimeString(),
                        $i->cancellation_reason,
                    ])->all()
                )
            );

            // Invoice line items (normalized). items was eager-loaded above.
            $itemRows = [];
            foreach ($invoices as $inv) {
                foreach ($inv->items as $item) {
                    $itemRows[] = [
                        $inv->invoice_number ?: 'DRAFT-' . $inv->id,
                        $item->description, $item->hsn_sac,
                        $item->quantity, $item->unit, $item->rate,
                        $item->amount, $item->gst_rate,
                        $item->cgst_amount, $item->sgst_amount, $item->igst_amount,
                        $item->total,
                    ];
                }
            }
            $zip->addFromString(
                "{$folder}/invoice_items.csv",
                $this->csv(
                    ['invoice_number', 'description', 'hsn_sac', 'quantity', 'unit', 'rate',
                     'amount', 'gst_rate', 'cgst', 'sgst', 'igst', 'total'],
                    $itemRows
                )
            );

            // Payments
            $payments = $company->payments()->with('invoice')->get();
            $zip->addFromString(
                "{$folder}/payments.csv",
                $this->csv(
                    ['receipt_number', 'invoice_number', 'received_at', 'amount', 'method', 'reference', 'notes'],
                    $payments->map(fn ($p) => [
                        $p->receipt_number,
                        $p->invoice->invoice_number ?? 'DRAFT-' . $p->invoice_id,
                        $p->received_at?->toDateString(),
                        $p->amount, $p->method, $p->reference_number, $p->notes,
                    ])->all()
                )
            );

            // Expenses
            $expenses = $company->expenses()->get();
            $zip->addFromString(
                "{$folder}/expenses.csv",
                $this->csv(
                    ['entry_date', 'category', 'vendor_name', 'description', 'amount', 'gst_amount', 'payment_method', 'reference_number'],
                    $expenses->map(fn ($e) => [
                        $e->entry_date?->toDateString(), $e->category, $e->vendor_name,
                        $e->description, $e->amount, $e->gst_amount,
                        $e->payment_method, $e->reference_number,
                    ])->all()
                )
            );
        }

        $zip->close();
        return $tmp;
    }

    /** Generate a CSV string from rows with a header. */
    private function csv(array $header, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header);
        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $row));
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    private function readme(User $user): string
    {
        $now = now()->format('d M Y, H:i');
        $app = config('app.name', 'Apna Invoice');
        return <<<TXT
        {$app} — Data backup
        ===========================

        Account: {$user->name} <{$user->email}>
        Generated: {$now}

        This ZIP contains CSV exports of your business data, organised by company:

        - company.csv         Business profile
        - customers.csv       Customer master
        - products.csv        Products / services
        - invoices.csv        Invoice headers
        - invoice_items.csv   Line items (keyed by invoice number)
        - payments.csv        Payment receipts
        - expenses.csv        Expense log

        CSV files open directly in Excel / Google Sheets / any spreadsheet tool.

        Note: this backup is a data export — it does NOT include PDFs. You can
        re-download any invoice or receipt PDF from {$app} at any time.

        Keep this ZIP in a safe place (e.g. encrypted cloud drive). Do not share
        it publicly — it contains customer GSTINs and payment history.
        TXT;
    }
}
