<?php

namespace App\Models;

use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\RecordsActivity;
use App\Support\PermissionGuard;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use DispatchesWebhooks, HasApiTokens, HasFactory, HasRoles, Notifiable, RecordsActivity;

    protected $fillable = [
        'company_id',
        'contact_id',
        'username',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isCustomer(): bool
    {
        return $this->contact_id !== null;
    }

    /**
     * Pin Spatie role/permission lookups for users to the API guard, so they
     * resolve the same guard whether called during a request or from artisan/tests.
     */
    public function guardName(): string
    {
        return PermissionGuard::name();
    }
}
