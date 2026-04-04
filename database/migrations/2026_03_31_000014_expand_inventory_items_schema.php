<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->integer('quantity')->default(0)->after('on_hand');
            $table->decimal('buy_price', 12, 2)->nullable()->after('unit_cost_iqd');
            $table->decimal('sell_price', 12, 2)->nullable()->after('buy_price');
        });

        DB::table('inventory_items')->update([
            'quantity' => DB::raw('on_hand'),
            'buy_price' => DB::raw('unit_cost_iqd'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn([
                'quantity',
                'buy_price',
                'sell_price',
            ]);
        });
    }
};
