<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_items', 'model')) {
                $table->string('model')->nullable()->after('name');
            }

            if (! Schema::hasColumn('inventory_items', 'part_number')) {
                $table->string('part_number')->nullable()->index()->after('model');
            }

            if (! Schema::hasColumn('inventory_items', 'similar_products')) {
                $table->json('similar_products')->nullable()->after('part_number');
            }

            if (! Schema::hasColumn('inventory_items', 'image_path')) {
                $table->string('image_path')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_items', 'image_path')) {
                $table->dropColumn('image_path');
            }

            if (Schema::hasColumn('inventory_items', 'similar_products')) {
                $table->dropColumn('similar_products');
            }

            if (Schema::hasColumn('inventory_items', 'part_number')) {
                $table->dropColumn('part_number');
            }

            if (Schema::hasColumn('inventory_items', 'model')) {
                $table->dropColumn('model');
            }
        });
    }
};
