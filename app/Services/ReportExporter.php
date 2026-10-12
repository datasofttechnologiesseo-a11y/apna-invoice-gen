<?php

namespace App\Services;

use App\Support\SafeExport;
use App\Reports\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns any Report into the four formats an Indian business and its CA
 * expect from every list: Excel (real .xlsx, numbers as numbers in lakh/crore
 * grouping), CSV (UTF-8 with BOM so Excel shows ₹ correctly), PDF and a
 * print-ready page.
 */
class ReportExporter
{
    /** Excel number format with Indian grouping: 1,23,45,678.90 */
    private const INR_FORMAT = '[>=10000000]##\,##\,##\,##0.00;[>=100000]##\,##\,##0.00;##,##0.00';
    private const QTY_FORMAT = '#,##0.###';

    public function xlsx(Report $report): StreamedResponse
    {
        $columns = $report->columns();
        $rows = $this->materialise($report);
        $totals = $this->totals($columns, $rows);

        return response()->streamDownload(function () use ($report, $columns, $rows, $totals) {
            $options = new Options();
            foreach ($columns as $i => $col) {
                $options->setColumnWidth($this->columnWidth($col), $i + 1);
            }
            $last = max(0, count($columns) - 1);
            $options->mergeCells(0, 1, $last, 1);
            $options->mergeCells(0, 2, $last, 2);
            $options->mergeCells(0, 3, $last, 3);

            $writer = new Writer($options);
            $writer->openToFile('php://output');
            $sheet = $writer->getCurrentSheet();
            $sheet->setName(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $report->title()), 0, 31));
            $sheet->setSheetView((new SheetView())->setFreezeRow(6));

            $company = $report->company();
            $writer->addRow(SafeExport::row([$report->title()], (new Style())->setFontBold()->setFontSize(14)));
            $writer->addRow(SafeExport::row([$company->name . ($company->gstin ? '  ·  GSTIN ' . $company->gstin : '')], (new Style())->setFontBold()));
            $writer->addRow(SafeExport::row([$report->subtitle() . '  ·  Generated ' . now()->format('d M Y, h:i A')], (new Style())->setFontColor('555555')));
            $writer->addRow(SafeExport::row([]));

            $head = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('167C88');
            $writer->addRow(SafeExport::row(array_column($columns, 'label'), $head));

            $styles = $this->columnStyles($columns);
            foreach ($rows as $row) {
                $cells = [];
                foreach ($columns as $i => $col) {
                    $cells[] = SafeExport::cell($this->cellValue($col, $row[$col['key']] ?? null), $styles[$i]);
                }
                $writer->addRow(new Row($cells));
            }

            if ($totals !== null) {
                $cells = [];
                foreach ($columns as $i => $col) {
                    $value = $i === 0 ? 'Total' : (array_key_exists($col['key'], $totals) ? $totals[$col['key']] : '');
                    $style = (clone $styles[$i])->setFontBold();
                    $cells[] = SafeExport::cell($value, $style);
                }
                $writer->addRow(new Row($cells));
            }

            $writer->close();
        }, $report->filename() . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function csv(Report $report): StreamedResponse
    {
        $columns = $report->columns();
        $rows = $this->materialise($report);
        $totals = $this->totals($columns, $rows);

        return response()->streamDownload(function () use ($report, $columns, $rows, $totals) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            csv_put($out, [$report->title()]);
            csv_put($out, [$report->company()->name . ($report->company()->gstin ? ' (GSTIN: ' . $report->company()->gstin . ')' : '')]);
            csv_put($out, [$report->subtitle()]);
            csv_put($out, []);
            csv_put($out, array_column($columns, 'label'));
            foreach ($rows as $row) {
                csv_put($out, array_map(fn ($col) => $this->plainValue($col, $row[$col['key']] ?? null), $columns));
            }
            if ($totals !== null) {
                $line = [];
                foreach ($columns as $i => $col) {
                    $line[] = $i === 0 ? 'Total' : (array_key_exists($col['key'], $totals) ? number_format($totals[$col['key']], 2, '.', '') : '');
                }
                csv_put($out, $line);
            }
            fclose($out);
        }, $report->filename() . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Report $report): Response
    {
        return Pdf::loadView('reports.pdf', $this->viewData($report))
            ->setPaper('A4', $report->landscape() ? 'landscape' : 'portrait')
            ->download($report->filename() . '.pdf');
    }

    public function print(Report $report): View
    {
        return view('reports.print', $this->viewData($report));
    }

    // ------------------------------------------------------------------

    private function viewData(Report $report): array
    {
        $columns = $report->columns();
        $rows = $this->materialise($report);

        return [
            'report' => $report,
            'company' => $report->company(),
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $this->totals($columns, $rows),
            'fmt' => fn (array $col, mixed $value) => $this->displayValue($col, $value),
        ];
    }

    private function materialise(Report $report): array
    {
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** Sum of every column flagged total => true, or null when none are. */
    private function totals(array $columns, array $rows): ?array
    {
        $totals = [];
        foreach ($columns as $col) {
            if (! empty($col['total'])) {
                $totals[$col['key']] = round(array_sum(array_map(fn ($r) => (float) ($r[$col['key']] ?? 0), $rows)), 2);
            }
        }

        return $totals ?: null;
    }

    private function columnStyles(array $columns): array
    {
        return array_map(function ($col) {
            $style = new Style();
            return match ($col['type'] ?? 'text') {
                'money' => $style->setFormat(self::INR_FORMAT),
                'qty' => $style->setFormat(self::QTY_FORMAT),
                'number' => $style->setFormat('0'),
                'percent' => $style->setFormat('0.##"%"'),
                default => $style,
            };
        }, $columns);
    }

    private function columnWidth(array $col): float
    {
        return match ($col['type'] ?? 'text') {
            'money' => 15,
            'qty', 'number', 'percent' => 10,
            'date' => 12,
            default => (float) ($col['width'] ?? max(12, min(40, mb_strlen($col['label']) + 6))),
        };
    }

    /** Real numbers for Excel so totals and formulas work. */
    private function cellValue(array $col, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($col['type'] ?? 'text') {
            'money', 'qty', 'percent' => (float) $value,
            'number' => (int) $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('d-m-Y') : (string) $value,
            default => (string) $value,
        };
    }

    private function plainValue(array $col, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($col['type'] ?? 'text') {
            'money' => number_format((float) $value, 2, '.', ''),
            'qty' => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.'),
            'number' => (string) (int) $value,
            'percent' => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'),
            'date' => $value instanceof \DateTimeInterface ? $value->format('d-m-Y') : (string) $value,
            default => (string) $value,
        };
    }

    /** What the PDF and print page show: Indian grouping, ₹-free figures. */
    public function displayValue(array $col, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($col['type'] ?? 'text') {
            'money' => inr((float) $value),
            'qty' => rtrim(rtrim(number_format((float) $value, 3), '0'), '.'),
            'number' => number_format((int) $value),
            'percent' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . '%',
            'date' => $value instanceof \DateTimeInterface ? $value->format('d M Y') : (string) $value,
            default => (string) $value,
        };
    }
}
