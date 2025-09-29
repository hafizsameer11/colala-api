<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Http\Request;

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

        switch ($type) {
            case 'product':
                $query = Product::with(['store', 'category:id,title'])
                    ->when($q, fn($qBuilder) =>
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
                $query = Service::when($q, fn($qBuilder) =>
                        $qBuilder->where('name', 'LIKE', "%$q%")
                                 ->orWhere('full_description', 'LIKE', "%$q%")
                    );
                break;
        }

        $results = $query->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Search results',
            'data'    => $results
        ]);
    }
}
