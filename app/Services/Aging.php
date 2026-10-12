<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aging buckets shared by receivables (customers) and payables (vendors).
 * Days are counted past the due date (or the document date when no due date
 * was set). Buckets follow the Tally / Zoho convention: not yet due and up to
 * 30 days late are "0-30".
 */
class Aging
{
    public const BUCKETS = ['b0_30' => '0-30 days', 'b31_60' => '31-60 days', 'b61_90' => '61-90 days', 'b90' => '90+ days'];

    public static function bucketFor(int $daysPastDue): string
    {
        return match (true) {
            $daysPastDue <= 30 => 'b0_30',
            $daysPastDue <= 60 => 'b31_60',
            $daysPastDue <= 90 => 'b61_90',
            default => 'b90',
        };
    }

    public static function daysPastDue($due, ?Carbon $today = null): int
    {
        $today ??= now()->startOfDay();
        $due = $due ? Carbon::parse($due)->startOfDay() : $today;

        return (int) max(0, $due->diffInDays($today, false));
    }

    public static function columns(string $partyLabel): array
    {
        $cols = [
            ['key' => 'party', 'label' => $partyLabel, 'width' => 30],
            ['key' => 'gstin', 'label' => 'GSTIN', 'width' => 18],
            ['key' => 'count', 'label' => 'Open Docs', 'type' => 'number'],
            ['key' => 'oldest', 'label' => 'Oldest (days)', 'type' => 'number'],
        ];
        foreach (self::BUCKETS as $key => $label) {
            $cols[] = ['key' => $key, 'label' => $label, 'type' => 'money', 'total' => true];
        }
        $cols[] = ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'total' => true];

        return $cols;
    }

    /**
     * @param  Collection<int, array{party_id: mixed, party: string, gstin: ?string, due: mixed, balance: float}>  $docs
     * @return array<int, array<string, mixed>> one row per party, largest total first
     */
    public static function byParty(Collection $docs): array
    {
        $today = now()->startOfDay();

        return $docs->groupBy('party_id')->map(function (Collection $group) use ($today) {
            $first = $group->first();
            $row = ['party' => $first['party'], 'gstin' => $first['gstin'], 'count' => $group->count(), 'oldest' => 0, 'total' => 0.0];
            foreach (array_keys(self::BUCKETS) as $b) {
                $row[$b] = 0.0;
            }
            foreach ($group as $doc) {
                $days = self::daysPastDue($doc['due'], $today);
                $row[self::bucketFor($days)] += $doc['balance'];
                $row['total'] += $doc['balance'];
                $row['oldest'] = max($row['oldest'], $days);
            }
            foreach (array_merge(array_keys(self::BUCKETS), ['total']) as $k) {
                $row[$k] = round($row[$k], 2);
            }

            return $row;
        })->sortByDesc('total')->values()->all();
    }
}
