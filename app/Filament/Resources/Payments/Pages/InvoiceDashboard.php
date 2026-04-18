<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\ServiceJob;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoiceDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Invoices Dashboard';

    protected static ?string $navigationLabel = 'Invoices Dashboard';

    protected string $view = 'filament.resources.payments.pages.invoice-dashboard';

    public function table(Table $table): Table
    {
        return $table
            ->query(ServiceJob::query()->withSum('payments', 'amount_iqd'))
            ->columns([
                TextColumn::make('job_code')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('customer.address')
                    ->label('Address')
                    ->toggleable()
                    ->wrap(),
                TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('final_price_iqd')
                    ->label('Total')
                    ->money('IQD')
                    ->sortable(),
                TextColumn::make('payments_sum_amount_iqd')
                    ->label('Paid')
                    ->money('IQD')
                    ->state(fn (ServiceJob $record): float => (float) ($record->payments_sum_amount_iqd ?? 0)),
                TextColumn::make('remaining_balance')
                    ->label('Balance')
                    ->money('IQD')
                    ->state(fn (ServiceJob $record): float => $this->calculateBalance($record))
                    ->color(fn (ServiceJob $record): string => $this->calculateBalance($record) > 0 ? 'danger' : 'success')
                    ->weight('bold'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_state')
                    ->label('Payment State')
                    ->options([
                        'unpaid' => 'Unpaid / Debt',
                        'paid' => 'Paid',
                    ])
                    ->query(function ($query, array $data) {
                        $state = $data['value'] ?? null;

                        if ($state === 'unpaid') {
                            return $query->whereRaw('COALESCE(final_price_iqd, estimated_price_iqd, 0) > (SELECT COALESCE(SUM(amount_iqd), 0) FROM payments WHERE payments.service_job_id = service_jobs.id)');
                        }

                        if ($state === 'paid') {
                            return $query->whereRaw('COALESCE(final_price_iqd, estimated_price_iqd, 0) <= (SELECT COALESCE(SUM(amount_iqd), 0) FROM payments WHERE payments.service_job_id = service_jobs.id)');
                        }

                        return $query;
                    }),
            ])
            ->recordClasses(fn (ServiceJob $record): string => $this->calculateBalance($record) > 0
                ? 'bg-red-50 dark:bg-red-500/10'
                : 'bg-green-50 dark:bg-green-500/10')
            ->recordActions([
                Action::make('previewInvoice')
                    ->label('Preview / Edit')
                    ->icon('heroicon-o-document-text')
                    ->modalWidth('3xl')
                    ->fillForm(fn (ServiceJob $record): array => [
                        'job_code' => $record->job_code,
                        'customer_name' => $record->customer_name,
                        'customer_address' => $record->customer?->address,
                        'customer_phone' => $record->customer_phone,
                        'category' => $record->category,
                        'final_price_iqd' => (float) ($record->final_price_iqd ?: $record->estimated_price_iqd ?: 0),
                        'issue' => $record->issue,
                        'notes' => $record->notes,
                        'invoice_discount_iqd' => (float) ($record->invoice_discount_iqd ?? 0),
                        'invoice_tax_iqd' => (float) ($record->invoice_tax_iqd ?? 0),
                        'invoice_items' => $record->invoice_items ?: [[
                            'item_name' => $record->category ?: 'Repair Service',
                            'item_type' => 'service',
                            'quantity' => 1,
                            'unit_price_iqd' => (float) ($record->final_price_iqd ?: $record->estimated_price_iqd ?: 0),
                        ]],
                        'payment_amount_iqd' => $this->calculateBalance($record),
                        'method' => 'cash',
                    ])
                    ->form([
                        TextInput::make('job_code')
                            ->disabled(),
                        TextInput::make('customer_name')
                            ->disabled(),
                        TextInput::make('customer_address')
                            ->label('Address')
                            ->disabled(),
                        TextInput::make('customer_phone')
                            ->disabled(),
                        TextInput::make('category')
                            ->required(),
                        TextInput::make('final_price_iqd')
                            ->label('Invoice Total')
                            ->numeric()
                            ->prefix('IQD')
                            ->required(),
                        Textarea::make('issue')
                            ->rows(3),
                        Textarea::make('notes')
                            ->rows(3),
                        Repeater::make('invoice_items')
                            ->label('Invoice Items')
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->collapsed()
                            ->reorderable()
                            ->schema([
                                TextInput::make('item_name')
                                    ->label('Item')
                                    ->required(),
                                Select::make('item_type')
                                    ->label('Type')
                                    ->options([
                                        'service' => 'Service',
                                        'part' => 'Part',
                                        'fee' => 'Fee',
                                        'other' => 'Other',
                                    ])
                                    ->default('service')
                                    ->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                                TextInput::make('unit_price_iqd')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('IQD')
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                        TextInput::make('invoice_discount_iqd')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('IQD')
                            ->default(0),
                        TextInput::make('invoice_tax_iqd')
                            ->label('Tax')
                            ->numeric()
                            ->prefix('IQD')
                            ->default(0),
                        TextInput::make('payment_amount_iqd')
                            ->label('New Payment')
                            ->numeric()
                            ->prefix('IQD')
                            ->default(0),
                        Select::make('method')
                            ->options([
                                'cash' => 'Cash',
                                'transfer' => 'Transfer',
                            ])
                            ->default('cash')
                            ->required(),
                    ])
                    ->action(function (ServiceJob $record, array $data): void {
                        $items = collect($data['invoice_items'] ?? [])
                            ->filter(fn (array $item): bool => filled($item['item_name'] ?? null))
                            ->map(function (array $item): array {
                                $quantity = max(1, (float) ($item['quantity'] ?? 1));
                                $unitPrice = max(0, (float) ($item['unit_price_iqd'] ?? 0));

                                return [
                                    'item_name' => $item['item_name'],
                                    'item_type' => $item['item_type'] ?? 'other',
                                    'quantity' => $quantity,
                                    'unit_price_iqd' => $unitPrice,
                                    'line_total_iqd' => $quantity * $unitPrice,
                                ];
                            })
                            ->values()
                            ->all();

                        $calculatedTotal = (float) collect($items)->sum('line_total_iqd');
                        $discount = max(0, (float) ($data['invoice_discount_iqd'] ?? 0));
                        $tax = max(0, (float) ($data['invoice_tax_iqd'] ?? 0));
                        $finalTotal = max(0, $calculatedTotal - $discount + $tax);

                        $record->update([
                            'category' => $data['category'],
                            'final_price_iqd' => $calculatedTotal > 0 ? $finalTotal : (float) $data['final_price_iqd'],
                            'issue' => $data['issue'],
                            'notes' => $data['notes'],
                            'invoice_items' => $items,
                            'invoice_discount_iqd' => $discount,
                            'invoice_tax_iqd' => $tax,
                        ]);

                        $payment = (float) ($data['payment_amount_iqd'] ?? 0);

                        if ($payment > 0) {
                            Payment::query()->create([
                                'service_job_id' => $record->id,
                                'amount_iqd' => $payment,
                                'method' => $data['method'],
                                'notes' => 'Created from invoice preview panel',
                            ]);
                        }
                    })
                    ->successNotificationTitle('Invoice updated successfully'),
                Action::make('printInvoice')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->url(fn (ServiceJob $record): string => url('/admin/invoices/'.$record->id.'/print'))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    private function calculateBalance(ServiceJob $record): float
    {
        $total = (float) ($record->final_price_iqd ?: $record->estimated_price_iqd ?: 0);
        $paid = (float) ($record->payments_sum_amount_iqd ?? 0);

        return max(0, $total - $paid);
    }
}
