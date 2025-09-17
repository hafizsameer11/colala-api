<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $userService;
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function register(RegisterRequest $registerRequest)
    {

        try {
            $data = $registerRequest->validated();
            $data['password'] = Hash::make($data['password']);
            $data['user_code'] =   $this->userService->createUserCode($data['full_name']);
            $user = $this->userService->create($data);
            return ResponseHelper::success($user);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
            // return ResponseHelper::error($e->getMessage());
        }
    }
    public function login(LoginRequest $loginRequest){

    }
}
