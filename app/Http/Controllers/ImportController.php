<?php

namespace App\Http\Controllers;

use App\Imports\Importer;
use App\Models\AuditLog;
use App\Services\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel / CSV import in three steps, the way users expect from Vyapar and
 * Zoho: download a template, upload, check a row-by-row preview, confirm.
 * Nothing is written until the preview is confirmed, and only rows marked
 * Ready are added - duplicates of existing records are skipped, never merged.
 */
class ImportController extends Controller
{
    private const IMPORTERS = [
        'customers' => \App\Imports\CustomerImporter::class,
        'products' => \App\Imports\ProductImporter::class,
        'vendors' => \App\Imports\VendorImporter::class,
    ];

    public function show(Request $request, string $type): View
    {
        return view('imports.show', ['importer' => $this->importer($request, $type)]);
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        $importer = $this->importer($request, $type);
        $fields = $importer->fields();

        return response()->streamDownload(function () use ($importer, $fields) {
            $options = new Options();
            foreach (array_values($fields) as $i => $f) {
                $options->setColumnWidth(max(14, mb_strlen($f['label']) + 6), $i + 1);
            }
            $writer = new Writer($options);
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName($importer->title());

            $head = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('167C88');
            $writer->addRow(Row::fromValues(array_map(fn ($f) => $f['label'] . (! empty($f['required']) ? ' *' : ''), array_values($fields)), $head));
            $writer->addRow(Row::fromValues(array_map(fn ($f) => $f['example'], array_values($fields)), (new Style())->setFontColor('6B7280')));

            $help = $writer->addNewSheetAndMakeItCurrent();
            $help->setName('Instructions');
            $writer->addRow(Row::fromValues(['How to fill this sheet'], (new Style())->setFontBold()->setFontSize(13)));
            $writer->addRow(Row::fromValues(['Fill one row per record on the first sheet. Replace or delete the grey example row. Columns marked * are required.']));
            $writer->addRow(Row::fromValues(['Records that already exist in Apna Invoice are skipped, never changed.']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Column', 'Required', 'What to enter', 'Example'], (new Style())->setFontBold()));
            foreach ($fields as $f) {
                $writer->addRow(Row::fromValues([$f['label'], ! empty($f['required']) ? 'Yes' : 'No', $f['help'], $f['example']]));
            }
            $writer->close();
        }, 'apna-invoice-' . $importer->key() . '-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request, SpreadsheetReader $reader, string $type): View|RedirectResponse
    {
        $importer = $this->importer($request, $type);
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt,ods'],
        ], [
            'file.mimes' => 'Upload an Excel (.xlsx), OpenDocument (.ods) or CSV file. Older .xls files: open in Excel and "Save As" .xlsx.',
            'file.max' => 'The file is larger than 5 MB. Split it into smaller files.',
        ]);

        try {
            $sheet = $reader->read($request->file('file'), Importer::MAX_ROWS);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Could not read this file: ' . ($e instanceof \InvalidArgumentException ? $e->getMessage() : 'it may be damaged or password-protected.')]);
        }

        // Match the sheet's headers to our fields; report what was ignored.
        $map = [];
        $unmatched = [];
        foreach ($sheet['headers'] as $i => $h) {
            if ($h === '') {
                continue;
            }
            $field = $importer->fieldForHeader($h);
            if ($field && ! in_array($field, $map, true)) {
                $map[$i] = $field;
            } else {
                $unmatched[] = $h;
            }
        }
        $missingRequired = collect($importer->fields())
            ->filter(fn ($f, $k) => ! empty($f['required']) && ! in_array($k, $map, true))
            // State can come from the GSTIN column, so it is not missing if GSTIN is there.
            ->reject(fn ($f, $k) => $k === 'state' && in_array('gstin', $map, true))
            ->map(fn ($f) => $f['label'])->values()->all();

        if (! $map || $missingRequired) {
            return back()->withErrors(['file' => $map
                ? 'These required columns were not found: ' . implode(', ', $missingRequired) . '. Download the template to see the expected headings.'
                : 'No recognisable column headings were found. Download the template and copy your data into it.']);
        }
        if (count($sheet['rows']) > Importer::MAX_ROWS) {
            return back()->withErrors(['file' => 'The file has more than ' . number_format(Importer::MAX_ROWS) . ' rows. Split it and import in parts.']);
        }
        if (! $sheet['rows']) {
            return back()->withErrors(['file' => 'The file has headings but no data rows.']);
        }

        $results = [];
        $seen = [];
        foreach ($sheet['rows'] as $r) {
            $row = [];
            foreach ($map as $i => $field) {
                $row[$field] = trim((string) ($r['cells'][$i] ?? ''));
            }
            $res = $importer->prepare($row);
            $status = 'ready';
            $note = null;
            if ($res['errors']) {
                $status = 'error';
            } elseif ($res['duplicate']) {
                $status = 'duplicate';
                $note = $res['duplicate'];
            } else {
                $key = $importer->fileKey($res['data']);
                if (isset($seen[$key])) {
                    $status = 'duplicate';
                    $note = 'Same as row ' . $seen[$key] . ' in this file';
                } else {
                    $seen[$key] = $r['line'];
                }
            }
            $results[] = ['line' => $r['line'], 'input' => $row, 'data' => $res['data'], 'errors' => $res['errors'], 'status' => $status, 'note' => $note];
        }

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($request, $token), [
            'type' => $type,
            'company_id' => $request->user()->ensureCompany()->id,
            'results' => $results,
        ], now()->addMinutes(30));

        return view('imports.preview', [
            'importer' => $importer,
            'results' => $results,
            'token' => $token,
            'fileName' => $request->file('file')->getClientOriginalName(),
            'unmatched' => $unmatched,
            // Every column that was read from the file, in template order.
            'mapped' => array_values(array_filter(array_keys($importer->fields()), fn ($k) => in_array($k, $map, true))),
            'counts' => collect($results)->countBy('status')->all() + ['ready' => 0, 'error' => 0, 'duplicate' => 0],
        ]);
    }

    public function confirm(Request $request, string $type): RedirectResponse
    {
        $importer = $this->importer($request, $type);
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $key = $this->cacheKey($request, $data['token']);
        $stash = Cache::pull($key);

        if (! $stash || $stash['type'] !== $type) {
            return redirect()->route('imports.show', $type)->withErrors(['file' => 'This preview has expired. Upload the file again.']);
        }

        // The preview was checked against one firm; switching firm in another
        // tab before confirming would have written the rows to the other one.
        if ((int) ($stash['company_id'] ?? 0) !== (int) $request->user()->ensureCompany()->id) {
            return redirect()->route('imports.show', $type)->withErrors(['file' => 'You switched to a different firm after the preview. Upload the file again for this firm.']);
        }

        // Re-check every row now: the same file confirmed from two previews
        // (or rows added by hand meanwhile) must not create duplicates.
        $ready = array_values(array_filter($stash['results'], fn ($r) => $r['status'] === 'ready'));
        $created = 0;
        DB::transaction(function () use ($importer, $ready, &$created) {
            foreach ($ready as $r) {
                $res = $importer->prepare($r['input']);
                if ($res['errors'] || $res['duplicate'] || ! $res['data']) {
                    continue;
                }
                $importer->create($res['data']);
                $created++;
            }
        });
        $ready = array_slice($ready, 0, $created);

        AuditLog::record($type . '.imported', count($ready) . ' ' . Str::lower($importer->title()) . ' imported from a spreadsheet', null);

        $skipped = count($stash['results']) - count($ready);

        return redirect()->to($importer->listRoute())->with('status',
            count($ready) . ' ' . Str::lower($importer->title()) . ' imported.' . ($skipped ? " {$skipped} " . Str::plural('row', $skipped) . ' skipped (errors or already present).' : ''));
    }

    private function importer(Request $request, string $type): Importer
    {
        abort_unless(isset(self::IMPORTERS[$type]), 404);
        $class = self::IMPORTERS[$type];

        return new $class($request->user()->ensureCompany(), $request->user());
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'import:' . $request->user()->id . ':' . $token;
    }
}
