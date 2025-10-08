<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\BulkProductUploadService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkProductUploadController extends Controller
{
    private $bulkUploadService;

    public function __construct(BulkProductUploadService $bulkUploadService)
    {
        $this->bulkUploadService = $bulkUploadService;
    }

    /**
     * Get CSV template and instructions
     */
    public function getTemplate()
    {
        try {
            $template = $this->bulkUploadService->getCsvTemplate();
            return ResponseHelper::success($template, 'CSV template retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Process bulk product upload (Queue-based)
     */
    public function upload(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'csv_data' => 'required|array',
                'csv_data.*' => 'required|array'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $results = $this->bulkUploadService->processBulkUpload($request->csv_data);

            return ResponseHelper::success($results, 'Bulk upload job has been queued');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Upload CSV file and process (Queue-based)
     */
    public function uploadFile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'csv_file' => 'required|file|mimes:csv,txt|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $file = $request->file('csv_file');
            $csvData = $this->parseCsvFile($file);
            
            $results = $this->bulkUploadService->processBulkUpload($csvData);

            return ResponseHelper::success($results, 'CSV file has been queued for processing');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Parse CSV file to array
     */
    private function parseCsvFile($file): array
    {
        $csvData = [];
        $handle = fopen($file->getPathname(), 'r');
        
        if ($handle === false) {
            throw new Exception('Could not read CSV file');
        }

        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('CSV file is empty or invalid');
        }

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $csvData[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $csvData;
    }

    /**
     * Get categories for reference
     */
    public function getCategories()
    {
        try {
            $categories = \App\Models\Category::select('id', 'title')->get();
            return ResponseHelper::success($categories, 'Categories retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get job status
     */
    public function getJobStatus(Request $request, $uploadId)
    {
        try {
            $status = $this->bulkUploadService->getJobStatus($uploadId);
            return ResponseHelper::success($status, 'Job status retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get all jobs for user
     */
    public function getUserJobs(Request $request)
    {
        try {
            $jobs = $this->bulkUploadService->getUserJobs($request->user()->id);
            return ResponseHelper::success($jobs, 'User jobs retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get job results
     */
    public function getJobResults(Request $request, $uploadId)
    {
        try {
            $results = $this->bulkUploadService->getJobResults($uploadId);
            return ResponseHelper::success($results, 'Job results retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
