<?php

namespace App\Http\Controllers\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFollowRequest;
use App\Services\Buyer\StoreFollowService;
use Exception;
use Illuminate\Http\Request;

class StoreFollowController extends Controller
{
    public function __construct(private StoreFollowService $svc) {}

    public function list(Request $req) {
        try {
            $follows = $this->svc->list($req->user()->id);
            return ResponseHelper::success($follows);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function toggle(StoreFollowRequest $req) {
        try {
            $result = $this->svc->toggle($req->user()->id, $req->store_id);
            $message = $result['following'] ? 'Store followed' : 'Store unfollowed';
            return ResponseHelper::success($result, $message);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function check(StoreFollowRequest $req) {
        try {
            $isFollowing = $this->svc->isFollowing($req->user()->id, $req->store_id);
            return ResponseHelper::success(['following'=>$isFollowing]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
