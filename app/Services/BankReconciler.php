<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PurchasePayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds what a bank line is in the books.
 *
 *   money in  -> a receipt recorded against an invoice (Payment)
 *   money out -> a payment to a vendor (PurchasePayment) or an expense paid
 *                from the bank (not cash, not one mirrored from a bill)
 *
 * A candidate has the same amount (to the paisa) and a date within a few
 * days of the bank date (cheques clear late, UPI posts next morning). The
 * closest date wins; a reference number found in the bank narration wins
 * outright. Anything already matched to another line is not offered again.
 */
class BankReconciler
{
    public const DAYS = 5;

    /** @return Collection<int, array{model: Model, label: string, score: float}> */
    public function candidates(BankStatementLine $line): Collection
    {
        $company = Company::findOrFail($line->company_id);
        $amount = $line->amount();
        $from = $line->txn_date->copy()->subDays(self::DAYS)->toDateString();
        $to = $line->txn_date->copy()->addDays(self::DAYS)->toDateString();
        $taken = BankStatementLine::where('company_id', $line->company_id)->where('status', 'matched')
            ->where('id', '!=', $line->id)->get(['matched_type', 'matched_id'])
            ->map(fn ($l) => $l->matched_type . '#' . $l->matched_id)->flip();

        $found = collect();
        if ($line->isCredit()) {
            // A receipt reaches the bank net of any TDS the customer kept back
            // (Payment::netReceived()); one without TDS, in full. A credit
            // equal to a TDS receipt's gross is some other money.
            $payments = Payment::where('company_id', $company->id)->whereBetween('received_at', [$from, $to])
                ->whereRaw('ABS((amount - COALESCE(tds_amount, 0)) - ?) < 0.005', [round($amount, 2)])
                ->with('invoice.customer')->get();
            foreach ($payments as $p) {
                $found->push(['model' => $p, 'date' => $p->received_at, 'ref' => $p->reference_number, 'label' => self::label($p)]);
            }
        } else {
            // A vendor paid in cash never passes through the bank.
            $vendorPayments = PurchasePayment::where('company_id', $company->id)->whereBetween('paid_on', [$from, $to])
                ->where(fn ($q) => $q->whereNull('method')->orWhere('method', '!=', 'cash'))
                ->whereRaw('ABS(amount - ?) < 0.005', [round($amount, 2)])->with('purchaseBill.vendor')->get();
            foreach ($vendorPayments as $p) {
                $found->push(['model' => $p, 'date' => $p->paid_on, 'ref' => $p->reference_number, 'label' => self::label($p)]);
            }
            // An expense paid from the bank. A cash memo's row holds taxable +
            // GST before the round-off, while the seller was paid the memo's
            // grand total, so the memo is matched on that.
            $expenses = Expense::where('expenses.company_id', $company->id)->whereNull('expenses.purchase_bill_id')
                ->where(fn ($q) => $q->whereNull('expenses.payment_method')->orWhere('expenses.payment_method', '!=', 'cash'))
                ->whereBetween('expenses.entry_date', [$from, $to])
                ->leftJoin('cash_memos', 'cash_memos.id', '=', 'expenses.cash_memo_id')
                ->whereRaw('ABS(COALESCE(cash_memos.grand_total, expenses.amount + expenses.gst_amount) - ?) < 0.005', [round($amount, 2)])
                ->select('expenses.*')->get();
            foreach ($expenses as $e) {
                $found->push(['model' => $e, 'date' => $e->entry_date, 'ref' => $e->reference_number, 'label' => self::label($e)]);
            }
        }

        $narration = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $line->description . (string) $line->reference));

        return $found
            ->reject(fn ($c) => $taken->has($c['model']->getMorphClass() . '#' . $c['model']->getKey()))
            ->map(function ($c) use ($line, $narration) {
                $ref = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c['ref']));
                $refHit = strlen($ref) >= 4 && str_contains($narration, $ref);
                $days = abs(Carbon::parse($c['date'])->diffInDays($line->txn_date));

                return ['model' => $c['model'], 'label' => $c['label'], 'score' => ($refHit ? 100 : 0) + (self::DAYS + 1 - $days)];
            })
            ->sortByDesc('score')->values();
    }

    /**
     * What a receipt, vendor payment or expense is, in the words the bank
     * screen uses: the kind, who it is with, which document, and its date,
     * amount (to the paisa, as matching is) and reference. Two payments to
     * the same vendor for the same bill differ by date or reference.
     *
     * @return array{kind: string, party: ?string, document: ?string, date: ?string, amount: float, ref: ?string, url: ?string}
     */
    public static function describe(?Model $model): ?array
    {
        return match (true) {
            $model instanceof Payment => [
                'kind' => 'Receipt ' . $model->receipt_number,
                'party' => $model->invoice?->billedCustomer()?->name,
                'document' => $model->invoice ? 'Invoice ' . $model->invoice->displayNumber() : null,
                'date' => $model->received_at?->format('d M Y'),
                'amount' => (float) $model->amount,
                'ref' => $model->reference_number,
                'url' => $model->invoice ? route('invoices.show', $model->invoice) : null,
            ],
            $model instanceof PurchasePayment => [
                'kind' => 'Payment made',
                'party' => $model->purchaseBill?->vendor?->name,
                'document' => $model->purchaseBill ? 'Purchase invoice ' . $model->purchaseBill->bill_number : null,
                'date' => $model->paid_on?->format('d M Y'),
                'amount' => (float) $model->amount,
                'ref' => $model->reference_number,
                'url' => $model->purchaseBill ? route('purchases.show', $model->purchaseBill) : null,
            ],
            $model instanceof Expense => [
                'kind' => 'Expense',
                'party' => $model->vendor_name ?: null,
                'document' => $model->categoryLabel() . ($model->description ? ': ' . $model->description : ''),
                'date' => $model->entry_date?->format('d M Y'),
                // A cash memo's seller was paid its grand total, round-off included.
                'amount' => $model->cash_memo_id && $model->cashMemo
                    ? (float) $model->cashMemo->grand_total
                    : (float) $model->amount + (float) $model->gst_amount,
                'ref' => $model->reference_number,
                'url' => route('finance.expenses.edit', $model),
            ],
            default => null,
        };
    }

    /** One line for the "match to" list: kind, party, document, date, amount, reference. */
    public static function label(Model $model): string
    {
        $d = self::describe($model);

        return collect([$d['kind'], $d['party'], $d['document'], $d['date'], '₹' . inr($d['amount']), $d['ref'] ? 'Ref ' . $d['ref'] : null])
            ->filter()->implode(' · ');
    }

    public function match(BankStatementLine $line, Model $model): void
    {
        $line->update([
            'status' => 'matched', 'matched_type' => $model->getMorphClass(), 'matched_id' => $model->getKey(), 'matched_at' => now(),
        ]);
    }

    /**
     * A line matched to a receipt, payment or expense that has since been
     * deleted goes back to "to review" (and can be matched again).
     */
    public function releaseOrphans(Company $company): int
    {
        return BankStatementLine::where('company_id', $company->id)->where('status', 'matched')
            ->whereDoesntHaveMorph('matched', [Payment::class, PurchasePayment::class, Expense::class])
            ->update(['status' => 'unmatched', 'matched_type' => null, 'matched_id' => null, 'matched_at' => null]);
    }

    public function unmatch(BankStatementLine $line): void
    {
        $line->update(['status' => 'unmatched', 'matched_type' => null, 'matched_id' => null, 'matched_at' => null]);
    }

    /**
     * Match every unmatched line that has exactly one candidate, or one that
     * is clearly ahead (its reference number is in the narration).
     */
    public function autoMatch(Company $company, ?int $statementId = null): int
    {
        $this->releaseOrphans($company);
        $count = 0;
        $lines = BankStatementLine::where('company_id', $company->id)->where('status', 'unmatched')
            ->when($statementId, fn ($q) => $q->where('bank_statement_id', $statementId))
            ->orderBy('txn_date')->get();
        foreach ($lines as $line) {
            $c = $this->candidates($line);
            if ($c->count() === 1 || ($c->count() > 1 && $c[0]['score'] >= 100 && $c[1]['score'] < 100)) {
                $this->match($line, $c[0]['model']);
                $count++;
            }
        }

        return $count;
    }
}
