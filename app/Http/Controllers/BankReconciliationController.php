<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Services\BankReconciler;
use App\Services\BankStatementReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bank reconciliation: upload the statement from net banking, and tick off
 * each line against a receipt, a vendor payment or an expense.
 */
class BankReconciliationController extends Controller
{
    private const MATCHABLE = [Payment::class, PurchasePayment::class, Expense::class];

    public function index(Request $request, BankReconciler $reconciler): View
    {
        $company = $request->user()->ensureCompany();
        $reconciler->releaseOrphans($company);
        $status = in_array($request->query('status'), ['unmatched', 'matched', 'ignored', 'all'], true) ? $request->query('status') : 'unmatched';
        $account = (string) $request->query('account', '');

        $base = BankStatementLine::where('company_id', $company->id)
            ->when($account !== '', fn ($q) => $q->whereHas('statement', fn ($s) => $s->where('account_name', $account)));
        $lines = (clone $base)->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('matched')->orderByDesc('txn_date')->orderByDesc('id')->paginate(50)->withQueryString();

        // Suggestions for the lines on screen that still need a match, and
        // open invoices for an unmatched receipt of exactly their balance.
        $suggestions = [];
        $invoiceHints = [];
        foreach ($lines as $line) {
            if ($line->status !== 'unmatched') {
                continue;
            }
            $suggestions[$line->id] = $reconciler->candidates($line)->take(5);
            if ($line->isCredit() && $suggestions[$line->id]->isEmpty()) {
                $invoiceHints[$line->id] = $company->invoices()->whereIn('status', ['final', 'partially_paid'])
                    ->whereRaw('ABS(balance - ?) < 0.005', [round($line->amount(), 2)])->with('customer')->limit(3)->get();
            }
        }

        $sum = fn (string $st, string $col) => round((float) (clone $base)->where('status', $st)->sum($col), 2);

        return view('bank.index', [
            'company' => $company,
            'lines' => $lines,
            'status' => $status,
            'account' => $account,
            'accounts' => BankStatement::where('company_id', $company->id)->distinct()->orderBy('account_name')->pluck('account_name'),
            'statements' => BankStatement::where('company_id', $company->id)->latest()->limit(10)->get(),
            'suggestions' => $suggestions,
            'invoiceHints' => $invoiceHints,
            'counts' => [
                'unmatched' => (clone $base)->where('status', 'unmatched')->count(),
                'matched' => (clone $base)->where('status', 'matched')->count(),
                'ignored' => (clone $base)->where('status', 'ignored')->count(),
            ],
            'unmatchedIn' => $sum('unmatched', 'credit'),
            'unmatchedOut' => $sum('unmatched', 'debit'),
        ]);
    }

    public function upload(Request $request, BankStatementReader $reader, BankReconciler $reconciler): RedirectResponse
    {
        $company = $request->user()->ensureCompany();
        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:80'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $parsed = $reader->read($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['file' => 'This statement could not be read. Download it again from net banking as Excel (.xlsx) or CSV.']);
        }

        $account = trim($data['account_name']);
        [$statement, $added, $skipped] = DB::transaction(function () use ($company, $request, $account, $parsed) {
            $statement = BankStatement::create([
                'company_id' => $company->id, 'user_id' => $request->user()->id, 'account_name' => $account,
                'file_name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 160),
                'period_from' => min(array_column($parsed, 'date')), 'period_to' => max(array_column($parsed, 'date')),
            ]);
            $added = $skipped = 0;
            $occurrence = [];
            foreach ($parsed as $i => $l) {
                // Same account, date, amounts, narration and running balance =
                // the same line, e.g. from overlapping statements. Two genuinely
                // identical lines in one statement (two ₹20 UPI payments to the
                // same shop) are both kept: the nth copy is its own line, and
                // uploading the file again still finds every one of them.
                $base = implode('|', [mb_strtolower($account), $l['date'], $l['debit'], $l['credit'], $l['description'], $l['reference'], $l['balance']]);
                $occurrence[$base] = ($occurrence[$base] ?? 0) + 1;
                $hash = sha1($base . '|#' . $occurrence[$base]);
                if (BankStatementLine::where('company_id', $company->id)->where('line_hash', $hash)->exists()) {
                    $skipped++;

                    continue;
                }
                BankStatementLine::create([
                    'company_id' => $company->id, 'bank_statement_id' => $statement->id, 'txn_date' => $l['date'],
                    'description' => $l['description'], 'reference' => $l['reference'], 'debit' => $l['debit'], 'credit' => $l['credit'],
                    'balance' => $l['balance'], 'line_hash' => $hash,
                ]);
                $added++;
            }
            if ($added === 0) {
                // The same file again: nothing new, so no empty statement in "Recent".
                $statement->delete();

                return [null, 0, $skipped];
            }
            $statement->update(['lines_count' => $added]);

            return [$statement, $added, $skipped];
        });

