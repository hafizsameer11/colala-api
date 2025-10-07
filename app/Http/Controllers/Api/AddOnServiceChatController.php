<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AddOnService;
use App\Models\AddOnServiceChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AddOnServiceChatController extends Controller
{
    public function getMessages(Request $request, $serviceId)
    {
        try {
            $user = Auth::user();
            
            // Verify the service belongs to the authenticated user
            $service = AddOnService::where('seller_id', $user->id)->findOrFail($serviceId);
            
            $messages = AddOnServiceChat::where('add_on_service_id', $serviceId)
                ->with(['sender:id,full_name,profile_picture'])
                ->orderBy('created_at', 'asc')
                ->get();

            return ResponseHelper::success([
                'service' => $service,
                'messages' => $messages
            ], 'Chat messages retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function sendMessage(Request $request, $serviceId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'sender_type' => 'required|in:seller,agent'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            
            // Verify the service belongs to the authenticated user
            $service = AddOnService::where('seller_id', $user->id)->findOrFail($serviceId);
            
            $chatMessage = AddOnServiceChat::create([
                'add_on_service_id' => $serviceId,
                'sender_id' => $user->id,
                'sender_type' => $request->sender_type,
                'message' => $request->message,
            ]);

            // Load sender information
            $chatMessage->load('sender:id,full_name,profile_picture');

            return ResponseHelper::success($chatMessage, 'Message sent successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function markAsRead(Request $request, $serviceId)
    {
        try {
            $user = Auth::user();
            
            // Verify the service belongs to the authenticated user
            $service = AddOnService::where('seller_id', $user->id)->findOrFail($serviceId);
            
            // Mark all messages as read for this service
            AddOnServiceChat::where('add_on_service_id', $serviceId)
                ->where('sender_type', 'agent') // Only mark agent messages as read
                ->update(['read_at' => now()]);

            return ResponseHelper::success(null, 'Messages marked as read');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
