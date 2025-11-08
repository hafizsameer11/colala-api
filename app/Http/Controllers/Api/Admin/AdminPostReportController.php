<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\PostReport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminPostReportController extends Controller
{
    /**
     * List all post reports with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = PostReport::with(['post.user', 'reporter', 'reviewer'])
                ->orderByDesc('created_at');

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('reason') && $request->reason !== 'all') {
                $query->where('reason', $request->reason);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhereHas('post', function ($postQuery) use ($search) {
                          $postQuery->where('body', 'like', "%{$search}%");
                      })
                      ->orWhereHas('reporter', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $perPage = (int) $request->get('per_page', 20);
            $reports = $query->paginate($perPage);

            // Format the response
            $reports->getCollection()->transform(function ($report) {
                return [
                    'id' => $report->id,
                    'post' => $report->post ? [
                        'id' => $report->post->id,
                        'body' => $report->post->body,
                        'user' => $report->post->user ? [
                            'id' => $report->post->user->id,
                            'full_name' => $report->post->user->full_name,
                            'email' => $report->post->user->email,
                        ] : null,
                        'created_at' => $report->post->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                    'reporter' => $report->reporter ? [
                        'id' => $report->reporter->id,
                        'full_name' => $report->reporter->full_name,
                        'email' => $report->reporter->email,
                    ] : null,
                    'reason' => $report->reason,
                    'description' => $report->description,
                    'status' => $report->status,
                    'reviewer' => $report->reviewer ? [
                        'id' => $report->reviewer->id,
                        'full_name' => $report->reviewer->full_name,
                    ] : null,
                    'reviewed_at' => $report->reviewed_at ? $report->reviewed_at->format('Y-m-d H:i:s') : null,
                    'admin_notes' => $report->admin_notes,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return ResponseHelper::success($reports);
        } catch (Exception $e) {
            Log::error('AdminPostReportController@index: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get single post report details
     */
    public function show($id)
    {
        try {
            $report = PostReport::with(['post.user', 'post.media', 'reporter', 'reviewer'])
                ->findOrFail($id);

            return ResponseHelper::success([
                'id' => $report->id,
                'post' => $report->post ? [
                    'id' => $report->post->id,
                    'body' => $report->post->body,
                    'visibility' => $report->post->visibility,
                    'user' => $report->post->user ? [
                        'id' => $report->post->user->id,
                        'full_name' => $report->post->user->full_name,
                        'email' => $report->post->user->email,
                        'profile_picture' => $report->post->user->profile_picture 
                            ? asset('storage/' . $report->post->user->profile_picture) 
                            : null,
                    ] : null,
                    'media' => $report->post->media_urls ?? [],
                    'created_at' => $report->post->created_at->format('Y-m-d H:i:s'),
                ] : null,
                'reporter' => $report->reporter ? [
                    'id' => $report->reporter->id,
                    'full_name' => $report->reporter->full_name,
                    'email' => $report->reporter->email,
                ] : null,
                'reason' => $report->reason,
                'description' => $report->description,
                'status' => $report->status,
                'reviewer' => $report->reviewer ? [
                    'id' => $report->reviewer->id,
                    'full_name' => $report->reviewer->full_name,
                ] : null,
                'reviewed_at' => $report->reviewed_at ? $report->reviewed_at->format('Y-m-d H:i:s') : null,
                'admin_notes' => $report->admin_notes,
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            Log::error('AdminPostReportController@show: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }

    /**
     * Update report status (review, resolve, dismiss)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'status' => 'required|in:reviewed,resolved,dismissed',
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            DB::beginTransaction();

            $report = PostReport::findOrFail($id);
            $admin = $request->user();

            $report->status = $request->status;
            $report->reviewed_by = $admin->id;
            $report->reviewed_at = now();
            
            if ($request->filled('admin_notes')) {
                $report->admin_notes = $request->admin_notes;
            }

            $report->save();

            DB::commit();

            return ResponseHelper::success([
                'id' => $report->id,
                'status' => $report->status,
                'reviewed_by' => $admin->full_name,
                'reviewed_at' => $report->reviewed_at->format('Y-m-d H:i:s'),
                'admin_notes' => $report->admin_notes,
            ], 'Report status updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('AdminPostReportController@updateStatus: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete post report
     */
    public function destroy($id)
    {
        try {
            $report = PostReport::findOrFail($id);
            $report->delete();

            return ResponseHelper::success(null, 'Post report deleted successfully');
        } catch (Exception $e) {
            Log::error('AdminPostReportController@destroy: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

