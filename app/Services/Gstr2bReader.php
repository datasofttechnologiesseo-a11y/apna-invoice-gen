<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a GSTR-2B as the GST portal gives it - the JSON (also when it comes
 * zipped) or the Excel workbook - into plain document rows:
 *
 *   kind (invoice | credit_note | debit_note), supplier_gstin, supplier_name,
 *   document_number, document_date (Y-m-d), document_value, taxable_value,
 *   igst, cgst, sgst, cess, reverse_charge, itc_available, place_of_supply
 *
 * B2B invoices and their amendments (b2ba) are "invoice"; B2B-CDNR notes are
 * "credit_note" / "debit_note". Import of goods, ISD and TDS/TCS sections
 * are not read.
 *
 * @return array{gstin: ?string, period: ?string, entries: array<int, array>}
 */
class Gstr2bReader
{
    public const MAX_ENTRIES = 20000;

    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());

        return match ($ext) {
            'json' => $this->fromJson((string) file_get_contents($file->getRealPath())),
            'zip' => $this->fromZip($file->getRealPath()),
            'xlsx' => $this->fromXlsx($file->getRealPath()),
            default => throw new \InvalidArgumentException('Upload the GSTR-2B file from the GST portal: the JSON (or its .zip) or the Excel (.xlsx).'),
        };
    }

    private function fromZip(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('That .zip could not be opened. Download it from the portal again.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.json')) {
                    return $this->fromJson((string) $zip->getFromIndex($i));
                }
            }
        } finally {
            $zip->close();
        }
        throw new \InvalidArgumentException('No GSTR-2B JSON was found inside that .zip.');
    }

    public function fromJson(string $raw): array
    {
        $root = json_decode($raw, true);
        if (! is_array($root)) {
            throw new \InvalidArgumentException('This is not a GSTR-2B JSON file.');
        }
        $data = $root['data'] ?? $root;
        $doc = $data['docdata'] ?? $data['docData'] ?? null;
        if (! is_array($doc)) {
            throw new \InvalidArgumentException('No GSTR-2B documents were found in this file. Check that it is the GSTR-2B JSON.');
        }

        $entries = [];
        foreach (['b2b', 'b2ba'] as $section) {
            foreach ($doc[$section] ?? [] as $supplier) {
                foreach ($supplier['inv'] ?? [] as $inv) {
                    $entries[] = $this->entry('invoice', $supplier, $inv['inum'] ?? '', $inv, $inv['items'] ?? []);
                }
            }
        }
        foreach (['cdnr', 'cdnra'] as $section) {
            foreach ($doc[$section] ?? [] as $supplier) {
                foreach ($supplier['nt'] ?? [] as $nt) {
                    $kind = strtoupper((string) ($nt['typ'] ?? $nt['ntty'] ?? 'C')) === 'D' ? 'debit_note' : 'credit_note';
                    $entries[] = $this->entry($kind, $supplier, $nt['ntnum'] ?? $nt['nt_num'] ?? '', $nt, $nt['items'] ?? []);
                }
            }
        }

        $period = null;
        if (preg_match('/^(\d{2})(\d{4})$/', (string) ($data['rtnprd'] ?? ''), $m)) {
            $period = $m[2] . '-' . $m[1];
        }

        return ['gstin' => isset($data['gstin']) ? strtoupper((string) $data['gstin']) : null, 'period' => $period, 'entries' => $this->cap($entries)];
    }

    private function entry(string $kind, array $supplier, string $number, array $doc, array $items): array
    {
        $sum = fn (string $k) => round(array_sum(array_map(fn ($i) => (float) ($i[$k] ?? 0), $items)), 2);

        return [
            'kind' => $kind,
            'supplier_gstin' => strtoupper(trim((string) ($supplier['ctin'] ?? ''))),
            'supplier_name' => mb_substr(trim((string) ($supplier['trdnm'] ?? '')), 0, 200) ?: null,
            'document_number' => mb_substr(trim($number), 0, 40),
            'document_date' => $this->date($doc['dt'] ?? $doc['idt'] ?? null),
            'document_value' => round((float) ($doc['val'] ?? 0), 2),
            'taxable_value' => $sum('txval'),
            'igst' => $sum('igst'),
            'cgst' => $sum('cgst'),
            'sgst' => $sum('sgst'),
            'cess' => $sum('cess'),
            'reverse_charge' => strtoupper((string) ($doc['rev'] ?? 'N')) === 'Y',
            'itc_available' => strtoupper((string) ($doc['itcavl'] ?? 'Y')) !== 'N',
            'place_of_supply' => isset($doc['pos']) ? substr((string) $doc['pos'], 0, 2) : null,
        ];
    }

    /**
     * The portal's Excel: sheets "B2B" and "B2B-CDNR", a few title and
     * two-level heading rows, then one row per document (several rows when a
     * document has several rates - summed here).
     */
    public function fromXlsx(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);
        $entries = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $name = strtoupper(trim($sheet->getName()));
                $kindFor = match (true) {
                    $name === 'B2B' || $name === 'B2BA' => fn () => 'invoice',
                    str_contains($name, 'CDNR') => fn (string $type) => str_contains(strtolower($type), 'debit') ? 'debit_note' : 'credit_note',
                    default => null,
                };
                if ($kindFor === null) {
                    continue;
                }
                $headerRows = [];
                $map = null;
                $docs = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $row->toArray());
                    if ($map === null) {
                        if (preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/i', $cells[0] ?? '')) {
                            $map = $this->columnMap($headerRows);
                        } else {
                            $headerRows[] = $cells;
                            if (count($headerRows) > 12) {
                                $headerRows = array_slice($headerRows, -12);
                            }

                            continue;
                        }
                    }
                    if (! preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/i', $cells[0] ?? '')) {
                        continue;
                    }
                    $get = fn (string $k) => isset($map[$k]) ? ($cells[$map[$k]] ?? '') : '';
                    $num = fn (string $k) => round((float) preg_replace('/[^0-9.\-]/', '', $get($k)), 2);
                    $key = strtoupper($get('gstin')) . '|' . $get('number') . '|' . $get('type');
                    $d = $docs[$key] ?? [
                        'kind' => $kindFor($get('type')),
                        'supplier_gstin' => strtoupper($get('gstin')),
                        'supplier_name' => mb_substr($get('name'), 0, 200) ?: null,
                        'document_number' => mb_substr($get('number'), 0, 40),
                        'document_date' => $this->date($get('date')),
                        'document_value' => $num('value'),
                        'taxable_value' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0,
                        'reverse_charge' => strtoupper(substr($get('rev'), 0, 1)) === 'Y',
                        'itc_available' => strtoupper(substr($get('itc'), 0, 1)) !== 'N',
                        'place_of_supply' => null,
                    ];
                    foreach (['taxable_value' => 'taxable', 'igst' => 'igst', 'cgst' => 'cgst', 'sgst' => 'sgst', 'cess' => 'cess'] as $field => $col) {
                        $d[$field] = round($d[$field] + $num($col), 2);
                    }
                    $docs[$key] = $d;
                }
                array_push($entries, ...array_values($docs));
            }
        } finally {
            $reader->close();
        }
        if (! $entries) {
            throw new \InvalidArgumentException('No B2B documents were found. Upload the GSTR-2B Excel downloaded from the GST portal (sheets "B2B" and "B2B-CDNR").');
        }

        return ['gstin' => null, 'period' => null, 'entries' => $this->cap($entries)];
    }

    /** Column positions from the heading rows above the data. */
    private function columnMap(array $headerRows): array
    {
        $labels = [];
        foreach ($headerRows as $row) {
            foreach ($row as $i => $text) {
                if ($text !== '') {
                    $labels[$i] = strtolower(($labels[$i] ?? '') . ' ' . $text);
                }
            }
        }
        $find = function (string $pattern, ?string $not = null) use ($labels) {
            foreach ($labels as $i => $l) {
                if (preg_match($pattern, $l) && ($not === null || ! preg_match($not, $l))) {
                    return $i;
                }
            }

            return null;
        };

        return array_filter([
            'gstin' => 0,
            'name' => $find('/trade|legal name/'),
            'number' => $find('/(invoice|note) number|note no|invoice no/'),
            'type' => $find('/(invoice|note) type/'),
            'date' => $find('/(invoice|note) date/'),
            'value' => $find('/(invoice|note) value/'),
            'rev' => $find('/reverse charge/'),
            'taxable' => $find('/taxable value/'),
            'igst' => $find('/integrated tax/'),
            'cgst' => $find('/central tax/'),
            'sgst' => $find('/state\s*\/?\s*ut tax|state tax/'),
            'cess' => $find('/cess/'),
            'itc' => $find('/itc availability|itc available/'),
        ], fn ($v) => $v !== null);
    }

    private function date(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-M-Y', 'd-M-y', 'd.m.Y'] as $f) {
            try {
                $d = Carbon::createFromFormat('!' . $f, $v);
                if ($d && $d->format($f) === $v) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
            }
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cap(array $entries): array
    {
        $entries = array_values(array_filter($entries, fn ($e) => $e['supplier_gstin'] !== '' && $e['document_number'] !== ''));
        if (count($entries) > self::MAX_ENTRIES) {
            throw new \InvalidArgumentException('This GSTR-2B has more than ' . number_format(self::MAX_ENTRIES) . ' documents. Contact support to import it.');
        }

        return $entries;
    }
}
