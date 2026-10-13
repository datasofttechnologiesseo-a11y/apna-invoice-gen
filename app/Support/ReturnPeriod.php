<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A GST return period: one month, or one quarter for QRMP filers (turnover up
 * to ₹5 crore who opted for quarterly returns). Read from ?month=YYYY-MM and
 * ?frequency=quarterly; a quarterly period is the Indian financial-year
 * quarter (Apr-Jun, Jul-Sep, Oct-Dec, Jan-Mar) containing that month.
 *
 * Default: last month, the return whose filing window is open now.
 */
final class ReturnPeriod
{
    /**
     * States/UTs whose QRMP filers pay GSTR-3B by the 22nd; everyone else by
     * the 24th (Notfn 82/2020-CT, proviso to Rule 61(5)).
     */
    private const DUE_22ND = ['22', '23', '24', '25', '26', '27', '29', '30', '31', '32', '33', '34', '35', '36', '37'];

    private function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly bool $quarterly,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $month = (string) $request->input('month', '');
        $pick = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m)
            ? Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay()
            : now()->subMonthNoOverflow()->startOfMonth();

        return self::for($pick, $request->input('frequency') === 'quarterly');
    }

    public static function for(Carbon $anyDayInPeriod, bool $quarterly = false): self
    {
        $start = $anyDayInPeriod->copy()->startOfMonth();
        if ($quarterly) {
            // FY quarters start in Apr, Jul, Oct, Jan.
            $offset = ($start->month - 1) % 3;
            $start = $start->subMonthsNoOverflow($offset);
        }
        $end = $start->copy()->addMonthsNoOverflow($quarterly ? 2 : 0)->endOfMonth();

        return new self($start, $end, $quarterly);
    }

    /** "October 2026" or "Oct-Dec 2026 (Q3 FY 2026-27)". */
    public function label(): string
    {
        if (! $this->quarterly) {
            return $this->start->format('F Y');
        }
        $fyStart = $this->start->month >= 4 ? $this->start->year : $this->start->year - 1;
        $q = intdiv(($this->start->month + 8) % 12, 3) + 1;

        return $this->start->format('M') . '-' . $this->end->format('M Y')
            . ' (Q' . $q . ' FY ' . $fyStart . '-' . substr((string) ($fyStart + 1), -2) . ')';
    }

    /** Value for the <input type="month"> that re-selects this period. */
    public function month(): string
    {
        return $this->start->format('Y-m');
    }

    /** Query string that re-opens this period on any return page. */
    public function query(): array
    {
        return array_filter(['month' => $this->month(), 'frequency' => $this->quarterly ? 'quarterly' : null]);
    }

    /** GSTR-1: 11th of the next month; quarterly (QRMP) 13th after the quarter. */
    public function gstr1Due(): Carbon
    {
        return $this->end->copy()->addDay()->startOfDay()->day($this->quarterly ? 13 : 11);
    }

    /** GSTR-3B: 20th of the next month; quarterly 22nd or 24th by state. */
    public function gstr3bDue(?string $stateCode = null): Carbon
    {
        $day = ! $this->quarterly ? 20 : (in_array((string) $stateCode, self::DUE_22ND, true) ? 22 : 24);

        return $this->end->copy()->addDay()->startOfDay()->day($day);
    }
}
