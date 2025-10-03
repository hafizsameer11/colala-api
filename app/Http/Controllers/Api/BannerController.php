<?php

// app/Http/Controllers/Api/BannerController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Models\Store;
use App\Services\BannerService;
use Exception;
use Illuminate\Support\Facades\Auth;

class BannerController extends Controller
{
    private BannerService $service;

    public function __construct(BannerService $service) {
        $this->service = $service;
    }

    protected function userStore(): Store {
        return Store::where('user_id', Auth::id())->firstOrFail();
    }

    public function index() {
        $store = $this->userStore();
        $data = Banner::where('store_id',$store->id)->latest()->get();
        return ResponseHelper::success(BannerResource::collection($data));
    }

    public function store(BannerRequest $request) {
        try {
            $banner = $this->service->create($this->userStore(), $request->validated());
            return ResponseHelper::success(new BannerResource($banner),"Created");
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function destroy(Banner $banner) {
        $this->authorizeAccess($banner);
        $banner->delete();
        return ResponseHelper::success(null,"Deleted");
    }

    protected function authorizeAccess(Banner $banner) {
        $store = $this->userStore();
        abort_if($banner->store_id !== $store->id, 403, "Unauthorized");
    }
    // app/Http/Controllers/Api/BannerController.php
public function update(BannerRequest $request, Banner $banner) {
    try {
        $this->authorizeAccess($banner);

        $data = [];
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('banners','public');
            $data['image_path'] = $path;
        }
        if ($request->filled('link')) {
            $data['link'] = $request->link;
        }

        $banner->update($data);

        return ResponseHelper::success(new BannerResource($banner), "Banner updated");
    } catch (Exception $e) {
        return ResponseHelper::error("Failed to update: ".$e->getMessage());
    }
}

}
