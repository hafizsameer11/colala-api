<?php 

namespace App\Http\Controllers\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
// use App\Http\Requests\Buyer\CreateDisputeRequest;
use App\Http\Requests\CreateDisputeRequest;
use App\Models\{Dispute, Chat, Store};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class DisputeController extends Controller
{
    // Create a new dispute for a chat / store_order
    public function store(CreateDisputeRequest $request)
    {
        try {
            $data = $request->validated();
            $user = $request->user();

            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $img) {
                    $imagePaths[] = $img->store('disputes', 'public');
                }
            }

            $dispute = Dispute::create([
                'chat_id'        => $data['chat_id'],
                'store_order_id' => $data['store_order_id'],
                'user_id'        => $user->id,
                'category'       => $data['category'],
                'details'        => $data['details'] ?? null,
                'images'         => $imagePaths,
                'status'         => 'open',
            ]);

            // Optional: post a system message inside chat to alert all parties
            $chat = Chat::find($data['chat_id']);
            $store=Store::find($chat->store_id);
            $chat->messages()->create([
                'user_id' => $user->id,
                'message' => "📌 Dispute created: {$data['category']}",
                'type'    => 'system',
                'sender_id' => $store->user_id, // Added sender_id field
            ]);

            return ResponseHelper::success($dispute, 'Dispute created successfully.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // List all disputes for logged-in user
    public function myDisputes(Request $request)
    {
        try {
            $disputes = Dispute::with('chat.storeOrder','chat.store','chat.user')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get();

            return ResponseHelper::success($disputes);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // View a single dispute with full chat
    public function show($id)
    {
        try {
            $dispute = Dispute::with(['chat.messages.user','storeOrder'])
                ->findOrFail($id);

            return ResponseHelper::success($dispute);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
