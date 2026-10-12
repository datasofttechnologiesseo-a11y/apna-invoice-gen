<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One document from an uploaded GSTR-2B (see App\Services\Gstr2bReader). */
class Gstr2bEntry extends Model
{
    protected $table = 'gstr2b_entries';

    protected $fillable = [
        'company_id', 'period', 'kind', 'supplier_gstin', 'supplier_name', 'document_number', 'document_date',
        'document_value', 'taxable_value', 'igst', 'cgst', 'sgst', 'cess', 'reverse_charge', 'itc_available', 'place_of_supply',
    ];

    protected $casts = [
        'document_date' => 'date',
        'document_value' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'igst' => 'decimal:2',
        'cgst' => 'decimal:2',
        'sgst' => 'decimal:2',
        'cess' => 'decimal:2',
        'reverse_charge' => 'boolean',
        'itc_available' => 'boolean',
    ];

    public function tax(): float
    {
        return round((float) $this->igst + (float) $this->cgst + (float) $this->sgst + (float) $this->cess, 2);
    }
}
