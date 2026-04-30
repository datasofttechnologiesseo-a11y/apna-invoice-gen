<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashMemo;
use App\Models\Expense;
use App\Models\State;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashMemoController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();

        $memos = $company->cashMemos()
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('memo_number', 'like', "%{$s}%")
                    ->orWhere('seller_name', 'like', "%{$s}%");
            }))
            ->when($request->from, fn ($q, $d) => $q->where('memo_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('memo_date', '<=', $d))
            ->orderByDesc('memo_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.cash-memos.index', compact('company', 'memos'));
    }

    /**
     * Bulk PDF — every cash memo in the filtered range as one CA-ready
     * statement (period summary + a single line per memo). Use this when
     * the user wants to send a month / quarter to their accountant.
     */
    public function exportPdf(Request $request): Response
    {
        $company = $request->user()->ensureCompany();
        [$from, $to, $label] = $this->resolveRange($request);

        $rows = $this->filteredQuery($company, $request, $from, $to)
            ->with('items')
            ->orderBy('memo_date')->orderBy('id')->get();

        $summary = [
            'count' => $rows->count(),
            'taxable' => (float) $rows->sum('taxable_value'),
            'cgst' => (float) $rows->sum('total_cgst'),
            'sgst' => (float) $rows->sum('total_sgst'),
            'igst' => (float) $rows->sum('total_igst'),
            'grand_total' => (float) $rows->sum('grand_total'),
        ];
        $summary['total_tax'] = $summary['cgst'] + $summary['sgst'] + $summary['igst'];
        $summaryWords = NumberToWords::indianRupees($summary['grand_total']);

        $byMode = $rows->groupBy('payment_mode')->map(fn ($g) => [
            'count' => $g->count(),
            'total' => (float) $g->sum('grand_total'),
        ]);

        $pdf = Pdf::loadView('finance.cash-memos.report', compact(
            'company', 'rows', 'summary', 'summaryWords', 'byMode', 'from', 'to', 'label'
        ))->setPaper('a4', 'portrait');

        $filename = 'cash-memos-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Bulk CSV — same data as the PDF, but flat for Excel / Tally import.
     * UTF-8 BOM so ₹ renders correctly on Indian Excel installs.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();
        [$from, $to, $label] = $this->resolveRange($request);

        $rows = $this->filteredQuery($company, $request, $from, $to)
            ->orderBy('memo_date')->orderBy('id')->get();

        $filename = 'cash-memos-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $company, $label, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            // Context block
            fputcsv($out, ["Cash Memo Statement (Purchase Vouchers)"]);
            fputcsv($out, ["Company", $company->name . ($company->gstin ? ' (GSTIN: ' . $company->gstin . ')' : '')]);
            fputcsv($out, ["Period", $label . ' [' . $from->format('d-M-Y') . ' to ' . $to->format('d-M-Y') . ']']);
            fputcsv($out, ["Generated on", now()->format('d-M-Y H:i')]);
            fputcsv($out, []);

            // Header row
            fputcsv($out, [
                'S.No.', 'Date', 'Memo No.', 'Seller Name', 'Seller GSTIN', 'Seller State',
                'Items', 'Taxable (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'IGST (Rs.)',
                'Round Off', 'Grand Total (Rs.)', 'Payment Mode', 'Reference', 'Notes',
            ]);

            $i = 0;
            $totals = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'grand' => 0];

            foreach ($rows as $m) {
                $i++;
                $totals['taxable'] += (float) $m->taxable_value;
                $totals['cgst']    += (float) $m->total_cgst;
                $totals['sgst']    += (float) $m->total_sgst;
                $totals['igst']    += (float) $m->total_igst;
                $totals['grand']   += (float) $m->grand_total;

                fputcsv($out, [
                    $i,
                    $m->memo_date->format('d-M-Y'),
                    $m->memo_number,
                    $m->seller_name,
                    $m->seller_gstin,
                    $m->seller_state,
                    $m->items()->count(),
                    number_format((float) $m->taxable_value, 2, '.', ''),
                    number_format((float) $m->total_cgst, 2, '.', ''),
                    number_format((float) $m->total_sgst, 2, '.', ''),
                    number_format((float) $m->total_igst, 2, '.', ''),
                    number_format((float) $m->round_off, 2, '.', ''),
                    number_format((float) $m->grand_total, 2, '.', ''),
                    strtoupper((string) $m->payment_mode),
                    $m->reference_number,
                    str_replace(["\r", "\n"], ' ', (string) $m->notes),
                ]);
            }

            // Totals row
            fputcsv($out, []);
            fputcsv($out, [
                '', '', '', '', '', '', 'TOTAL',
                number_format($totals['taxable'], 2, '.', ''),
                number_format($totals['cgst'], 2, '.', ''),
                number_format($totals['sgst'], 2, '.', ''),
                number_format($totals['igst'], 2, '.', ''),
                '',
                number_format($totals['grand'], 2, '.', ''),
                '', '', '',
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Apply the same search + date filters used by index() so a CA who
     * filtered on screen exports exactly what they were looking at.
     */
    private function filteredQuery($company, Request $request, $from, $to)
    {
        return $company->cashMemos()
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('memo_number', 'like', "%{$s}%")
                    ->orWhere('seller_name', 'like', "%{$s}%");
            }))
            ->whereBetween('memo_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Resolve the period to export. If the user filtered by from/to on the
     * index page, those win. Otherwise default to the current month — the
     * most common "send last month to my CA" case.
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->date('from');
        $to = $request->date('to');

        if ($from && $to) {
            return [$from->startOfDay(), $to->endOfDay(), $from->format('d M Y') . ' – ' . $to->format('d M Y')];
        }
        if ($from) {
            $to = now();
            return [$from->startOfDay(), $to->endOfDay(), 'From ' . $from->format('d M Y')];
        }
        // Default: current month
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        return [$start, $end, $start->format('F Y')];
    }

    public function create(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        $memo = new CashMemo([
            'memo_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'expense_category' => 'misc',
        ]);
        $items = [['description' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit' => '', 'rate' => 0, 'amount' => 0]];
        $states = State::orderBy('name')->get(['id', 'name', 'code']);
        $nextMemoNumber = $company->nextCashMemoNumber();

        return view('finance.cash-memos.form', compact('company', 'memo', 'items', 'states', 'nextMemoNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validated($request);

        if ($company->isBooksLockedOn($data['memo_date'])) {
            return redirect()->back()->withInput()
                ->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. Cannot create a cash memo dated on or before that.");
        }

        return DB::transaction(function () use ($company, $user, $data) {
            $company = $company->lockForUpdate()->find($company->id);

            // Compute money figures from line items + GST inputs
            $computed = $this->compute($data);

            // Create the linked expense first so the memo can store expense_id
            $expense = $company->expenses()->create([
                'user_id' => $user->id,
                'entry_date' => $data['memo_date'],
                'category' => $data['expense_category'] ?? 'misc',
                'vendor_name' => $data['seller_name'],
                'description' => 'Cash purchase from ' . $data['seller_name'],
                'amount' => $computed['taxable_value'],
                'gst_amount' => $computed['total_cgst'] + $computed['total_sgst'] + $computed['total_igst'],
                'payment_method' => $data['payment_mode'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // If the user provided a custom memo number, use it as-is (and don't advance
            // the auto-counter). Otherwise auto-generate via the FY-aware bump.
            $memoNumber = ! empty($data['memo_number'])
                ? trim($data['memo_number'])
                : $company->bumpCashMemoCounter($data['memo_date']);

            $memo = $company->cashMemos()->create([
                'user_id' => $user->id,
                'memo_number' => $memoNumber,
                'memo_date' => $data['memo_date'],
                'seller_name' => $data['seller_name'],
                'seller_address' => $data['seller_address'] ?? null,
                'seller_gstin' => $data['seller_gstin'] ?? null,
                'seller_phone' => $data['seller_phone'] ?? null,
                'seller_state' => $data['seller_state'] ?? null,
                'subtotal' => $computed['subtotal'],
                'discount' => $computed['discount'],
                'taxable_value' => $computed['taxable_value'],
                'total_cgst' => $computed['total_cgst'],
                'total_sgst' => $computed['total_sgst'],
                'total_igst' => $computed['total_igst'],
                'round_off' => $computed['round_off'],
                'grand_total' => $computed['grand_total'],
                'amount_in_words' => NumberToWords::indianRupees($computed['grand_total']),
                'payment_mode' => $data['payment_mode'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'expense_category' => $data['expense_category'] ?? 'misc',
                'notes' => $data['notes'] ?? null,
                'expense_id' => $expense->id,
            ]);

            foreach ($data['items'] as $idx => $row) {
                $memo->items()->create([
                    'sort_order' => $idx,
                    'description' => $row['description'],
                    'hsn_sac' => $row['hsn_sac'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'] ?? null,
                    'rate' => $row['rate'],
                    'amount' => (float) $row['quantity'] * (float) $row['rate'],
                ]);
            }

            // Back-fill expense with the cash_memo_id now that memo exists
            $expense->update(['cash_memo_id' => $memo->id]);

            AuditLog::record('cash_memo.created',
                "Cash Memo {$memoNumber} · ₹" . number_format($memo->grand_total, 2) . " · " . $memo->seller_name,
                $memo
            );

            return redirect()->route('finance.cash-memos.show', $memo)
                ->with('status', "Cash memo {$memoNumber} created · Expense entry added.");
        });
    }

    public function show(Request $request, CashMemo $cashMemo): View
    {
        abort_unless($cashMemo->user_id === $request->user()->id, 403);
        $cashMemo->load(['items', 'company.state']);
        return view('finance.cash-memos.show', ['memo' => $cashMemo]);
    }

    public function pdf(Request $request, CashMemo $cashMemo): Response
    {
        abort_unless($cashMemo->user_id === $request->user()->id, 403);
        $cashMemo->load(['items', 'company.state']);

        $pdf = Pdf::loadView('finance.cash-memos.pdf', ['memo' => $cashMemo])
            ->setPaper('a4');

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $cashMemo->memo_number);
        return $pdf->download('cash-memo-' . $safeNumber . '.pdf');
    }

    public function destroy(Request $request, CashMemo $cashMemo): RedirectResponse
    {
        abort_unless($cashMemo->user_id === $request->user()->id, 403);

        $company = $cashMemo->company;
        if ($company->isBooksLockedOn($cashMemo->memo_date)) {
            return redirect()->back()->with('error', "Books are locked. This cash memo cannot be deleted.");
        }

        $auditSnapshot = $cashMemo->only(['memo_number', 'memo_date', 'seller_name', 'grand_total', 'expense_id']);

        DB::transaction(function () use ($cashMemo) {
            // Delete linked expense too — they're a single accounting entry
            if ($cashMemo->expense_id) {
                Expense::where('id', $cashMemo->expense_id)->delete();
            }
            $cashMemo->delete();
        });

        AuditLog::record('cash_memo.deleted',
            "Cash Memo {$auditSnapshot['memo_number']} deleted · ₹" . number_format($auditSnapshot['grand_total'], 2) . " · " . $auditSnapshot['seller_name'],
            null,
            $auditSnapshot
        );

        return redirect()->route('finance.cash-memos.index')->with('status', 'Cash memo deleted.');
    }

    private function validated(Request $request): array
    {
        $companyId = $request->user()->ensureCompany()->id;

        return $request->validate([
            'memo_date' => ['required', 'date'],
            'memo_number' => [
                'nullable', 'string', 'max:40',
                // Per-company uniqueness when provided
                \Illuminate\Validation\Rule::unique('cash_memos', 'memo_number')
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'seller_name' => ['required', 'string', 'max:160'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'seller_gstin' => ['nullable', 'string', 'max:20'],
            'seller_phone' => ['nullable', 'string', 'max:30'],
            'seller_state' => ['nullable', 'string', 'max:80'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:28'],
            'is_interstate' => ['nullable', 'boolean'],
            'payment_mode' => ['required', 'string', 'in:cash,upi,card,bank,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'expense_category' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Derive subtotal, discount, GST split, round-off, grand total from form input.
     */
    private function compute(array $data): array
    {
        $subtotal = 0.0;
        foreach ($data['items'] as $row) {
            $subtotal += (float) $row['quantity'] * (float) $row['rate'];
        }

        $discount = (float) ($data['discount'] ?? 0);
        $taxable = max(0, $subtotal - $discount);

        $rate = (float) ($data['gst_rate'] ?? 0);
        $gstAmount = round($taxable * $rate / 100, 2);

        $cgst = $sgst = $igst = 0.0;
        if ($rate > 0) {
            if (! empty($data['is_interstate'])) {
                $igst = $gstAmount;
            } else {
                $cgst = round($gstAmount / 2, 2);
                $sgst = $gstAmount - $cgst; // absorb rounding diff
            }
        }

        $preRound = $taxable + $cgst + $sgst + $igst;
        $grandTotal = round($preRound);
        $roundOff = round($grandTotal - $preRound, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'taxable_value' => round($taxable, 2),
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
            'round_off' => $roundOff,
            'grand_total' => $grandTotal,
        ];
    }
}
