<?php 
namespace App\Services;

use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\Store;
use App\Models\SubService;
use Illuminate\Support\Facades\Auth;

class ServiceService
{
    public function create(array $data)
    {
        $user=Auth::user();
        $store=Store::where('user_id',$user->id)->first();
        $data['store_id']=$store->id;
        
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
    public function relatedServices($categoryId)
    {
        return Service::with('media','subServices')
            ->where('service_category_id', $categoryId)
            ->get();
    }
    public function getById(int $id)
    {
        return Service::with('media','subServices','store')->findOrFail($id);
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
