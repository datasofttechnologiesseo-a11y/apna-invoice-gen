<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Indian Permanent Account Number — 10 chars:
 *   [A-Z]{5}  — alphabetic prefix (4th char is entity type: P=Individual,
 *               C=Company, H=HUF, F=Firm, A=AoP, T=Trust, B=BOI, L=Local
 *               Authority, J=Artificial Juridical Person, G=Government)
 *   [0-9]{4}  — sequence
 *   [A-Z]     — check character
 */
class ValidPan implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) return;
        $pan = strtoupper(trim((string) $value));

        if (! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            $fail('The :attribute is not a valid 10-character PAN.');
        }
    }
}
