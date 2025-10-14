<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSocialFeedController extends Controller
{
    /**
     * Get all social feed posts with filtering and pagination
     */
    public function getAllPosts(Request $request)
    {
        try {
            $query = Post::with(['user', 'media', 'likes', 'comments', 'shares']);

            // Apply filters
            if ($request->has('store_name') && $request->store_name !== 'all') {
                $query->whereHas('user.store', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->store_name}%");
                });
            }

            if ($request->has('date_range')) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('body', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%");
                      });
                });
            }

            $posts = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_posts' => Post::count(),
                'total_likes' => PostLike::count(),
                'total_comments' => PostComment::count(),
                'total_shares' => PostShare::count(),
            ];

            return ResponseHelper::success([
                'posts' => $this->formatPostsData($posts),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed post information
     */
    public function getPostDetails($postId)
    {
        try {
            $post = Post::with([
                'user.store',
                'media',
                'likes.user',
                'comments.user',
                'shares.user'
            ])->findOrFail($postId);

            $postData = [
                'post_info' => [
                    'id' => $post->id,
                    'body' => $post->body,
                    'visibility' => $post->visibility,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ],
                'user_info' => [
                    'user_id' => $post->user->id,
                    'name' => $post->user->full_name,
                    'email' => $post->user->email,
                    'store_name' => $post->user->store->store_name ?? null,
                    'location' => $post->user->store->store_location ?? null,
                ],
                'media' => $post->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'type' => $media->type,
                        'url' => asset('storage/' . $media->path),
                        'position' => $media->position,
                    ];
                }),
                'engagement' => [
                    'likes_count' => $post->likes->count(),
                    'comments_count' => $post->comments->count(),
                    'shares_count' => $post->shares->count(),
                    'total_engagement' => $post->likes->count() + $post->comments->count() + $post->shares->count(),
                ],
                'recent_comments' => $post->comments()->with('user')->latest()->limit(10)->get()->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'user_name' => $comment->user->full_name,
                        'created_at' => $comment->created_at,
                        'formatted_date' => $comment->created_at->diffForHumans(),
                    ];
                }),
                'recent_likes' => $post->likes()->with('user')->latest()->limit(10)->get()->map(function ($like) {
                    return [
                        'id' => $like->id,
                        'user_name' => $like->user->full_name,
                        'created_at' => $like->created_at,
                        'formatted_date' => $like->created_at->diffForHumans(),
                    ];
                }),
            ];

            return ResponseHelper::success($postData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update post visibility
     */
    public function updatePostVisibility(Request $request, $postId)
    {
        try {
            $request->validate([
                'visibility' => 'required|in:public,private,friends',
            ]);

            $post = Post::findOrFail($postId);
            
            $post->update([
                'visibility' => $request->visibility,
            ]);

            return ResponseHelper::success([
                'post_id' => $post->id,
                'new_visibility' => $request->visibility,
                'updated_at' => $post->updated_at,
            ], 'Post visibility updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete post
     */
    public function deletePost($postId)
    {
        try {
            $post = Post::findOrFail($postId);
            
            // Delete related data
            $post->likes()->delete();
            $post->comments()->delete();
            $post->shares()->delete();
            $post->media()->delete();
            $post->delete();

            return ResponseHelper::success(null, 'Post deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get post comments
     */
    public function getPostComments($postId)
    {
        try {
            $post = Post::findOrFail($postId);
            
            $comments = $post->comments()
                ->with('user')
                ->latest()
                ->paginate(20);

            return ResponseHelper::success([
                'comments' => $comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'user_name' => $comment->user->full_name,
                        'user_email' => $comment->user->email,
                        'created_at' => $comment->created_at,
                        'formatted_date' => $comment->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete post comment
     */
    public function deleteComment($postId, $commentId)
    {
        try {
            $comment = PostComment::where('post_id', $postId)->findOrFail($commentId);
            $comment->delete();

            return ResponseHelper::success(null, 'Comment deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get social feed statistics
     */
    public function getSocialFeedStatistics()
    {
        try {
            $stats = [
                'total_posts' => Post::count(),
                'total_likes' => PostLike::count(),
                'total_comments' => PostComment::count(),
                'total_shares' => PostShare::count(),
                'public_posts' => Post::where('visibility', 'public')->count(),
                'private_posts' => Post::where('visibility', 'private')->count(),
                'friends_posts' => Post::where('visibility', 'friends')->count(),
            ];

            // Daily engagement trends
            $dailyStats = Post::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as posts_count,
                (SELECT COUNT(*) FROM post_likes pl WHERE DATE(pl.created_at) = DATE(posts.created_at)) as likes_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE DATE(pc.created_at) = DATE(posts.created_at)) as comments_count,
                (SELECT COUNT(*) FROM post_shares ps WHERE DATE(ps.created_at) = DATE(posts.created_at)) as shares_count
            ')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get();

            return ResponseHelper::success([
                'current_stats' => $stats,
                'daily_trends' => $dailyStats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get top performing posts
     */
    public function getTopPosts(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            
            $topPosts = Post::with(['user.store', 'media'])
                ->selectRaw('
                    posts.*,
                    (SELECT COUNT(*) FROM post_likes WHERE post_id = posts.id) as likes_count,
                    (SELECT COUNT(*) FROM post_comments WHERE post_id = posts.id) as comments_count,
                    (SELECT COUNT(*) FROM post_shares WHERE post_id = posts.id) as shares_count,
                    ((SELECT COUNT(*) FROM post_likes WHERE post_id = posts.id) + 
                     (SELECT COUNT(*) FROM post_comments WHERE post_id = posts.id) + 
                     (SELECT COUNT(*) FROM post_shares WHERE post_id = posts.id)) as total_engagement
                ')
                ->orderByDesc('total_engagement')
                ->limit($limit)
                ->get();

            return ResponseHelper::success([
                'top_posts' => $topPosts->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'content' => $post->body,
                        'user_name' => $post->user->full_name,
                        'store_name' => $post->user->store->store_name ?? null,
                        'likes_count' => $post->likes_count,
                        'comments_count' => $post->comments_count,
                        'shares_count' => $post->shares_count,
                        'total_engagement' => $post->total_engagement,
                        'created_at' => $post->created_at,
                        'formatted_date' => $post->created_at->format('d-m-Y H:i A'),
                    ];
                })
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format posts data for response
     */
    private function formatPostsData($posts)
    {
        return $posts->map(function ($post) {
            return [
                'id' => $post->id,
                'body' => $post->body,
                'user_name' => $post->user->full_name,
                'store_name' => $post->user->store->store_name ?? null,
                'location' => $post->user->store->store_location ?? null,
                'visibility' => $post->visibility,
                'likes_count' => $post->likes->count(),
                'comments_count' => $post->comments->count(),
                'shares_count' => $post->shares->count(),
                'media_count' => $post->media->count(),
                'created_at' => $post->created_at,
                'formatted_date' => $post->created_at->diffForHumans(),
                'time_ago' => $post->created_at->diffForHumans(),
            ];
        });
    }
}
