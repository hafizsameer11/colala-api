<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{PostCreateRequest, PostUpdateRequest, PostCommentRequest};
use App\Services\PostService;
use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    private $postService;
    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

    public function index(Request $req)
    {
        try {
            $posts = $this->postService->getAll($req);
            return ResponseHelper::success($posts, "Posts fetched successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function store(PostCreateRequest $req)
    {
        try {
            $post = $this->postService->create($req->validated(), $req->user());
            return ResponseHelper::success($post, "Post created successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $post = $this->postService->findById($id);
            return ResponseHelper::success($post, "Post details fetched");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(PostUpdateRequest $req, $id)
    {
        try {
            $post = $this->postService->updateByUser($id, $req->validated(), $req->user());
            return ResponseHelper::success($post, "Post updated successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function destroy($id, Request $req)
    {
        try {
            $this->postService->deleteByUser($id, $req->user());
            return ResponseHelper::success(null, "Post deleted successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function toggleLike($id, Request $req)
    {
        try {
            $result = $this->postService->toggleLike($id, $req->user());
            return ResponseHelper::success($result, "Like toggled successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function comments($id)
    {
        try {
            $comments = $this->postService->getComments($id);
            return ResponseHelper::success($comments, "Comments fetched");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function addComment(PostCommentRequest $req, $id)
    {
        try {
            $comment = $this->postService->addComment($id, $req->user(), $req->validated()['body']);
            return ResponseHelper::success($comment, "Comment added");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function deleteComment($postId, $commentId, Request $req)
    {
        try {
            $this->postService->deleteComment($postId, $commentId, $req->user());
            return ResponseHelper::success(null, "Comment deleted");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function share($id, Request $req)
    {
        try {
            $this->postService->share($id, $req->user(), $req->channel);
            return ResponseHelper::success(null, "Post shared successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
