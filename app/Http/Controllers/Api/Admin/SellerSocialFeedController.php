<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\PostMedia;
use App\Models\SavedItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class SellerSocialFeedController extends Controller
{
    /**
     * Get all posts for a specific seller
     */
    public function getSellerPosts(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Post::with([
                'user:id,full_name,profile_picture,email,phone',
                'media',
                'likes',
                'comments.user:id,full_name,profile_picture',
                'shares'
            ])->where('user_id', $userId);

            // Filter by visibility
            if ($request->has('visibility') && $request->visibility !== 'all') {
                $query->where('visibility', $request->visibility);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('body', 'like', "%{$search}%");
            }

            $posts = $query->latest()->paginate(20);

            // Get summary statistics
            $totalPosts = Post::where('user_id', $userId)->count();
            $publicPosts = Post::where('user_id', $userId)->where('visibility', 'public')->count();
            $followersPosts = Post::where('user_id', $userId)->where('visibility', 'followers')->count();
            $totalLikes = PostLike::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();
            $totalComments = PostComment::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();
            $totalShares = PostShare::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();

            $posts->getCollection()->transform(function ($post) {
                return [
                    'id' => $post->id,
                    'body' => $post->body,
                    'visibility' => $post->visibility,
                    'likes_count' => $post->likes_count,
                    'comments_count' => $post->comments_count,
                    'shares_count' => $post->shares_count,
                    'media_urls' => $post->media_urls,
                    'user' => [
                        'id' => $post->user->id,
                        'name' => $post->user->full_name,
                        'email' => $post->user->email,
                        'phone' => $post->user->phone,
                        'profile_picture' => $post->user->profile_picture ? asset('storage/' . $post->user->profile_picture) : null
                    ],
                    'likes' => $post->likes->map(function ($like) {
                        return [
                            'id' => $like->id,
                            'user_id' => $like->user_id,
                            'created_at' => $like->created_at->format('d-m-Y H:i:s')
                        ];
                    }),
                    'comments' => $post->comments->take(5)->map(function ($comment) {
                        return [
                            'id' => $comment->id,
                            'body' => $comment->body,
                            'user' => [
                                'id' => $comment->user->id,
                                'name' => $comment->user->full_name,
                                'profile_picture' => $comment->user->profile_picture ? asset('storage/' . $comment->user->profile_picture) : null
                            ],
                            'created_at' => $comment->created_at->format('d-m-Y H:i:s')
                        ];
                    }),
                    'shares' => $post->shares->map(function ($share) {
                        return [
                            'id' => $share->id,
                            'user_id' => $share->user_id,
                            'channel' => $share->channel,
                            'created_at' => $share->created_at->format('d-m-Y H:i:s')
                        ];
                    }),
                    'created_at' => $post->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $post->updated_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success([
                'posts' => $posts,
                'summary_stats' => [
                    'total_posts' => $totalPosts,
                    'public_posts' => $publicPosts,
                    'followers_posts' => $followersPosts,
                    'total_likes' => $totalLikes,
                    'total_comments' => $totalComments,
                    'total_shares' => $totalShares
                ]
            ], 'Seller posts retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed post information
     */
    public function getPostDetails($userId, $postId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $post = Post::with([
                'user:id,full_name,profile_picture,email,phone',
                'media',
                'likes.user:id,full_name,profile_picture',
                'comments.user:id,full_name,profile_picture',
                'shares.user:id,full_name,profile_picture'
            ])->where('user_id', $userId)
              ->findOrFail($postId);

            $postDetails = [
                'post_info' => [
                    'id' => $post->id,
                    'body' => $post->body,
                    'visibility' => $post->visibility,
                    'likes_count' => $post->likes_count,
                    'comments_count' => $post->comments_count,
                    'shares_count' => $post->shares_count,
                    'media_urls' => $post->media_urls,
                    'created_at' => $post->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $post->updated_at->format('d-m-Y H:i:s')
                ],
                'author_info' => [
                    'id' => $post->user->id,
                    'name' => $post->user->full_name,
                    'email' => $post->user->email,
                    'phone' => $post->user->phone,
                    'profile_picture' => $post->user->profile_picture ? asset('storage/' . $post->user->profile_picture) : null,
                    'store_name' => $store->store_name
                ],
                'media' => $post->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'type' => $media->type,
                        'url' => asset('storage/' . $media->path),
                        'position' => $media->position
                    ];
                }),
                'likes' => $post->likes->map(function ($like) {
                    return [
                        'id' => $like->id,
                        'user_id' => $like->user_id,
                        'user_name' => $like->user->full_name,
                        'user_avatar' => $like->user->profile_picture ? asset('storage/' . $like->user->profile_picture) : null,
                        'created_at' => $like->created_at->format('d-m-Y H:i:s')
                    ];
                }),
                'comments' => $post->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->full_name,
                            'profile_picture' => $comment->user->profile_picture ? asset('storage/' . $comment->user->profile_picture) : null
                        ],
                        'created_at' => $comment->created_at->format('d-m-Y H:i:s'),
                        'updated_at' => $comment->updated_at->format('d-m-Y H:i:s')
                    ];
                }),
                'shares' => $post->shares->map(function ($share) {
                    return [
                        'id' => $share->id,
                        'user_id' => $share->user_id,
                        'user_name' => $share->user->full_name,
                        'user_avatar' => $share->user->profile_picture ? asset('storage/' . $share->user->profile_picture) : null,
                        'channel' => $share->channel,
                        'created_at' => $share->created_at->format('d-m-Y H:i:s')
                    ];
                })
            ];

            return ResponseHelper::success($postDetails, 'Post details retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get all comments for a specific post
     */
    public function getPostComments(Request $request, $userId, $postId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $post = Post::where('user_id', $userId)->findOrFail($postId);

            $query = PostComment::with(['user:id,full_name,profile_picture', 'replies.user:id,full_name,profile_picture'])
                ->where('post_id', $postId)
                ->whereNull('parent_id'); // Only top-level comments

            $comments = $query->latest()->paginate(20);

            $comments->getCollection()->transform(function ($comment) {
                return [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->full_name,
                        'profile_picture' => $comment->user->profile_picture ? asset('storage/' . $comment->user->profile_picture) : null
                    ],
                    'replies' => $comment->replies->map(function ($reply) {
                        return [
                            'id' => $reply->id,
                            'body' => $reply->body,
                            'user' => [
                                'id' => $reply->user->id,
                                'name' => $reply->user->full_name,
                                'profile_picture' => $reply->user->profile_picture ? asset('storage/' . $reply->user->profile_picture) : null
                            ],
                            'created_at' => $reply->created_at->format('d-m-Y H:i:s')
                        ];
                    }),
                    'created_at' => $comment->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $comment->updated_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($comments, 'Post comments retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a post
     */
    public function deletePost($userId, $postId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $post = Post::where('user_id', $userId)->findOrFail($postId);

            // Delete associated media files
            foreach ($post->media as $media) {
                if (Storage::disk('public')->exists($media->path)) {
                    Storage::disk('public')->delete($media->path);
                }
                $media->delete();
            }

            // Delete associated likes, comments, and shares
            $post->likes()->delete();
            $post->comments()->delete();
            $post->shares()->delete();

            $post->delete();

            return ResponseHelper::success(null, 'Post deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a comment
     */
    public function deleteComment($userId, $postId, $commentId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $post = Post::where('user_id', $userId)->findOrFail($postId);
            $comment = PostComment::where('post_id', $postId)->findOrFail($commentId);

            // Delete replies first
            $comment->replies()->delete();
            $comment->delete();

            // Update post comments count
            $post->decrement('comments_count');

            return ResponseHelper::success(null, 'Comment deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get social feed statistics for seller
     */
    public function getSocialFeedStatistics($userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $totalPosts = Post::where('user_id', $userId)->count();
            $publicPosts = Post::where('user_id', $userId)->where('visibility', 'public')->count();
            $followersPosts = Post::where('user_id', $userId)->where('visibility', 'followers')->count();

            $totalLikes = PostLike::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();

            $totalComments = PostComment::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();

            $totalShares = PostShare::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();

            $totalSaved = SavedItem::whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count();

            // Get posts with most engagement
            $topPosts = Post::where('user_id', $userId)
                ->withCount(['likes', 'comments', 'shares'])
                ->orderByRaw('(likes_count + comments_count + shares_count) DESC')
                ->limit(5)
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'body' => $post->body,
                        'likes_count' => $post->likes_count,
                        'comments_count' => $post->comments_count,
                        'shares_count' => $post->shares_count,
                        'total_engagement' => $post->likes_count + $post->comments_count + $post->shares_count,
                        'created_at' => $post->created_at->format('d-m-Y H:i:s')
                    ];
                });

            return ResponseHelper::success([
                'post_counts' => [
                    'total' => $totalPosts,
                    'public' => $publicPosts,
                    'followers' => $followersPosts
                ],
                'engagement_counts' => [
                    'total_likes' => $totalLikes,
                    'total_comments' => $totalComments,
                    'total_shares' => $totalShares,
                    'total_saved' => $totalSaved
                ],
                'engagement_rate' => $totalPosts > 0 ? round((($totalLikes + $totalComments + $totalShares) / $totalPosts), 2) : 0,
                'top_posts' => $topPosts
            ], 'Social feed statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update post visibility
     */
    public function updatePostVisibility(Request $request, $userId, $postId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'visibility' => 'required|string|in:public,followers'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $post = Post::where('user_id', $userId)->findOrFail($postId);
            $post->update(['visibility' => $request->visibility]);

            return ResponseHelper::success([
                'post_id' => $post->id,
                'visibility' => $post->visibility,
                'updated_at' => $post->updated_at->format('d-m-Y H:i:s')
            ], 'Post visibility updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get post engagement analytics
     */
    public function getPostEngagementAnalytics(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            $posts = Post::where('user_id', $userId)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->withCount(['likes', 'comments', 'shares'])
                ->get();

            $analytics = [
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'total_posts' => $posts->count(),
                'total_likes' => $posts->sum('likes_count'),
                'total_comments' => $posts->sum('comments_count'),
                'total_shares' => $posts->sum('shares_count'),
                'average_engagement' => $posts->count() > 0 ? round($posts->avg(function ($post) {
                    return $post->likes_count + $post->comments_count + $post->shares_count;
                }), 2) : 0,
                'posts_by_visibility' => [
                    'public' => $posts->where('visibility', 'public')->count(),
                    'followers' => $posts->where('visibility', 'followers')->count()
                ],
                'daily_engagement' => $this->getDailyEngagementData($posts)
            ];

            return ResponseHelper::success($analytics, 'Post engagement analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get daily engagement data
     */
    private function getDailyEngagementData($posts)
    {
        $dailyData = [];
        
        foreach ($posts as $post) {
            $date = $post->created_at->format('Y-m-d');
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'date' => $date,
                    'posts' => 0,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0
                ];
            }
            
            $dailyData[$date]['posts']++;
            $dailyData[$date]['likes'] += $post->likes_count;
            $dailyData[$date]['comments'] += $post->comments_count;
            $dailyData[$date]['shares'] += $post->shares_count;
        }

        return array_values($dailyData);
    }
}
