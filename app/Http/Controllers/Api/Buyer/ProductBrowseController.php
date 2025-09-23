<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\Buyer\ProductBrowseService;

class ProductBrowseController extends Controller {
    public function __construct(private ProductBrowseService $svc) {}
    public function byCategory($categoryId) {
       try{
         return ResponseHelper::success($this->svc->byCategory((int)$categoryId));
       }
         catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }
}
