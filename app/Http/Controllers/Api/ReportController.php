<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController as WebReport;
use App\Services\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reports and registers (config/reports.php) in the mobile app: the hub, one
 * report's rows on screen, and its Excel / CSV / PDF file. Rows, totals and
 * files are the web's own (App\Reports\*, ReportExporter), and every report
 * takes the web filters in the query string: from / to (YYYY-MM-DD) and, where
 * a report has them, search / status.
 */
class ReportController extends Controller
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        $reports = collect(config('reports.registry'))->map(function ($class, $key) use ($company, $request) {
            $report = new $class($company, $request);

            return [
                'key' => $key,
                'title' => WebReport::screenTitle($key, $report),
                'document_title' => $report->title(),
                'group' => $report->group(),
                'uses_dates' => $report->usesDates(),
            ];
        })->values();

        return response()->json([
            'data' => $reports,
            'meta' => [
                'presets' => collect(WebReport::presets())->map(fn ($p, $key) => [
                    'key' => $key, 'label' => $p['label'],
                    'from' => $p['from']->toDateString(), 'to' => $p['to']->toDateString(),
                ])->values(),
                'formats' => self::FORMATS,
            ],
        ]);
    }

    /** One report: columns, the first 500 rows (as on the web) and totals over every row. */
    public function show(Request $request, ReportExporter $exporter, string $report): JsonResponse
    {
        $instance = WebReport::make($request, $report);
        $screen = $exporter->screen($instance, WebReport::SCREEN_LIMIT);

        $columns = array_map(fn ($c) => [
            'key' => $c['key'],
            'label' => $c['label'],
            'type' => $c['type'] ?? 'text',
            'total' => ! empty($c['total']),
        ], $screen['columns']);

        return response()->json([
            'key' => $report,
            'title' => WebReport::screenTitle($report, $instance),
            'document_title' => $instance->title(),
            'subtitle' => $instance->subtitle(),
            'group' => $instance->group(),
            'uses_dates' => $instance->usesDates(),
            'filters' => [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
            'columns' => $columns,
            'rows' => array_map(fn ($row) => $this->row($columns, $row), $screen['rows']),
            'totals' => $screen['totals'],
            'truncated' => $screen['truncated'],
            'limit' => WebReport::SCREEN_LIMIT,
        ]);
    }

    /** The report as a file: xlsx, csv or pdf (logged in the activity log like the web export). */
    public function export(Request $request, ReportExporter $exporter, string $report, string $format)
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);

        return app(WebReport::class)->export($request, $exporter, $report, $format);
    }

    /** Column values only, typed for JSON: dates as YYYY-MM-DD, amounts and quantities as numbers. */
    private function row(array $columns, array $row): array
    {
        $out = [];
        foreach ($columns as $col) {
            $v = $row[$col['key']] ?? null;
            $out[$col['key']] = match (true) {
                $v === null || $v === '' => null,
                $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
                in_array($col['type'], ['money', 'qty', 'percent'], true) && is_numeric($v) => (float) $v,
                $col['type'] === 'number' && is_numeric($v) => (int) $v,
                default => is_scalar($v) ? $v : (string) $v,
            };
        }

        return $out;
    }
}
