<?php

namespace App\Reports;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One exportable list or register. A report knows its columns and how to
 * fetch its rows for the active company, using the same filters as the
 * screen it is exported from; ReportExporter turns it into Excel, CSV, PDF
 * or a print page, so every list in the app exports the same way.
 *
 * Column types drive formatting: text, date, money (2 decimals, Indian
 * grouping), qty (up to 3 decimals), number (integer), percent.
 */
abstract class Report
{
    public function __construct(protected Company $company, protected Request $request) {}

    abstract public function title(): string;

    /** @return array<int, array{key: string, label: string, type?: string, total?: bool}> */
    abstract public function columns(): array;

    /** @return iterable<array<string, mixed>> rows keyed by column key */
    abstract public function rows(): iterable;

    /** Filters / period line printed under the title. */
    public function subtitle(): string
    {
        [$from, $to] = $this->dateRange();
        $parts = [];
        if ($from || $to) {
            $parts[] = ($from ? $from->format('d M Y') : 'Beginning') . ' to ' . ($to ? $to->format('d M Y') : 'today');
        }
        foreach ($this->filterLabels() as $label) {
            $parts[] = $label;
        }

        return $parts ? implode(' · ', $parts) : 'All records';
    }

    /** Extra human-readable filter descriptions (status, search…). */
    protected function filterLabels(): array
    {
        $labels = [];
        if (filled($this->request->query('search'))) {
            $labels[] = 'Search: "' . Str::limit((string) $this->request->query('search'), 40) . '"';
        }

        return $labels;
    }

    /** Whether the period (from/to) filter applies; masters and aging say no. */
    public function usesDates(): bool
    {
        return true;
    }

    /** Sidebar / reports-hub grouping. */
    public function group(): string
    {
        return 'Registers';
    }

    public function landscape(): bool
    {
        return count($this->columns()) > 7;
    }

    public function filename(): string
    {
        return Str::slug($this->title()) . '-' . Str::slug($this->company->name) . '-' . now()->format('Y-m-d');
    }

    public function company(): Company
    {
        return $this->company;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} from ?from= / ?to=, ignoring junk. */
    protected function dateRange(): array
    {
        return [$this->parseDate($this->request->query('from')), $this->parseDate($this->request->query('to'))];
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (blank($value) || ! is_string($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
