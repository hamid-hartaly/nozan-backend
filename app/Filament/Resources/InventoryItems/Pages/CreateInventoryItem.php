<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    /**
     * Keep legacy and new stock columns aligned.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('quantity', $data)) {
            $data['on_hand'] = (int) $data['quantity'];
        }

        return $data;
    }
}
