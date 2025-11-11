<?php 


// app/Http/Controllers/Api/AnnouncementController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\AnnouncementService;
use Exception;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    private AnnouncementService $service;

    public function __construct(AnnouncementService $service) {
        $this->service = $service;
    }

    protected function userStore(): Store {
        $store = Store::where('user_id', Auth::id())->first();
        if(!$store){
            $storeUser = StoreUser::where('user_id', Auth::user()->id)->first();
            if($storeUser){
                $store = $storeUser->store;
            }
        }
        if(!$store){
            throw new Exception('Store not found');
        }
        return $store;
    }

    public function index() {
        $store = $this->userStore();
        $data = Announcement::where('store_id',$store->id)->latest()->get();
        return ResponseHelper::success(AnnouncementResource::collection($data));
    }

    public function store(AnnouncementRequest $request) {
        try {
            $announcement = $this->service->create($this->userStore(), $request->validated());
            return ResponseHelper::success(new AnnouncementResource($announcement),"Created");
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function destroy(Announcement $announcement) {
        $this->authorizeAccess($announcement);
        $announcement->delete();
        return ResponseHelper::success(null,"Deleted");
    }

    protected function authorizeAccess(Announcement $announcement) {
        $store = $this->userStore();
        abort_if($announcement->store_id !== $store->id, 403, "Unauthorized");
    }
    // app/Http/Controllers/Api/AnnouncementController.php
public function update(AnnouncementRequest $request, Announcement $announcement) {
    try {
        $this->authorizeAccess($announcement);
        $announcement->update([
            'message' => $request->message,
        ]);
        return ResponseHelper::success(new AnnouncementResource($announcement), "Announcement updated");
    } catch (\Exception $e) {
        return ResponseHelper::error("Failed to update: ".$e->getMessage());
    }
}

}
