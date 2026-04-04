<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_from_nullable_defaults_to_staff_for_unknown_values(): void
    {
        $this->assertSame(UserRole::Staff, UserRole::fromNullable(null));
        $this->assertSame(UserRole::Staff, UserRole::fromNullable('unknown-role'));
    }

    public function test_only_admin_and_accountant_can_record_payment(): void
    {
        $this->assertTrue(UserRole::Admin->canRecordPayment());
        $this->assertTrue(UserRole::Accountant->canRecordPayment());
        $this->assertFalse(UserRole::Staff->canRecordPayment());
        $this->assertFalse(UserRole::Customer->canRecordPayment());
    }

    public function test_customer_cannot_access_admin_panel(): void
    {
        $this->assertTrue(UserRole::Admin->canAccessAdminPanel());
        $this->assertTrue(UserRole::Accountant->canAccessAdminPanel());
        $this->assertTrue(UserRole::Staff->canAccessAdminPanel());
        $this->assertFalse(UserRole::Customer->canAccessAdminPanel());
    }
}
