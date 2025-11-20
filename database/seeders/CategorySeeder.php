<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\ServiceCategory;

class CategorySeeder extends Seeder
{
    public function run()
    {
        // Read from categories.txt file directly
        $txtFile = base_path('categories.txt');
        
        if (!file_exists($txtFile)) {
            $this->command->error("categories.txt file not found at: {$txtFile}");
            $this->command->info("Falling back to hardcoded categories...");
            $this->runHardcoded();
            return;
        }

        $handle = fopen($txtFile, 'r');
        if (!$handle) {
            $this->command->error("Could not open categories.txt file");
            $this->command->info("Falling back to hardcoded categories...");
            $this->runHardcoded();
            return;
        }

        // Skip header row
        $header = fgetcsv($handle);
        
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 3) {
                continue;
            }
            
            $level1 = trim($data[0] ?? '');
            $level2 = trim($data[1] ?? '');
            $level3 = trim($data[2] ?? '');
            
            if ($level1) {
                $rows[] = [$level1, $level2, $level3];
            }
        }
        fclose($handle);

        $this->processRows($rows);
    }

    private function processRows(array $rows)
    {
        foreach ($rows as $row) {
            $level1 = trim($row[0]);
            $level2 = trim($row[1]);
            $level3 = trim($row[2]);

            // SERVICES GO TO ServiceCategory TABLE ONLY (not Category table)
            if ($level1 === "Services") {
                // Add service subcategories to ServiceCategory table
                if ($level2) {
                    ServiceCategory::firstOrCreate(
                        ['title' => $level2],
                        ['image' => null, 'is_active' => 1]
                    );
                }
                continue;
            }

            // Level 1 - Always create, even if no Level 2
            $parent1 = Category::firstOrCreate(
                ['title' => $level1, 'parent_id' => null]
            );

            if (!$level2) {
                continue; // No Level 2, so we're done with this row
            }

            // Level 2
            $parent2 = Category::firstOrCreate(
                ['title' => $level2, 'parent_id' => $parent1->id]
            );

            if (!$level3) {
                continue; // No Level 3, so we're done with this row
            }

            // Level 3
            Category::firstOrCreate(
                ['title' => $level3, 'parent_id' => $parent2->id]
            );
        }
    }

    private function runHardcoded()
    {
        $rows = [
            // ========================= VEHICLES =========================
            ['Vehicles', '', ''],
            ['Vehicles', 'Cars', ''],
            ['Vehicles', 'Motorcycles & Scooters', ''],
            ['Vehicles', 'Buses & Microbuses', ''],
            ['Vehicles', 'Trucks & Trailers', ''],
            ['Vehicles', 'Vehicle Parts & Accessories', ''],
            ['Vehicles', 'Construction & Heavy Machinery', ''],
            ['Vehicles', 'Watercraft & Boats', ''],
            ['Vehicles', 'Car Services', ''],

            // ========================= PROPERTY =========================
            ['Property', '', ''],
            ['Property', 'New Builds', ''],
            ['Property', 'Houses & Apartments For Rent', ''],
            ['Property', 'Houses & Apartments For Sale', ''],
            ['Property', 'Short Let', ''],
            ['Property', 'Land & Plots For Sale', ''],
            ['Property', 'Land & Plots for Rent', ''],
            ['Property', 'Event Centres, Venues & Workstations', ''],
            ['Property', 'Commercial Property for Rent', ''],
            ['Property', 'Commercial Property for Sale', ''],
            ['Property', 'Real Estate Agents & Services', ''],
            ['Property', 'Property Valuation & Surveying', ''],

            // ================= MOBILE PHONES & TABLETS =================
            ['Mobile Phones & Tablets', '', ''],
            ['Mobile Phones & Tablets', 'Mobile Phones', ''],
            ['Mobile Phones & Tablets', 'Tablets', ''],
            ['Mobile Phones & Tablets', 'Accessories for Phones & Tablets', ''],
            ['Mobile Phones & Tablets', 'Smart Watches', ''],
            ['Mobile Phones & Tablets', 'Headphones', ''],

            // ======================== ELECTRONICS =======================
            ['Electronics', '', ''],
            ['Electronics', 'Laptops & Computers', ''],
            ['Electronics', 'TV & DVD Equipment', ''],
            ['Electronics', 'Televisions & Home Theater', ''],
            ['Electronics', 'Audio & Music Equipment', ''],
            ['Electronics', 'Headphones', ''],
            ['Electronics', 'Photo & Video Cameras', ''],
            ['Electronics', 'Security & Surveillance', ''],
            ['Electronics', 'Video Game Consoles', ''],
            ['Electronics', 'Video Games', ''],
            ['Electronics', 'Printers & Scanners', ''],
            ['Electronics', 'Computer Monitors', ''],
            ['Electronics', 'Computer Hardware', ''],
            ['Electronics', 'Computer Accessories', ''],
            ['Electronics', 'Networking Products', ''],
            ['Electronics', 'Accessories & Supplies for Electronics', ''],
            ['Electronics', 'Software', ''],

            // ============ HOME, FURNITURE & APPLIANCES =================
            ['Home, Furniture & Appliances', '', ''],
            ['Home, Furniture & Appliances', 'Furniture', ''],
            ['Home, Furniture & Appliances', 'Lighting', ''],
            ['Home, Furniture & Appliances', 'Storage & Organization', ''],
            ['Home, Furniture & Appliances', 'Home Accessories', ''],
            ['Home, Furniture & Appliances', 'Home Appliances', ''],
            ['Home, Furniture & Appliances', 'Kitchen Appliances', ''],
            ['Home, Furniture & Appliances', 'Kitchenware & Cookware', ''],
            ['Home, Furniture & Appliances', 'Household Chemicals', ''],
            ['Home, Furniture & Appliances', 'Garden Supplies', ''],
            ['Home, Furniture & Appliances', 'Generators & Solar', ''],

            // ================= SOLAR & POWER SOLUTIONS =================
            ['Solar & Power Solutions', '', ''],
            ['Solar & Power Solutions', 'Solar Panels', ''],
            ['Solar & Power Solutions', 'Inverters', ''],
            ['Solar & Power Solutions', 'Solar Batteries', ''],
            ['Solar & Power Solutions', 'Charge Controllers', ''],
            ['Solar & Power Solutions', 'Solar Lights & Kits', ''],
            ['Solar & Power Solutions', 'UPS & Stabilizers', ''],

            // =========================== FASHION ========================
            ['Fashion', '', ''],
            ['Fashion', "Women's Fashion", ''],
            ['Fashion', "Women's Fashion", "Women's Clothing"],
            ['Fashion', "Women's Fashion", "Women's Shoes"],
            ['Fashion', "Women's Fashion", "Women's Bags"],
            ['Fashion', "Women's Fashion", "Women's Jewelry"],
            ['Fashion', "Women's Fashion", "Women's Watches"],
            ['Fashion', "Women's Fashion", "Women's Clothing Accessories"],
            ['Fashion', "Women's Fashion", "Women's Wedding Wear & Accessories"],
            ['Fashion', "Men's Fashion", ''],
            ['Fashion', "Men's Fashion", "Men's Clothing"],
            ['Fashion', "Men's Fashion", "Men's Shoes"],
            ['Fashion', "Men's Fashion", "Men's Bags"],
            ['Fashion', "Men's Fashion", "Men's Jewelry"],
            ['Fashion', "Men's Fashion", "Men's Watches"],
            ['Fashion', "Men's Fashion", "Men's Clothing Accessories"],
            ['Fashion', "Men's Fashion", "Men's Wedding Wear & Accessories"],
            ['Fashion', "Kids' Fashion", ''],
            ['Fashion', "Kids' Fashion", "Children's Clothing"],
            ['Fashion', "Kids' Fashion", "Children's Shoes"],
            ['Fashion', "Kids' Fashion", "Babies & Kids Accessories"],

            // ================== JEWELRY & WATCHES ======================
            ['Jewelry & Watches', '', ''],
            ['Jewelry & Watches', 'Gold Jewelry', ''],
            ['Jewelry & Watches', 'Silver Jewelry', ''],
            ['Jewelry & Watches', 'Diamond & Gemstones', ''],
            ['Jewelry & Watches', 'Luxury Watches', ''],
            ['Jewelry & Watches', 'Fashion Watches', ''],
            ['Jewelry & Watches', 'Wedding Rings & Bands', ''],

            // ============== LUGGAGE, BAGS & TRAVEL =====================
            ['Luggage, Bags & Travel', '', ''],
            ['Luggage, Bags & Travel', 'Suitcases & Travel Bags', ''],
            ['Luggage, Bags & Travel', 'Backpacks', ''],
            ['Luggage, Bags & Travel', 'Handbags & Wallets', ''],
            ['Luggage, Bags & Travel', 'Travel Accessories', ''],
            ['Luggage, Bags & Travel', 'Umbrellas', ''],

            // ===================== HEALTH & BEAUTY =====================
            ['Health & Beauty', '', ''],
            ['Health & Beauty', 'Hair Beauty', ''],
            ['Health & Beauty', 'Face Care', ''],
            ['Health & Beauty', 'Oral Care', ''],
            ['Health & Beauty', 'Body Care', ''],
            ['Health & Beauty', 'Fragrance', ''],
            ['Health & Beauty', 'Makeup', ''],
            ['Health & Beauty', 'Sexual Wellness', ''],
            ['Health & Beauty', 'Tools & Accessories', ''],
            ['Health & Beauty', 'Vitamins & Supplements', ''],
            ['Health & Beauty', 'Massagers', ''],
            ['Health & Beauty', 'Medical Supplies', ''],
            ['Health & Beauty', 'Wheelchairs & Mobility Aids', ''],
            ['Health & Beauty', 'Blood Pressure Monitors', ''],
            ['Health & Beauty', 'Glucometers & Test Strips', ''],
            ['Health & Beauty', 'First Aid & PPE', ''],

            // ===================== SERVICES (SPECIAL) ==================
            ['Services', 'Building & Trades Services', ''],
            ['Services', 'Car Services', ''],
            ['Services', 'Computer & IT Services', ''],
            ['Services', 'Repair Services', ''],
            ['Services', 'Cleaning Services', ''],
            ['Services', 'Printing Services', ''],
            ['Services', 'Manufacturing Services', ''],
            ['Services', 'Logistics Services', ''],
            ['Services', 'Legal Services', ''],
            ['Services', 'Tax & Financial Services', ''],
            ['Services', 'Recruitment Services', ''],
            ['Services', 'Rental Services', ''],
            ['Services', 'Chauffeur & Airport Transfer Services', ''],
            ['Services', 'Travel Agents & Tours', ''],
            ['Services', 'Classes & Courses', ''],
            ['Services', 'Child Care & Education Services', ''],
            ['Services', 'Health & Beauty Services', ''],
            ['Services', 'Fitness & Personal Training Services', ''],
            ['Services', 'Party, Catering & Event Services', ''],
            ['Services', 'DJ & Entertainment Services', ''],
            ['Services', 'Wedding Venues & Services', ''],
            ['Services', 'Photography & Video Services', ''],
            ['Services', 'Landscaping & Gardening Services', ''],
            ['Services', 'Pet Services', ''],
            ['Services', 'Other Services', ''],

            // ===================== WEDDING & EVENTS ====================
            ['Wedding & Events', '', ''],
            ['Wedding & Events', 'Wedding Gowns & Suits', ''],
            ['Wedding & Events', 'Wedding Accessories', ''],
            ['Wedding & Events', 'Event Decorations', ''],
            ['Wedding & Events', 'Catering Equipment', ''],
            ['Wedding & Events', 'Event Planning Services', ''],

            // ================= REPAIR & CONSTRUCTION ===================
            ['Repair & Construction', '', ''],
            ['Repair & Construction', 'Electrical Equipment', ''],
            ['Repair & Construction', 'Building Materials & Supplies', ''],
            ['Repair & Construction', 'Plumbing & Water Systems', ''],
            ['Repair & Construction', 'Electrical Hand Tools', ''],
            ['Repair & Construction', 'Hand Tools', ''],
            ['Repair & Construction', 'Measuring & Testing Tools', ''],
            ['Repair & Construction', 'Hardware & Fasteners', ''],
            ['Repair & Construction', 'Doors & Security', ''],
            ['Repair & Construction', 'Windows & Glass', ''],
            ['Repair & Construction', 'Other Repair & Construction Items', ''],
            ['Repair & Construction', 'Automotive Tools & Garage Equipment', ''],

            // ============ COMMERCIAL EQUIPMENT & TOOLS =================
            ['Commercial Equipment & Tools', '', ''],
            ['Commercial Equipment & Tools', 'Medical Equipment & Supplies', ''],
            ['Commercial Equipment & Tools', 'Safety Equipment & Protective Gear', ''],
            ['Commercial Equipment & Tools', 'Manufacturing Equipment', ''],
            ['Commercial Equipment & Tools', 'Manufacturing Materials & Supplies', ''],
            ['Commercial Equipment & Tools', 'Retail & Store Equipment', ''],
            ['Commercial Equipment & Tools', 'Restaurant & Catering Equipment', ''],
            ['Commercial Equipment & Tools', 'Stationery & Office Equipment', ''],
            ['Commercial Equipment & Tools', 'Salon & Beauty Equipment', ''],
            ['Commercial Equipment & Tools', 'Printing & Graphics Equipment', ''],
            ['Commercial Equipment & Tools', 'Stage & Event Equipment', ''],

            // ================== INDUSTRIAL MACHINERY ===================
            ['Industrial Machinery', '', ''],
            ['Industrial Machinery', 'Industrial Generators', ''],
            ['Industrial Machinery', 'Welding Machines', ''],
            ['Industrial Machinery', 'Borehole Drilling Equipment', ''],
            ['Industrial Machinery', 'Factory Machines', ''],
            ['Industrial Machinery', 'Packaging Machines', ''],

            // ==================== OFFICE & STATIONERY ==================
            ['Office & Stationery', '', ''],
            ['Office & Stationery', 'Office Furniture', ''],
            ['Office & Stationery', 'Stationery & Supplies', ''],
            ['Office & Stationery', 'Printer Ink & Toner', ''],
            ['Office & Stationery', 'Office Electronics', ''],

            // ============ SCHOOL SUPPLIES & UNIFORMS ===================
            ['School Supplies & Uniforms', '', ''],
            ['School Supplies & Uniforms', 'School Uniforms', ''],
            ['School Supplies & Uniforms', 'School Bags', ''],
            ['School Supplies & Uniforms', 'Textbooks & Educational Materials', ''],
            ['School Supplies & Uniforms', 'Stationery', ''],

            // ========== LEISURE, ARTS & ENTERTAINMENT ==================
            ['Leisure, Arts & Entertainment', '', ''],
            ['Leisure, Arts & Entertainment', 'Sports Equipment', ''],
            ['Leisure, Arts & Entertainment', 'Massagers', ''],
            ['Leisure, Arts & Entertainment', 'Musical Instruments & Gear', ''],
            ['Leisure, Arts & Entertainment', 'Books & Table Games', ''],
            ['Leisure, Arts & Entertainment', 'Arts, Crafts & Awards', ''],
            ['Leisure, Arts & Entertainment', 'Outdoor Gear', ''],
            ['Leisure, Arts & Entertainment', 'Smoking Accessories', ''],
            ['Leisure, Arts & Entertainment', 'Music & Video', ''],
            ['Leisure, Arts & Entertainment', 'Fitness & Personal Training Services', ''],

            // ======================= BABIES & KIDS =====================
            ['Babies & Kids', '', ''],
            ['Babies & Kids', 'Toys, Games & Bikes', ''],
            ['Babies & Kids', 'Action Figures & Dolls', ''],
            ['Babies & Kids', 'Board Games & Puzzles', ''],
            ['Babies & Kids', 'Drones & RC Toys', ''],
            ['Babies & Kids', "Children's Furniture", ''],
            ['Babies & Kids', "Children's Clothing", ''],
            ['Babies & Kids', "Children's Shoes", ''],
            ['Babies & Kids', 'Babies & Kids Accessories', ''],
            ['Babies & Kids', 'Baby Gear & Equipment', ''],
            ['Babies & Kids', 'Care & Feeding', ''],
            ['Babies & Kids', 'Maternity & Pregnancy', ''],
            ['Babies & Kids', 'Transport & Safety', ''],
            ['Babies & Kids', 'Playground Equipment', ''],

            // ================= FOOD, AGRICULTURE & FARMING =============
            ['Food, Agriculture & Farming', '', ''],
            ['Food, Agriculture & Farming', 'Food & Beverages', ''],
            ['Food, Agriculture & Farming', 'Farm Animals', ''],
            ['Food, Agriculture & Farming', 'Feeds, Supplements & Seeds', ''],
            ['Food, Agriculture & Farming', 'Farm Machinery & Equipment', ''],

            // ======================= ANIMALS & PETS ====================
            ['Animals & Pets', '', ''],
            ['Animals & Pets', "Pet's Accessories", ''],
            ['Animals & Pets', 'Cats & Kittens', ''],
            ['Animals & Pets', 'Dogs & Puppies', ''],
            ['Animals & Pets', 'Fish', ''],
            ['Animals & Pets', 'Birds', ''],
            ['Animals & Pets', 'Other Animals', ''],
            ['Animals & Pets', 'Pet Services', ''],

            // ================== SUPERMARKET & GROCERIES =================
            ['Supermarket & Groceries', '', ''],
            ['Supermarket & Groceries', 'Food Cupboard', ''],
            ['Supermarket & Groceries', 'Beverages', ''],
            ['Supermarket & Groceries', 'Household Cleaning', ''],
            ['Supermarket & Groceries', 'Laundry & Cleaning', ''],
            ['Supermarket & Groceries', 'Baby Products', ''],
            ['Supermarket & Groceries', 'Wine, Spirits & Tobacco', ''],

            // ================= SPORTING GOODS & FITNESS =================
            ['Sporting Goods & Fitness', '', ''],
            ['Sporting Goods & Fitness', 'Exercise & Fitness Equipment', ''],
            ['Sporting Goods & Fitness', 'Sports Clothing', ''],
            ['Sporting Goods & Fitness', 'Outdoor & Adventure', ''],
            ['Sporting Goods & Fitness', 'Team Sports', ''],

            // ======================== GIFTS & HAMPERS ===================
            ['Gifts & Hampers', '', ''],
            ['Gifts & Hampers', 'Gift Sets & Boxes', ''],
            ['Gifts & Hampers', 'Corporate Gifts', ''],
            ['Gifts & Hampers', 'Birthday & Anniversary Gifts', ''],
            ['Gifts & Hampers', 'Christmas & Festive Hampers', ''],

            // =========================== GAMING =========================
            ['Gaming', '', ''],
            ['Gaming', 'Consoles & Accessories', ''],
            ['Gaming', 'Video Games', ''],

            // ================== BOOKS, MOVIES & MUSIC ===================
            ['Books, Movies & Music', '', ''],
            ['Books, Movies & Music', 'Books', ''],
            ['Books, Movies & Music', 'Musical Instruments', ''],
            ['Books, Movies & Music', 'Movies & TV Shows', ''],

            // ================= VIRTUAL & DIGITAL PRODUCTS ===============
            ['Virtual & Digital Products', '', ''],
            ['Virtual & Digital Products', 'Airtime & Mobile Data', ''],
            ['Virtual & Digital Products', 'Gift Cards & Vouchers', ''],
            ['Virtual & Digital Products', 'Software Licenses', ''],
            ['Virtual & Digital Products', 'Online Courses & eBooks', ''],
            ['Virtual & Digital Products', 'Streaming Subscriptions', ''],

            // ========================== AUTOMOTIVE ======================
            ['Automotive', '', ''],
            ['Automotive', 'Car Accessories', ''],
            ['Automotive', 'Motorcycle Parts & Accessories', ''],
        ];

        $this->processRows($rows);
    }
}
