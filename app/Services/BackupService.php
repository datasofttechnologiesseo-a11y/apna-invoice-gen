<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Gstr2bEntry;
use App\Models\QuotationItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Generates a ZIP of a user's business data as CSV files: everything needed
 * to rebuild the books and the GST returns outside the app (invoices with
 * place of supply and supply type, payments with TDS, credit notes,
 * purchases, stock, bank matches and GSTR-2B). Used both for manual downloads and for
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
                    ['bank_account_name', $company->bank_account_name],
                    ['bank_account_number', $company->bank_account_number],
                    ['bank_account_type', $company->bank_account_type],
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
                    ['id', 'name', 'sku', 'kind', 'hsn_sac', 'unit', 'rate', 'gst_rate', 'is_active', 'description',
                     'purchase_rate', 'track_stock', 'opening_stock', 'low_stock_level'],
                    $products->map(fn ($p) => [
                        $p->id, $p->name, $p->sku, $p->kind, $p->hsn_sac, $p->unit,
                        $p->rate, $p->gst_rate, $p->is_active ? 1 : 0, $p->description,
                        $p->purchase_rate, $p->track_stock ? 1 : 0, $p->opening_stock, $p->low_stock_level,
                    ])->all()
                )
            );

            // Invoices — eager-load items so the inner loop isn't N+1.
            $invoices = $company->invoices()->with(['customer', 'items', 'placeOfSupply'])->get();
            $zip->addFromString(
                "{$folder}/invoices.csv",
                $this->csv(
                    ['id', 'invoice_number', 'status', 'customer', 'invoice_date', 'due_date', 'currency',
                     'subtotal', 'cgst', 'sgst', 'igst', 'total_tax', 'grand_total', 'paid_amount', 'balance',
                     'finalized_at', 'cancelled_at', 'cancellation_reason',
                     // Added later, at the end so sheets built on older backups still line up.
                     'customer_gstin', 'place_of_supply', 'is_interstate', 'reverse_charge', 'supply_type',
                     'export_port_code', 'shipping_bill_number', 'shipping_bill_date',
                     'exchange_rate', 'round_off', 'credited_amount', 'eway_bill_number', 'notes', 'terms'],
                    $invoices->map(fn ($i) => [
                        $i->id, $i->invoice_number, $i->status, $i->customer?->name,
                        $i->invoice_date?->toDateString(), $i->due_date?->toDateString(),
                        $i->currency, $i->subtotal, $i->total_cgst, $i->total_sgst, $i->total_igst,
                        $i->total_tax, $i->grand_total, $i->paid_amount, $i->balance,
                        $i->finalized_at?->toDateTimeString(), $i->cancelled_at?->toDateTimeString(),
                        $i->cancellation_reason,
                        $i->customer?->gstin,
                        $i->placeOfSupply ? $i->placeOfSupply->gst_code . ' ' . $i->placeOfSupply->name : null,
                        $i->is_interstate ? 1 : 0, $i->reverse_charge ? 1 : 0, $i->supply_type,
                        $i->export_port_code, $i->shipping_bill_number, $this->day($i->shipping_bill_date),
                        $i->exchange_rate, $i->round_off, $i->credited_amount, $i->eway_bill_number, $i->notes, $i->terms,
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
                        $item->total, $item->discount, $item->product_id,
                    ];
                }
            }
            $zip->addFromString(
                "{$folder}/invoice_items.csv",
                $this->csv(
                    ['invoice_number', 'description', 'hsn_sac', 'quantity', 'unit', 'rate',
                     'amount', 'gst_rate', 'cgst', 'sgst', 'igst', 'total', 'discount', 'product_id'],
                    $itemRows
                )
            );

            // Payments
            $payments = $company->payments()->with('invoice')->get();
            $zip->addFromString(
                "{$folder}/payments.csv",
                $this->csv(
                    ['receipt_number', 'invoice_number', 'received_at', 'amount', 'method', 'reference', 'notes',
                     'tds_amount', 'tds_section', 'tds_rate'],
                    $payments->map(fn ($p) => [
                        $p->receipt_number,
                        $p->invoice->invoice_number ?? 'DRAFT-' . $p->invoice_id,
                        $p->received_at?->toDateString(),
                        $p->amount, $p->method, $p->reference_number, $p->notes,
                        $p->tds_amount, $p->tds_section, $p->tds_rate,
                    ])->all()
                )
            );

            // Expenses (the columns at the end feed GSTR-3B input credit)
            $expenses = $company->expenses()->get();
            $zip->addFromString(
                "{$folder}/expenses.csv",
                $this->csv(
                    ['entry_date', 'category', 'vendor_name', 'description', 'amount', 'gst_amount', 'payment_method', 'reference_number',
                     'is_interstate', 'itc_eligible', 'from_purchase_invoice_id', 'from_cash_memo_id'],
                    $expenses->map(fn ($e) => [
                        $e->entry_date?->toDateString(), $e->category, $e->vendor_name,
                        $e->description, $e->amount, $e->gst_amount,
                        $e->payment_method, $e->reference_number,
                        $e->is_interstate ? 1 : 0, $e->itc_eligible ? 1 : 0, $e->purchase_bill_id, $e->cash_memo_id,
                    ])->all()
                )
            );

            // Credit notes
            $notes = $company->creditNotes()->with('invoice:id,invoice_number')->get();
            $zip->addFromString(
                "{$folder}/credit_notes.csv",
                $this->csv(
                    ['credit_note_number', 'credit_note_date', 'invoice_number', 'amount', 'taxable_value', 'cgst', 'sgst', 'igst', 'reason', 'notes'],
                    $notes->map(fn ($n) => [
                        $n->credit_note_number, $n->credit_note_date?->toDateString(), $n->invoice?->invoice_number,
                        $n->amount, $n->taxable_value, $n->total_cgst, $n->total_sgst, $n->total_igst, $n->reason, $n->notes,
                    ])->all()
                )
            );

            // Quotations
            $quotes = $company->quotations()->with('customer:id,name')->get();
            $zip->addFromString(
                "{$folder}/quotations.csv",
                $this->csv(
                    ['id', 'quote_number', 'status', 'customer', 'quote_date', 'valid_until', 'subtotal', 'cgst', 'sgst', 'igst', 'total_tax', 'grand_total'],
                    $quotes->map(fn ($q) => [
                        $q->id, $q->quote_number, $q->status, $q->customer?->name,
                        optional($q->quote_date)->toDateString(), optional($q->valid_until)->toDateString(),
                        $q->subtotal, $q->total_cgst, $q->total_sgst, $q->total_igst, $q->total_tax, $q->grand_total,
                    ])->all()
                )
            );

            // Vendors
            $vendors = $company->vendors()->with('state')->get();
            $zip->addFromString(
                "{$folder}/vendors.csv",
                $this->csv(
                    ['id', 'name', 'gstin', 'phone', 'email', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'],
                    $vendors->map(fn ($v) => [
                        $v->id, $v->name, $v->gstin, $v->phone, $v->email, $v->address_line1, $v->address_line2,
                        $v->city, $v->state?->name, $v->postal_code, $v->country,
                    ])->all()
                )
            );

            // Purchase invoices, their lines and the payments made on them
            $bills = $company->purchaseBills()->with(['vendor:id,name,gstin', 'items', 'payments'])->get();
            $zip->addFromString(
                "{$folder}/purchase_invoices.csv",
                $this->csv(
                    ['id', 'bill_number', 'bill_date', 'due_date', 'vendor', 'vendor_gstin', 'reverse_charge', 'itc_eligible', 'is_interstate',
                     'subtotal', 'cgst', 'sgst', 'igst', 'total_tax', 'grand_total', 'paid_amount', 'balance', 'status'],
                    $bills->map(fn ($b) => [
                        $b->id, $b->bill_number, optional($b->bill_date)->toDateString(), optional($b->due_date)->toDateString(),
                        $b->vendor?->name, $b->vendor?->gstin,
                        $b->reverse_charge ? 1 : 0, $b->itc_eligible ? 1 : 0, $b->is_interstate ? 1 : 0,
                        $b->subtotal, $b->total_cgst, $b->total_sgst, $b->total_igst, $b->total_tax, $b->grand_total,
                        $b->paid_amount, $b->balance, $b->status,
                    ])->all()
                )
            );
            $billItems = [];
            $billPayments = [];
            foreach ($bills as $b) {
                foreach ($b->items as $it) {
                    $billItems[] = [$b->bill_number, $it->description, $it->hsn_sac, $it->quantity, $it->unit, $it->rate,
                        $it->amount, $it->gst_rate, $it->cgst_amount, $it->sgst_amount, $it->igst_amount, $it->total];
                }
                foreach ($b->payments as $pay) {
                    $billPayments[] = [$b->bill_number, optional($pay->paid_on)->toDateString(), $pay->amount, $pay->method,
                        $pay->reference_number, $pay->notes];
                }
            }
            $zip->addFromString("{$folder}/purchase_invoice_items.csv", $this->csv(
                ['bill_number', 'description', 'hsn_sac', 'quantity', 'unit', 'rate', 'amount', 'gst_rate', 'cgst', 'sgst', 'igst', 'total'],
                $billItems
            ));
            $zip->addFromString("{$folder}/vendor_payments.csv", $this->csv(
                ['bill_number', 'paid_on', 'amount', 'method', 'reference', 'notes'],
                $billPayments
            ));

            // Cash memos
            $memos = $company->cashMemos()->get();
            $zip->addFromString(
                "{$folder}/cash_memos.csv",
                $this->csv(
                    ['memo_number', 'memo_date', 'seller_name', 'seller_gstin', 'subtotal', 'discount', 'taxable_value',
                     'cgst', 'sgst', 'igst', 'round_off', 'grand_total', 'payment_mode', 'itc_eligible'],
                    $memos->map(fn ($m) => [
                        $m->memo_number, optional($m->memo_date)->toDateString(), $m->seller_name, $m->seller_gstin,
                        $m->subtotal, $m->discount, $m->taxable_value, $m->total_cgst, $m->total_sgst, $m->total_igst,
                        $m->round_off, $m->grand_total, $m->payment_mode, $m->itc_eligible ? 1 : 0,
                    ])->all()
                )
            );

            // Quotation lines (keyed by quote number, or draft id)
            $quoteNumbers = $quotes->mapWithKeys(fn ($q) => [$q->id => $q->quote_number ?: 'DRAFT-' . $q->id]);
            $quoteLines = QuotationItem::whereIn('quotation_id', $quotes->pluck('id'))->orderBy('quotation_id')->orderBy('id')->get();
            $zip->addFromString("{$folder}/quotation_items.csv", $this->csv(
                ['quote_number', 'description', 'hsn_sac', 'quantity', 'unit', 'rate', 'discount', 'amount', 'gst_rate', 'cgst', 'sgst', 'igst', 'product_id'],
                $quoteLines->map(fn ($l) => [
                    $quoteNumbers[$l->quotation_id] ?? null, $l->description, $l->hsn_sac, $l->quantity, $l->unit, $l->rate,
                    $l->discount, $l->amount, $l->gst_rate, $l->cgst_amount, $l->sgst_amount, $l->igst_amount, $l->product_id,
                ])->all()
            ));

            // Stock movements: with opening stock in products.csv these rebuild stock on any date.
            $itemNames = $products->pluck('name', 'id');
            $moves = StockMovement::where('company_id', $company->id)->orderBy('moved_on')->orderBy('id')->get();
            $zip->addFromString("{$folder}/stock_movements.csv", $this->csv(
                ['moved_on', 'product_id', 'item', 'quantity', 'kind', 'source', 'source_id', 'note'],
                $moves->map(fn ($m) => [
                    $this->day($m->moved_on), $m->product_id, $itemNames[$m->product_id] ?? null,
                    $m->quantity, $m->kind, $m->source_type ? class_basename($m->source_type) : null, $m->source_id, $m->note,
                ])->all()
            ));

            // Bank statements and their lines, with what each line was matched to
            $statements = BankStatement::where('company_id', $company->id)->orderBy('id')->get();
            $zip->addFromString("{$folder}/bank_statements.csv", $this->csv(
                ['id', 'account_name', 'file_name', 'period_from', 'period_to', 'lines_count'],
                $statements->map(fn ($st) => [
                    $st->id, $st->account_name, $st->file_name, $this->day($st->period_from), $this->day($st->period_to), $st->lines_count,
                ])->all()
            ));
            $bankLines = BankStatementLine::where('company_id', $company->id)->orderBy('bank_statement_id')->orderBy('id')->get();
            $zip->addFromString("{$folder}/bank_statement_lines.csv", $this->csv(
                ['bank_statement_id', 'txn_date', 'description', 'reference', 'debit', 'credit', 'balance', 'status', 'matched_to', 'matched_id'],
                $bankLines->map(fn ($l) => [
                    $l->bank_statement_id, $this->day($l->txn_date), $l->description, $l->reference,
                    $l->debit, $l->credit, $l->balance, $l->status,
                    $l->matched_type ? class_basename($l->matched_type) : null, $l->matched_id,
                ])->all()
            ));

            // GSTR-2B documents uploaded from the portal
            $twoB = Gstr2bEntry::where('company_id', $company->id)->orderBy('period')->orderBy('id')->get();
            $zip->addFromString("{$folder}/gstr2b_entries.csv", $this->csv(
                ['period', 'kind', 'supplier_gstin', 'supplier_name', 'document_number', 'document_date', 'document_value',
                 'taxable_value', 'igst', 'cgst', 'sgst', 'cess', 'reverse_charge', 'itc_available', 'place_of_supply'],
                $twoB->map(fn ($e) => [
                    $e->period, $e->kind, $e->supplier_gstin, $e->supplier_name, $e->document_number, $this->day($e->document_date),
                    $e->document_value, $e->taxable_value, $e->igst, $e->cgst, $e->sgst, $e->cess,
                    $e->reverse_charge ? 1 : 0, $e->itc_available ? 1 : 0, $e->place_of_supply,
                ])->all()
            ));
        }

        $zip->close();
        return $tmp;
    }

    /** Generate a CSV string from rows with a header. */
    private function csv(array $header, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        csv_put($fh, $header);
        foreach ($rows as $row) {
            csv_put($fh, array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $row));
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        // UTF-8 byte order mark: Excel then shows ₹, Hindi and other Indian
        // scripts correctly instead of garbled characters.
        return "\xEF\xBB\xBF" . $out;
    }

    /** A date column as Y-m-d, whether the model casts it or not. */
    private function day(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value ? substr((string) $value, 0, 10) : null;
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
        - credit_notes.csv    Credit notes against invoices
        - quotations.csv      Quotations
        - expenses.csv        Expense log (with GST input credit flags)
        - vendors.csv         Vendor master
        - purchase_invoices.csv       Purchase invoices
        - purchase_invoice_items.csv  Purchase lines (keyed by bill number)
        - vendor_payments.csv         Payments made to vendors
        - cash_memos.csv      Cash purchase memos
        - quotation_items.csv Quotation lines (keyed by quote number)
        - stock_movements.csv Every stock in and out (opening stock is in products.csv)
        - bank_statements.csv, bank_statement_lines.csv  Uploaded bank statements and their matches
        - gstr2b_entries.csv  GSTR-2B documents uploaded from the GST portal

        CSV files open directly in Excel / Google Sheets / any spreadsheet tool.

        Note: this backup is a data export — it does NOT include PDFs. You can
        re-download any invoice or receipt PDF from {$app} at any time.

        Keep this ZIP in a safe place (e.g. encrypted cloud drive). Do not share
        it publicly — it contains customer GSTINs and payment history.
        TXT;
    }
}
