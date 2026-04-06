<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Models\Customer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateJob extends CreateRecord
{
    protected static string $resource = JobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $customer = null;

        if (! empty($data['customer_phone'])) {
            $customer = Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                [
                    'name' => $data['customer_name'] ?? 'Unknown customer',
                    'email' => null,
                    'address' => null,
                ]
            );
        }

        $data['created_by_user_id'] = Auth::id();
        $data['customer_id'] = $customer ? (string) $customer->id : null;
        $data['customer_record_id'] = $customer?->id;
        $data['customer_name'] = $customer?->name ?? ($data['customer_name'] ?? null);
        $data['customer_phone'] = $customer?->phone ?? ($data['customer_phone'] ?? null);

        return $data;
    }
}
