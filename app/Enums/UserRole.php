<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Staff = 'staff';
    case Cashier = 'cashier';
    case Technician = 'technician';
    case Customer = 'customer';

    public static function fromNullable(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Staff;
    }

    public function canRecordPayment(): bool
    {
        return match ($this) {
            self::Admin, self::Accountant, self::Cashier => true,
            default => false,
        };
    }

    public function canAccessJobs(): bool
    {
        return match ($this) {
            self::Customer => false,
            default => true,
        };
    }

    public function canAccessInventory(): bool
    {
        return $this->canAccessJobs();
    }

    public function canCreateJob(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            default => false,
        };
    }

    public function canOperateJobs(): bool
    {
        return match ($this) {
            self::Admin, self::Staff, self::Technician => true,
            default => false,
        };
    }

    public function canSendWhatsappTemplate(): bool
    {
        return $this->canOperateJobs();
    }

    public function canAccessAdminPanel(): bool
    {
        return match ($this) {
            self::Customer => false,
            default => true,
        };
    }
}
