<?php 
namespace App\Services;

use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\SubService;
use Exception;
use Illuminate\Support\Facades\Auth;

class ServiceService
{
    public function create(array $data)
    {
        $user=Auth::user();
        $store=Store::where('user_id',$user->id)->first();
        // if cannot find store id it can bbe the other user not the owner so lets check in the store user table
        if(!$store){
            $storeUser = StoreUser::where('user_id', Auth::user()->id)->first();
            if($storeUser){
                $store = $storeUser->store;
            }
        }
        if(!$store){
            throw new Exception('Store not found'); 
        }
        $data['store_id']=$store->id;
        
        //check if have video 
          if (!empty($data['video'])) {
            $path = $data['video']->store('services', 'public');
            $data['video'] = $path;
        }

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
        //check if has video
      
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
    public function getById($id)
    {
        return Service::with(['media','subServices','store' => function ($q) {
                $q->withCount('followers')
                  ->withSum('soldItems', 'qty');
            },
            'store.soldItems',
            'store.socialLinks',])->findOrFail((int)$id);
    }

    public function update(int $id, array $data)
    {
        $service = Service::findOrFail($id);
        $service->update($data);

        return $service->load('media','subServices');
    }

    public function getAll()
{
    return Service::with(['media','subServices','store'])
        ->withCount([
            'stats as views'       => fn($q) => $q->where('event_type', 'view'),
            'stats as impressions' => fn($q) => $q->where('event_type', 'impression'),
            'stats as clicks'      => fn($q) => $q->where('event_type', 'click'),
            'stats as chats'       => fn($q) => $q->where('event_type', 'chat'),
            'stats as phone_views' => fn($q) => $q->where('event_type', 'phone_view'),
        ])
        ->get();
}
    public function myservices()
{
    $user = Auth::user();
    $store = Store::where('user_id', $user->id)->first();
    //if cannot find store id it can bbe the other user not the owner so lets check in the store user table
    if(!$store){
        $storeUser = StoreUser::where('user_id', Auth::user()->id)->first();
        if($storeUser){
            $store = $storeUser->store;
        }
    }
    if(!$store){
        throw new Exception('Store not found');
    }
    return Service::with(['media','subServices'])->where('store_id', $store->id)
        ->withCount([
            'stats as views'       => fn($q) => $q->where('event_type', 'view'),
            'stats as impressions' => fn($q) => $q->where('event_type', 'impression'),
            'stats as clicks'      => fn($q) => $q->where('event_type', 'click'),
            'stats as chats'       => fn($q) => $q->where('event_type', 'chat'),
            'stats as phone_views' => fn($q) => $q->where('event_type', 'phone_view'),
        ])
        ->get();
}


    public function delete(int $id)
    {
        return Service::findOrFail($id)->delete();
    }

    public function markAsSold(int $id)
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        $service = Service::where('store_id', $store->id)->findOrFail($id);
        $service->update([
            'is_sold' => true,
            'is_unavailable' => false, // Reset unavailable when marking as sold
        ]);
        
        return $service->fresh();
    }

    public function markAsUnavailable(int $id)
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        $service = Service::where('store_id', $store->id)->findOrFail($id);
        $service->update([
            'is_unavailable' => true,
            'is_sold' => false, // Reset sold when marking as unavailable
        ]);
        
        return $service->fresh();
    }

    public function markAsAvailable(int $id)
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        $service = Service::where('store_id', $store->id)->findOrFail($id);
        $service->update([
            'is_sold' => false, // Reset sold when marking as available
            'is_unavailable' => false, // Reset unavailable when marking as available
        ]);
        
        return $service->fresh();
    }
}
