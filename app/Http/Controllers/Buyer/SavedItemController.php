<?php

namespace App\Http\Controllers\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavedItemRequest;
use App\Services\SavedItemService;
use Exception;
use Illuminate\Http\Request;

class SavedItemController extends Controller
{
    public function __construct(private SavedItemService $svc) {}

    public function list(Request $req) {
        try {
            $items = $this->svc->list($req->user()->id);
            return ResponseHelper::success($items);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function toggle(SavedItemRequest $req) {
        try {
            $result = $this->svc->toggle($req->user()->id, $req->product_id);
            $message = $result['saved'] ? 'Product saved' : 'Product unsaved';
            return ResponseHelper::success($result, $message);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function check(SavedItemRequest $req) {
        try {
            $isSaved = $this->svc->isSaved($req->user()->id, $req->product_id);
            return ResponseHelper::success(['saved'=>$isSaved]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
