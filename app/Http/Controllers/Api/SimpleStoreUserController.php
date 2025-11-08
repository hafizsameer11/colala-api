<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SimpleStoreUserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SimpleStoreUserController extends Controller
{
    private $storeUserService;

    public function __construct(SimpleStoreUserService $storeUserService)
    {
        $this->storeUserService = $storeUserService;
    }

    /**
     * Get all users for a store
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $storeId = $user->store->id;
            
            if (!$storeId) {
                return ResponseHelper::error('User is not associated with any store', 403);
            }
            
            $users = $this->storeUserService->getAllStoreUsers($storeId);
            return ResponseHelper::success($users, 'Store users retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Add user to store
     */
    public function addUser(Request $request)
    {
        try {
            $user = Auth::user();
            $storeId = $user->store->id;
            
            if (!$storeId) {
                return ResponseHelper::error('User is not associated with any store', 403);
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8',
                'role' => 'required',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = $this->storeUserService->addUserToStore($storeId, $request->all());
            return ResponseHelper::success($user, 'User added to store successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Remove user from store
     */
    public function removeUser(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            $storeId = $user->store_id;
            
            if (!$storeId) {
                return ResponseHelper::error('User is not associated with any store', 403);
            }
            
            $this->storeUserService->removeUserFromStore($storeId, $userId);
            return ResponseHelper::success(null, 'User removed from store successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user's store information
     */
    public function getUserStore(Request $request)
    {
        try {
            $store = $this->storeUserService->getUserStore($request->user()->id);
            return ResponseHelper::success($store, 'User store information retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
