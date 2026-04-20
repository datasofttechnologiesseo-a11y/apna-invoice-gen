<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => $this->faker->word(),
            'hsn_sac' => (string) $this->faker->numberBetween(1000, 9999),
            'quantity' => 1,
            'unit' => 'pcs',
            'rate' => 1000,
            'amount' => 1000,
            'gst_rate' => 18,
            'cgst_amount' => 90,
            'sgst_amount' => 90,
            'igst_amount' => 0,
            'total' => 1180,
            'sort_order' => 0,
        ];
    }
}
