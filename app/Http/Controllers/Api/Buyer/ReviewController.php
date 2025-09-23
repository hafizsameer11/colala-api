<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\ReviewCreateRequest;
use App\Models\OrderItem;
use App\Services\Buyer\ReviewService;

class ReviewController extends Controller {
    public function __construct(private ReviewService $svc) {}

    public function create(ReviewCreateRequest $req, OrderItem $orderItem) {
        return ResponseHelper::success(
            $this->svc->create($req->user()->id, $orderItem, $req->validated())
        );
    }
}
