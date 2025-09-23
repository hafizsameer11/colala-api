<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CheckoutPreviewRequest;
use App\Services\Buyer\CartService;
use App\Services\Buyer\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cartSvc, private CheckoutService $chk) {}

    public function preview(CheckoutPreviewRequest $req)
    {
        try{
            $cart = $this->cartSvc->getOrCreateCart($req->user()->id);
        $data = $this->chk->preview(
            $cart,
            (int)$req->delivery_address_id,
            $req->delivery_pricing_ids,
            $req->payment_method
        );
        return ResponseHelper::success($data);
        }catch(\Exception $e){
            return ResponseHelper::error( $e->getMessage(), 500);
           }
    }

    public function place(CheckoutPreviewRequest $req)
    {
        try {
            $cart = $this->cartSvc->getOrCreateCart($req->user()->id);
            $preview = $this->chk->preview(
                $cart,
                (int)$req->delivery_address_id,
                $req->delivery_pricing_ids,
                $req->payment_method
            );
            $order = $this->chk->place($cart, $preview);
            return ResponseHelper::success($order);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    
    // public function 
}
