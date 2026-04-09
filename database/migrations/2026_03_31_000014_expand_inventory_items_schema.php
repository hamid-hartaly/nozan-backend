<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_items', 'quantity')) {
                $table->integer('quantity')->default(0);
            }

            if (! Schema::hasColumn('inventory_items', 'buy_price')) {
                $table->decimal('buy_price', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('inventory_items', 'sell_price')) {
                $table->decimal('sell_price', 12, 2)->nullable();
            }
        });

        DB::table('inventory_items')->update([
            'quantity' => DB::raw('on_hand'),
            'buy_price' => DB::raw('unit_cost_iqd'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $columns = [
                'quantity',
                'buy_price',
                'sell_price',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('inventory_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
