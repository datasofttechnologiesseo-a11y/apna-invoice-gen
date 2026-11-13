<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Gstr2bEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The GSTR-2B the portal gives you, stored per month: read an upload, list the
 * months on file and export the match. The web page and the mobile app both
 * go through here; the match itself is App\Services\Gstr2bMatcher.
 */
class Gstr2bBook
{
    /** Rules for an upload: the 2B file, and the month when the file does not say. */
    public const UPLOAD_RULES = [
        'file' => ['required', 'file', 'max:20480'],
        'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
    ];

    /** The match's buckets, in the order the page and the export show them. */
    public const LABELS = [
        'matched' => 'Matched', 'mismatch' => 'Amounts differ', 'missing' => 'In 2B, missing in books',
        'not_in_2b' => 'In books, not in 2B yet', 'no_gstin' => 'Supplier has no GSTIN', 'notes' => 'Supplier credit/debit note',
        'no_itc' => 'In 2B, ITC not available',
    ];

    public function __construct(private readonly Gstr2bReader $reader) {}

    /** ?month=YYYY-MM, default last month (the 2B generated on the 14th). */
    public static function period(mixed $month): string
    {
        $m = is_string($month) ? $month : '';

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->subMonthNoOverflow()->format('Y-m');
    }

    /** Months with a 2B on file, latest first. */
    public function uploadedPeriods(Company $company): Collection
    {
        return Gstr2bEntry::where('company_id', $company->id)->distinct()->orderByDesc('period')->pluck('period');
    }

    /**
     * Read an uploaded 2B and replace that month's documents with it.
     * Problems with the file are thrown as a validation error on "file".
     *
     * @return array{period: string, documents: int}
     */
    public function import(Company $company, UploadedFile $file, ?string $month): array
    {
        try {
            $parsed = $this->reader->read($file);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'This file could not be read. Download the GSTR-2B again from the GST portal and upload it as it is.']);
        }

        if ($parsed['gstin'] && $company->gstin && $parsed['gstin'] !== strtoupper($company->gstin)) {
            throw ValidationException::withMessages(['file' => "This GSTR-2B is for GSTIN {$parsed['gstin']}, but this business is {$company->gstin}. Switch to that business first."]);
        }
        $period = $parsed['period'] ?? $month ?? self::period($month);
        if (! $parsed['entries']) {
            throw ValidationException::withMessages(['file' => 'This GSTR-2B has no B2B invoices or notes to match.']);
        }

        DB::transaction(function () use ($company, $period, $parsed) {
            Gstr2bEntry::where('company_id', $company->id)->where('period', $period)->delete();
            $now = now();
            foreach (array_chunk($parsed['entries'], 500) as $chunk) {
                Gstr2bEntry::insert(array_map(fn ($e) => $e + [
                    'company_id' => $company->id, 'period' => $period, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
        });
        AuditLog::record('gstr2b.uploaded', 'GSTR-2B for '.$period.' uploaded · '.count($parsed['entries']).' documents', null);

        return ['period' => $period, 'documents' => count($parsed['entries'])];
    }

    /** The match for one month as a CSV download for the CA. */
    public function export(Company $company, string $period): StreamedResponse
    {
        $r = app(Gstr2bMatcher::class)->match($company, $period);
        $labels = self::LABELS;

        return response()->streamDownload(function () use ($r, $labels, $company, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            csv_put($out, ['GSTR-2B match - '.$company->name.($company->gstin ? ' ('.$company->gstin.')' : '').' - '.$period]);
            csv_put($out, []);
            csv_put($out, ['Status', 'Supplier GSTIN', 'Supplier', 'Document no.', 'Date', '2B taxable', '2B tax', 'Books bill no.', 'Books taxable', 'Books tax', 'Tax difference']);
            $n = fn ($v) => $v === null ? '' : number_format((float) $v, 2, '.', '');
            foreach ($labels as $key => $label) {
                foreach ($r[$key] as $row) {
                    $e = $row['entry'] ?? null;
                    $b = $row['bill'] ?? null;
                    csv_put($out, [
                        $label,
                        $e?->supplier_gstin ?? $b?->vendor?->gstin,
                        $e?->supplier_name ?? $b?->vendor?->name,
                        $e?->document_number ?? $b?->bill_number,
                        ($e?->document_date ?? $b?->bill_date)?->format('d-m-Y'),
                        $e ? $n($e->taxable_value) : '', $e ? $n($e->tax()) : '',
                        $b?->bill_number, $b ? $n($b->subtotal) : '', $b ? $n($b->total_tax) : '',
                        isset($row['tax_diff']) ? $n($row['tax_diff']) : '',
                    ]);
                }
            }
            fclose($out);
        }, 'gstr2b-match-'.$period.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
