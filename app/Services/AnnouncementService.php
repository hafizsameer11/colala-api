<?php 

namespace App\Services;
use App\Models\Announcement;
use App\Models\Store;

class AnnouncementService {
    public function create(Store $store, array $data): Announcement {
        return Announcement::create([
            'store_id' => $store->id,
            'message'  => $data['message'],
        ]);
    }
}