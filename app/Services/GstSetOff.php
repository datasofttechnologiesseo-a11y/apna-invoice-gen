<?php

namespace App\Services;

/**
 * Applies input tax credit against output tax in the order the law allows
 * (s.49(5) CGST Act, Rule 88A, Circular 98/17/2019):
 *
 *   IGST credit -> IGST liability first, then CGST and SGST (any order; we
 *                  take CGST first, then SGST)
 *   CGST credit -> CGST liability, then IGST
 *   SGST credit -> SGST liability, then IGST
 *   CGST credit is never used against SGST, nor SGST against CGST.
 *
 * Netting each head on its own with max(0, …) - the old approach - showed
 * ₹18,000 payable on CGST 9k + SGST 9k when ₹18,000 of IGST credit was
 * sitting unused. Returns the cash payable per head and the credit left
 * over to carry forward.
 */
class GstSetOff
{
    /**
     * @param  array{igst: float, cgst: float, sgst: float}  $liability  output tax per head
     * @param  array{igst: float, cgst: float, sgst: float}  $credit     ITC available per head
     * @return array{cash: array{igst: float, cgst: float, sgst: float, total: float},
     *               carried: array{igst: float, cgst: float, sgst: float, total: float},
     *               used: array{igst: float, cgst: float, sgst: float},
     *               matrix: array<string, array<string, float>>}
     *
     * matrix[credit head][liability head] says which credit paid which tax,
     * the way Table 6.1 of the return lays it out. Display only: the cash,
     * carried and used figures do not depend on it.
     */
    public function settle(array $liability, array $credit): array
    {
        $due = [
            'igst' => max(0.0, round((float) ($liability['igst'] ?? 0), 2)),
            'cgst' => max(0.0, round((float) ($liability['cgst'] ?? 0), 2)),
            'sgst' => max(0.0, round((float) ($liability['sgst'] ?? 0), 2)),
        ];
        $left = [
            'igst' => max(0.0, round((float) ($credit['igst'] ?? 0), 2)),
            'cgst' => max(0.0, round((float) ($credit['cgst'] ?? 0), 2)),
            'sgst' => max(0.0, round((float) ($credit['sgst'] ?? 0), 2)),
        ];
        $used = ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        $zero = ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
        $matrix = ['igst' => $zero, 'cgst' => $zero, 'sgst' => $zero];

        $apply = function (string $creditHead, string $dueHead) use (&$due, &$left, &$used, &$matrix): void {
            $amount = min($left[$creditHead], $due[$dueHead]);
            if ($amount <= 0) {
                return;
            }
            $left[$creditHead] = round($left[$creditHead] - $amount, 2);
            $due[$dueHead] = round($due[$dueHead] - $amount, 2);
            $used[$dueHead] = round($used[$dueHead] + $amount, 2);
            $matrix[$creditHead][$dueHead] = round($matrix[$creditHead][$dueHead] + $amount, 2);
        };

        // IGST credit must be exhausted first, against IGST, then the others.
        $apply('igst', 'igst');
        // What is left may go to CGST and SGST in any proportion (Rule 88A,
        // Circular 98/17/2019). Fill first what each head's own credit cannot
        // cover; pouring it all into CGST left SGST payable in cash while CGST
        // credit was carried forward unused.
        $shortfall = fn (string $h) => max(0.0, round($due[$h] - $left[$h], 2));
        foreach (['cgst', 'sgst'] as $h) {
            $amount = min($left['igst'], $shortfall($h));
            if ($amount > 0) {
                $left['igst'] = round($left['igst'] - $amount, 2);
                $due[$h] = round($due[$h] - $amount, 2);
                $used[$h] = round($used[$h] + $amount, 2);
                $matrix['igst'][$h] = round($matrix['igst'][$h] + $amount, 2);
            }
        }
        // Any IGST credit still left must still be used before CGST/SGST credit.
        $apply('igst', 'cgst');
        $apply('igst', 'sgst');
        // Then each state/central credit against its own head, then IGST.
        $apply('cgst', 'cgst');
        $apply('sgst', 'sgst');
        $apply('cgst', 'igst');
        $apply('sgst', 'igst');

        $cash = $due;
        $cash['total'] = round($cash['igst'] + $cash['cgst'] + $cash['sgst'], 2);
        $carried = $left;
        $carried['total'] = round($carried['igst'] + $carried['cgst'] + $carried['sgst'], 2);

        return ['cash' => $cash, 'carried' => $carried, 'used' => $used, 'matrix' => $matrix];
    }
}
