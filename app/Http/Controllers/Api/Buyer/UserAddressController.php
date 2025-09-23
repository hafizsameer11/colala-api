<?php

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\UserAddressRequest;
use App\Models\UserAddress;
use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    // list all addresses for buyer
    public function index(Request $request) {
      try{
          $addresses = UserAddress::where('user_id', $request->user()->id)->get();
        return ResponseHelper::success($addresses);
      }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }

    // create new address
    public function store(UserAddressRequest $request) {
      try{
          $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // if default, unset others
        if (!empty($data['is_default']) && $data['is_default']) {
            UserAddress::where('user_id', $request->user()->id)->update(['is_default'=>false]);
        }

        $address = UserAddress::create($data);
        return ResponseHelper::success($address, 'Address created');
      }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }

    // show single address
    public function show(Request $request, $id) {
      try{
          $address = UserAddress::where('user_id', $request->user()->id)->findOrFail($id);
        return ResponseHelper::success($address);
      }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
      }
    }

    // update address
    public function update(UserAddressRequest $request, $id) {
      try{
          $address = UserAddress::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validated();

        if (!empty($data['is_default']) && $data['is_default']) {
            UserAddress::where('user_id', $request->user()->id)->update(['is_default'=>false]);
        }

        $address->update($data);
        return ResponseHelper::success($address, 'Address updated');
      }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }

    // delete address
    public function destroy(Request $request, $id) {
       try{
         $address = UserAddress::where('user_id', $request->user()->id)->findOrFail($id);
        $address->delete();
        return ResponseHelper::success([], 'Address deleted');
       }catch(\Exception $e){
          return ResponseHelper::error( $e->getMessage(), 500);
         }
    }
}