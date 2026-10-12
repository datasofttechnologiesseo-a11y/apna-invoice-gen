<?php

namespace App\Services;

use App\Support\SafeExport;
use App\Models\Company;
use App\Support\ReturnPeriod;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GSTR-1 as an Excel workbook in the layout of the GST offline tool's Excel
 * template: one sheet per table (b2b, b2cl, b2cs, cdnr, cdnur, exemp,
 * hsn(b2b), hsn(b2c), docs), a summary block in rows 1-3, column headings in
 * row 4 and data from row 5. Dates are dd-Mmm-yyyy text and place of supply is
 * "27-Maharashtra", as the tool expects. A "Read me" sheet at the end carries
 * the table-wise totals and how they tie to GSTR-3B.
 */
class Gstr1Workbook
{
    private const MONEY = '0.00';

    public function download(Company $company, ReturnPeriod $period, array $data): StreamedResponse
    {
        $filename = 'GSTR1_' . ($company->gstin ?: 'unregistered') . '_' . $period->start->format('mY')
            . ($period->quarterly ? '_' . $period->end->format('mY') : '') . '.xlsx';

        return response()->streamDownload(function () use ($company, $period, $data) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $first = true;
            foreach ($this->sheets($data) as $name => $sheet) {
                $this->writeSheet($writer, $first, $name, $sheet);
                $first = false;
            }
            $this->writeReadMe($writer, $company, $period, $data);
            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array<string, array{title: string, summary: array, headings: array, rows: array, money: int[]}> */
    private function sheets(array $d): array
    {
        $date = fn ($v) => $v ? $v->format('d-M-Y') : '';
        $rate = fn ($v) => 0 + (float) $v;
        $sum = fn (array $rows, string $k) => round(array_sum(array_map(fn ($r) => (float) ($r[$k] ?? 0), $rows)), 2);
        $distinct = fn (array $rows, string $k) => count(array_unique(array_map(fn ($r) => $r[$k] ?? '', $rows)));
        // Invoice value is per invoice, not per rate line: count it once.
        $invoiceValue = function (array $rows) {
            $seen = [];
            foreach ($rows as $r) {
                $seen[$r['number']] = (float) $r['value'];
            }

            return round(array_sum($seen), 2);
        };
        $hsn = fn (string $tab) => array_values(array_filter($d['hsn'], fn ($r) => $r['tab'] === $tab));
        // Table 12 has had no "Total Value" since April 2021 (Notfn 79/2020).
        $hsnSheet = function (string $tab, string $title) use ($hsn, $sum, $distinct, $rate) {
            $rows = $hsn($tab);

            return [
                'title' => $title,
                'summary' => [['No. of HSN', '', '', '', '', 'Total Taxable Value', 'Total Integrated Tax', 'Total Central Tax', 'Total State/UT Tax', 'Total Cess'],
                    [$distinct($rows, 'hsn'), '', '', '', '', $sum($rows, 'taxable'), $sum($rows, 'igst'), $sum($rows, 'cgst'), $sum($rows, 'sgst'), 0.0]],
                'headings' => ['HSN', 'Description', 'UQC', 'Total Quantity', 'Rate', 'Taxable Value', 'Integrated Tax Amount', 'Central Tax Amount', 'State/UT Tax Amount', 'Cess Amount'],
                'rows' => array_map(fn ($r) => [$r['hsn'], $this->hsnDescription($r['description']), $this->uqc($r['uqc'], $r['hsn']),
                    str_starts_with(trim($r['hsn']), '99') ? 0 : $r['qty'],
                    $rate($r['rate']), $r['taxable'], $r['igst'], $r['cgst'], $r['sgst'], 0.0], $rows),
                'money' => [5, 6, 7, 8, 9],
            ];
        };

        return [
            'b2b' => [
                'title' => 'Summary For B2B, SEZ, DE (4A, 4B, 6B, 6C)',
                'summary' => [['No. of Recipients', '', 'No. of Invoices', '', 'Total Invoice Value', '', '', '', '', '', '', 'Total Taxable Value', 'Total Cess'],
                    [$distinct($d['b2b'], 'gstin'), '', $distinct($d['b2b'], 'number'), '', $invoiceValue($d['b2b']), '', '', '', '', '', '', $sum($d['b2b'], 'taxable'), 0.0]],
                'headings' => ['GSTIN/UIN of Recipient', 'Receiver Name', 'Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply',
                    'Reverse Charge', 'Applicable % of Tax Rate', 'Invoice Type', 'E-Commerce GSTIN', 'Rate', 'Taxable Value', 'Cess Amount'],
                'rows' => array_map(fn ($r) => [$r['gstin'], $r['name'], $r['number'], $date($r['date']), $r['value'], $r['pos'],
                    $r['rcm'], '', $r['type'], '', $rate($r['rate']), $r['taxable'], 0.0], $d['b2b']),
                'money' => [4, 11, 12],
            ],
            'b2cl' => [
                'title' => 'Summary For B2CL(5)',
                'summary' => [['No. of Invoices', '', 'Total Inv Value', '', '', '', 'Total Taxable Value', 'Total Cess', ''],
                    [$distinct($d['b2cl'], 'number'), '', $invoiceValue($d['b2cl']), '', '', '', $sum($d['b2cl'], 'taxable'), 0.0, '']],
                'headings' => ['Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount', 'E-Commerce GSTIN'],
                'rows' => array_map(fn ($r) => [$r['number'], $date($r['date']), $r['value'], $r['pos'], '', $rate($r['rate']), $r['taxable'], 0.0, ''], $d['b2cl']),
                'money' => [2, 6, 7],
            ],
            'b2cs' => [
                'title' => 'Summary For B2CS(7)',
                'summary' => [['', '', '', '', 'Total Taxable Value', 'Total Cess', ''],
                    ['', '', '', '', $sum($d['b2cs'], 'taxable'), 0.0, '']],
                'headings' => ['Type', 'Place Of Supply', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount', 'E-Commerce GSTIN'],
                'rows' => array_map(fn ($r) => [$r['type'], $r['pos'], '', $rate($r['rate']), $r['taxable'], 0.0, ''], $d['b2cs']),
                'money' => [4, 5],
            ],
            'cdnr' => [
                'title' => 'Summary For CDNR(9B)',
                'summary' => [['No. of Recipients', '', 'No. of Notes', '', '', '', '', '', 'Total Note Value', '', '', 'Total Taxable Value', 'Total Cess'],
                    [$distinct($d['cdnr'], 'gstin'), '', $distinct($d['cdnr'], 'number'), '', '', '', '', '', $invoiceValue($d['cdnr']), '', '', $sum($d['cdnr'], 'taxable'), 0.0]],
                'headings' => ['GSTIN/UIN of Recipient', 'Receiver Name', 'Note Number', 'Note Date', 'Note Type', 'Place Of Supply',
                    'Reverse Charge', 'Note Supply Type', 'Note Value', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount'],
                'rows' => array_map(fn ($r) => [$r['gstin'], $r['name'], $r['number'], $date($r['date']), $r['type'], $r['pos'],
                    $r['rcm'], $r['supply_type'], $r['value'], '', $rate($r['rate']), $r['taxable'], 0.0], $d['cdnr']),
                'money' => [8, 11, 12],
            ],
            'cdnur' => [
                'title' => 'Summary For CDNUR(9B)',
                'summary' => [['', 'No. of Notes', '', '', '', 'Total Note Value', '', '', 'Total Taxable Value', 'Total Cess'],
                    ['', $distinct($d['cdnur'], 'number'), '', '', '', $invoiceValue($d['cdnur']), '', '', $sum($d['cdnur'], 'taxable'), 0.0]],
                'headings' => ['UR Type', 'Note Number', 'Note Date', 'Note Type', 'Place Of Supply', 'Note Value', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount'],
                'rows' => array_map(fn ($r) => [$r['ur_type'], $r['number'], $date($r['date']), $r['type'], $r['pos'], $r['value'], '', $rate($r['rate']), $r['taxable'], 0.0], $d['cdnur']),
                'money' => [5, 8, 9],
            ],
            'exp' => [
                'title' => 'Summary For EXP(6)',
                'summary' => [['', 'No. of Invoices', '', 'Total Invoice Value', '', 'No. of Shipping Bill', '', '', 'Total Taxable Value', 'Total Cess'],
                    ['', $distinct($d['exp'] ?? [], 'number'), '', $invoiceValue($d['exp'] ?? []), '',
                        count(array_unique(array_filter(array_column($d['exp'] ?? [], 'shipping_bill_number')))), '', '', $sum($d['exp'] ?? [], 'taxable'), 0.0]],
                'headings' => ['Export Type', 'Invoice Number', 'Invoice date', 'Invoice Value', 'Port Code', 'Shipping Bill Number', 'Shipping Bill Date', 'Rate', 'Taxable Value', 'Cess Amount'],
                'rows' => array_map(fn ($r) => [$r['exp_typ'], $r['number'], $date($r['date']), $r['value'], (string) ($r['port_code'] ?? ''),
                    (string) ($r['shipping_bill_number'] ?? ''), $date($r['shipping_bill_date'] ?? null), $rate($r['rate']), $r['taxable'], 0.0], $d['exp'] ?? []),
                'money' => [3, 8, 9],
            ],
            'exemp' => [
                'title' => 'Summary For Nil rated, exempted and non GST outward supplies (8)',
                'summary' => [['', 'Total Nil Rated Supplies', 'Total Exempted Supplies', 'Total Non-GST Supplies'],
                    ['', $sum($d['exemp'], 'nil'), $sum($d['exemp'], 'exempt'), $sum($d['exemp'], 'non_gst')]],
                'headings' => ['Description', 'Nil Rated Supplies', 'Exempted(other than nil rated/non GST supply)', 'Non-GST Supplies'],
                'rows' => array_map(fn ($r) => [$r['description'], $r['nil'], $r['exempt'], $r['non_gst']], $d['exemp']),
                'money' => [1, 2, 3],
            ],
            'hsn(b2b)' => $hsnSheet('B2B', 'Summary For HSN(12) - B2B'),
            'hsn(b2c)' => $hsnSheet('B2C', 'Summary For HSN(12) - B2C'),
            'docs' => [
                'title' => 'Summary of documents issued during the tax period (13)',
                'summary' => [['', '', '', 'Total Number', 'Total Cancelled'],
                    ['', '', '', array_sum(array_column($d['docs'], 'total')), array_sum(array_column($d['docs'], 'cancelled'))]],
                'headings' => ['Nature of Document', 'Sr. No. From', 'Sr. No. To', 'Total Number', 'Cancelled'],
                'rows' => array_map(fn ($r) => [$r['nature'], $r['from'], $r['to'], $r['total'], $r['cancelled']], $d['docs']),
                'money' => [],
            ],
        ];
    }

    private function writeSheet(Writer $writer, bool $first, string $name, array $sheet): void
    {
        $ws = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $ws->setName($name);
        $ws->setSheetView((new SheetView())->setFreezeRow(5));
        foreach ($sheet['headings'] as $i => $h) {
            $ws->setColumnWidth(max(12, min(40, mb_strlen($h) + 4)), $i + 1);
        }

        $bold = (new Style())->setFontBold();
        $head = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('167C88');
        $money = (new Style())->setFormat(self::MONEY);
        $plain = new Style();

        $writer->addRow(SafeExport::row([$sheet['title']], $bold));
        $writer->addRow(SafeExport::row($sheet['summary'][0], $bold));
        $writer->addRow(new Row(array_map(
            fn ($v, $i) => SafeExport::cell($v, in_array($i, $sheet['money'], true) && is_float($v) ? $money : $plain),
            $sheet['summary'][1], array_keys($sheet['summary'][1]),
        )));
        $writer->addRow(SafeExport::row($sheet['headings'], $head));
        foreach ($sheet['rows'] as $row) {
            $writer->addRow(new Row(array_map(
                fn ($v, $i) => SafeExport::cell(is_float($v) ? round($v, 2) : $v, in_array($i, $sheet['money'], true) ? $money : $plain),
                $row, array_keys($row),
            )));
        }
    }

    private function writeReadMe(Writer $writer, Company $company, ReturnPeriod $period, array $d): void
    {
        $ws = $writer->addNewSheetAndMakeItCurrent();
        $ws->setName('Read me');
        $ws->setColumnWidth(10, 1);
        $ws->setColumnWidth(44, 2);
        foreach ([3, 4, 5, 6, 7] as $c) {
            $ws->setColumnWidth(16, $c);
        }
        $bold = (new Style())->setFontBold();
        $money = (new Style())->setFormat(self::MONEY);
        $head = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('167C88');

        $writer->addRow(SafeExport::row(['GSTR-1 - ' . $company->name . ($company->gstin ? ' (GSTIN ' . $company->gstin . ')' : '')], (new Style())->setFontBold()->setFontSize(13)));
        $writer->addRow(SafeExport::row(['Return period: ' . $period->label() . ' (' . $period->start->format('d-M-Y') . ' to ' . $period->end->format('d-M-Y') . ')  ·  Due ' . $period->gstr1Due()->format('d-M-Y')]));
        $writer->addRow(SafeExport::row(['Generated ' . now()->format('d-M-Y h:i A') . ' by Apna Invoice. Sheets b2b ... docs follow the GST offline tool Excel template (headings in row 4, data from row 5).']));
        $writer->addRow(SafeExport::row([]));
        $writer->addRow(SafeExport::row(['Table', 'Section', 'Records', 'Taxable value', 'IGST', 'CGST', 'SGST/UTGST'], $head));
        foreach ($d['tables'] as $t) {
            $writer->addRow(new Row([
                SafeExport::cell($t['table']), SafeExport::cell($t['label']), SafeExport::cell($t['records']),
                SafeExport::cell($t['taxable'], $money), SafeExport::cell($t['igst'], $money),
                SafeExport::cell($t['cgst'], $money), SafeExport::cell($t['sgst'], $money),
            ]));
        }
        $writer->addRow(SafeExport::row([]));
        $s = $d['summary'];
        $writer->addRow(SafeExport::row(['', 'Ties to GSTR-3B for the same period'], $bold));
        $writer->addRow(new Row([SafeExport::cell('3.1(a)'), SafeExport::cell('Outward taxable supplies, net of credit notes'), SafeExport::cell(''),
            SafeExport::cell($s['taxable'], $money), SafeExport::cell($s['igst'], $money), SafeExport::cell($s['cgst'], $money), SafeExport::cell($s['sgst'], $money)]));
        $writer->addRow(new Row([SafeExport::cell('3.1(b)'), SafeExport::cell('Zero rated (exports, SEZ), net of credit notes'), SafeExport::cell(''),
            SafeExport::cell($s['zero_taxable'], $money), SafeExport::cell($s['zero_igst'], $money)]));
        $writer->addRow(new Row([SafeExport::cell('3.1(c)'), SafeExport::cell('Nil rated, net of credit notes'), SafeExport::cell(''), SafeExport::cell($s['nil'], $money)]));
        $writer->addRow(new Row([SafeExport::cell('-'), SafeExport::cell('Reverse-charge sales (tax paid by recipient, not in 3B)'), SafeExport::cell(''), SafeExport::cell($s['rcm_taxable'], $money)]));
        $writer->addRow(SafeExport::row([]));
        $writer->addRow(SafeExport::row(['', 'Not covered (fill on the portal if you have them): deemed exports (6C), advances (11), e-commerce (14/15), amendments (9A/9C/10).']));
    }

    /** The offline tool rejects special characters in the HSN description. */
    private function hsnDescription(?string $text): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 ,\-\/()&]/', ' ', (string) $text))), 0, 30);
    }

    /**
     * UQC as the offline tool lists it ("NOS-NUMBERS"). Services (SAC 99xxxx)
     * take "NA" with no quantity; free-text units fall back to OTH-OTHERS.
     */
    private function uqc(string $unit, string $hsn = ''): string
    {
        if (str_starts_with(trim($hsn), '99')) {
            return 'NA';
        }
        $names = [
            'NOS' => 'NUMBERS', 'PCS' => 'PIECES', 'BOX' => 'BOX', 'BAG' => 'BAGS', 'BTL' => 'BOTTLES', 'BDL' => 'BUNDLES',
            'CAN' => 'CANS', 'CBM' => 'CUBIC METERS', 'CCM' => 'CUBIC CENTIMETERS', 'DOZ' => 'DOZENS', 'DRM' => 'DRUMS',
            'GMS' => 'GRAMMES', 'GGK' => 'GREAT GROSS', 'GRS' => 'GROSS', 'KGS' => 'KILOGRAMS', 'KLR' => 'KILOLITRE',
            'KME' => 'KILOMETRE', 'LTR' => 'LITRES', 'MLT' => 'MILILITRE', 'MTR' => 'METERS', 'MTS' => 'METRIC TON',
            'PAC' => 'PACKS', 'PRS' => 'PAIRS', 'QTL' => 'QUINTAL', 'ROL' => 'ROLLS', 'SET' => 'SETS', 'SQF' => 'SQUARE FEET',
            'SQM' => 'SQUARE METERS', 'TBS' => 'TABLETS', 'TON' => 'TONNES', 'TUB' => 'TUBES', 'UNT' => 'UNITS', 'YDS' => 'YARDS',
        ];
        $alias = ['NO' => 'NOS', 'PC' => 'PCS', 'KG' => 'KGS', 'G' => 'GMS', 'GM' => 'GMS', 'L' => 'LTR', 'ML' => 'MLT',
            'M' => 'MTR', 'UNIT' => 'UNT', 'DOZEN' => 'DOZ', 'PAIR' => 'PRS'];
        $code = strtoupper(trim($unit));
        $code = $alias[$code] ?? $code;

        return isset($names[$code]) ? $code . '-' . $names[$code] : 'OTH-OTHERS';
    }
}
