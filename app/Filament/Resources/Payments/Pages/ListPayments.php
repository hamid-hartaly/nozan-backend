<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openInvoices')
                ->label('Invoices Dashboard')
                ->icon('heroicon-o-table-cells')
                ->url(PaymentResource::getUrl('invoices')),
            CreateAction::make(),
        ];
    }
}
