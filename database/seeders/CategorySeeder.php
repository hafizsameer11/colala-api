<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        // Clear existing categories
        // Category::truncate();

        $categories = $this->getCategories();
        $this->processCategories($categories);
    }

    private function processCategories(array $categories)
    {
        foreach ($categories as $category) {
            $level1 = $category['level1'] ?? null;
            $level2 = $category['level2'] ?? null;
            $level3 = $category['level3'] ?? null;

            if (!$level1) continue;

            // Level 1 - Main Category
            $parent1 = Category::firstOrCreate(
                ['title' => $level1, 'parent_id' => null],
                ['color' => $this->getRandomColor(), 'image' => 'category/u6YQNnCqPy2E2lFJgXjoA8tnSTSUzEKDVE8PkGmL.jpg']
            );

            if (!$level2) continue;

            // Level 2 - Subcategory
            $parent2 = Category::firstOrCreate(
                ['title' => $level2, 'parent_id' => $parent1->id],
                ['color' => $this->getRandomColor(), 'image' => 'category/u6YQNnCqPy2E2lFJgXjoA8tnSTSUzEKDVE8PkGmL.jpg']
            );

            if (!$level3) continue;

            // Level 3 - Sub-subcategory
            Category::firstOrCreate(
                ['title' => $level3, 'parent_id' => $parent2->id],
                ['color' => $this->getRandomColor(), 'image' => 'category/u6YQNnCqPy2E2lFJgXjoA8tnSTSUzEKDVE8PkGmL.jpg']
            );
        }
    }

    private function getRandomColor()
    {
        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
        return $colors[array_rand($colors)];
    }

    private function getCategories()
    {
        return [
            // ==================== 1. APPLIANCES ====================
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Blenders'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Deep Fryers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Juicers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Air Fryers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Rice Cookers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Toasters & Ovens'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Microwaves'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Electric Pressure Cookers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Electric Cookware'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Food Processors'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Coffee Makers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Electric Drink Mixers'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Vacuum Cleaners'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Kettles'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Yam Pounders'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Irons'],
            ['level1' => 'Appliances', 'level2' => 'Small Appliances', 'level3' => 'Bundles'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Washing Machines'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Refrigerators'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Freezers'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Air Conditioners'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Heaters'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Fans'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Air Purifiers'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Water Dispensers'],
            ['level1' => 'Appliances', 'level2' => 'Large Appliances', 'level3' => 'Generators & Inverters'],

            // ==================== 2. PHONES & TABLETS ====================
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Smartphones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Android Phones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'iPhones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Basic Phones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Refurbished Phones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Rugged Phones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Dual-SIM Phones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Phones', 'level3' => 'Cordless Telephones'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Tablets', 'level3' => 'iPads'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Tablets', 'level3' => 'Android Tablets'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Tablets', 'level3' => 'Educational Tablets'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Tablets', 'level3' => 'Amazon Fire Tablets'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Tablets', 'level3' => 'Microsoft Tablets'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Tablets', 'level3' => 'Tablet Accessories'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Chargers & Adapters'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Cables'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Power Banks'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Batteries & Battery Chargers'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Bluetooth Headsets'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Earphones & Headsets'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'MicroSD Cards'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Screen Protectors'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Selfie Sticks & Tripods'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Smart Watches'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Phone Camera Lenses'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Car Phone Accessories'],
            ['level1' => 'Phones & Tablets', 'level2' => 'Mobile Accessories', 'level3' => 'Accessory Kits'],

            // ==================== 3. HEALTH & BEAUTY ====================
            ['level1' => 'Health & Beauty', 'level2' => 'Makeup', 'level3' => 'Concealers & Color Correctors'],
            ['level1' => 'Health & Beauty', 'level2' => 'Makeup', 'level3' => 'Foundation'],
            ['level1' => 'Health & Beauty', 'level2' => 'Makeup', 'level3' => 'Powder'],
            ['level1' => 'Health & Beauty', 'level2' => 'Makeup', 'level3' => 'Lipstick'],
            ['level1' => 'Health & Beauty', 'level2' => 'Makeup', 'level3' => 'Eyeliner & Kajal'],
            ['level1' => 'Health & Beauty', 'level2' => 'Makeup', 'level3' => 'Mascara'],
            ['level1' => 'Health & Beauty', 'level2' => 'Personal Care', 'level3' => 'Skin Care'],
            ['level1' => 'Health & Beauty', 'level2' => 'Personal Care', 'level3' => 'Sunscreens & Tanning Products'],
            ['level1' => 'Health & Beauty', 'level2' => 'Personal Care', 'level3' => 'Deodorants & Antiperspirants'],
            ['level1' => 'Health & Beauty', 'level2' => 'Personal Care', 'level3' => 'Lip Care'],
            ['level1' => 'Health & Beauty', 'level2' => 'Personal Care', 'level3' => 'Contraceptives & Lubricants'],
            ['level1' => 'Health & Beauty', 'level2' => 'Personal Care', 'level3' => 'Piercing & Tattoo Supplies'],
            ['level1' => 'Health & Beauty', 'level2' => 'Fragrances', 'level3' => "Women's Fragrances"],
            ['level1' => 'Health & Beauty', 'level2' => 'Fragrances', 'level3' => "Men's Fragrances"],
            ['level1' => 'Health & Beauty', 'level2' => 'Hair Care', 'level3' => 'Hair Cutting Tools'],
            ['level1' => 'Health & Beauty', 'level2' => 'Hair Care', 'level3' => 'Shampoo & Conditioner'],
            ['level1' => 'Health & Beauty', 'level2' => 'Hair Care', 'level3' => 'Wigs & Hair Accessories'],
            ['level1' => 'Health & Beauty', 'level2' => 'Oral Care', 'level3' => 'Toothpaste'],
            ['level1' => 'Health & Beauty', 'level2' => 'Oral Care', 'level3' => 'Teeth Whitening'],
            ['level1' => 'Health & Beauty', 'level2' => 'Health Care', 'level3' => 'First Aid'],
            ['level1' => 'Health & Beauty', 'level2' => 'Health Care', 'level3' => 'Medical Supplies & Equipment'],
            ['level1' => 'Health & Beauty', 'level2' => 'Health Care', 'level3' => 'Alternative Medicine'],
            ['level1' => 'Health & Beauty', 'level2' => 'Health Care', 'level3' => 'Feminine Care'],
            ['level1' => 'Health & Beauty', 'level2' => 'Health Care', 'level3' => 'Diabetes Care'],
            ['level1' => 'Health & Beauty', 'level2' => 'Vitamins & Supplements', 'level3' => 'Vitamins & Minerals'],
            ['level1' => 'Health & Beauty', 'level2' => 'Vitamins & Supplements', 'level3' => 'Dietary Supplements'],
            ['level1' => 'Health & Beauty', 'level2' => 'Vitamins & Supplements', 'level3' => 'Multivitamins & Prenatal Vitamins'],

            // ==================== 4. HOME & OFFICE ====================
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Bath'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Bedding'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Home Decor'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Wall Art'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Furniture'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Cookware & Bakeware'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Cutlery & Knife Accessories'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Vacuums & Floor Care'],
            ['level1' => 'Home & Office', 'level2' => 'Home & Kitchen', 'level3' => 'Small Appliances'],
            ['level1' => 'Home & Office', 'level2' => 'Office Products', 'level3' => 'Office & School Supplies'],
            ['level1' => 'Home & Office', 'level2' => 'Office Products', 'level3' => 'Office Furniture & Lighting'],
            ['level1' => 'Home & Office', 'level2' => 'Office Products', 'level3' => 'Packaging Materials'],
            ['level1' => 'Home & Office', 'level2' => 'Storage & Organization', 'level3' => 'Storage Solutions'],
            ['level1' => 'Home & Office', 'level2' => 'Storage & Organization', 'level3' => 'Stationery'],
            ['level1' => 'Home & Office', 'level2' => 'Storage & Organization', 'level3' => 'Lighting'],

            // ==================== 5. ELECTRONICS ====================
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'Televisions'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'Smart TVs'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'LED & LCD TVs'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'QLED & OLED TVs'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'Curved TVs'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'TV Sizes (32", 43", 55")'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'Brands (LG, Samsung, Hisense)'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'TV Accessories'],
            ['level1' => 'Electronics', 'level2' => 'Television & Video', 'level3' => 'DVD Players & Recorders'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Digital Cameras'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'SLR Cameras'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Compact Cameras'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Instant Cameras'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Professional Video Cameras'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Action & Sports Cameras'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Drones with Camera'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'CCTV & Video Surveillance'],
            ['level1' => 'Electronics', 'level2' => 'Cameras & Photography', 'level3' => 'Projectors'],
            ['level1' => 'Electronics', 'level2' => 'Home Audio', 'level3' => 'Home Theatre Systems'],
            ['level1' => 'Electronics', 'level2' => 'Home Audio', 'level3' => 'Sound Bars'],
            ['level1' => 'Electronics', 'level2' => 'Home Audio', 'level3' => 'Bluetooth Speakers'],
            ['level1' => 'Electronics', 'level2' => 'Home Audio', 'level3' => 'Subwoofers'],
            ['level1' => 'Electronics', 'level2' => 'Home Audio', 'level3' => 'Receivers & Amplifiers'],
            ['level1' => 'Electronics', 'level2' => 'Home Audio', 'level3' => 'Brands (LG, JBL, Hisense)'],
            ['level1' => 'Electronics', 'level2' => 'Power & Energy', 'level3' => 'Generators'],
            ['level1' => 'Electronics', 'level2' => 'Power & Energy', 'level3' => 'Power Inverters'],
            ['level1' => 'Electronics', 'level2' => 'Power & Energy', 'level3' => 'Solar & Wind Power'],
            ['level1' => 'Electronics', 'level2' => 'Power & Energy', 'level3' => 'Stabilizers'],
            ['level1' => 'Electronics', 'level2' => 'Power & Energy', 'level3' => 'Batteries & Chargers'],
            ['level1' => 'Electronics', 'level2' => 'Power & Energy', 'level3' => 'Lithium Batteries'],

            // ==================== 6. FASHION ====================
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Clothing'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Dresses'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Shoes & Flats'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Accessories'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Jewelry'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Handbags & Wallets'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Underwear & Sleepwear'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Maternity Wear'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Traditional Wear'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Beach & Swimwear'],
            ['level1' => 'Fashion', 'level2' => "Women's Fashion", 'level3' => 'Costumes & Accessories'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Clothing'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'T-Shirts & Polo Shirts'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Trousers & Chinos'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Shoes & Sneakers'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Accessories'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Jewelry'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Jerseys'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Underwear & Sleepwear'],
            ['level1' => 'Fashion', 'level2' => "Men's Fashion", 'level3' => 'Traditional & Cultural Wear'],
            ['level1' => 'Fashion', 'level2' => "Kids' Fashion", 'level3' => "Boys' Fashion"],
            ['level1' => 'Fashion', 'level2' => "Kids' Fashion", 'level3' => "Girls' Fashion"],
            ['level1' => 'Fashion', 'level2' => 'All Fashion', 'level3' => 'Fabrics'],
            ['level1' => 'Fashion', 'level2' => 'All Fashion', 'level3' => 'Luggage & Travel Gear'],
            ['level1' => 'Fashion', 'level2' => 'All Fashion', 'level3' => 'Uniforms, Work & Safety'],
            ['level1' => 'Fashion', 'level2' => 'All Fashion', 'level3' => 'Multi-Packs'],
            ['level1' => 'Fashion', 'level2' => 'All Fashion', 'level3' => 'Weddings & Special Occasions'],
            ['level1' => 'Fashion', 'level2' => 'Watches & Sunglasses', 'level3' => "Men's Watches"],
            ['level1' => 'Fashion', 'level2' => 'Watches & Sunglasses', 'level3' => "Women's Watches"],
            ['level1' => 'Fashion', 'level2' => 'Watches & Sunglasses', 'level3' => "Men's Sunglasses"],
            ['level1' => 'Fashion', 'level2' => 'Watches & Sunglasses', 'level3' => "Women's Sunglasses"],

            // ==================== 7. SUPERMARKET ====================
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Grains & Rice'],
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Pasta & Noodles'],
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Herbs, Spices & Seasoning'],
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Cooking Oil'],
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Malt Drinks'],
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Coffee'],
            ['level1' => 'Supermarket', 'level2' => 'Food Cupboard', 'level3' => 'Water'],
            ['level1' => 'Supermarket', 'level2' => 'Beverages', 'level3' => 'Soft Drinks'],
            ['level1' => 'Supermarket', 'level2' => 'Beverages', 'level3' => 'Milk & Cream'],
            ['level1' => 'Supermarket', 'level2' => 'Beverages', 'level3' => 'Energy Drinks'],
            ['level1' => 'Supermarket', 'level2' => 'Beverages', 'level3' => 'Juices'],
            ['level1' => 'Supermarket', 'level2' => 'Beverages', 'level3' => 'Bottled Beverages'],
            ['level1' => 'Supermarket', 'level2' => 'Household Essentials', 'level3' => 'Laundry Supplies'],
            ['level1' => 'Supermarket', 'level2' => 'Household Essentials', 'level3' => 'Dishwashing'],
            ['level1' => 'Supermarket', 'level2' => 'Household Essentials', 'level3' => 'Bathroom Cleaners'],
            ['level1' => 'Supermarket', 'level2' => 'Household Essentials', 'level3' => 'Air Fresheners'],
            ['level1' => 'Supermarket', 'level2' => 'Household Essentials', 'level3' => 'Toilet Paper & Wipes'],
            ['level1' => 'Supermarket', 'level2' => 'Household Essentials', 'level3' => 'Cleaning Tools'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'Beers'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'Vodka'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'Whiskey'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'Liquors'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'Red Wine'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'White Wine'],
            ['level1' => 'Supermarket', 'level2' => 'Alcoholic Beverages', 'level3' => 'Champagne & Sparkling Wine'],
            ['level1' => 'Supermarket', 'level2' => 'Baby Essentials', 'level3' => 'Disposable Diapers'],
            ['level1' => 'Supermarket', 'level2' => 'Baby Essentials', 'level3' => 'Wipes & Refills'],
            ['level1' => 'Supermarket', 'level2' => 'Baby Essentials', 'level3' => 'Bottle Feeding'],

            // ==================== 8. BABY PRODUCTS ====================
            ['level1' => 'Baby Products', 'level2' => 'Apparel & Accessories', 'level3' => 'Baby Boys'],
            ['level1' => 'Baby Products', 'level2' => 'Apparel & Accessories', 'level3' => 'Baby Girls'],
            ['level1' => 'Baby Products', 'level2' => 'Diapering', 'level3' => 'Disposable Diapers'],
            ['level1' => 'Baby Products', 'level2' => 'Diapering', 'level3' => 'Diaper Bags'],
            ['level1' => 'Baby Products', 'level2' => 'Diapering', 'level3' => 'Wipes & Holders'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Bibs & Burp Cloths'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Breastfeeding'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Bottle Feeding'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Pacifiers & Accessories'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Food Storage'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Highchairs & Booster Seats'],
            ['level1' => 'Baby Products', 'level2' => 'Feeding', 'level3' => 'Solid Feeding'],
            ['level1' => 'Baby Products', 'level2' => 'Bathing & Skin Care', 'level3' => 'Bathing Tubs & Seats'],
            ['level1' => 'Baby Products', 'level2' => 'Bathing & Skin Care', 'level3' => 'Washcloths & Towels'],
            ['level1' => 'Baby Products', 'level2' => 'Bathing & Skin Care', 'level3' => 'Grooming & Healthcare Kits'],
            ['level1' => 'Baby Products', 'level2' => 'Bathing & Skin Care', 'level3' => 'Bathroom Safety'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Baby & Toddler Toys'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Activity Play Centers'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Music & Sound Toys'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Bath Toys'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Backpacks & Carriers'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Walkers'],
            ['level1' => 'Baby Products', 'level2' => 'Toys & Gear', 'level3' => 'Swings, Jumpers & Bouncers'],

            // ==================== 9. GAMING ====================
            ['level1' => 'Gaming', 'level2' => 'PlayStation', 'level3' => 'PS5, PS4, PS3, Vita'],
            ['level1' => 'Gaming', 'level2' => 'Xbox', 'level3' => 'Xbox One, Xbox 360'],
            ['level1' => 'Gaming', 'level2' => 'Nintendo', 'level3' => 'Switch, Wii, DS, 3DS'],
            ['level1' => 'Gaming', 'level2' => 'Gaming CDs', 'level3' => null],

            // ==================== 10. MUSICAL INSTRUMENTS ====================
            ['level1' => 'Musical Instruments', 'level2' => 'Instruments', 'level3' => 'Guitars'],
            ['level1' => 'Musical Instruments', 'level2' => 'Instruments', 'level3' => 'Drums & Percussion'],
            ['level1' => 'Musical Instruments', 'level2' => 'Instruments', 'level3' => 'Keyboards & MIDI'],
            ['level1' => 'Musical Instruments', 'level2' => 'Instruments', 'level3' => 'Wind & Woodwind Instruments'],
            ['level1' => 'Musical Instruments', 'level2' => 'Instruments', 'level3' => 'Band & Orchestra Instruments'],
            ['level1' => 'Musical Instruments', 'level2' => 'Studio & Live Equipment', 'level3' => 'Live Sound & Stage Equipment'],
            ['level1' => 'Musical Instruments', 'level2' => 'Studio & Live Equipment', 'level3' => 'Studio Recording Equipment'],
            ['level1' => 'Musical Instruments', 'level2' => 'Studio & Live Equipment', 'level3' => 'DJ & Karaoke Equipment'],

            // ==================== 11. OTHERS ====================
            ['level1' => 'Others', 'level2' => 'Toys & Games', 'level3' => 'Games'],
            ['level1' => 'Others', 'level2' => 'Toys & Games', 'level3' => 'Dress-Up & Pretend Play'],
            ['level1' => 'Others', 'level2' => 'Toys & Games', 'level3' => 'Sports & Outdoor Play'],
            ['level1' => 'Others', 'level2' => 'Toys & Games', 'level3' => 'Top Toys & Games'],
            ['level1' => 'Others', 'level2' => 'Automobile', 'level3' => 'Car Care'],
            ['level1' => 'Others', 'level2' => 'Automobile', 'level3' => 'Car Electronics & Accessories'],
            ['level1' => 'Others', 'level2' => 'Automobile', 'level3' => 'Interior & Exterior Accessories'],
            ['level1' => 'Others', 'level2' => 'Automobile', 'level3' => 'Lights & Lighting'],
            ['level1' => 'Others', 'level2' => 'Automobile', 'level3' => 'Oils & Fluids'],
            ['level1' => 'Others', 'level2' => 'Automobile', 'level3' => 'Tyres & Rims'],
            ['level1' => 'Others', 'level2' => 'Sporting Goods', 'level3' => 'Cardio Equipment'],
            ['level1' => 'Others', 'level2' => 'Sporting Goods', 'level3' => 'Strength Training Equipment'],
            ['level1' => 'Others', 'level2' => 'Sporting Goods', 'level3' => 'Sports Accessories'],
            ['level1' => 'Others', 'level2' => 'Sporting Goods', 'level3' => 'Team Sports'],
            ['level1' => 'Others', 'level2' => 'Sporting Goods', 'level3' => 'Outdoor & Adventure'],

            // ==================== 12. VEHICLES ====================
            ['level1' => 'Vehicles', 'level2' => 'Cars', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Motorcycles & Scooters', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Buses & Microbuses', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Trucks & Trailers', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Vehicle Parts & Accessories', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Construction & Heavy Machinery', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Watercraft & Boats', 'level3' => null],
            ['level1' => 'Vehicles', 'level2' => 'Car Services', 'level3' => null],

            // ==================== 13. PROPERTY ====================
            ['level1' => 'Property', 'level2' => 'New Builds', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Houses & Apartments for Rent', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Houses & Apartments for Sale', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Short Let', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Land & Plots for Rent', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Land & Plots for Sale', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Commercial Property for Rent', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Commercial Property for Sale', 'level3' => null],
            ['level1' => 'Property', 'level2' => 'Event Centres, Venues & Workspaces', 'level3' => null],

            // ==================== 15. ANIMALS & PETS ====================
            ['level1' => 'Animals & Pets', 'level2' => 'Dogs & Puppies', 'level3' => null],
            ['level1' => 'Animals & Pets', 'level2' => 'Cats & Kittens', 'level3' => null],
            ['level1' => 'Animals & Pets', 'level2' => 'Fish', 'level3' => null],
            ['level1' => 'Animals & Pets', 'level2' => 'Birds', 'level3' => null],
            ['level1' => 'Animals & Pets', 'level2' => 'Other Animals', 'level3' => null],
            ['level1' => 'Animals & Pets', 'level2' => 'Pet Accessories', 'level3' => null],
            ['level1' => 'Animals & Pets', 'level2' => 'Pet Services', 'level3' => null],
        ];
    }
}
