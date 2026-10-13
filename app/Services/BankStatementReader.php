<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a bank statement exported from net banking (Excel .xlsx or CSV) into
 * lines: date, description, reference, debit, credit, balance.
 *
 * Indian banks put account details above the table and name the columns
 * differently (HDFC "Withdrawal Amt." / "Deposit Amt.", SBI "Debit" /
 * "Credit", ICICI "Withdrawal Amount (INR)", some a single "Amount" with a
 * "Dr/Cr" column). The table starts at the first row that has a date column
 * and money columns; everything above it is skipped, as are opening/closing
 * balance and total rows.
 */
class BankStatementReader
{
    public const MAX_LINES = 5000;

    /** Lines with an amount whose date could not be read, from the last read(). */
    public int $unreadable = 0;

    /** @return array<int, array{date: string, description: string, reference: ?string, debit: float, credit: float, balance: ?float}> */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $rows = match ($ext) {
            'xlsx' => $this->rows(new XlsxReader(), $file->getRealPath()),
            'csv', 'txt' => $this->rows(new CsvReader($this->csvOptions($file->getRealPath())), $file->getRealPath()),
            'xls' => throw new \InvalidArgumentException('This is an old Excel (.xls) file. Open it in Excel and "Save As" .xlsx, or download the statement as CSV.'),
            default => throw new \InvalidArgumentException('Upload the statement as an Excel (.xlsx) or CSV file.'),
        };

