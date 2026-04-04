<?php

namespace App\Filament\Resources\InventoryItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InventoryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sku')
                    ->required()
                    ->maxLength(255),
                TextInput::make('category')
                    ->required()
                    ->maxLength(50),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('low_stock_threshold')
                    ->required()
                    ->default(5)
                    ->numeric(),
                TextInput::make('sell_price')
                    ->label('price')
                    ->prefix('IQD')
                    ->numeric(),
                TextInput::make('supplier')
                    ->maxLength(255),
                TextInput::make('location')
                    ->maxLength(255),
            ]);
    }
}
