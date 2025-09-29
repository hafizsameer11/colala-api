<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyCouponRequest;
use App\Http\Requests\Buyer\AddCartItemRequest;
use App\Http\Requests\Buyer\UpdateCartQtyRequest;
use App\Models\CartItem;
use App\Services\Buyer\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller {
    /**
 * @OA\Tag(
 *   name="Users",
 *   description="Operations about users"
 * )
 */
    public function __construct(private CartService $svc) {}

    public function show(Request $req) {
        $cart = $this->svc->getOrCreateCart($req->user()->id);
        return ResponseHelper::success($this->svc->show($cart));
    }

    public function add(AddCartItemRequest $request) {
        $cart = $this->svc->add($request->user()->id, $request->validated());
        return ResponseHelper::success($this->svc->show($cart));
    }

    public function updateQty(UpdateCartQtyRequest $request, $id) {
        $item = CartItem::findOrFail($id);
        abort_unless($item->cart->user_id === $request->user()->id, 403);
        $this->svc->updateQty($item, (int)$request->qty);
        $cart = $this->svc->getOrCreateCart($request->user()->id);
        return ResponseHelper::success($this->svc->show($cart));
    }

    public function remove(Request $request, $id) {
        $item = CartItem::findOrFail($id);
        abort_unless($item->cart->user_id === $request->user()->id, 403);
        $this->svc->remove($item);
        $cart = $this->svc->getOrCreateCart($request->user()->id);
        return ResponseHelper::success($this->svc->show($cart));
    }

    public function clear(Request $request) {
        $cart = $this->svc->getOrCreateCart($request->user()->id);
        $this->svc->clear($cart);
        return ResponseHelper::success(['cleared'=>true]);
    }
    public function applyCoupon(ApplyCouponRequest $request) {
       try{
        $data = $request->validated();
        return ResponseHelper::success($this->svc->applyCoupon($request->user()->id,$data));
       }catch(\Exception $e){
        return ResponseHelper::error($e->getMessage(),500);
       }
    }
}