        $plural = fn (int $n, string $word) => $n . ' ' . \Illuminate\Support\Str::plural($word, $n);
        if ($statement) {
            $auto = $reconciler->autoMatch($company, $statement->id);
            AuditLog::record('bank.statement_uploaded', "Bank statement ({$account}) uploaded · {$added} lines, {$auto} matched automatically", null);
            $msg = $plural($added, 'new line') . ' read' . ($skipped ? " ({$skipped} uploaded before were skipped)" : '') . ". {$auto} matched automatically.";
        } else {
            $msg = 'Nothing new in this file. All ' . $plural($skipped, 'line') . ' were uploaded before.';
        }
        if ($reader->unreadable > 0) {
            $msg .= ' ' . $plural($reader->unreadable, 'line') . ' left out because the date could not be read. Check ' . ($reader->unreadable === 1 ? 'it' : 'them') . ' in the file.';
        }
        // Everything still open on this account, not just from this file.
        $left = BankStatementLine::where('company_id', $company->id)->where('status', 'unmatched')
            ->whereHas('statement', fn ($s) => $s->where('account_name', $account))->count();

        return redirect()->route('bank.index', ['account' => $account])
            ->with('status', $msg . ($left > 0 ? ' ' . $plural($left, 'line') . ' to review below.' : ' All lines are reconciled.'));
    }

    public function match(Request $request, BankStatementLine $line, BankReconciler $reconciler): RedirectResponse
    {
        $this->authorizeLine($request, $line);
        $data = $request->validate(['candidate' => ['required', 'string', 'max:120']]);
        [$type, $id] = array_pad(explode('#', $data['candidate'], 2), 2, null);
        // Only something the reconciler itself offers for this line can be matched.
        $model = $reconciler->candidates($line)->first(fn ($c) => $c['model']->getMorphClass() === $type && (string) $c['model']->getKey() === (string) $id)['model'] ?? null;
        abort_unless($model, 422, 'That entry is not a match for this bank line.');
        $reconciler->match($line, $model);

        return back()->with('status', 'Matched.');
    }

    public function unmatch(Request $request, BankStatementLine $line, BankReconciler $reconciler): RedirectResponse
    {
        $this->authorizeLine($request, $line);
        $reconciler->unmatch($line);

        return back()->with('status', 'Match removed. The line is back in the list to review.');
    }

    public function ignore(Request $request, BankStatementLine $line): RedirectResponse
    {
        $this->authorizeLine($request, $line);
        $line->update(['status' => 'ignored', 'matched_type' => null, 'matched_id' => null, 'matched_at' => now()]);

        return back()->with('status', 'Line marked as nothing to match.');
    }

    public function autoMatch(Request $request, BankReconciler $reconciler): RedirectResponse
    {
        $count = $reconciler->autoMatch($request->user()->ensureCompany());

        return back()->with('status', $count ? "{$count} more lines matched." : 'No new matches found. Enter the missing receipts or expenses, then try again.');
    }

    public function destroy(Request $request, BankStatement $statement): RedirectResponse
    {
        abort_unless($statement->company_id === $request->user()->ensureCompany()->id, 403);
        $statement->delete();

        return redirect()->route('bank.index')->with('status', 'Statement removed. Receipts and payments are not affected.');
    }

    private function authorizeLine(Request $request, BankStatementLine $line): void
    {
        abort_unless($line->company_id === $request->user()->ensureCompany()->id, 403);
    }
}
