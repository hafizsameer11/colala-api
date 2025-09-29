<?php

namespace App\Services;

use App\Models\{Post, PostMedia, PostLike, PostComment, PostShare, SavedItem};
use Illuminate\Support\Facades\Auth;

class PostService
{
    public function create(array $data, $user)
    {
        $post = Post::create([
            'user_id'    => $user->id,
            'body'       => $data['body'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
        ]);

        if (isset($data['media'])) {
            foreach ($data['media'] as $i => $file) {
                $mime = $file->getClientMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';
                $path = $file->store("posts/{$post->id}", 'public');

                PostMedia::create([
                    'post_id'  => $post->id,
                    'path'     => $path,
                    'type'     => $type,
                    'position' => $i,
                ]);
            }
        }

        return $post->load(['user:id,full_name,profile_picture','media']);
    }

    public function getAll($req)
{
    $posts = Post::with([
            'user:id,full_name,profile_picture',   // keep user basic fields
            'user.store:id,user_id,store_name',    // ✅ add store for that user
            'media'
        ])
        ->orderByDesc('id')
        ->paginate(20);

    $myPosts = Post::with([
            'user:id,full_name,profile_picture',
            'user.store:id,user_id,store_name',
            'media'
        ])
        ->where('user_id', Auth::id())
        ->orderByDesc('id')
        ->paginate(20);

    return [
        'posts'   => $posts,
        'myPosts' => $myPosts,
    ];
}


    public function findById($id)
    {
        return Post::with(['user:id,full_name,profile_picture','media','comments.user:id,full_name,profile_picture'])
            ->findOrFail($id);
    }

    public function updateByUser($id, array $data, $user)
    {
        $post = Post::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $post->update($data);
        return $post;
    }

    public function deleteByUser($id, $user)
    {
        $post = Post::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        return $post->delete();
    }

    public function toggleLike($id, $user)
    {
        $post = Post::findOrFail($id);

        $like = PostLike::where('post_id', $id)->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();
            $post->decrement('likes_count');
            return ['liked' => false, 'likes_count' => $post->likes_count];
        }

        PostLike::create(['post_id' => $id, 'user_id' => $user->id]);
        $post->increment('likes_count');
        $savedItem=SavedItem::create(['item_id' => $id, 'user_id' => $user->id, 'type' => 'post']);

        return ['liked' => true, 'likes_count' => $post->likes_count];
    }

    public function getComments($postId)
    {
        return PostComment::with(['user:id,full_name,profile_picture','replies.user:id,full_name,profile_picture'])
            ->where('post_id', $postId)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(20);
    }

    public function addComment($postId, $user, string $body, $parentId = null)
    {
        $post = Post::findOrFail($postId);

        $comment = PostComment::create([
            'post_id'   => $post->id,
            'user_id'   => $user->id,
            'body'      => $body,
            'parent_id' => $parentId,
        ]);

        $post->increment('comments_count');

        return $comment->load('user:id,full_name,profile_picture');
    }

    public function deleteComment($postId, $commentId, $user)
    {
        $comment = PostComment::where('post_id', $postId)
            ->where('id', $commentId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $comment->post->decrement('comments_count');
        return $comment->delete();
    }

    public function share($id, $user, $channel = null)
    {
        $post = Post::findOrFail($id);

        PostShare::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'channel' => $channel,
        ]);

        $post->increment('shares_count');
    }
}
