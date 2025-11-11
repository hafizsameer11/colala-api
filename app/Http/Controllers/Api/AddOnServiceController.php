<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AddOnService;
use App\Models\AddOnServiceChat;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AddOnServiceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $services = AddOnService::where('seller_id', $user->id)
                ->with(['latestChat'])
                ->latest()
                ->get();

            return ResponseHelper::success($services, 'Add-on services retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'service_type' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            
            // Verify user has a store
            $store = Store::where('user_id', $user->id)->first();
            if (!$store) {
                return ResponseHelper::error('Store not found. Please complete store setup first.', 404);
            }

            $service = AddOnService::create([
                'seller_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'service_type' => $request->service_type,
                'description' => $request->description,
                'status' => 'pending'
            ]);

            // Create initial chat message from system
            AddOnServiceChat::create([
                'add_on_service_id' => $service->id,
                'sender_id' => $user->id,
                'sender_type' => 'seller',
                'message' => "Service request submitted: {$request->service_type}. Waiting for agent response.",
            ]);

            return ResponseHelper::success($service->load('chats'), 'Add-on service request submitted successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $user = Auth::user();
            $service = AddOnService::where('seller_id', $user->id)
                ->with(['chats.sender'])
                ->findOrFail($id);

            return ResponseHelper::success($service, 'Add-on service retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,in_progress,completed,cancelled'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            $service = AddOnService::where('seller_id', $user->id)->findOrFail($id);
            
            $service->update(['status' => $request->status]);

            return ResponseHelper::success($service, 'Service status updated successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
