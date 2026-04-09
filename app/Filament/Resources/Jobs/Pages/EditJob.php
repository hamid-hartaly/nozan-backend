<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Models\Customer;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJob extends EditRecord
{
    protected static string $resource = JobResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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

        $data['customer_id'] = $customer ? (string) $customer->id : ($data['customer_id'] ?? null);
        $data['customer_record_id'] = $customer?->id ?? ($data['customer_record_id'] ?? null);
        $data['customer_name'] = $customer?->name ?? ($data['customer_name'] ?? null);
        $data['customer_phone'] = $customer?->phone ?? ($data['customer_phone'] ?? null);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
