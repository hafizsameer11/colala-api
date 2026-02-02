<?php

namespace App\Traits;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;

trait HasExportSupport
{
    /**
     * Handle export request - returns all data if export=true, otherwise returns paginated
     */
    protected function handleExport(Request $request, $query, $perPage = 20, $message = 'Data exported successfully')
    {
        // Check if export is requested
        if ($request->has('export') && $request->export == 'true') {
            $data = $query->latest()->get();
            return ResponseHelper::success($data, $message);
        }

        return $query->latest()->paginate($request->get('per_page', $perPage));
    }
}

