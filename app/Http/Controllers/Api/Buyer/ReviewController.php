<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\ReviewCreateRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\StoreReview;
use App\Models\StoreUser;
use App\Services\Buyer\ReviewService;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller {
    public function __construct(private ReviewService $svc) {}

    public function create(ReviewCreateRequest $req, OrderItem $orderItem) {
       try{
         return ResponseHelper::success(
            $this->svc->create($req->user()->id, $orderItem, $req->validated())
        );
       }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }
    public function list(){
        try{
            $user=Auth::user();
            if($user->role==='store'){
                $store=Store::where('user_id',$user->id)->first();
                if(!$store){
                    return ResponseHelper::error('Store not found',404);
                }
                $storeReveiws=StoreReview::with('user')->where('store_id',$store->id)->latest()->get();
                $productReveiews=ProductReview::with('user','orderItem.product')->where('store_id',$store->id)->latest()->get();
                return ResponseHelper::success([
                    'store_reviews'=>$storeReveiws,
                    'product_reviews'=>$productReveiews
                ]);
            }else{
                $storeReveiws=StoreReview::with('user')->where('user_id',$user->id)->latest()->get();
                $productReveiews=ProductReview::with('user','orderItem.product')->where('user_id',$user->id)->latest()->get();
                return ResponseHelper::success([
                   'store_reviews'=>$storeReveiws,
                   'product_reviews'=>$productReveiews
                ]);
            }

          

        }catch(\Exception $e){
           return ResponseHelper::error( $e->getMessage(), 500);
          }
    }
}
