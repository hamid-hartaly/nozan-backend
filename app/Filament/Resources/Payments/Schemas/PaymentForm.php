<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_job_id')
                    ->relationship('serviceJob', 'job_code')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('amount_iqd')
                    ->required()
                    ->numeric(),
                Select::make('method')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Transfer',
                    ])
                    ->default('cash')
                    ->required(),
            ]);
    }
}
