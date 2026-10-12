<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One uploaded bank statement file. */
class BankStatement extends Model
{
    protected $fillable = ['company_id', 'user_id', 'account_name', 'file_name', 'period_from', 'period_to', 'lines_count'];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
