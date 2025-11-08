<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostReport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PostReportController extends Controller
{
    /**
     * Report a post
     */
    public function report(Request $request, $postId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|in:spam,inappropriate_content,harassment,false_information,copyright_violation,other',
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = $request->user();
            if (!$user) {
                return ResponseHelper::error('Unauthenticated', 401);
            }

            // Check if post exists
            $post = Post::findOrFail($postId);

            // Check if user already reported this post
            $existingReport = PostReport::where('post_id', $postId)
                ->where('reported_by', $user->id)
                ->first();

            if ($existingReport) {
                return ResponseHelper::error('You have already reported this post', 422);
            }

            // Create report
            $report = PostReport::create([
                'post_id' => $postId,
                'reported_by' => $user->id,
                'reason' => $request->reason,
                'description' => $request->description,
                'status' => 'pending',
            ]);

            return ResponseHelper::success([
                'id' => $report->id,
                'post_id' => $report->post_id,
                'reason' => $report->reason,
                'status' => $report->status,
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
            ], 'Post reported successfully. Our team will review it shortly.');
        } catch (Exception $e) {
            Log::error('PostReportController@report: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

