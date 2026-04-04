<?php

namespace App\Filament\Resources\Jobs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_code')
                    ->label('Job Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('tv_model')
                    ->label('TV Model')
                    ->searchable(),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'emergency' => 'Emergency',
                        'urgent' => 'Urgent',
                        'normal' => 'Normal',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'emergency' => 'danger',
                        'urgent' => 'warning',
                        'normal' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): ?string => $state === 'emergency' ? 'heroicon-m-exclamation-triangle' : null),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING', 'pending' => 'warning',
                        'REPAIR', 'repair' => 'info',
                        'FINISHED', 'finished' => 'success',
                        'OUT', 'out' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('final_price_iqd')
                    ->label('Final Price')
                    ->money('IQD')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'REPAIR' => 'Repair',
                        'FINISHED' => 'Finished',
                        'OUT' => 'Out',
                    ]),
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
