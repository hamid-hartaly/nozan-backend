<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'hamid.hartaly@gmail.com',
                'name' => 'Ahmad',
                'password' => 'P@ssword123',
                'role' => 'admin',
                'can_record_payment' => true,
            ],
            [
                'email' => 'accounts@nozan-service.local',
                'name' => 'Narin Finance',
                'password' => 'P@ssword123',
                'role' => 'accountant',
                'can_record_payment' => true,
            ],
            [
                'email' => 'workshop@nozan-service.local',
                'name' => 'Workshop One',
                'password' => 'P@ssword123',
                'role' => 'staff',
                'can_record_payment' => false,
            ],
            [
                'email' => 'workshop-two@nozan-service.local',
                'name' => 'Workshop Two',
                'password' => 'P@ssword123',
                'role' => 'staff',
                'can_record_payment' => false,
            ],
            [
                'email' => 'cashier@nozan-service.local',
                'name' => 'Front Desk Cashier',
                'password' => 'P@ssword123',
                'role' => 'staff',
                'can_record_payment' => true,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $user['password'],
                    'role' => $user['role'],
                    'can_record_payment' => $user['can_record_payment'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
