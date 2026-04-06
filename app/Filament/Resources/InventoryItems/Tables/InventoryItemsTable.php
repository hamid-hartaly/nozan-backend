<?php

namespace App\Filament\Resources\InventoryItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->sortable(),
                BadgeColumn::make('stock_health')
                    ->label('Stock')
                    ->state(fn ($record): string => (int) $record->quantity <= (int) $record->low_stock_threshold ? 'Low' : 'OK')
                    ->color(fn (string $state): string => $state === 'Low' ? 'danger' : 'success'),
                TextColumn::make('sell_price')
                    ->label('price')
                    ->money('IQD')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
