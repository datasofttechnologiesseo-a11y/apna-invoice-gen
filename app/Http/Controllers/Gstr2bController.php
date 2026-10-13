<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Gstr2bEntry;
use App\Services\Gstr2bMatcher;
use App\Services\Gstr2bReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GSTR-2B match: upload the 2B the portal gives you, see which purchase
 * invoices it confirms, which differ, and what is on one side only.
 */
class Gstr2bController extends Controller
{
    public function show(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        $period = $this->period($request);

        return view('finance.gstr2b', [
            'company' => $company,
            'period' => $period,
            'periodLabel' => Carbon::createFromFormat('!Y-m', $period)->format('F Y'),
            'result' => app(Gstr2bMatcher::class)->match($company, $period),
            'uploadedPeriods' => Gstr2bEntry::where('company_id', $company->id)->distinct()->orderByDesc('period')->pluck('period'),
        ]);
    }

    public function upload(Request $request, Gstr2bReader $reader): RedirectResponse
    {
        $company = $request->user()->ensureCompany();
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        try {
            $parsed = $reader->read($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'This file could not be read. Download the GSTR-2B again from the GST portal and upload it as it is.']);
        }

        if ($parsed['gstin'] && $company->gstin && $parsed['gstin'] !== strtoupper($company->gstin)) {
            return back()->withErrors(['file' => "This GSTR-2B is for GSTIN {$parsed['gstin']}, but this business is {$company->gstin}. Switch to that business first."]);
        }
        $period = $parsed['period'] ?? $request->input('month') ?? $this->period($request);
        if (! $parsed['entries']) {
            return back()->withErrors(['file' => 'This GSTR-2B has no B2B invoices or notes to match.']);
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
        AuditLog::record('gstr2b.uploaded', 'GSTR-2B for ' . $period . ' uploaded · ' . count($parsed['entries']) . ' documents', null);

        return redirect()->route('finance.gstr2b', ['month' => $period])
            ->with('status', 'GSTR-2B for ' . Carbon::createFromFormat('!Y-m', $period)->format('F Y') . ' read: ' . count($parsed['entries']) . ' documents. The match is below.');
    }

    public function export(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();
        $period = $this->period($request);
        $r = app(Gstr2bMatcher::class)->match($company, $period);
        $labels = [
            'matched' => 'Matched', 'mismatch' => 'Amounts differ', 'missing' => 'In 2B, missing in books',
            'not_in_2b' => 'In books, not in 2B yet', 'no_gstin' => 'Supplier has no GSTIN', 'notes' => 'Supplier credit/debit note',
            'no_itc' => 'In 2B, ITC not available',
        ];

        return response()->streamDownload(function () use ($r, $labels, $company, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            csv_put($out, ['GSTR-2B match - ' . $company->name . ($company->gstin ? ' (' . $company->gstin . ')' : '') . ' - ' . $period]);
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
        }, 'gstr2b-match-' . $period . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** ?month=YYYY-MM, default last month (the 2B generated on the 14th). */
    private function period(Request $request): string
    {
        $m = (string) $request->input('month', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->subMonthNoOverflow()->format('Y-m');
    }
}
