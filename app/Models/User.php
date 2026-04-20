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
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }
}
