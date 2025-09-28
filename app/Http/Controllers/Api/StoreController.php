<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function getAll(Request $req) {
        // This is a placeholder implementation. Replace with actual logic to fetch stores.
        try{
            $Stores=Store::latest()->get();
            return ResponseHelper::success($Stores);
        }catch(\Exception $e){
            return ResponseHelper::error( $e->getMessage(), 500);
        }
    }
public function getById(Request $req, $storeId)
{
    try {
        $store = Store::with([
            'user','socialLinks','businessDetails','addresses',
            'deliveryPricing','categories','products.orderItems',
            'products.images','products.variations','services'
        ])
        ->withSum(['soldItems as total_sold' => function ($q) {
            $q->select(DB::raw('COALESCE(SUM(qty),0)'));
        }], 'qty')
        ->findOrFail($storeId);

        return ResponseHelper::success($store);
    } catch (\Exception $e) {
        return ResponseHelper::error($e->getMessage(), 500);
    }
}
}
