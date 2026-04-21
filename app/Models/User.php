<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function activeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'active_company_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Return (creating if necessary) the currently selected company for this user.
     * Used by every controller that needs "the company context for this request".
     */
    public function ensureCompany(): Company
    {
        if ($this->active_company_id) {
            $active = $this->companies()->find($this->active_company_id);
            if ($active) {
                return $active;
            }
        }

        $first = $this->companies()->orderBy('id')->first();
        if ($first) {
            $this->forceFill(['active_company_id' => $first->id])->save();
            return $first;
        }

        $new = $this->companies()->create([
            'user_id' => $this->id,
            'name' => $this->name . "'s Company",
            'country' => 'India',
            'default_currency' => 'INR',
        ]);
        $this->forceFill(['active_company_id' => $new->id])->save();
        return $new;
    }

    public function switchCompany(Company $company): void
    {
        if ($company->user_id !== $this->id) {
            abort(403);
        }
        $this->forceFill(['active_company_id' => $company->id])->save();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * Mass-assignable via `create()`/`update($request->all())`. Intentionally
     * narrow: anything sensitive (is_super_admin, referral_code, referral
     * link, backup preferences) is set via forceFill in controllers that are
     * guaranteed to have already validated the caller.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active_company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'auto_backup_enabled' => 'boolean',
            'last_backup_sent_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    /**
     * Return the user's unique referral code, generating one on first access.
     * Uses AI-<4 char alphanum> — short, shareable, ~1.6m combinations.
     *
     * The unique constraint on the column is the source of truth for
     * uniqueness; we catch the QueryException from a colliding insert and
     * retry rather than relying on a pre-flight SELECT (which is racy under
     * concurrent sign-ups).
     */
    public function ensureReferralCode(): string
    {
        if ($this->referral_code) {
            return $this->referral_code;
        }

        for ($i = 0; $i < 10; $i++) {
            $candidate = 'AI-' . strtoupper(\Illuminate\Support\Str::random(4));
            try {
                $this->forceFill(['referral_code' => $candidate])->save();
                return $candidate;
            } catch (\Illuminate\Database\QueryException $e) {
                // 23000 / 23505 = integrity constraint violation. Retry.
                if (! in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                    throw $e;
                }
                // Clear the local copy so the next save() inserts again.
                $this->referral_code = null;
            }
        }

        throw new \RuntimeException('Could not allocate a unique referral code after 10 attempts.');
    }
}
