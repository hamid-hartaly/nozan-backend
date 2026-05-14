<?php

namespace App\Filament\Resources\Jobs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_code')
                    ->required()
                    ->helperText('Use a unique job code such as NGS-YYMMDD-0001')
                    ->maxLength(255),
                Select::make('customer_record_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('tv_model')
                    ->label('TV Model')
                    ->required()
                    ->maxLength(255),
                TextInput::make('category')
                    ->required()
                    ->default('TV')
                    ->maxLength(255),
                TextInput::make('priority')
                    ->required()
                    ->default('Normal')
                    ->maxLength(50),
                Textarea::make('issue')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'REPAIR' => 'Repair',
                        'FINISHED' => 'Finished',
                        'OUT' => 'Out',
                    ])
                    ->default('PENDING')
                    ->required(),
                TextInput::make('estimated_price_iqd')
                    ->label('Estimated Price')
                    ->prefix('IQD')
                    ->numeric(),
                TextInput::make('final_price_iqd')
                    ->label('Final Price')
                    ->prefix('IQD')
                    ->numeric(),
            ]);
    }
}
