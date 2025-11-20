<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class MissingCategorySeeder extends Seeder
{
    public function run()
    {
        $rows = [

            // ================== VEHICLES (MISSING ITEMS) ==================
            ['Vehicles', 'Watercraft & Boats', ''],
            ['Vehicles', 'Construction & Heavy Machinery', ''],
            ['Vehicles', 'Trucks & Trailers', ''],
            ['Vehicles', 'Motorcycles & Scooters', ''],
            ['Vehicles', 'Buses & Microbuses', ''],
            ['Vehicles', 'Vehicle Parts & Accessories', ''],

            // ================== INDUSTRIAL MACHINERY ==================
            ['Industrial Machinery', '', ''],
            ['Industrial Machinery', 'Industrial Generators', ''],
            ['Industrial Machinery', 'Welding Machines', ''],
            ['Industrial Machinery', 'Borehole Drilling Equipment', ''],
            ['Industrial Machinery', 'Factory Machines', ''],
            ['Industrial Machinery', 'Packaging Machines', ''],

            // ================== AUTOMOTIVE ==================
            ['Automotive', '', ''],
            ['Automotive', 'Car Accessories', ''],
            ['Automotive', 'Motorcycle Parts & Accessories', ''],

            // ================== GAMING ==================
            ['Gaming', '', ''],
            ['Gaming', 'Consoles & Accessories', ''],
            ['Gaming', 'Video Games', ''],

            // ================== LEISURE ==================
            ['Leisure, Arts & Entertainment', 'Outdoor Gear', ''],

            // ================== JEWELRY (EXTRA ITEMS) ==================
            ['Jewelry & Watches', 'Diamond & Gemstones', ''],
            ['Jewelry & Watches', 'Luxury Watches', ''],
            ['Jewelry & Watches', 'Fashion Watches', ''],
            ['Jewelry & Watches', 'Wedding Rings & Bands', ''],
        ];

        foreach ($rows as $row) {
            $level1 = $row[0];
            $level2 = $row[1];
            $level3 = $row[2];

            // Create or get level 1
            $parent1 = Category::firstOrCreate(
                ['title' => $level1, 'parent_id' => null]
            );

            if (!$level2) continue;

            // Create or get level 2
            $parent2 = Category::firstOrCreate(
                ['title' => $level2, 'parent_id' => $parent1->id]
            );

            if (!$level3) continue;

            // Create or get level 3
            Category::firstOrCreate(
                ['title' => $level3, 'parent_id' => $parent2->id]
            );
        }
    }
}
