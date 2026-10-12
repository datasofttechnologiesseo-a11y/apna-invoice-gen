<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Does a business have anything in its books? A business with books is
 * never deleted: its invoices, purchases (input tax credit), expenses,
 * payments, bank statements and stock entries would go with it by
 * ON DELETE CASCADE, and GST records must be kept for 72 months (CGST s.36).
 * Quotations count too, so their delete never hits a foreign key.
 */
final class BusinessBooks
{
    private const TABLES = [
        'invoices', 'quotations', 'credit_notes', 'purchase_bills', 'purchase_payments',
        'expenses', 'cash_memos', 'payments', 'bank_statements', 'stock_movements',
    ];

    public static function exist(Company $company): bool
    {
        foreach (self::TABLES as $table) {
            if (DB::table($table)->where('company_id', $company->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
