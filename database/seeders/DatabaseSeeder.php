<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@nozan.local'],
            [
                'name' => 'Ahmad',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        );

        $customer = Customer::query()->firstOrCreate(
            ['phone' => '07508389007'],
            ['name' => 'Xalidq'],
        );

        $job = ServiceJob::query()->firstOrNew([
            'job_code' => 'NGS-DEMO-0001',
        ]);

        $job->customer_id = (string) $customer->id;
        $job->customer_name = $customer->name;
        $job->customer_phone = $customer->phone;
        $job->tv_model = 'Samsung 65"';
        $job->category = 'LED';
        $job->priority = 'Urgent';
        $job->issue = 'Demo issue for queue preview';
        $job->estimated_price_iqd = 75000;
        $job->status = 'pending';
        $job->created_by_user_id = $admin->id;
        $job->received_at = now();
        $job->save();

        $inventoryItem = InventoryItem::query()->firstOrNew([
            'name' => 'Main board',
        ]);

        $inventoryItem->sku = 'MB-001';
        $inventoryItem->category = 'Board';
        $inventoryItem->on_hand = 4;
        $inventoryItem->reserved = 0;
        $inventoryItem->reorder_level = 2;
        $inventoryItem->quantity = 4;
        $inventoryItem->unit_cost_iqd = 45000;
        $inventoryItem->buy_price = 45000;
        $inventoryItem->sell_price = 60000;
        $inventoryItem->location = 'Main shelf';
        $inventoryItem->save();
    }
}

