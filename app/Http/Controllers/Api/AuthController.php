<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ActivityHelper;
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
    /**
     * User login
     *
     * @param LoginRequest $loginRequest
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function login(LoginRequest $loginRequest){
        try{
            $data=$loginRequest->validated();
            $user=$this->userService->login($data);
            $activity=ActivityHelper::log($user->id,"user login");
            return ResponseHelper::success($user,"user login successfully");
        }catch(\Exception $e){
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }

    }
}
