<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected $walletService;
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
        // $this->middleware('auth:api');
    }
    public function getBalance(Request $req)
    {
       try{
        $wallet=$this->walletService->getBalance($req->user()->id);
        return ResponseHelper::success($wallet,'Wallet fetched successfully');
        // return response()->json(['status'=>'success','data'=>$wallet],200);
       }catch(\Exception $e){
        return ResponseHelper::error($e->getMessage(),500);
       }
    }
    public function topUp(Request $req){
        try{
            $req->validate([
                'amount'=>'required|numeric|min:1'
            ]);
            $wallet=$this->walletService->topUp($req->user()->id,$req->amount);
            return ResponseHelper::success($wallet,'Wallet topped up successfully');
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);
        }
    }
}
