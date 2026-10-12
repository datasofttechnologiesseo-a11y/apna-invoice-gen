<?php

namespace App\Http\Controllers;

use App\Reports\Report;
use App\Services\ReportExporter;
use Illuminate\Http\Request;

/**
 * GET /reports/{report}/{format} - every list's Excel / CSV / PDF / Print
 * button lands here with that screen's filters in the query string.
 */
class ReportController extends Controller
{
    /** On-screen rows before the page asks you to export instead. */
    private const SCREEN_LIMIT = 500;

    /** Reports hub: every report, grouped, with one-click period exports. */
    public function index(Request $request)
    {
        $company = $request->user()->ensureCompany();
        $groups = collect(config('reports.registry'))
            ->map(fn ($class, $key) => ['key' => $key, 'report' => new $class($company, $request)])
            ->groupBy(fn ($r) => $r['report']->group());

        return view('reports.index', [
            'company' => $company,
            'groups' => $groups,
            'presets' => $this->presets(),
        ]);
    }

    /** One report on screen, with period presets and the export toolbar. */
    public function show(Request $request, ReportExporter $exporter, string $report)
    {
        $instance = $this->make($request, $report);
        $rows = [];
        $truncated = false;
        foreach ($instance->rows() as $row) {
            if (count($rows) >= self::SCREEN_LIMIT) {
                $truncated = true;
                break;
            }
            $rows[] = $row;
        }
        $columns = $instance->columns();
        // Totals on screen cover every row, not just the ones shown.
        $totals = null;
        if (collect($columns)->contains(fn ($c) => ! empty($c['total']))) {
            $totals = [];
            $all = $truncated ? iterator_to_array((function () use ($instance) { yield from $instance->rows(); })(), false) : $rows;
            foreach ($columns as $c) {
                if (! empty($c['total'])) {
                    $totals[$c['key']] = round(array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $all)), 2);
                }
            }
        }

        return view('reports.show', [
            'key' => $report,
            'report' => $instance,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'truncated' => $truncated,
            'limit' => self::SCREEN_LIMIT,
            'presets' => $this->presets(),
            // On screen, amounts show in whole rupees; unit prices keep paise.
            // The PDF, print and Excel versions keep exact paise.
            'fmt' => fn (array $col, mixed $value) => ($col['type'] ?? 'text') === 'money' && empty($col['unit_price']) && $value !== null && $value !== ''
                ? rupees((float) $value)
                : $exporter->displayValue($col, $value),
        ]);
    }

    /**
     * The name a report has on screen: the same words as the menu
     * ("Payments received", "Items"). The report classes keep their own
     * titles for file names and printed headings.
     */
    public static function screenTitle(string $key, ?Report $report = null): string
    {
        return [
            'sales-register' => 'Sales register',
            'purchase-register' => 'Purchase register',
            'quotations' => 'Quotations',
            'credit-notes' => 'Credit notes',
            'receipts' => 'Payments received',
            'vendor-payments' => 'Payments made',
            'customers' => 'Customers',
            'products' => 'Items',
            'vendors' => 'Vendors',
            'receivables-aging' => 'Receivables aging',
            'payables-aging' => 'Payables aging',
            'hsn-summary' => 'HSN summary (GSTR-1 Table 12)',
            'stock-summary' => 'Stock summary',
        ][$key] ?? ($report?->title() ?? $key);
    }

    /** Indian business periods: month, quarter and April-March financial year. */
    private function presets(): array
    {
        $today = now();
        $fyStart = $today->month >= 4 ? $today->copy()->setDate($today->year, 4, 1) : $today->copy()->setDate($today->year - 1, 4, 1);
        $qStart = $fyStart->copy()->addMonths(intdiv($fyStart->diffInMonths($today->copy()->startOfMonth()), 3) * 3);

        return [
            'this_month' => ['label' => 'This month', 'from' => $today->copy()->startOfMonth(), 'to' => $today->copy()->endOfMonth()],
            'last_month' => ['label' => 'Last month', 'from' => $today->copy()->subMonthNoOverflow()->startOfMonth(), 'to' => $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => ['label' => 'This quarter', 'from' => $qStart->copy(), 'to' => $qStart->copy()->addMonths(3)->subDay()],
            'this_fy' => ['label' => 'This FY ' . $fyStart->format('Y') . '-' . $fyStart->copy()->addYear()->format('y'), 'from' => $fyStart->copy(), 'to' => $fyStart->copy()->addYear()->subDay()],
            'last_fy' => ['label' => 'Last FY ' . $fyStart->copy()->subYear()->format('Y') . '-' . $fyStart->format('y'), 'from' => $fyStart->copy()->subYear(), 'to' => $fyStart->copy()->subDay()],
        ];
    }

    private function make(Request $request, string $report): Report
    {
        $class = config('reports.registry.' . $report);
        abort_unless($class && is_subclass_of($class, Report::class), 404);

        return new $class($request->user()->ensureCompany(), $request);
    }

    public function export(Request $request, ReportExporter $exporter, string $report, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf', 'print'], true), 404);
        $instance = $this->make($request, $report);

        \App\Models\AuditLog::record('report.exported', $instance->title() . ' · ' . strtoupper($format), null);

        return match ($format) {
            'xlsx' => $exporter->xlsx($instance),
            'csv' => $exporter->csv($instance),
            'pdf' => $exporter->pdf($instance),
            'print' => $exporter->print($instance),
        };
    }
}
