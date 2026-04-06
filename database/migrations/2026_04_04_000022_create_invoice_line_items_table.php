<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_line_items')) {
            return;
        }

        Schema::create('invoice_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20)->default('extra');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price_iqd', 14, 2)->default(0);
            $table->decimal('line_total_iqd', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
