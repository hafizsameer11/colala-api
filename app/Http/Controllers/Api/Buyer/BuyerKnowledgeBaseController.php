<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Exception;
use Illuminate\Http\Request;

class BuyerKnowledgeBaseController extends Controller
{
    /**
     * Get knowledge base items for buyers
     * Includes both 'buyer' and 'general' types
     */
    public function index(Request $request)
    {
        try {
            $query = KnowledgeBase::active()
                ->whereIn('type', ['buyer', 'general'])
                ->orderBy('created_at', 'desc');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by type (buyer or general)
            if ($request->has('type') && in_array($request->type, ['buyer', 'general'])) {
                $query->where('type', $request->type);
            }

            $knowledgeBase = $query->paginate($request->get('per_page', 20));

            return ResponseHelper::success([
                'knowledge_base' => $knowledgeBase->map(function ($item) {
                    return $this->formatKnowledgeBaseItem($item);
                }),
                'pagination' => [
                    'current_page' => $knowledgeBase->currentPage(),
                    'last_page' => $knowledgeBase->lastPage(),
                    'per_page' => $knowledgeBase->perPage(),
                    'total' => $knowledgeBase->total(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get single knowledge base item
     */
    public function show($id)
    {
        try {
            $item = KnowledgeBase::active()
                ->whereIn('type', ['buyer', 'general'])
                ->findOrFail($id);

            // Increment view count
            $item->increment('view_count');

            return ResponseHelper::success($this->formatKnowledgeBaseItem($item));

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }

    /**
     * Format knowledge base item for response
     */
    private function formatKnowledgeBaseItem(KnowledgeBase $item): array
    {
        // ✅ If url is present, use it; otherwise use video file URL
        $mediaUrl = null;
        if ($item->url) {
            $mediaUrl = $item->url;
        } elseif ($item->video) {
            $mediaUrl = asset('storage/' . $item->video);
        }

        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'type' => $item->type,
            'media_url' => $mediaUrl, // Single URL field for frontend
            'view_count' => $item->view_count,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
