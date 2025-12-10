<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCreateUpdateRequest;
use App\Services\ProductService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    private $productService;
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function getAll()
    {
        try {
            return ResponseHelper::success($this->productService->getAll());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getAllforBuyer(){
        try {
            return ResponseHelper::success($this->productService->getAllforBuyer());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Get products with referral fees set
     * Returns products that have referral_fee not null
     */
    public function getReferralProducts(Request $request){
        try {
            $products = $this->productService->getReferralProducts();
            $user = $request->user();
            
            $responseData = [
                'user_code' => $user ? $user->user_code : null,
                'products' => $products
            ];
            
            return ResponseHelper::success($responseData);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Get products from VIP users
     * Returns complete product details for products from users with plan='vip'
     */
    public function getVipProducts(Request $request){
        try {
            $products = $this->productService->getVipProducts();
            
            return ResponseHelper::success($products);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function create(ProductCreateUpdateRequest $request)
    {
        try {
            $data=$request->validated();
            $product=$this->productService->create($data);
            return ResponseHelper::success($product);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(ProductCreateUpdateRequest $request, $id)
    {
        try {
            return ResponseHelper::success($this->productService->update($id, $request->validated()));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return ResponseHelper::success($this->productService->delete($id));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function markAsSold($id)
    {
        try {
            $product = $this->productService->markAsSold($id);
            return ResponseHelper::success($product, 'Product marked as sold successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function markAsUnavailable($id)
    {
        try {
            $product = $this->productService->markAsUnavailable($id);
            return ResponseHelper::success($product, 'Product marked as unavailable successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function markAsAvailable($id)
    {
        try {
            $product = $this->productService->markAsAvailable($id);
            return ResponseHelper::success($product, 'Product marked as available successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function myproducts()
    {
        try {
            return ResponseHelper::success($this->productService->myproducts());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function updateQuantity(Request $request, $id)
    {
        try {
            $request->validate([
                'quantity' => 'required|integer|min:0'
            ]);

            $product = $this->productService->updateQuantity($id, $request->quantity);
            return ResponseHelper::success($product, 'Product quantity updated successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Upload or update product video separately
     */
    public function uploadVideo(Request $request, $id)
    {
        try {
            $request->validate([
                'video' => 'required|file|mimes:mp4,mov,avi,webm|max:10240', // Max 10MB
            ]);

            if (!$request->hasFile('video')) {
                return ResponseHelper::error('Video file is required', 422);
            }

            $product = $this->productService->uploadVideo($id, $request->file('video'));
            return ResponseHelper::success($product, 'Product video uploaded successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Delete product video
     */
    public function deleteVideo($id)
    {
        try {
            $product = $this->productService->deleteVideo($id);
            return ResponseHelper::success($product, 'Product video deleted successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
