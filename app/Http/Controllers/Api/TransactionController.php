<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function __construct( private TransactionService $transactionService)
    {
        
    }
    public function getForAuthUser(){
        try{
            $user=Auth::user();
            $transactions=$this->transactionService->getForUser($user->id);
            return ResponseHelper::success($transactions);
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);
            // return response()->json(['error'=>$e->getMessage()],500);
        }
    }
}
