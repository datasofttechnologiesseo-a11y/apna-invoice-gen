<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\State;
use App\Models\User;
use App\Rules\ValidGstin;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Bulk import from an Excel/CSV sheet. Each importer declares its template
 * columns, turns one sheet row into model attributes (or errors), and spots
 * duplicates. Rows are only ever ADDED: an existing record that matches is
 * reported as a duplicate and left untouched, so an import can never alter
 * data a business already relies on.
 */
abstract class Importer
{
    /** Hard limits so one upload cannot stall the server. */
    public const MAX_ROWS = 2000;

    public function __construct(protected Company $company, protected User $user) {}

    abstract public function key(): string;

    abstract public function title(): string;

    /** Where to go after a successful import. */
    abstract public function listRoute(): string;

    /**
     * @return array<string, array{label: string, required?: bool, help: string, example: string, aliases?: array<int, string>}>
     *         keyed by field name, in template column order
     */
    abstract public function fields(): array;

    /**
     * Validate and normalise one row.
     *
     * @param  array<string, string>  $row  values keyed by field name (already trimmed)
     * @return array{data: ?array, errors: array<int, string>, duplicate: ?string}
     */
    abstract public function prepare(array $row): array;

    /** Persist one prepared row. */
    abstract public function create(array $data): void;

    /** Key used to catch the same record appearing twice in one file. */
    abstract public function fileKey(array $data): string;

    // ------------------------------------------------------------------

    /**
     * Map a sheet header ("Customer Name", "GST No.") to a field key. A
     * field's own name or template heading wins over another field's alias,
     * so the template's "Description" column is the description, not the
     * item name that also answers to "description" in other software.
     */
    public function fieldForHeader(string $header): ?string
    {
        $norm = self::normaliseHeader($header);
        foreach ($this->fields() as $key => $def) {
            if (self::normaliseHeader($key) === $norm || self::normaliseHeader($def['label']) === $norm) {
                return $key;
            }
        }
        foreach ($this->fields() as $key => $def) {
            foreach ($def['aliases'] ?? [] as $alias) {
                if (self::normaliseHeader($alias) === $norm) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function normaliseHeader(string $h): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(str_replace('*', '', $h)));
    }

    // ---- shared helpers ------------------------------------------------

    private ?Collection $states = null;

    protected function states(): Collection
    {
        return $this->states ??= State::all();
    }

    /**
     * Resolve a state from what people actually type - "Maharashtra",
     * "27", "MH" - or, when blank, from the GSTIN's first two digits.
     *
     * @return array{0: ?State, 1: ?string} state and an error message
     */
    protected function resolveState(?string $value, ?string $gstin): array
    {
        $value = trim((string) $value);
        $fromGstin = $gstin ? $this->states()->firstWhere('gst_code', substr($gstin, 0, 2)) : null;

        if ($value === '') {
            return [$fromGstin, null];
        }

        $v = Str::lower($value);
        $digits = preg_replace('/\D/', '', $value);
        $state = $this->states()->first(function (State $s) use ($v, $digits) {
            return Str::lower($s->name) === $v
                || Str::lower((string) $s->code) === $v
                || ($digits !== '' && str_pad($digits, 2, '0', STR_PAD_LEFT) === $s->gst_code && strlen($digits) <= 2)
                || Str::lower($s->name . ' (' . $s->gst_code . ')') === $v;
        });

        if (! $state) {
            return [null, "State \"{$value}\" not recognised. Use the state name (e.g. Maharashtra) or its GST code (27)."];
        }
        if ($fromGstin && $fromGstin->id !== $state->id) {
            return [null, "GSTIN {$gstin} belongs to {$fromGstin->name} ({$fromGstin->gst_code}), but the state column says {$state->name}."];
        }

        return [$state, null];
    }

    /** Upper-cased GSTIN or null, with an error when it is not a valid one. */
    protected function checkGstin(?string $gstin): array
    {
        $gstin = strtoupper(preg_replace('/\s+/', '', (string) $gstin));
        if ($gstin === '') {
            return [null, null];
        }
        $error = null;
        (new ValidGstin())->validate('gstin', $gstin, function ($msg) use (&$error) {
            $error = is_string($msg) ? $msg : 'GSTIN is not valid.';
        });

        return [$error ? null : $gstin, $error ? "GSTIN \"{$gstin}\": {$error}" : null];
    }

    protected static function digits(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }

    protected static function number(?string $v): ?float
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        // "1,25,000.50", "₹ 1200", "Rs. 1,200/-", "INR 500", "18%" all read as
        // numbers. The currency word goes first: stripping letters alone left
        // the dot of "Rs." in place and read "Rs. 1,200" as 0.12.
        $v = preg_replace('/^\s*(?:₹|rs\.?|inr)\s*/iu', '', $v);
        $v = preg_replace('/\s*\/-\s*$/', '', $v);
        $clean = preg_replace('/[^0-9.\-]/', '', $v);
        if (substr_count($clean, '.') > 1) {
            return null; // ambiguous - let the row be reported, not guessed
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
