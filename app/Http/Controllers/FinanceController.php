<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Invoice;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel, $periodKey] = $this->resolvePeriod($request);
        $view = in_array($request->query('view'), ['accrual', 'cash', 'gst'], true)
            ? $request->query('view')
            : 'accrual';

        // ── Revenue comes from finalized invoices ──
        $invoicesInPeriod = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$periodStart, $periodEnd]);

        $revenue = [
            'taxable' => (float) (clone $invoicesInPeriod)->sum('subtotal'),
            'gst_collected' => (float) (clone $invoicesInPeriod)->sum('total_tax'),
            'grand_total' => (float) (clone $invoicesInPeriod)->sum('grand_total'),
            'received' => (float) (clone $invoicesInPeriod)->sum('paid_amount'),
            'outstanding' => (float) $company->invoices()
                ->whereIn('status', ['final', 'partially_paid'])
                ->sum('balance'),
        ];

        // ── Expenses from the ledger ──
        $expensesInPeriod = $company->expenses()
            ->whereBetween('entry_date', [$periodStart, $periodEnd]);

        $expense = [
            'taxable' => (float) (clone $expensesInPeriod)->sum('amount'),
            'gst_itc' => (float) (clone $expensesInPeriod)->sum('gst_amount'),
        ];
        $expense['cash_out'] = $expense['taxable'] + $expense['gst_itc'];

        // ── Core P&L numbers ──
        $netProfit = $revenue['taxable'] - $expense['taxable'];
        $margin = $revenue['taxable'] > 0 ? ($netProfit / $revenue['taxable']) * 100 : 0;
        $cashInHand = $revenue['received'] - $expense['cash_out'];
        $gstPayable = $revenue['gst_collected'] - $expense['gst_itc'];

        // ── 12-month trend ──
        $trend = $this->monthlyTrend($company, $periodEnd);

        // ── Expenses by category ──
        $byCategory = (clone $expensesInPeriod)
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($expense) {
                $cfg = config('expense_categories.' . $row->category, ['label' => ucfirst($row->category), 'color' => '#6b7280']);
                return [
                    'category' => $row->category,
                    'label' => $cfg['label'],
                    'color' => $cfg['color'],
                    'total' => (float) $row->total,
                    'count' => $row->count,
                    'share' => $expense['taxable'] > 0 ? (float) $row->total / $expense['taxable'] * 100 : 0,
                ];
            });

        // ── Top 10 expenses ──
        $topExpenses = (clone $expensesInPeriod)
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return view('finance.index', compact(
            'company', 'periodStart', 'periodEnd', 'periodLabel', 'periodKey', 'view',
            'revenue', 'expense', 'netProfit', 'margin', 'cashInHand', 'gstPayable',
            'trend', 'byCategory', 'topExpenses'
        ));
    }

    public function expenses(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel, $periodKey] = $this->resolvePeriod($request);

        $base = $this->filteredExpensesQuery($company, $request, $periodStart, $periodEnd);

        $expenses = (clone $base)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        // Aggregates over the same filtered set (not paginated — full period totals)
        $totals = (clone $base)
            ->selectRaw('SUM(amount) as taxable, SUM(gst_amount) as gst, COUNT(*) as cnt')
            ->first();

        $byCategory = (clone $base)
            ->selectRaw('category, SUM(amount) as taxable, SUM(gst_amount) as gst, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderByDesc('taxable')
            ->get()
            ->map(function ($r) {
                $cfg = config('expense_categories.' . $r->category, ['label' => ucfirst($r->category), 'color' => '#6b7280']);
                return [
                    'category' => $r->category,
                    'label' => $cfg['label'],
                    'color' => $cfg['color'],
                    'taxable' => (float) $r->taxable,
                    'gst' => (float) $r->gst,
                    'count' => (int) $r->cnt,
                ];
            });

        $summary = [
            'taxable' => (float) ($totals->taxable ?? 0),
            'gst' => (float) ($totals->gst ?? 0),
            'count' => (int) ($totals->cnt ?? 0),
        ];
        $summary['cash_out'] = $summary['taxable'] + $summary['gst'];

        return view('finance.expenses', compact(
            'company', 'expenses', 'periodStart', 'periodEnd', 'periodLabel', 'periodKey',
            'summary', 'byCategory'
        ));
    }

    public function expensesPdf(Request $request): Response
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel, $periodKey] = $this->resolvePeriod($request);

        $rows = $this->filteredExpensesQuery($company, $request, $periodStart, $periodEnd)
            ->orderBy('entry_date')->orderBy('id')->get();

        $byCategory = $rows->groupBy('category')->map(function ($items, $cat) {
            $cfg = config('expense_categories.' . $cat, ['label' => ucfirst($cat)]);
            return [
                'label' => $cfg['label'],
                'taxable' => (float) $items->sum('amount'),
                'gst' => (float) $items->sum('gst_amount'),
                'count' => $items->count(),
            ];
        })->sortByDesc('taxable')->values();

        $summary = [
            'taxable' => (float) $rows->sum('amount'),
            'gst' => (float) $rows->sum('gst_amount'),
            'count' => $rows->count(),
        ];
        $summary['cash_out'] = $summary['taxable'] + $summary['gst'];
        $summaryWords = NumberToWords::indianRupees($summary['cash_out']);

        $filters = [
            'category' => $request->query('category'),
            'search' => $request->query('search'),
        ];

        $pdf = Pdf::loadView('finance.expenses-report', compact(
            'company', 'rows', 'byCategory', 'summary', 'summaryWords',
            'periodStart', 'periodEnd', 'periodLabel', 'filters'
        ))->setPaper('a4', 'portrait');

        $filename = 'expenses-' . $periodStart->format('Ymd') . '-' . $periodEnd->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }

    public function expensesCsv(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel] = $this->resolvePeriod($request);

        $rows = $this->filteredExpensesQuery($company, $request, $periodStart, $periodEnd)
            ->orderBy('entry_date')->orderBy('id')->get();

        $filename = 'expenses-' . $periodStart->format('Ymd') . '-' . $periodEnd->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $company, $periodLabel, $periodStart, $periodEnd) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 cleanly with ₹ etc
            fwrite($out, "\xEF\xBB\xBF");

            // Header rows for CA — company + period context first
            fputcsv($out, ["Expense Statement"]);
            fputcsv($out, ["Company", $company->name . ($company->gstin ? ' (GSTIN: ' . $company->gstin . ')' : '')]);
            fputcsv($out, ["Period", $periodLabel . ' [' . $periodStart->format('d-M-Y') . ' to ' . $periodEnd->format('d-M-Y') . ']']);
            fputcsv($out, ["Generated on", now()->format('d-M-Y H:i')]);
            fputcsv($out, []);

            // Column headers
            fputcsv($out, [
                'S.No.', 'Date', 'Category', 'Vendor / Paid To', 'Description',
                'Taxable (Rs.)', 'GST/ITC (Rs.)', 'Total (Rs.)', 'Payment Mode', 'Reference', 'Notes',
            ]);

            $i = 0; $totalTaxable = 0; $totalGst = 0;
            foreach ($rows as $e) {
                $i++;
                $taxable = (float) $e->amount;
                $gst = (float) $e->gst_amount;
                $totalTaxable += $taxable;
                $totalGst += $gst;
                fputcsv($out, [
                    $i,
                    $e->entry_date->format('d-M-Y'),
                    config('expense_categories.' . $e->category . '.label', ucfirst($e->category)),
                    $e->vendor_name,
                    $e->description,
                    number_format($taxable, 2, '.', ''),
                    number_format($gst, 2, '.', ''),
                    number_format($taxable + $gst, 2, '.', ''),
                    strtoupper((string) $e->payment_method),
                    $e->reference_number,
                    str_replace(["\r", "\n"], ' ', (string) $e->notes),
                ]);
            }

            // Totals row
            fputcsv($out, []);
            fputcsv($out, [
                '', '', '', '', 'TOTAL',
                number_format($totalTaxable, 2, '.', ''),
                number_format($totalGst, 2, '.', ''),
                number_format($totalTaxable + $totalGst, 2, '.', ''),
                '', '', '',
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Receivables aging report
    |--------------------------------------------------------------------------
    | The single most-asked weekly question for every Indian SME owner:
    | "Who owes me what, and how old is the debt?"
    |
    | Buckets follow Indian accounting convention:
    |   - Current (not yet due, or due ≤ 30 days)
    |   - 31–60 days past due
    |   - 61–90 days past due
    |   - 91+ days past due (write-off candidates / recovery)
    |
    | The base date for "days overdue" is the invoice's due_date if set;
    | otherwise we fall back to invoice_date so we never miss an open balance.
    */

    public function aging(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        $today = now()->startOfDay();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        $rows = $this->bucketAging($invoices, $today);

        // Aggregate per customer for the headline view (one row per customer)
        $byCustomer = $rows->groupBy('customer_id')->map(function ($group, $custId) use ($today) {
            $first = $group->first();
            return [
                'customer_id' => $custId,
                'name' => $first['customer_name'],
                'gstin' => $first['customer_gstin'],
                'invoice_count' => $group->count(),
                'oldest_days' => $group->max('days_overdue'),
                'total' => round($group->sum('balance'), 2),
                'current' => round($group->where('bucket', 'current')->sum('balance'), 2),
                'b30_60' => round($group->where('bucket', '30-60')->sum('balance'), 2),
                'b60_90' => round($group->where('bucket', '60-90')->sum('balance'), 2),
                'b90_plus' => round($group->where('bucket', '90+')->sum('balance'), 2),
            ];
        })->sortByDesc('total')->values();

        $summary = [
            'customers'   => $byCustomer->count(),
            'invoices'    => $rows->count(),
            'total'       => round($rows->sum('balance'), 2),
            'current'     => round($rows->where('bucket', 'current')->sum('balance'), 2),
            'b30_60'      => round($rows->where('bucket', '30-60')->sum('balance'), 2),
            'b60_90'      => round($rows->where('bucket', '60-90')->sum('balance'), 2),
            'b90_plus'    => round($rows->where('bucket', '90+')->sum('balance'), 2),
        ];

        return view('finance.aging', compact('company', 'rows', 'byCustomer', 'summary', 'today'));
    }

    public function agingPdf(Request $request): Response
    {
        $company = $request->user()->ensureCompany();
        $today = now()->startOfDay();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->with('customer')
            ->orderBy('due_date')->get();

        $rows = $this->bucketAging($invoices, $today);
        $byCustomer = $rows->groupBy('customer_id')->map(function ($group, $custId) {
            $first = $group->first();
            return [
                'name' => $first['customer_name'],
                'gstin' => $first['customer_gstin'],
                'invoice_count' => $group->count(),
                'oldest_days' => $group->max('days_overdue'),
                'total' => round($group->sum('balance'), 2),
                'current' => round($group->where('bucket', 'current')->sum('balance'), 2),
                'b30_60' => round($group->where('bucket', '30-60')->sum('balance'), 2),
                'b60_90' => round($group->where('bucket', '60-90')->sum('balance'), 2),
                'b90_plus' => round($group->where('bucket', '90+')->sum('balance'), 2),
            ];
        })->sortByDesc('total')->values();

        $summary = [
            'customers'   => $byCustomer->count(),
            'invoices'    => $rows->count(),
            'total'       => round($rows->sum('balance'), 2),
            'current'     => round($rows->where('bucket', 'current')->sum('balance'), 2),
            'b30_60'      => round($rows->where('bucket', '30-60')->sum('balance'), 2),
            'b60_90'      => round($rows->where('bucket', '60-90')->sum('balance'), 2),
            'b90_plus'    => round($rows->where('bucket', '90+')->sum('balance'), 2),
        ];

        $pdf = Pdf::loadView('finance.aging-pdf', compact('company', 'rows', 'byCustomer', 'summary', 'today'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('receivables-aging-' . $today->format('Ymd') . '.pdf');
    }

    public function agingCsv(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();
        $today = now()->startOfDay();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid'])
            ->where('balance', '>', 0)
            ->with('customer')
            ->orderBy('due_date')->get();

        $rows = $this->bucketAging($invoices, $today);
        $filename = 'receivables-aging-' . $today->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $company, $today) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ["Receivables Aging Report"]);
            fputcsv($out, ["Company", $company->name . ($company->gstin ? ' (GSTIN: ' . $company->gstin . ')' : '')]);
            fputcsv($out, ["As on", $today->format('d-M-Y')]);
            fputcsv($out, []);
            fputcsv($out, [
                'S.No.', 'Customer', 'Customer GSTIN', 'Invoice No.', 'Invoice Date',
                'Due Date', 'Days Overdue', 'Bucket', 'Grand Total (Rs.)', 'Paid (Rs.)', 'Balance (Rs.)',
            ]);
            $i = 0;
            foreach ($rows as $r) {
                $i++;
                fputcsv($out, [
                    $i,
                    $r['customer_name'],
                    $r['customer_gstin'],
                    $r['invoice_number'],
                    $r['invoice_date']?->format('d-M-Y'),
                    $r['due_date']?->format('d-M-Y'),
                    $r['days_overdue'],
                    $r['bucket'],
                    number_format((float) $r['grand_total'], 2, '.', ''),
                    number_format((float) $r['paid_amount'], 2, '.', ''),
                    number_format((float) $r['balance'], 2, '.', ''),
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['', '', '', '', '', '', '', 'TOTAL', '', '', number_format((float) $rows->sum('balance'), 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Compute days overdue + bucket for each open invoice. Shared by the
     * view, PDF and CSV endpoints — single source of bucket truth.
     */
    private function bucketAging($invoices, Carbon $today)
    {
        return $invoices->map(function ($inv) use ($today) {
            $base = $inv->due_date ?? $inv->invoice_date ?? $today;
            $days = (int) max(0, $today->diffInDays($base, false) * -1);
            // Bucket the same way Tally and Zoho Books do — "current" includes
            // not-yet-due and ≤30 days overdue; sub-₹5cr owners think in "this month vs older"
            if ($days <= 30) {
                $bucket = 'current';
            } elseif ($days <= 60) {
                $bucket = '30-60';
            } elseif ($days <= 90) {
                $bucket = '60-90';
            } else {
                $bucket = '90+';
            }
            return [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer?->name ?? '—',
                'customer_gstin' => $inv->customer?->gstin,
                'grand_total' => (float) $inv->grand_total,
                'paid_amount' => (float) $inv->paid_amount,
                'balance' => (float) $inv->balance,
                'days_overdue' => $days,
                'bucket' => $bucket,
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | GSTR-3B summary report
    |--------------------------------------------------------------------------
    | Self-declaration return that every GST-registered taxpayer files monthly
    | (or quarterly under QRMP). We compute the figures for the most-used
    | sections from the data already in the books:
    |
    |   Section 3.1(a) — Outward taxable supplies (other than zero/nil/exempt)
    |   Section 3.1(d) — Inward supplies liable to reverse charge
    |   Section 4(A)(5) — ITC available (from purchase invoices + cash memos)
    |   Section 6.1     — Net tax payable
    |
    | This is a HELPER, not a filing tool — the user (or their CA) cross-checks
    | the figures against GSTR-1 and ITC ledgers before filing.
    */

    public function gstr3b(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel] = $this->resolveGstr3bPeriod($request);

        $data = $this->buildGstr3b($company, $periodStart, $periodEnd);

        return view('finance.gstr3b', array_merge($data, [
            'company' => $company,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodLabel' => $periodLabel,
        ]));
    }

    public function gstr3bPdf(Request $request): Response
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel] = $this->resolveGstr3bPeriod($request);

        $data = $this->buildGstr3b($company, $periodStart, $periodEnd);

        $pdf = Pdf::loadView('finance.gstr3b-pdf', array_merge($data, [
            'company' => $company,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodLabel' => $periodLabel,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download('gstr3b-' . $periodStart->format('Ym') . '.pdf');
    }

    public function gstr3bCsv(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();
        [$periodStart, $periodEnd, $periodLabel] = $this->resolveGstr3bPeriod($request);

        $data = $this->buildGstr3b($company, $periodStart, $periodEnd);
        $filename = 'gstr3b-' . $periodStart->format('Ym') . '.csv';

        return response()->streamDownload(function () use ($data, $company, $periodLabel, $periodStart, $periodEnd) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ["GSTR-3B Summary (computed from books)"]);
            fputcsv($out, ["Company", $company->name . ($company->gstin ? ' (GSTIN: ' . $company->gstin . ')' : '')]);
            fputcsv($out, ["Period", $periodLabel . ' [' . $periodStart->format('d-M-Y') . ' to ' . $periodEnd->format('d-M-Y') . ']']);
            fputcsv($out, ["Generated on", now()->format('d-M-Y H:i')]);
            fputcsv($out, []);

            // 3.1 — Outward + reverse-charge supplies
            fputcsv($out, ['Section 3.1 — Details of Outward Supplies & Inward Supplies Liable to Reverse Charge']);
            fputcsv($out, ['Nature of supplies', 'Taxable value (Rs.)', 'IGST (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'Cess (Rs.)']);
            $a = $data['outward'];
            $d = $data['rcm_outward'];
            fputcsv($out, ['(a) Outward taxable supplies (other than zero rated, nil rated and exempted)',
                number_format($a['taxable'], 2, '.', ''),
                number_format($a['igst'], 2, '.', ''),
                number_format($a['cgst'], 2, '.', ''),
                number_format($a['sgst'], 2, '.', ''),
                '0.00',
            ]);
            fputcsv($out, ['(b) Outward taxable supplies (zero rated)', '0.00', '0.00', '0.00', '0.00', '0.00']);
            fputcsv($out, ['(c) Other outward supplies (Nil rated, exempted)', '0.00', '0.00', '0.00', '0.00', '0.00']);
            fputcsv($out, ['(d) Inward supplies (liable to reverse charge)',
                number_format($d['taxable'], 2, '.', ''),
                number_format($d['igst'], 2, '.', ''),
                number_format($d['cgst'], 2, '.', ''),
                number_format($d['sgst'], 2, '.', ''),
                '0.00',
            ]);
            fputcsv($out, ['(e) Non-GST outward supplies', '0.00', '0.00', '0.00', '0.00', '0.00']);
            fputcsv($out, []);

            // 4 — ITC
            fputcsv($out, ['Section 4 — Eligible ITC']);
            fputcsv($out, ['Details', 'IGST (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)', 'Cess (Rs.)']);
            $itc = $data['itc'];
            fputcsv($out, ['(A)(5) All other ITC',
                number_format($itc['igst'], 2, '.', ''),
                number_format($itc['cgst'], 2, '.', ''),
                number_format($itc['sgst'], 2, '.', ''),
                '0.00',
            ]);
            fputcsv($out, ['(C) Net ITC available (A – B)',
                number_format($itc['igst'], 2, '.', ''),
                number_format($itc['cgst'], 2, '.', ''),
                number_format($itc['sgst'], 2, '.', ''),
                '0.00',
            ]);
            fputcsv($out, []);

            // 6.1 — Net tax payable
            fputcsv($out, ['Section 6.1 — Payment of Tax']);
            fputcsv($out, ['Description', 'IGST (Rs.)', 'CGST (Rs.)', 'SGST (Rs.)']);
            fputcsv($out, ['Total tax payable (from outward supplies)',
                number_format($a['igst'], 2, '.', ''),
                number_format($a['cgst'], 2, '.', ''),
                number_format($a['sgst'], 2, '.', ''),
            ]);
            fputcsv($out, ['ITC available (from inward supplies)',
                number_format($itc['igst'], 2, '.', ''),
                number_format($itc['cgst'], 2, '.', ''),
                number_format($itc['sgst'], 2, '.', ''),
            ]);
            fputcsv($out, ['Net cash payable',
                number_format(max(0, $a['igst'] - $itc['igst']), 2, '.', ''),
                number_format(max(0, $a['cgst'] - $itc['cgst']), 2, '.', ''),
                number_format(max(0, $a['sgst'] - $itc['sgst']), 2, '.', ''),
            ]);
            fputcsv($out, []);

            fputcsv($out, ['Note', 'Computed from your books. Verify against GSTR-1 + ITC ledgers before filing.']);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Aggregate the GSTR-3B figures for a period from invoices + expenses + cash memos.
     */
    private function buildGstr3b($company, Carbon $from, Carbon $to): array
    {
        // 3.1(a) — Outward taxable supplies (regular invoices, excludes RCM)
        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $regular = $invoices->where('reverse_charge', false);
        $rcm     = $invoices->where('reverse_charge', true);

        $outward = [
            'taxable' => round((float) $regular->sum('subtotal'), 2),
            'igst'    => round((float) $regular->sum('total_igst'), 2),
            'cgst'    => round((float) $regular->sum('total_cgst'), 2),
            'sgst'    => round((float) $regular->sum('total_sgst'), 2),
        ];

        // 3.1(d) — Inward supplies liable to reverse charge (we report from the
        // invoice side: outgoing where the buyer pays). Tax is "would-have-been"
        // on the line items even though we set CGST/SGST/IGST to ₹0 on the invoice.
        // For now, surface the taxable value; the buyer side computes their own tax.
        $rcm_outward = [
            'taxable' => round((float) $rcm->sum('subtotal'), 2),
            'igst'    => 0.0,
            'cgst'    => 0.0,
            'sgst'    => 0.0,
        ];

        // 4(A)(5) — ITC available: GST paid on expenses + cash memos in the period.
        // For sub-₹5cr regular dealers this is the bulk of their ITC claim.
        //
        // Expenses use the `is_interstate` flag set by the user when entering each row:
        //   - intra-state (default): GST splits 50/50 into CGST + SGST per CGST Act §9(1)
        //   - inter-state:           GST goes entirely to IGST per IGST Act §5(1)
        // Cash memos already store CGST/SGST/IGST separately, so we just sum those.
        $expensesInPeriod = $company->expenses()
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->get(['gst_amount', 'is_interstate']);

        $intraExpenseGst = (float) $expensesInPeriod->where('is_interstate', false)->sum('gst_amount');
        $interExpenseGst = (float) $expensesInPeriod->where('is_interstate', true)->sum('gst_amount');

        $cashMemos = $company->cashMemos()
            ->whereBetween('memo_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $itc = [
            'igst' => round((float) $cashMemos->sum('total_igst') + $interExpenseGst, 2),
            'cgst' => round((float) $cashMemos->sum('total_cgst') + ($intraExpenseGst / 2), 2),
            'sgst' => round((float) $cashMemos->sum('total_sgst') + ($intraExpenseGst / 2), 2),
        ];
        $itc['total'] = round($itc['igst'] + $itc['cgst'] + $itc['sgst'], 2);

        // 6.1 — Net cash payable per head
        $netCash = [
            'igst' => round(max(0, $outward['igst'] - $itc['igst']), 2),
            'cgst' => round(max(0, $outward['cgst'] - $itc['cgst']), 2),
            'sgst' => round(max(0, $outward['sgst'] - $itc['sgst']), 2),
        ];
        $netCash['total'] = round($netCash['igst'] + $netCash['cgst'] + $netCash['sgst'], 2);

        return [
            'outward' => $outward,
            'rcm_outward' => $rcm_outward,
            'itc' => $itc,
            'netCash' => $netCash,
            'invoiceCount' => $invoices->count(),
            'expenseCount' => $company->expenses()->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])->count(),
            'cashMemoCount' => $cashMemos->count(),
        ];
    }

    /**
     * GSTR-3B is filed for a calendar month. We default to the previous month
     * (since the current month isn't yet closed for filing on the 20th).
     */
    private function resolveGstr3bPeriod(Request $request): array
    {
        $month = $request->input('month'); // expected format: YYYY-MM
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::parse($month . '-01')->startOfMonth();
        } else {
            // Default: previous month if we're past the 11th (GSTR-1 filing window),
            // else still on previous month so users can prepare ahead.
            $start = now()->subMonthNoOverflow()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();
        return [$start, $end, $start->format('F Y')];
    }

    /**
     * Build the filtered expenses query (period + category + free-text search)
     * shared by the index, PDF and CSV endpoints.
     */
    private function filteredExpensesQuery($company, Request $request, Carbon $from, Carbon $to)
    {
        return $company->expenses()
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('description', 'like', "%{$s}%")
                    ->orWhere('vendor_name', 'like', "%{$s}%")
                    ->orWhere('reference_number', 'like', "%{$s}%");
            }));
    }

    public function createExpense(Request $request): View
    {
        $expense = new Expense(['entry_date' => now()->toDateString(), 'payment_method' => 'bank']);
        return view('finance.entry-form', compact('expense'));
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->ensureCompany();
        $data = $this->validated($request);

        if ($company->isBooksLockedOn($data['entry_date'])) {
            return redirect()->back()->withInput()
                ->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. Cannot add expense dated on or before that.");
        }

        $expense = $company->expenses()->create(array_merge($data, ['user_id' => $user->id]));

        AuditLog::record('expense.created',
            "Expense ₹" . number_format($expense->amount, 2) . " · " . ($expense->vendor_name ?: $expense->description),
            $expense
        );

        return redirect()->route('finance.expenses')
            ->with('status', "Expense of ₹" . number_format($expense->amount, 2) . " added.");
    }

    public function editExpense(Request $request, Expense $expense): View
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
        return view('finance.entry-form', compact('expense'));
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
        $company = $expense->company;
        if ($company->isBooksLockedOn($expense->entry_date)) {
            return redirect()->back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This expense cannot be edited.");
        }

        $data = $this->validated($request);
        if ($company->isBooksLockedOn($data['entry_date'])) {
            return redirect()->back()->withInput()
                ->with('error', "Cannot move this expense to a date inside the locked period (up to {$company->books_locked_until->format('d M Y')}).");
        }

        $before = $expense->only(['amount', 'gst_amount', 'category', 'vendor_name', 'description', 'entry_date']);
        $expense->update($data);
        AuditLog::record('expense.updated',
            "Expense #{$expense->id} updated · ₹" . number_format($expense->amount, 2),
            $expense,
            ['before' => $before, 'after' => $expense->only(['amount', 'gst_amount', 'category', 'vendor_name', 'description', 'entry_date'])]
        );
        return redirect()->route('finance.expenses')->with('status', 'Expense updated.');
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
        $company = $expense->company;
        if ($company->isBooksLockedOn($expense->entry_date)) {
            return redirect()->back()->with('error', "Books are locked up to {$company->books_locked_until->format('d M Y')}. This expense cannot be deleted because it falls inside the closed period.");
        }
        AuditLog::record('expense.deleted',
            "Expense #{$expense->id} deleted · ₹" . number_format($expense->amount, 2) . " · " . ($expense->vendor_name ?: $expense->description),
            $expense,
            $expense->toArray()
        );
        $expense->delete();

        return redirect()->route('finance.expenses')->with('status', 'Expense deleted.');
    }

    /**
     * Single-expense voucher PDF.
     *
     * - If the expense was created via a Cash Memo, redirect to the memo's PDF
     *   (the memo IS the canonical voucher).
     * - Otherwise generate an "Expense Voucher" PDF on the fly.
     * - ?inline=1 streams to browser (View). Otherwise forces download.
     */
    public function expensePdf(Request $request, Expense $expense): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($expense->user_id === $request->user()->id, 403);

        if ($expense->cash_memo_id) {
            return redirect()->route('finance.cash-memos.pdf', $expense->cash_memo_id);
        }

        $expense->load('company.state');
        $amountInWords = NumberToWords::indianRupees(
            (float) $expense->amount + (float) $expense->gst_amount
        );

        $pdf = Pdf::loadView('finance.expense-voucher', compact('expense', 'amountInWords'))
            ->setPaper('a4', 'portrait');

        $filename = 'expense-voucher-' . $expense->id . '-' . $expense->entry_date->format('Ymd') . '.pdf';

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(config('expense_categories')))],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'is_interstate' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,upi,card,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // gst_amount is validated as nullable but its DB column is NOT NULL default 0.
        // Coerce empty / null to 0 so Laravel doesn't insert a literal NULL that
        // bypasses the column default and triggers a 1048 integrity violation.
        $data['gst_amount'] = $data['gst_amount'] ?? 0;

        // Same coercion for is_interstate — checkbox is absent from the request body
        // when unchecked, but DB column is NOT NULL with default false.
        $data['is_interstate'] = $request->boolean('is_interstate');

        return $data;
    }

    /**
     * Resolve a period from the request. Supports every common Indian-accountant
     * range: today, yesterday, this/last week, this/last month, this/last
     * (Indian-fiscal) quarter, this/last fiscal half-year, this/last financial
     * year (Apr–Mar), this/last calendar year, year-to-date, and custom.
     *
     * Quarters and half-years follow the *Indian financial-year* convention:
     *   Q1 Apr–Jun · Q2 Jul–Sep · Q3 Oct–Dec · Q4 Jan–Mar
     *   H1 Apr–Sep · H2 Oct–Mar
     *
     * Custom uses ?from=&to= (YYYY-MM-DD, inclusive on both ends).
     */
    private function resolvePeriod(Request $request): array
    {
        $key = $request->query('period', 'this_month');
        $now = Carbon::now();

        // Indian financial year (Apr 1 → Mar 31)
        $fyStart = $now->copy()->month >= 4
            ? Carbon::create($now->year, 4, 1)->startOfDay()
            : Carbon::create($now->year - 1, 4, 1)->startOfDay();
        $fyEnd = $fyStart->copy()->addYear()->subDay()->endOfDay();

        // Indian fiscal quarter for "now" (Q1 starts 1 Apr)
        $monthsFromApr = ($now->month + 12 - 4) % 12; // 0..11 from 1 Apr
        $qIdx = intdiv($monthsFromApr, 3); // 0..3 → Q1..Q4
        $qStart = $fyStart->copy()->addMonths($qIdx * 3);
        $qEnd = $qStart->copy()->addMonths(3)->subDay()->endOfDay();
        $qLabel = ['Q1 (Apr–Jun)', 'Q2 (Jul–Sep)', 'Q3 (Oct–Dec)', 'Q4 (Jan–Mar)'][$qIdx];

        // Indian fiscal half-year (H1 Apr–Sep, H2 Oct–Mar)
        $hIdx = intdiv($monthsFromApr, 6); // 0 or 1
        $hStart = $fyStart->copy()->addMonths($hIdx * 6);
        $hEnd = $hStart->copy()->addMonths(6)->subDay()->endOfDay();
        $hLabel = $hIdx === 0 ? 'H1 (Apr–Sep)' : 'H2 (Oct–Mar)';

        return match ($key) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                'Today · ' . $now->format('d M Y'),
                $key,
            ],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
                'Yesterday · ' . $now->copy()->subDay()->format('d M Y'),
                $key,
            ],
            'this_week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
                'This week · ' . $now->copy()->startOfWeek(Carbon::MONDAY)->format('d M') . ' – ' . $now->copy()->endOfWeek(Carbon::SUNDAY)->format('d M Y'),
                $key,
            ],
            'last_week' => [
                $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
                'Last week · ' . $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->format('d M') . ' – ' . $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->format('d M Y'),
                $key,
            ],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'Last month · ' . $now->copy()->subMonthNoOverflow()->format('M Y'),
                $key,
            ],
            'this_quarter' => [
                $qStart,
                $qEnd,
                'This quarter · ' . $qLabel . ' ' . $qStart->format('Y'),
                $key,
            ],
            'last_quarter' => [
                $qStart->copy()->subMonths(3),
                $qStart->copy()->subDay()->endOfDay(),
                'Last quarter · ' . ['Q1 (Apr–Jun)', 'Q2 (Jul–Sep)', 'Q3 (Oct–Dec)', 'Q4 (Jan–Mar)'][($qIdx + 3) % 4] . ' ' . $qStart->copy()->subMonths(3)->format('Y'),
                $key,
            ],
            'this_half' => [
                $hStart,
                $hEnd,
                'This half-year · ' . $hLabel . ' ' . $hStart->format('Y'),
                $key,
            ],
            'last_half' => [
                $hStart->copy()->subMonths(6),
                $hStart->copy()->subDay()->endOfDay(),
                'Last half-year · ' . ($hIdx === 0 ? 'H2 (Oct–Mar)' : 'H1 (Apr–Sep)') . ' ' . $hStart->copy()->subMonths(6)->format('Y'),
                $key,
            ],
            'this_fy' => [
                $fyStart,
                $fyEnd,
                'This FY · ' . $fyStart->format('Y') . '–' . $fyEnd->format('y'),
                $key,
            ],
            'last_fy' => [
                $fyStart->copy()->subYear(),
                $fyEnd->copy()->subYear(),
                'Last FY · ' . $fyStart->copy()->subYear()->format('Y') . '–' . $fyEnd->copy()->subYear()->format('y'),
                $key,
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                'This calendar year · ' . $now->format('Y'),
                $key,
            ],
            'last_year' => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
                'Last calendar year · ' . $now->copy()->subYear()->format('Y'),
                $key,
            ],
            'ytd' => [
                $fyStart,
                $now->copy()->endOfDay(),
                'Financial Year to Date · FY ' . $fyStart->format('Y') . '–' . $fyEnd->format('y') . ' (till ' . $now->format('d M Y') . ')',
                $key,
            ],
            'custom' => [
                Carbon::parse($request->query('from', $now->copy()->startOfMonth()->toDateString()))->startOfDay(),
                Carbon::parse($request->query('to', $now->toDateString()))->endOfDay(),
                'Custom period',
                $key,
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'This month · ' . $now->format('M Y'),
                'this_month',
            ],
        };
    }

    /**
     * Last 12 months of revenue (invoice subtotal) vs expenses (expense amount).
     * Grouped in PHP so the query is portable (MySQL + SQLite alike).
     */
    private function monthlyTrend($company, Carbon $pivot): array
    {
        $start = $pivot->copy()->subMonths(11)->startOfMonth();

        $invoices = $company->invoices()
            ->whereIn('status', ['final', 'partially_paid', 'paid'])
            ->where('invoice_date', '>=', $start)
            ->get(['invoice_date', 'subtotal']);

        $expenses = $company->expenses()
            ->where('entry_date', '>=', $start)
            ->get(['entry_date', 'amount']);

        $revByMonth = $invoices->groupBy(fn ($i) => $i->invoice_date->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('subtotal'));

        $expByMonth = $expenses->groupBy(fn ($e) => $e->entry_date->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $ym = $month->format('Y-m');
            $out[] = [
                'label' => $month->format('M y'),
                'ym' => $ym,
                'revenue' => $revByMonth[$ym] ?? 0.0,
                'expenses' => $expByMonth[$ym] ?? 0.0,
            ];
        }
        return $out;
    }
}
