<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\ServiceJob;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class OpsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingJobs = ServiceJob::query()->whereIn('status', ['pending', 'PENDING'])->count();
        $finishedToday = ServiceJob::query()
            ->whereIn('status', ['finished', 'FINISHED', 'OUT', 'CHECKED_OUT'])
            ->whereDate('updated_at', today())
            ->count();
        $totalInvoiced = (float) ServiceJob::query()->sum('final_price_iqd');
        $totalPaid = (float) Payment::query()->sum('amount_iqd');

        $inventoryColumns = array_flip(Schema::getColumnListing('inventory_items'));
        $stockColumn = isset($inventoryColumns['on_hand'])
            ? 'on_hand'
            : (isset($inventoryColumns['quantity']) ? 'quantity' : null);
        $thresholdColumn = isset($inventoryColumns['reorder_level'])
            ? 'reorder_level'
            : (isset($inventoryColumns['low_stock_threshold']) ? 'low_stock_threshold' : null);

        $lowStockCount = 0;
        if ($stockColumn !== null && $thresholdColumn !== null) {
            $lowStockCount = (int) InventoryItem::query()
                ->whereColumn($stockColumn, '<=', $thresholdColumn)
                ->count();
        }

        return [
            Stat::make('Today Jobs', ServiceJob::query()->whereDate('created_at', today())->count())
                ->description('New service tickets created today')
                ->color('info'),
            Stat::make('Pending Queue', $pendingJobs)
                ->description('Tickets waiting for workshop action')
                ->color($pendingJobs > 0 ? 'warning' : 'success'),
            Stat::make('Billing Snapshot', number_format($totalInvoiced) . ' / ' . number_format($totalPaid) . ' IQD')
                ->description('Invoiced / collected')
                ->color($totalPaid < $totalInvoiced ? 'danger' : 'success'),
            Stat::make('Finished Today', $finishedToday)
                ->description('Completed or delivered jobs')
                ->color('success'),
            Stat::make('Low Stock', $lowStockCount)
                ->description('Parts at or below reorder level')
                ->color('danger'),
        ];
    }
}
