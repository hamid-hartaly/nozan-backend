<?php

namespace App\Filament\Resources\Jobs;

use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Filament\Resources\Jobs\Pages\ListJobs;
use App\Filament\Resources\Jobs\Tables\JobsTable;
use App\Models\Customer;
use App\Models\ServiceJob;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobResource extends Resource
{
    protected static ?string $model = ServiceJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $recordTitleAttribute = 'job_code';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $pluralModelLabel = 'Jobs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer & Intake')
                ->schema([
                    Forms\Components\TextInput::make('job_code')
                        ->label('Job Code')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Auto generated: NGS-YYMMDD-0001'),

                    Forms\Components\TextInput::make('customer_name')
                        ->label('Customer')
                        ->required()
                        ->datalist(
                            Customer::query()
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name')
                                ->filter()
                                ->values()
                                ->toArray()
                        ),

                    Forms\Components\TextInput::make('customer_phone')
                        ->label('Phone Number')
                        ->required()
                        ->tel()
                        ->maxLength(20)
                        ->datalist(
                            Customer::query()
                                ->orderBy('phone')
                                ->limit(50)
                                ->pluck('phone')
                                ->filter()
                                ->values()
                                ->toArray()
                        ),

                    Forms\Components\TextInput::make('tv_model')
                        ->label('TV Model')
                        ->required(),
                ])
                ->columns(2),

            Section::make('Issue & Priority')
                ->schema([
                    Forms\Components\Select::make('category')
                        ->label('Issue Option')
                        ->required()
                        ->options([
                            'panel' => 'A. PANEL',
                            'screen_broken' => 'B. Screen broken',
                            'led' => 'C. LED',
                            'main_board' => 'D. Main Board',
                        ]),

                    Forms\Components\Select::make('priority')
                        ->label('Priority')
                        ->required()
                        ->default('normal')
                        ->options([
                            'emergency' => 'C. Emergency',
                            'urgent' => 'B. Urgent',
                            'normal' => 'A. Normal',
                        ]),

                    Forms\Components\Textarea::make('issue')
                        ->label('Issue Description')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('estimated_price_iqd')
                        ->label('Estimated Price')
                        ->numeric()
                        ->inputMode('decimal')
                        ->prefix('IQD')
                        ->required(),
                ])
                ->columns(2),

            Section::make('Status')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->required()
                        ->default('pending')
                        ->options([
                            'pending' => 'A. Pending',
                            'repair' => 'B. Repair',
                            'finished' => 'C. Finished',
                        ])
                        ->live(),

                    Forms\Components\Select::make('repair_outcome')
                        ->label('Finished Result')
                        ->options([
                            'repaired' => 'A. چاککرا',
                            'cannot_repair' => 'B. چاکنابێت',
                        ])
                        ->visible(fn ($get) => $get('status') === 'finished')
                        ->live(),

                    Forms\Components\TextInput::make('final_price_iqd')
                        ->label('Final Price')
                        ->numeric()
                        ->inputMode('decimal')
                        ->prefix('IQD')
                        ->visible(fn ($get) => $get('status') === 'finished' && $get('repair_outcome') === 'repaired'
                        ),

                    Forms\Components\Textarea::make('repair_notes')
                        ->label('چی شتێک چاککرا')
                        ->rows(4)
                        ->visible(fn ($get) => $get('status') === 'finished' && $get('repair_outcome') === 'repaired'
                        ),

                    Forms\Components\Select::make('cannot_repair_reason')
                        ->label('هۆکاری چاکنابوون')
                        ->options([
                            'cannot_repair' => 'چاکنابێت',
                            'no_materials' => 'مەواد نیە',
                            'owner_declined' => 'خاوەنی چاکی ناکات',
                        ])
                        ->visible(fn ($get) => $get('status') === 'finished' && $get('repair_outcome') === 'cannot_repair'
                        ),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return JobsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderByRaw("
                CASE
                    WHEN priority = 'emergency' THEN 1
                    WHEN priority = 'urgent' THEN 2
                    WHEN priority = 'normal' THEN 3
                    ELSE 4
                END
            ")
            ->latest('created_at');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobs::route('/'),
            'create' => CreateJob::route('/create'),
            'edit' => EditJob::route('/{record}/edit'),
        ];
    }
}
