<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gstr2bEntry;
use App\Models\PurchaseBill;
use App\Services\Gstr2bBook;
use App\Services\Gstr2bMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON API for the GSTR-2B match: upload the 2B file from the portal, see
 * the match for a month, export it as CSV. Same reader, matcher and export
 * as the web page (App\Services\Gstr2bBook, Gstr2bMatcher).
 */
class Gstr2bController extends Controller
{
    public function __construct(private readonly Gstr2bBook $book) {}

    /** ?month=YYYY-MM (default last month). */
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();

        return response()->json($this->payload($company, Gstr2bBook::period($request->input('month'))));
    }

    /** Multipart: file (the 2B JSON/Excel), month (YYYY-MM, used when the file does not say). */
    public function upload(Request $request): JsonResponse
    {
        $company = $request->user()->ensureCompany();
        $request->validate(Gstr2bBook::UPLOAD_RULES);

        $done = $this->book->import($company, $request->file('file'), $request->input('month'));
        $period = $done['period'];

        return response()->json([
            'message' => 'GSTR-2B for '.Carbon::createFromFormat('!Y-m', $period)->format('F Y').' read: '.$done['documents'].' documents.',
            'documents' => $done['documents'],
        ] + $this->payload($company, $period), 201);
    }

    public function export(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();

        return $this->book->export($company, Gstr2bBook::period($request->input('month')));
    }

    private function payload($company, string $period): array
    {
        $result = app(Gstr2bMatcher::class)->match($company, $period);

        $buckets = [];
        foreach (array_keys(Gstr2bBook::LABELS) as $key) {
            $buckets[$key] = array_map(fn (array $row) => [
                'entry' => isset($row['entry']) ? $this->entry($row['entry']) : null,
                'bill' => isset($row['bill']) ? $this->bill($row['bill']) : null,
                'taxable_diff' => $row['taxable_diff'] ?? null,
                'tax_diff' => $row['tax_diff'] ?? null,
            ], $result[$key]);
        }

        $summary = $result['summary'];
        $summary['uploaded_at'] = $summary['uploaded_at'] ? Carbon::parse($summary['uploaded_at'])->toIso8601String() : null;

        return [
            'period' => $period,
            'period_label' => Carbon::createFromFormat('!Y-m', $period)->format('F Y'),
            'uploaded_periods' => $this->book->uploadedPeriods($company)->values(),
            'labels' => Gstr2bBook::LABELS,
            'summary' => $summary,
            'buckets' => $buckets,
        ];
    }

    private function entry(Gstr2bEntry $e): array
    {
        return [
            'id' => $e->id,
            'kind' => $e->kind,
            'supplier_gstin' => $e->supplier_gstin,
            'supplier_name' => $e->supplier_name,
            'document_number' => $e->document_number,
            'document_date' => optional($e->document_date)->toDateString(),
            'document_value' => (float) $e->document_value,
            'taxable_value' => (float) $e->taxable_value,
            'igst' => (float) $e->igst,
            'cgst' => (float) $e->cgst,
            'sgst' => (float) $e->sgst,
            'cess' => (float) $e->cess,
            'tax' => $e->tax(),
            'reverse_charge' => (bool) $e->reverse_charge,
            'itc_available' => (bool) $e->itc_available,
            'place_of_supply' => $e->place_of_supply,
        ];
    }

    private function bill(PurchaseBill $b): array
    {
        return [
            'id' => $b->id,
            'bill_number' => $b->bill_number,
            'bill_date' => optional($b->bill_date)->toDateString(),
            'vendor_id' => $b->vendor_id,
            'vendor_name' => $b->vendor?->name,
            'vendor_gstin' => $b->vendor?->gstin,
            'subtotal' => (float) $b->subtotal,
            'total_tax' => (float) $b->total_tax,
            'grand_total' => (float) $b->grand_total,
        ];
    }
}
