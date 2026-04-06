<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'sku' => 'PNL-55-QLED',
                'name' => '55 inch QLED panel ribbon kit',
                'category' => 'Panel',
                'on_hand' => 7,
                'reserved' => 2,
                'reorder_level' => 4,
                'unit_cost_iqd' => 85000,
                'supplier' => 'Erbil Display Parts',
                'location' => 'Shelf A1',
            ],
            [
                'sku' => 'PWR-LG-220',
                'name' => 'LG power supply board 220V',
                'category' => 'Power',
                'on_hand' => 3,
                'reserved' => 1,
                'reorder_level' => 5,
                'unit_cost_iqd' => 42000,
                'supplier' => 'Noor Electronics',
                'location' => 'Shelf B3',
            ],
            [
                'sku' => 'MB-SAM-4K',
                'name' => 'Samsung 4K main board',
                'category' => 'Board',
                'on_hand' => 11,
                'reserved' => 4,
                'reorder_level' => 6,
                'unit_cost_iqd' => 93000,
                'supplier' => 'Nozan Wholesale',
                'location' => 'Drawer C2',
            ],
            [
                'sku' => 'AUD-TCL-50',
                'name' => 'TCL 50 audio amp module',
                'category' => 'Audio',
                'on_hand' => 2,
                'reserved' => 0,
                'reorder_level' => 3,
                'unit_cost_iqd' => 26000,
                'supplier' => 'Rania Tech',
                'location' => 'Shelf D1',
            ],
            [
                'sku' => 'ACC-HDMI-SET',
                'name' => 'HDMI cleaning and port repair kit',
                'category' => 'Accessory',
                'on_hand' => 16,
                'reserved' => 5,
                'reorder_level' => 8,
                'unit_cost_iqd' => 9000,
                'supplier' => 'Noor Electronics',
                'location' => 'Bin E5',
            ],
            [
                'sku' => 'LED-BL-49',
                'name' => '49 inch LED backlight strip pack',
                'category' => 'Panel',
                'on_hand' => 4,
                'reserved' => 2,
                'reorder_level' => 4,
                'unit_cost_iqd' => 38000,
                'supplier' => 'Erbil Display Parts',
                'location' => 'Shelf A4',
            ],
        ];

        foreach ($items as $item) {
            InventoryItem::query()->updateOrCreate(
                ['sku' => $item['sku']],
                $item,
            );
        }
    }
}
