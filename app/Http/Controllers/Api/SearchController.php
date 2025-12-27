<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ProductStatHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
      public function search(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,store,service',
            'q'    => 'nullable|string|max:255',
        ]);

        $type = $request->input('type');
        $q    = $request->input('q', '');
        
        // Get authenticated user
        $user = Auth::user();
        $isSeller = $user && $user->role === 'seller';
        
        // Get store IDs for seller
        $storeIds = [];
        if ($isSeller) {
            // Get user's own store
            if ($user->store) {
                $storeIds[] = $user->store->id;
            }
            
            // Get stores user has access to via StoreUser
            $storeUserStores = StoreUser::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('store_id')
                ->toArray();
            
            $storeIds = array_unique(array_merge($storeIds, $storeUserStores));
        }

        switch ($type) {
            case 'product':
                $query = Product::with(['store', 'category:id,title','images']);
                
                // If seller, filter by their store(s)
                if ($isSeller && !empty($storeIds)) {
                    $query->whereIn('store_id', $storeIds);
                }
                
                // Apply search query
                $query->when($q, fn($qBuilder) =>
                    $qBuilder->where('name', 'LIKE', "%$q%")
                             ->orWhere('description', 'LIKE', "%$q%")
                );
                break;

            case 'store':
                $query = Store::with('categories:id,title')
                    ->when($q, fn($qBuilder) =>
                        $qBuilder->where('store_name', 'LIKE', "%$q%")
                                 ->orWhere('store_email', 'LIKE', "%$q%")
                    );
                break;

            case 'service':
                $query = Service::with('store:id,store_name','media')-> when($q, fn($qBuilder) =>
                        $qBuilder->where('name', 'LIKE', "%$q%")
                                 ->orWhere('full_description', 'LIKE', "%$q%")
                    );
                break;
        }

        $results = $query->paginate(20);

        // Record impression for search results
        if ($type === 'product') {
            foreach ($results->items() as $product) {
                ProductStatHelper::record($product->id, 'impression');
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Search results',
            'data'    => $results
        ]);
    }
}
