<?php

namespace App\Rules;

use App\Models\State;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an Indian GSTIN against:
 *   1. Format  — 15 chars: 2 digit state + 10 char PAN + 1 entity digit + Z + checksum
 *   2. State  — prefix must match the supplied state's GST code (if stateId is passed)
 *   3. Checksum — the 15th character is a mod-36 verification digit
 *
 * The rule is nullable-friendly: empty / null inputs pass (let `required` rule
 * handle that separately).
 */
class ValidGstin implements ValidationRule
{
    private const BASE36 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(private readonly ?int $stateId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) return;
        $gstin = strtoupper(trim((string) $value));

        // Format check — same regex we've been using.
        if (! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            $fail('The :attribute is not a valid 15-character GSTIN.');
            return;
        }

        // Checksum check.
        if (! self::hasValidChecksum($gstin)) {
            $fail('The :attribute has an invalid checksum. Double-check the last character.');
            return;
        }

        // Cross-check state prefix if a state was supplied.
        if ($this->stateId) {
            $state = State::find($this->stateId);
            if ($state && $state->gst_code && substr($gstin, 0, 2) !== $state->gst_code) {
                $fail('The :attribute state code (first 2 digits) doesn\'t match the selected state (' . $state->name . ', code ' . $state->gst_code . ').');
            }
        }
    }

    /**
     * GSTIN checksum: for each of the first 14 characters, multiply its base-36
     * value by an alternating factor starting with 1 (i.e. 1, 2, 1, 2, …). If the
     * product is >= 36, sum its base-36 "digits" (div 36 + mod 36). Sum all such
     * values, take mod 36, subtract from 36 (mod 36) → that index in the base-36
     * alphabet is the expected check character.
     *
     * Verified against real public GSTINs (e.g. 27AAACT2727Q1ZW · TCS).
     * Reference: GSTN-approved implementations used across tax SaaS products.
     */
    public static function hasValidChecksum(string $gstin): bool
    {
        if (strlen($gstin) !== 15) return false;

        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $pos = strpos(self::BASE36, $gstin[$i]);
            if ($pos === false) return false;
            // Factors alternate 1, 2, 1, 2, … starting at position 0.
            $factor = ($i % 2 === 0) ? 1 : 2;
            $product = $pos * $factor;
            $sum += intdiv($product, 36) + ($product % 36);
        }
        $expected = (36 - ($sum % 36)) % 36;
        return self::BASE36[$expected] === $gstin[14];
    }
}