        return $this->parse($rows);
    }

    /** @param array<int, array<int, string>> $rows */
    public function parse(array $rows): array
    {
        [$headerAt, $map] = $this->findHeader($rows);
        if ($headerAt === null) {
            throw new \InvalidArgumentException('Transactions table not found. The statement needs a Date column and Debit/Credit (or Withdrawal/Deposit) columns.');
        }

        $lines = [];
        $this->unreadable = 0;
        foreach (array_slice($rows, $headerAt + 1) as $cells) {
            $get = fn (?int $i) => $i === null ? '' : trim((string) ($cells[$i] ?? ''));
            $date = $this->date($get($map['date']));
            if (! $date) {
                // A blank line, a total, the footer - or a real line whose date
                // could not be read: count those so the user is told.
                $money = isset($map['amount']) ? $get($map['amount']) : ($get($map['debit'] ?? null) . $get($map['credit'] ?? null));
                if ($get($map['date']) !== '' && preg_match('/\d/', $money) && abs($this->money($money)) > 0) {
                    $this->unreadable++;
                }

                continue;
            }
            $debit = $credit = 0.0;
            if (isset($map['amount'])) {
                $amount = $this->money($get($map['amount']));
                // The Dr/Cr column as letters only: "C", "CR", "Cr.", "Credit",
                // "Deposit" mark money in; "D", "DR", "Dr.", "Debit",
                // "Withdrawal" money out. Reading only "CR" as a credit turned
                // every "C" deposit into a withdrawal.
                $type = strtoupper(preg_replace('/[^A-Za-z]/', '', $get($map['type'] ?? null)));
                $raw = strtoupper($get($map['amount']));
                $typeCredit = $type !== '' && ($type === 'C' || str_contains($type, 'CR') || str_starts_with($type, 'DEP'));
                $typeDebit = $type !== '' && ! $typeCredit && ($type === 'D' || str_contains($type, 'DR') || str_starts_with($type, 'DEB') || str_starts_with($type, 'WITH'));
                $isCredit = $typeCredit
                    || (! $typeDebit && (str_contains($raw, 'CR') || (! $type && $amount > 0 && ! str_contains($raw, 'DR'))));
                $isCredit ? $credit = abs($amount) : $debit = abs($amount);
            } else {
                $debit = abs($this->money($get($map['debit'] ?? null)));
                $credit = abs($this->money($get($map['credit'] ?? null)));
            }
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }
            $desc = $get($map['description'] ?? null);
            $lines[] = [
                'date' => $date,
                'description' => mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 255),
                'reference' => mb_substr($get($map['reference'] ?? null), 0, 80) ?: null,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => isset($map['balance']) && $get($map['balance']) !== '' ? round($this->money($get($map['balance'])), 2) : null,
            ];
            if (count($lines) > self::MAX_LINES) {
                throw new \InvalidArgumentException('The statement has more than ' . number_format(self::MAX_LINES) . ' lines. Upload it a few months at a time.');
            }
        }
        if (! $lines) {
            throw new \InvalidArgumentException('No transactions were found below the column headings.');
        }

        return $lines;
    }

    /** @return array{0: ?int, 1: array<string, int>} */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 60, true) as $at => $cells) {
            $map = [];
            foreach ($cells as $i => $text) {
                $t = strtolower(trim((string) $text));
                if ($t === '') {
                    continue;
                }
                $set = function (string $key) use (&$map, $i) {
                    $map[$key] ??= $i;
                };
                match (true) {
                    (bool) preg_match('/^(txn |tran |transaction |post(ing)? )?date|^date$|value d(a)?t(e)?/', $t) && ! str_contains($t, 'value') => $set('date'),
                    (bool) preg_match('/value d(a)?t(e)?/', $t) => $set('value_date'),
                    (bool) preg_match('/withdrawal|debit|^dr\.?$|paid out|amount out/', $t) && ! str_contains($t, 'credit') => $set('debit'),
                    // "Amount in" is money in; "Amount in INR" / "Amount in Rs" is the
                    // one amount column, signed by the Dr/Cr column.
                    (bool) preg_match('/deposit|credit|^cr\.?$|paid in|amount in(?!\s*(inr|rs\b|rupees|₹))/u', $t) => $set('credit'),
                    (bool) preg_match('/balance/', $t) => $set('balance'),
                    (bool) preg_match('/narration|description|particulars|remarks|details|transaction remarks/', $t) => $set('description'),
                    (bool) preg_match('/chq|cheque|ref|utr|instrument/', $t) => $set('reference'),
                    (bool) preg_match('/^(dr|cr)\s*\/\s*(cr|dr)$|^type$|cr\/dr|dr\/cr/', $t) => $set('type'),
                    (bool) preg_match('/^amount|txn amount|transaction amount/', $t) => $set('amount'),
                    default => null,
                };
            }
            $map['date'] ??= $map['value_date'] ?? null;
            unset($map['value_date']);
            if (isset($map['date']) && (isset($map['debit']) || isset($map['credit']) || isset($map['amount']))) {
                if (isset($map['debit']) || isset($map['credit'])) {
                    unset($map['amount'], $map['type']);
                }

                return [$at, array_filter($map, fn ($v) => $v !== null)];
            }
        }

        return [null, []];
    }

    private function rows($reader, string $path): array
    {
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_float($v) && floor($v) === $v && abs($v) < 1e15 ? number_format($v, 0, '.', '') : trim((string) $v)), $row->toArray());
                    if (count($rows) > self::MAX_LINES + 100) {
                        break 2;
                    }
                }
                break; // first sheet
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function money(string $v): float
    {
        $v = preg_replace('/\b(cr|dr|inr|rs)\b\.?/i', '', $v);
        $neg = str_contains($v, '(') && str_contains($v, ')');
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $v);

        return is_numeric($clean) ? ($neg ? -1 : 1) * (float) $clean : 0.0;
    }

    private function date(string $v): ?string
    {
        $v = trim(preg_replace('/\s+/', ' ', $v));
        if ($v === '' || ! preg_match('/\d/', $v)) {
            return null;
        }
        // "05-OCT-2026", "5/10/26", "05 Oct 2026 10:15": day first, as Indian
        // banks write it. A format counts only if it reads the whole value
        // without warnings (31/02 does not roll over into March).
        $v = preg_replace_callback('/[A-Za-z]{3,}/', fn ($m) => ucfirst(strtolower($m[0])), $v);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y', 'd-M-Y', 'd M Y', 'd-M-y', 'd M y', 'd/M/Y', 'd/M/y', 'M d, Y', 'd F Y',
            'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd M Y H:i', 'd-M-Y H:i:s'] as $f) {
            $d = \DateTime::createFromFormat('!' . $f, $v);
            $errors = \DateTime::getLastErrors();
            if ($d && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $year = (int) $d->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return Carbon::instance($d)->toDateString();
                }
                // "26" read as year 0026 by a four-digit format: try the next one.
            }
        }

        return null;
    }

    private function csvOptions(string $path): CsvOptions
    {
        $options = new CsvOptions();
        $sample = (string) file_get_contents($path, false, null, 0, 4096);
        $counts = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t"), '|' => substr_count($sample, '|')];
        arsort($counts);
        $options->FIELD_DELIMITER = (string) array_key_first($counts);

        return $options;
    }
}
