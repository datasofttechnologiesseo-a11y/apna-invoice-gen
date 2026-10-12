<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads the first sheet of an uploaded .xlsx / .ods / .csv into rows of
 * strings. The header row is the first row with at least two non-empty
 * cells, so a title line above the table (as Tally and most exports add)
 * is skipped automatically.
 */
class SpreadsheetReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array{line: int, cells: array<int, string>}>}
     */
    public function read(UploadedFile $file, int $maxRows): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $reader = match ($ext) {
            'xlsx' => new XlsxReader(),
            'ods' => new OdsReader(),
            'csv', 'txt' => new CsvReader($this->csvOptions($file->getRealPath())),
            default => throw new \InvalidArgumentException('Upload an Excel (.xlsx), OpenDocument (.ods) or CSV file.'),
        };

        $reader->open($file->getRealPath());
        $headers = null;
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(fn ($v) => $this->stringify($v), $row->toArray());
                    if ($headers === null) {
                        if (count(array_filter($cells, fn ($c) => $c !== '')) >= 2) {
                            $headers = $cells;
                        }
                        continue;
                    }
                    if (count(array_filter($cells, fn ($c) => $c !== '')) === 0) {
                        continue;
                    }
                    $rows[] = ['line' => $line, 'cells' => $cells];
                    if (count($rows) > $maxRows) {
                        break 2;
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    private function stringify(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            // Excel stores 9876543210 and 560001 as numbers; keep them whole.
            return number_format($v, 0, '.', '');
        }

        return trim((string) $v);
    }

    /** Excel in India often saves CSV with ";" or tabs; sniff the first line. */
    private function csvOptions(string $path): CsvOptions
    {
        $options = new CsvOptions();
        $first = (string) fgets(fopen($path, 'r'));
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $options->FIELD_DELIMITER = (string) array_key_first($counts);

        return $options;
    }
}
