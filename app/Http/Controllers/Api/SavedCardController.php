<?php 


// app/Http/Controllers/Api/SavedCardController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavedCardRequest;
use App\Http\Resources\SavedCardResource;
use App\Models\SavedCard;
use Exception;
use Illuminate\Support\Facades\Auth;

class SavedCardController extends Controller
{
    public function index() {
        $cards = SavedCard::where('user_id', Auth::id())->get();
        return ResponseHelper::success(SavedCardResource::collection($cards));
    }

    public function store(SavedCardRequest $request) {
        try {
            $user = Auth::user();

            // Normally you'd tokenize via payment gateway
            $last4 = substr($request->card_number, -4);
            $brand = 'MasterCard'; // detect brand (can use package)

            $card = SavedCard::create([
                'user_id'      => $user->id,
                'card_holder'  => $request->card_holder,
                'last4'        => $last4,
                'brand'        => $brand,
                'expiry_month' => $request->expiry_month,
                'expiry_year'  => $request->expiry_year,
                'gateway_ref'  => 'tok_'.uniqid(), // dummy token for now
            ]);

            return ResponseHelper::success(new SavedCardResource($card),"Card saved");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed to save card: ".$e->getMessage());
        }
    }

    public function setActive($id) {
        $user = Auth::user();
        SavedCard::where('user_id',$user->id)->update(['is_active'=>false]);
        $card = SavedCard::where('user_id',$user->id)->findOrFail($id);
        $card->update(['is_active'=>true]);
        return ResponseHelper::success(new SavedCardResource($card),"Active card set");
    }

    public function toggleAutodebit($id) {
        $card = SavedCard::where('user_id',Auth::id())->findOrFail($id);
        $card->update(['is_autodebit'=>!$card->is_autodebit]);
        return ResponseHelper::success(new SavedCardResource($card),"Autodebit updated");
    }

    public function destroy($id) {
        $card = SavedCard::where('user_id',Auth::id())->findOrFail($id);
        $card->delete();
        return ResponseHelper::success(null,"Card deleted");
    }
}
