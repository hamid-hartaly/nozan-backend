<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_cost_iqd', 12, 2)->default(0);
            $table->decimal('sell_price_iqd', 12, 2)->default(0);
            $table->integer('low_stock_threshold')->default(3);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
