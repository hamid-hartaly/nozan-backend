<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $role
 * @property bool $can_record_payment
 * @property bool $is_active
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_record_payment',
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

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'accountant', 'staff', 'cashier', 'technician'], true);
    }

    public function roleEnum(): UserRole
    {
        return UserRole::fromNullable($this->role);
    }

    public function canRecordPaymentPermission(): bool
    {
        return $this->roleEnum()->canRecordPayment() || (bool) $this->can_record_payment;
    }
}
