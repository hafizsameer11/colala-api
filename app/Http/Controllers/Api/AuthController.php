<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ActivityHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $userService,$walletService;
    public function __construct(UserService $userService,WalletService $walletService)
    {
        $this->userService = $userService;
        $this->walletService = $walletService;
    }
    public function register(RegisterRequest $registerRequest)
    {

        try {
            $data = $registerRequest->validated();
            $data['password'] = Hash::make($data['password']);
            $data['user_code'] =   $this->userService->createUserCode($data['full_name']);
            $user = $this->userService->create($data);
            $wallet=$this->walletService->create(['user_id'=>$user->id]);
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
    public function login(LoginRequest $loginRequest)
    {
        try {
            $data = $loginRequest->validated();
            $user = $this->userService->login($data);
            $token=$user->createToken('auth_token')->plainTextToken;
            $activity = ActivityHelper::log($user->id, "user login");
            $respone=
            [
                'user'=>$user,
                'token'=>$token
            ];
            //check if user have wallet otherwise creste wallet
            if(!$user->wallet){
                $wallet=$this->walletService->create(['user_id'=>$user->id]);
            }
            
            return ResponseHelper::success($respone, "user login successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function forgetPassword(Request $request)
    {
        try {
            $email = $request->email;
            $user = $this->userService->forgetPassword($email);
            return ResponseHelper::success([], "Email Sent successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $email = $request->email;
            $otp = $request->otp;
            $user = $this->userService->verifyOtp($email, $otp);
            return ResponseHelper::success($user, "otp verified successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function resetPassword(Request $request)
    {
        try {
            $email = $request->email;
            $password = $request->password;
            $user = $this->userService->resetPassword($email, $password);
            return ResponseHelper::success($user, "Password reset successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
  public function editProfile(Request $request)
{
    try {
        $user = $request->user();
        $data = $request->only([
            'full_name',
            'email',
            'phone',
            'user_name',
            'profile_picture',
            'country',
            'state'
        ]);

        $updatedUser = $this->userService->update($data, $user->id);

        return ResponseHelper::success($updatedUser, "Profile updated successfully");
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return ResponseHelper::error($e->getMessage());
    }
}

}
