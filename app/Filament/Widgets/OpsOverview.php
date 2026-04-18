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
        if (! Schema::hasTable('service_jobs')) {
            return $this->emptyStats();
        }

        $serviceJobColumns = array_flip(Schema::getColumnListing('service_jobs'));
        $statusColumn = isset($serviceJobColumns['status']);
        $updatedAtColumn = isset($serviceJobColumns['updated_at']);
        $createdAtColumn = isset($serviceJobColumns['created_at']);
        $finalPriceColumn = isset($serviceJobColumns['final_price_iqd']);

        $pendingJobs = $statusColumn
            ? ServiceJob::query()->whereIn('status', ['pending', 'PENDING'])->count()
            : 0;

        $finishedToday = 0;
        if ($statusColumn && $updatedAtColumn) {
            $finishedToday = ServiceJob::query()
                ->whereIn('status', ['finished', 'FINISHED', 'OUT', 'CHECKED_OUT'])
                ->whereDate('updated_at', today())
                ->count();
        }

        $todayJobs = $createdAtColumn
            ? ServiceJob::query()->whereDate('created_at', today())->count()
            : 0;

        $totalInvoiced = $finalPriceColumn
            ? (float) ServiceJob::query()->sum('final_price_iqd')
            : 0.0;

        $totalPaid = 0.0;
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'amount_iqd')) {
            $totalPaid = (float) Payment::query()->sum('amount_iqd');
        }

        $stockColumn = null;
        $thresholdColumn = null;
        if (Schema::hasTable('inventory_items')) {
            $inventoryColumns = array_flip(Schema::getColumnListing('inventory_items'));
            $stockColumn = isset($inventoryColumns['on_hand'])
                ? 'on_hand'
                : (isset($inventoryColumns['quantity']) ? 'quantity' : null);
            $thresholdColumn = isset($inventoryColumns['reorder_level'])
                ? 'reorder_level'
                : (isset($inventoryColumns['low_stock_threshold']) ? 'low_stock_threshold' : null);
        }

        $lowStockCount = 0;
        if ($stockColumn !== null && $thresholdColumn !== null) {
            $lowStockCount = (int) InventoryItem::query()
                ->whereColumn($stockColumn, '<=', $thresholdColumn)
                ->count();
        }

        return [
            Stat::make('Today Jobs', $todayJobs)
                ->description('New service tickets created today')
                ->color('info'),
            Stat::make('Pending Queue', $pendingJobs)
                ->description('Tickets waiting for workshop action')
                ->color($pendingJobs > 0 ? 'warning' : 'success'),
            Stat::make('Billing Snapshot', number_format($totalInvoiced).' / '.number_format($totalPaid).' IQD')
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

    private function emptyStats(): array
    {
        return [
            Stat::make('Today Jobs', 0)->description('Waiting for migration sync')->color('gray'),
            Stat::make('Pending Queue', 0)->description('Waiting for migration sync')->color('gray'),
            Stat::make('Billing Snapshot', '0 / 0 IQD')->description('Waiting for migration sync')->color('gray'),
            Stat::make('Finished Today', 0)->description('Waiting for migration sync')->color('gray'),
            Stat::make('Low Stock', 0)->description('Waiting for migration sync')->color('gray'),
        ];
    }
}
