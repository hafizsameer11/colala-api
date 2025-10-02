<?php 


namespace App\Services;
use App\Models\Banner;
use App\Models\Store;

class BannerService {
    public function create(Store $store, array $data): Banner {
        $path = $data['image']->store('banners','public');
        return Banner::create([
            'store_id'   => $store->id,
            'image_path' => $path,
            'link'       => $data['link'] ?? null,
        ]);
    }
}