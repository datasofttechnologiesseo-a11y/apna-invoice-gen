<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Someone invited to work in a business, and what they may do there.
 *
 *  admin       everything except the team, backups and deleting the business
 *  accountant  sales, purchases, GST, banking, reports - not company settings
 *              or the team
 *  staff       billing: quotations, invoices, receipts, customers, items -
 *              no purchases, expenses, accounts, GST or settings, and no
 *              cancelling or deleting
 *
 * Rules are by route name; anything not listed for a role is refused.
 */
class CompanyMember extends Model
{
    public const ROLES = [
        'admin' => ['label' => 'Admin', 'help' => 'Everything you can do, except managing the team, backups and deleting the business.'],
        'accountant' => ['label' => 'Accountant / CA', 'help' => 'Sales, purchases, expenses, GST returns, banking and reports. Cannot change company settings or the team.'],
        'staff' => ['label' => 'Billing staff', 'help' => 'Makes quotations and invoices, records payments, adds customers and items. Cannot see purchases, accounts or GST, or cancel and delete.'],
    ];

    private const RULES = [
        'admin' => [
            'allow' => ['*'],
            'deny' => ['team.*', 'backup.*', 'companies.create', 'companies.store', 'companies.destroy', 'admin.*', 'onboarding.*'],
        ],
        'accountant' => [
            'allow' => ['*'],
            'deny' => ['team.*', 'backup.*', 'companies.*', 'company.*', 'admin.*', 'onboarding.*'],
        ],
        'staff' => [
            'allow' => ['invoices.*', 'quotations.*', 'customers.*', 'products.index', 'products.search', 'products.quick-create',
                'products.create', 'products.store', 'products.edit', 'products.update', 'payments.receipt', 'credit-notes.pdf',
                'help', 'notifications.*', 'review-invite.*', 'search.suggest', 'settings.index'],
            'deny' => ['invoices.destroy', 'invoices.cancel', 'invoices.gstr1', 'quotations.destroy', 'customers.destroy'],
        ],
    ];

    protected $fillable = ['company_id', 'user_id', 'email', 'role', 'token', 'invited_by', 'accepted_at'];

    protected $casts = ['accepted_at' => 'datetime'];

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role]['label'] ?? ucfirst((string) $this->role);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /** May someone with this role open this route? */
    public static function allows(string $role, ?string $routeName): bool
    {
        $rules = self::RULES[$role] ?? null;
        if (! $rules || $routeName === null) {
            return false;
        }
        foreach ($rules['deny'] as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return false;
            }
        }
        foreach ($rules['allow'] as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
