<?php 


// app/Services/PostService.php
namespace App\Services;

use App\Models\{Post,PostMedia,PostLike,PostComment,PostShare};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PostService
{
    public function create(array $data, $user)
    {
        $post = Post::create([
            'user_id' => $user->id,
            'body' => $data['body'] ?? null,
            'visibility' => $data['visibility'] ?? 'public'
        ]);

        if(isset($data['media'])){
            foreach ($data['media'] as $i => $file) {
                $mime = $file->getClientMimeType();
                $type = str_starts_with($mime,'video') ? 'video' : 'image';
                $path = $file->store("posts/{$post->id}", 'public');
                PostMedia::create([
                    'post_id'=>$post->id,'path'=>$path,'type'=>$type,'position'=>$i
                ]);
            }
        }
        return $post;
    }

    public function update(Post $post, array $data) {
        $post->update($data);
        return $post;
    }

    public function delete(Post $post) {
        return $post->delete();
    }

    public function toggleLike(Post $post, $user)
    {
        $like = PostLike::where('post_id',$post->id)->where('user_id',$user->id)->first();
        if($like){
            $like->delete();
            $post->decrement('likes_count');
            return false;
        }
        PostLike::create(['post_id'=>$post->id,'user_id'=>$user->id]);
        $post->increment('likes_count');
        return true;
    }

    public function addComment(Post $post, $user, string $body)
    {
        $c = PostComment::create([
            'post_id'=>$post->id,'user_id'=>$user->id,'body'=>$body
        ]);
        $post->increment('comments_count');
        return $c;
    }

    public function deleteComment(PostComment $comment)
    {
        $comment->post->decrement('comments_count');
        return $comment->delete();
    }

    public function share(Post $post, $user, $channel=null)
    {
        PostShare::create(['post_id'=>$post->id,'user_id'=>$user->id,'channel'=>$channel]);
        $post->increment('shares_count');
    }
    public function getAll($req)
{
    $posts= Post::with(['user:id,full_name,profile_picture','media'])
        ->orderByDesc('id')
        ->paginate(20);
        $myPosts = Post::with(['user:id,full_name,profile_picture','media'])
        ->where('user_id',Auth::user()->id)
        ->orderByDesc('id')
        ->paginate(20);
    return ['posts'=>$posts,'myPosts'=>$myPosts];
}

public function findById($id)
{
    return Post::with(['user:id,full_name,profile_picture','media','comments'])->findOrFail($id);
}

public function updateByUser($id, array $data, $user)
{
    $post = Post::where('id',$id)->where('user_id',$user->id)->firstOrFail();
    $post->update($data);
    return $post;
}

public function deleteByUser($id, $user)
{
    $post = Post::where('id',$id)->where('user_id',$user->id)->firstOrFail();
    return $post->delete();
}

// public function toggleLike($id, $user)
// {
//     $post = Post::findOrFail($id);
//     $liked = PostLike::where('post_id',$id)->where('user_id',$user->id)->first();

//     if ($liked) {
//         $liked->delete();
//         $post->decrement('likes_count');
//         return ['liked'=>false,'likes_count'=>$post->likes_count];
//     }
//     PostLike::create(['post_id'=>$id,'user_id'=>$user->id]);
//     $post->increment('likes_count');
//     return ['liked'=>true,'likes_count'=>$post->likes_count];
// }

public function getComments($postId)
{
    return PostComment::with('user:id,full_name')->where('post_id',$postId)->latest()->paginate(20);
}

// public function deleteComment($postId, $commentId, $user)
// {
//     $comment = PostComment::where('post_id',$postId)->where('id',$commentId)->where('user_id',$user->id)->firstOrFail();
//     $comment->delete();
//     Post::where('id',$postId)->decrement('comments_count');
// }

}
