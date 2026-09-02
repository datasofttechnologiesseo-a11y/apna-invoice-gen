<?php

/**
 * Concurrency probe for invoice numbering.
 *
 *   php scripts/concurrency-probe.php setup 12      # create 12 draft invoices
 *   php scripts/concurrency-probe.php finalize <id> # one worker, run in parallel
 *   php scripts/concurrency-probe.php verify        # check the series
 *   php scripts/concurrency-probe.php teardown
 *
 * Why a script and not a test: the suite runs on SQLite in memory, where
 * lockForUpdate is meaningless because the whole database serialises anyway.
 * Production is MySQL. So the only honest way to check that two people issuing
 * an invoice at the same moment cannot get the same number is to run real
 * concurrent processes against real MySQL.
 *
 * Rule 46(b) requires a consecutive serial number unique within the financial
 * year. Two invoices sharing a number is not a display bug - it is a defective
 * document for the buyer's input credit and a problem at assessment.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\DB;

const MARKER = 'concurrency-probe';

$command = $argv[1] ?? 'verify';

/** The company the probe operates on, tagged so teardown can find it. */
function probeCompany(): ?Company
{
    return Company::where('name', MARKER)->first();
}

switch ($command) {
    case 'setup':
        $count = (int) ($argv[2] ?? 10);

        $state = State::query()->first() ?? State::factory()->create(['gst_code' => '27']);
        $user = User::query()->first();
        if (! $user) {
            fwrite(STDERR, "no user in the database to own the probe\n");
            exit(1);
        }

        Company::where('name', MARKER)->delete();
        $company = Company::forceCreate([
            'user_id' => $user->id,
            'name' => MARKER,
            'state_id' => $state->id,
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'PROBE',
            'invoice_number_padding' => 4,
            'invoice_counter' => 0,
            'receipt_prefix' => 'PR',
            'receipt_number_padding' => 4,
            'onboarded_at' => now(),
        ]);

        $customer = Customer::forceCreate([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => 'Probe Buyer',
            'state_id' => $state->id,
            'country' => 'India',
        ]);

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $invoice = Invoice::forceCreate([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'status' => 'draft',
                'subtotal' => 1000,
                'grand_total' => 1180,
                'balance' => 1180,
                'is_interstate' => false,
                'place_of_supply_state_id' => $state->id,
            ]);
            InvoiceItem::forceCreate([
                'invoice_id' => $invoice->id,
                'description' => 'Probe line',
                'quantity' => 1,
                'rate' => 1000,
                'amount' => 1000,
                'gst_rate' => 18,
                'cgst_amount' => 90,
                'sgst_amount' => 90,
                'igst_amount' => 0,
                'total' => 1180,
                'hsn_sac' => '998314',
            ]);
            $ids[] = $invoice->id;
        }

        echo implode(' ', $ids), "\n";
        break;

    case 'finalize':
        $id = (int) ($argv[2] ?? 0);
        $invoice = Invoice::find($id);
        if (! $invoice) {
            fwrite(STDERR, "invoice {$id} not found\n");
            exit(1);
        }

        // The same sequence the controller runs: lock the company row inside a
        // transaction, then allocate. If the lock does not hold, two workers
        // read the same counter and emit the same number.
        try {
            DB::transaction(function () use ($invoice) {
                $company = $invoice->company()->lockForUpdate()->first();
                $number = $company->bumpCounterForFinalize($invoice->invoice_date?->toDateString());

                $invoice->update([
                    'invoice_number' => $number,
                    'status' => 'final',
                    'finalized_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            fwrite(STDERR, "worker {$id} failed: ".$e->getMessage()."\n");
            exit(1);
        }
        break;

    case 'verify':
        $company = probeCompany();
        if (! $company) {
            fwrite(STDERR, "probe company not found; run setup first\n");
            exit(1);
        }

        $numbers = Invoice::where('company_id', $company->id)
            ->whereNotNull('invoice_number')
            ->orderBy('id')
            ->pluck('invoice_number')
            ->all();

        $total = count($numbers);
        $unique = count(array_unique($numbers));

        $trailing = array_map(fn ($n) => (int) preg_replace('/\D/', '', substr($n, -6)), $numbers);
        sort($trailing);
        $gaps = [];
        for ($i = 1; $i < count($trailing); $i++) {
            if ($trailing[$i] !== $trailing[$i - 1] + 1) {
                $gaps[] = "{$trailing[$i-1]} -> {$trailing[$i]}";
            }
        }

        printf("issued        : %d\n", $total);
        printf("unique        : %d\n", $unique);
        printf("duplicates    : %d\n", $total - $unique);
        printf("counter value : %d\n", (int) $company->fresh()->invoice_counter);
        printf("gaps          : %s\n", $gaps ? implode(', ', $gaps) : 'none');
        printf("\n%s\n", ($total === $unique && ! $gaps && $total > 0)
            ? 'PASS - every number unique and consecutive under concurrency'
            : 'FAIL - the series is not safe under concurrency');

        exit(($total === $unique && ! $gaps && $total > 0) ? 0 : 1);

    case 'teardown':
        $company = probeCompany();
        if ($company) {
            InvoiceItem::whereIn('invoice_id',
                Invoice::where('company_id', $company->id)->pluck('id'))->delete();
            Invoice::where('company_id', $company->id)->delete();
            Customer::where('company_id', $company->id)->delete();
            $company->delete();
        }
        echo "probe data removed\n";
        break;

    default:
        fwrite(STDERR, "unknown command: {$command}\n");
        exit(1);
}
