<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;

class ServiceCategorySeeder extends Seeder
{
    public function run()
    {
        // Clear existing service categories
        ServiceCategory::truncate();

        $services = $this->getServiceCategories();
        $this->processServiceCategories($services);
    }

    private function processServiceCategories(array $services)
    {
        foreach ($services as $service) {
            ServiceCategory::firstOrCreate(
                ['title' => $service],
                ['image' => 'category/u6YQNnCqPy2E2lFJgXjoA8tnSTSUzEKDVE8PkGmL.jpg', 'is_active' => true]
            );
        }
    }

    private function getServiceCategories()
    {
        return [
            'Building & Trades Services',
            'Car Services',
            'Computer & IT Services',
            'Repair Services',
            'Cleaning Services',
            'Printing Services',
            'Manufacturing Services',
            'Logistics Services',
            'Legal Services',
            'Tax & Financial Services',
            'Recruitment Services',
            'Rental Services',
            'Chauffeur & Airport Transfers',
            'Travel Agents & Tours',
            'Classes & Courses',
            'Child Care & Education',
            'Health & Beauty Services',
            'Fitness & Personal Training',
            'Party, Catering & Event Services',
            'DJ & Entertainment Services',
            'Wedding Services',
            'Photography & Video Services',
            'Landscaping & Gardening',
            'Pet Services',
            'Other Services',
        ];
    }
}

