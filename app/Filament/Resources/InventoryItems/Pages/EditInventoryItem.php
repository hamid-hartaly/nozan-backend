<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InventoryItems\InventoryItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    /**
     * Keep legacy and new stock columns aligned.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('quantity', $data)) {
            $data['on_hand'] = (int) $data['quantity'];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
