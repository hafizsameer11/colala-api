<?php 
namespace App\Services;

use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\SubService;

class ServiceService
{
    public function create(array $data)
    {
        $service = Service::create($data);

        // Media
        if (!empty($data['media'])) {
            foreach ($data['media'] as $file) {
                $path = $file->store('services', 'public');
                ServiceMedia::create([
                    'service_id' => $service->id,
                    'type' => str_contains($file->getClientMimeType(), 'video') ? 'video' : 'image',
                    'path' => $path,
                ]);
            }
        }

        // Sub-services
        if (!empty($data['sub_services'])) {
            foreach ($data['sub_services'] as $sub) {
                SubService::create([
                    'service_id' => $service->id,
                    'name' => $sub['name'],
                    'price_from' => $sub['price_from'] ?? null,
                    'price_to' => $sub['price_to'] ?? null,
                ]);
            }
        }

        return $service->load('media','subServices');
    }

    public function update(int $id, array $data)
    {
        $service = Service::findOrFail($id);
        $service->update($data);

        return $service->load('media','subServices');
    }

    public function getAll()
    {
        return Service::with('media','subServices')->get();
    }

    public function delete(int $id)
    {
        return Service::findOrFail($id)->delete();
    }
}
