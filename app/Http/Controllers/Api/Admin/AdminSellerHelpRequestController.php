<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SellerHelpRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSellerHelpRequestController extends Controller
{
    /**
     * List seller help requests with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = SellerHelpRequest::query()->orderByDesc('created_at');

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('service_type') && $request->service_type !== 'all') {
                $query->where('service_type', $request->service_type);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('full_name', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $perPage = (int) $request->get('per_page', 20);
            // Check if export is requested
            if ($request->has('export') && $request->export == 'true') {
                $requests = $query->get();
                return ResponseHelper::success($requests, 'Seller help requests exported successfully');
            }

            $requests = $query->paginate($perPage);

            return ResponseHelper::success($requests);
        } catch (Exception $e) {
            Log::error('AdminSellerHelpRequestController@index: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}


