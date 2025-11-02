<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminKnowledgeBaseController extends Controller
{
    /**
     * Get all knowledge base items with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = KnowledgeBase::with('creator')
                ->orderBy('created_at', 'desc');

            // Filter by type
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $knowledgeBase = $query->paginate($request->get('per_page', 20));

            // Get statistics
            $stats = [
                'total_items' => KnowledgeBase::count(),
                'general_items' => KnowledgeBase::where('type', 'general')->count(),
                'buyer_items' => KnowledgeBase::where('type', 'buyer')->count(),
                'seller_items' => KnowledgeBase::where('type', 'seller')->count(),
                'active_items' => KnowledgeBase::where('is_active', true)->count(),
                'total_views' => KnowledgeBase::sum('view_count'),
            ];

            return ResponseHelper::success([
                'knowledge_base' => $knowledgeBase->map(function ($item) {
                    return $this->formatKnowledgeBaseItem($item);
                }),
                'statistics' => $stats,
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
            $item = KnowledgeBase::with('creator')->findOrFail($id);
            return ResponseHelper::success($this->formatKnowledgeBaseItem($item));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }

    /**
     * Create new knowledge base item
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:5000',
                'type' => 'required|in:general,buyer,seller',
                'url' => 'nullable|url|max:500',
                'video' => 'nullable|file|mimes:mp4,avi,mov,wmv,flv,webm|max:51200', // Max 50MB
                'is_active' => 'nullable|boolean',
            ]);

            // Validate that either url or video is provided
            if (!$request->has('url') && !$request->hasFile('video')) {
                $validator->errors()->add('media', 'Either URL or video file must be provided.');
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $data = $request->only(['title', 'description', 'type', 'is_active']);
            $data['created_by'] = Auth::id();

            // Handle URL
            if ($request->filled('url')) {
                $data['url'] = $request->url;
                $data['video'] = null; // Clear video if URL is provided
            }

            // Handle video upload
            if ($request->hasFile('video')) {
                // Delete old video if exists (in case of update)
                $videoPath = $request->file('video')->store('knowledge-base/videos', 'public');
                $data['video'] = $videoPath;
                $data['url'] = null; // Clear URL if video is provided
            }

            $item = KnowledgeBase::create($data);

            return ResponseHelper::success(
                $this->formatKnowledgeBaseItem($item->load('creator')),
                'Knowledge base item created successfully',
                201
            );

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update knowledge base item
     */
    public function update(Request $request, $id)
    {
        try {
            $item = KnowledgeBase::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:5000',
                'type' => 'sometimes|required|in:general,buyer,seller',
                'url' => 'nullable|url|max:500',
                'video' => 'nullable|file|mimes:mp4,avi,mov,wmv,flv,webm|max:51200',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $data = $request->only(['title', 'description', 'type', 'is_active']);

            // Handle URL update
            if ($request->has('url')) {
                if ($request->filled('url')) {
                    $data['url'] = $request->url;
                    // Delete old video if URL is provided
                    if ($item->video) {
                        Storage::disk('public')->delete($item->video);
                        $data['video'] = null;
                    }
                } else {
                    $data['url'] = null;
                }
            }

            // Handle video upload
            if ($request->hasFile('video')) {
                // Delete old video if exists
                if ($item->video) {
                    Storage::disk('public')->delete($item->video);
                }
                $videoPath = $request->file('video')->store('knowledge-base/videos', 'public');
                $data['video'] = $videoPath;
                // Clear URL if video is uploaded
                $data['url'] = null;
            }

            $item->update($data);

            return ResponseHelper::success(
                $this->formatKnowledgeBaseItem($item->fresh('creator')),
                'Knowledge base item updated successfully'
            );

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete knowledge base item
     */
    public function destroy($id)
    {
        try {
            $item = KnowledgeBase::findOrFail($id);

            // Delete video file if exists
            if ($item->video) {
                Storage::disk('public')->delete($item->video);
            }

            $item->delete();

            return ResponseHelper::success(null, 'Knowledge base item deleted successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleStatus($id)
    {
        try {
            $item = KnowledgeBase::findOrFail($id);
            $item->is_active = !$item->is_active;
            $item->save();

            return ResponseHelper::success(
                $this->formatKnowledgeBaseItem($item->load('creator')),
                'Status updated successfully'
            );

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format knowledge base item for response
     */
    private function formatKnowledgeBaseItem(KnowledgeBase $item): array
    {
        // Determine media URL: if url exists, use it; otherwise use video file URL
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
            'url' => $item->url,
            'video' => $item->video ? asset('storage/' . $item->video) : null,
            'media_url' => $mediaUrl, // Combined URL for frontend convenience
            'is_active' => $item->is_active,
            'view_count' => $item->view_count,
            'created_by' => $item->creator ? [
                'id' => $item->creator->id,
                'name' => $item->creator->full_name,
                'email' => $item->creator->email,
            ] : null,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
