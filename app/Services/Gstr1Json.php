<?php

namespace App\Services;

use App\Models\Company;
use App\Support\ReturnPeriod;

/**
 * GSTR-1 as the JSON file the GST portal accepts under Returns > GSTR-1 >
 * Prepare Offline > Upload - the same file the offline tool produces, built
 * from the same GSTR-1 build as the page, the Excel and GSTR-3B.
 *
 * Shape (GSTN offline-tool schema): gstin, fp (MMYYYY; the last month of the
 * quarter for quarterly filers), b2b, b2cl, b2cs, cdnr, cdnur, exp, nil,
 * hsn {hsn_b2b, hsn_b2c}, doc_issue. Dates dd-mm-yyyy; place of supply as
 * the two-digit state code; services carry UQC "NA" and no quantity; Table
 * 12 has no "total value" (dropped from the form in April 2021).
 * Empty sections are left out, as the offline tool does.
 */
class Gstr1Json
{
    public const VERSION = 'GST3.2.4';

    /** Document-type codes of Table 13 on the portal. */
    private const DOC_INVOICE = 1;
    private const DOC_CREDIT_NOTE = 5;

    public function build(Company $company, ReturnPeriod $period, array $d): array
    {
        $home = (string) ($company->state?->gst_code ?? substr((string) $company->gstin, 0, 2));
        $money = fn ($v) => round((float) $v, 2);
        $date = fn ($v) => $v ? $v->format('d-m-Y') : null;
        $pos = fn (?string $p) => substr((string) $p, 0, 2);
        $rate = fn ($v) => 0 + round((float) $v, 2);
        $det = fn (array $r, bool $heads = true) => array_filter([
            'txval' => $money($r['taxable']),
            'rt' => $rate($r['rate']),
            'iamt' => $money($r['igst'] ?? 0),
            'camt' => $heads ? $money($r['cgst'] ?? 0) : null,
            'samt' => $heads ? $money($r['sgst'] ?? 0) : null,
            'csamt' => 0.0,
        ], fn ($v) => $v !== null);

        $out = [
            'gstin' => strtoupper((string) $company->gstin),
            'fp' => $period->end->format('mY'),
            'version' => self::VERSION,
            'hash' => 'hash',
        ];

        // 4A/4B/6B/6C - grouped by recipient, then by invoice.
        $b2b = [];
        foreach ($d['b2b'] as $r) {
            $inv = &$b2b[$r['gstin']][$r['number']];
            $inv ??= [
                'inum' => (string) $r['number'], 'idt' => $date($r['date']), 'val' => $money($r['value']),
                'pos' => $pos($r['pos']), 'rchrg' => $r['rcm'], 'inv_typ' => $r['inv_typ'] ?? 'R', 'itms' => [],
            ];
            $inv['itms'][] = ['num' => count($inv['itms']) + 1, 'itm_det' => $det($r)];
            unset($inv);
        }
        if ($b2b) {
            $out['b2b'] = array_map(fn ($ctin) => ['ctin' => (string) $ctin, 'inv' => array_values($b2b[$ctin])], array_keys($b2b));
        }

        // 5 - B2C large, grouped by place of supply.
        $b2cl = [];
        foreach ($d['b2cl'] as $r) {
            $p = $pos($r['pos']);
            $inv = &$b2cl[$p][$r['number']];
            $inv ??= ['inum' => (string) $r['number'], 'idt' => $date($r['date']), 'val' => $money($r['value']), 'itms' => []];
            $inv['itms'][] = ['num' => count($inv['itms']) + 1, 'itm_det' => $det($r, false)];
            unset($inv);
        }
        if ($b2cl) {
            $out['b2cl'] = array_map(fn ($p) => ['pos' => (string) $p, 'inv' => array_values($b2cl[$p])], array_keys($b2cl));
        }

        // 7 - B2C others, one row per place of supply and rate.
        if ($d['b2cs']) {
            $out['b2cs'] = array_map(function ($r) use ($pos, $home, $money, $rate) {
                $p = $pos($r['pos']);
                $intra = $p === $home;

                return array_filter([
                    'sply_ty' => $intra ? 'INTRA' : 'INTER',
                    'pos' => $p,
                    'typ' => $r['type'] ?? 'OE',
                    'txval' => $money($r['taxable']),
                    'rt' => $rate($r['rate']),
                    'iamt' => $intra ? null : $money($r['igst']),
                    'camt' => $intra ? $money($r['cgst']) : null,
                    'samt' => $intra ? $money($r['sgst']) : null,
                    'csamt' => 0.0,
                ], fn ($v) => $v !== null);
            }, $d['b2cs']);
        }

        // 9B - credit notes to registered recipients.
        $cdnr = [];
        foreach ($d['cdnr'] as $r) {
            $nt = &$cdnr[$r['gstin']][$r['number']];
            $nt ??= [
                'ntty' => 'C', 'nt_num' => (string) $r['number'], 'nt_dt' => $date($r['date']), 'val' => $money($r['value']),
                'pos' => $pos($r['pos']), 'rchrg' => $r['rcm'], 'inv_typ' => $r['inv_typ'] ?? 'R', 'itms' => [],
            ];
            $nt['itms'][] = ['num' => count($nt['itms']) + 1, 'itm_det' => $det($r)];
            unset($nt);
        }
        if ($cdnr) {
            $out['cdnr'] = array_map(fn ($ctin) => ['ctin' => (string) $ctin, 'nt' => array_values($cdnr[$ctin])], array_keys($cdnr));
        }

        // 9B - credit notes to unregistered recipients (B2CL / exports).
        $cdnur = [];
        foreach ($d['cdnur'] as $r) {
            $nt = &$cdnur[$r['number']];
            $nt ??= [
                'typ' => $r['ur_type'], 'ntty' => 'C', 'nt_num' => (string) $r['number'], 'nt_dt' => $date($r['date']),
                'val' => $money($r['value']), 'pos' => $pos($r['pos']), 'itms' => [],
            ];
            if ($nt['typ'] !== 'B2CL') {
                unset($nt['pos']); // exports carry no place of supply
            }
            $nt['itms'][] = ['num' => count($nt['itms']) + 1, 'itm_det' => $det($r, false)];
            unset($nt);
        }
        if ($cdnur) {
            $out['cdnur'] = array_values($cdnur);
        }

        // 6A - exports, grouped by with / without payment of IGST.
        $exp = [];
        foreach ($d['exp'] ?? [] as $r) {
            $inv = &$exp[$r['exp_typ']][$r['number']];
            $inv ??= array_filter([
                'inum' => (string) $r['number'], 'idt' => $date($r['date']), 'val' => $money($r['value']),
                'sbpcode' => $r['port_code'] ?: null, 'sbnum' => $r['shipping_bill_number'] ?: null,
                'sbdt' => $date($r['shipping_bill_date'] ?? null),
            ], fn ($v) => $v !== null) + ['itms' => []];
            $inv['itms'][] = ['txval' => $money($r['taxable']), 'rt' => $rate($r['rate']), 'iamt' => $money($r['igst']), 'csamt' => 0.0];
            unset($inv);
        }
        if ($exp) {
            $out['exp'] = array_map(fn ($t) => ['exp_typ' => $t, 'inv' => array_values($exp[$t])], array_keys($exp));
        }

        // 8 - nil rated, exempt and non-GST.
        $nilCodes = [
            'Inter-State supplies to registered persons' => 'INTRB2B',
            'Intra-State supplies to registered persons' => 'INTRAB2B',
            'Inter-State supplies to unregistered persons' => 'INTRB2C',
            'Intra-State supplies to unregistered persons' => 'INTRAB2C',
        ];
        $nil = [];
        foreach ($d['exemp'] as $r) {
            if (abs($r['nil']) < 0.005 && abs($r['exempt']) < 0.005 && abs($r['non_gst']) < 0.005) {
                continue;
            }
            $nil[] = [
                'sply_ty' => $nilCodes[$r['description']],
                'expt_amt' => $money($r['exempt']), 'nil_amt' => $money($r['nil']), 'ngsup_amt' => $money($r['non_gst']),
            ];
        }
        if ($nil) {
            $out['nil'] = ['inv' => $nil];
        }

        // 12 - HSN summary, B2B and B2C tabs.
        $hsn = ['hsn_b2b' => [], 'hsn_b2c' => []];
        foreach ($d['hsn'] as $r) {
            $tab = $r['tab'] === 'B2B' ? 'hsn_b2b' : 'hsn_b2c';
            $service = str_starts_with(trim((string) $r['hsn']), '99');
            $hsn[$tab][] = [
                'num' => count($hsn[$tab]) + 1,
                'hsn_sc' => (string) $r['hsn'],
                'desc' => mb_substr(trim(preg_replace('/[^A-Za-z0-9 ,\-\/()&]/', ' ', (string) $r['description'])), 0, 30),
                'uqc' => $service ? 'NA' : $this->uqcCode((string) $r['uqc']),
                'qty' => $service ? 0 : round((float) $r['qty'], 2),
                'rt' => $rate($r['rate']),
                'txval' => $money($r['taxable']),
                'iamt' => $money($r['igst']),
                'camt' => $money($r['cgst']),
                'samt' => $money($r['sgst']),
                'csamt' => 0.0,
            ];
        }
        $hsn = array_filter($hsn);
        if ($hsn) {
            $out['hsn'] = $hsn;
        }

        // 13 - documents issued.
        $docs = [];
        foreach ($d['docs'] as $r) {
            $code = $r['nature'] === 'Credit Note' ? self::DOC_CREDIT_NOTE : self::DOC_INVOICE;
            $docs[] = [
                'doc_num' => $code,
                'doc_typ' => $r['nature'],
                'docs' => [[
                    'num' => 1, 'from' => (string) $r['from'], 'to' => (string) $r['to'],
                    'totnum' => (int) $r['total'], 'cancel' => (int) $r['cancelled'],
                    'net_issue' => (int) $r['total'] - (int) $r['cancelled'],
                ]],
            ];
        }
        if ($docs) {
            $out['doc_issue'] = ['doc_det' => $docs];
        }

        return $out;
    }

    /** UQC code as the JSON carries it ("NOS"); anything unknown is OTH. */
    private function uqcCode(string $unit): string
    {
        $known = ['NOS', 'PCS', 'BOX', 'BAG', 'BTL', 'BDL', 'CAN', 'CBM', 'CCM', 'DOZ', 'DRM', 'GMS', 'GGK', 'GRS', 'KGS', 'KLR',
            'KME', 'LTR', 'MLT', 'MTR', 'MTS', 'PAC', 'PRS', 'QTL', 'ROL', 'SET', 'SQF', 'SQM', 'TBS', 'TON', 'TUB', 'UNT', 'YDS'];
        $alias = ['NO' => 'NOS', 'PC' => 'PCS', 'KG' => 'KGS', 'G' => 'GMS', 'GM' => 'GMS', 'L' => 'LTR', 'ML' => 'MLT',
            'M' => 'MTR', 'UNIT' => 'UNT', 'DOZEN' => 'DOZ', 'PAIR' => 'PRS'];
        $code = strtoupper(trim($unit));
        $code = $alias[$code] ?? $code;

        return in_array($code, $known, true) ? $code : 'OTH';
    }
}
