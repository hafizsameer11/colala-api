<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ActivityHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\UserNotification;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'store'=>$user->store,
                'token'=>$token
            ];
            //check if user have wallet otherwise creste wallet
            if(!$user->wallet){
                $wallet=$this->walletService->create(['user_id'=>$user->id]);
            }
            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'Login Notification',
                'content' => 'You have successfully logged in to your account.',
                'is_read' => false,
            ]);
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

/**
 * Get authenticated user's plan
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getPlan(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return ResponseHelper::error('Unauthenticated', 401);
        }

        return ResponseHelper::success([
            'plan' => $user->plan ?? 'basic',
            'user_id' => $user->id,
            'full_name' => $user->full_name,
        ], 'User plan retrieved successfully');
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return ResponseHelper::error($e->getMessage());
    }
}

/**
 * Generate guest token for anonymous users
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function generateGuestToken()
{
    try {
        // Generate a unique guest token
        $guestToken = 'guest_' . Str::random(32) . '_' . time();
        
        // You can store this token in cache with expiration if needed
        // Cache::put("guest_token_{$guestToken}", true, now()->addHours(24));
        
        return ResponseHelper::success([
            'guest_token' => $guestToken,
            'expires_at' => now()->addHours(24)->toISOString(),
            'token_type' => 'guest'
        ], 'Guest token generated successfully');
        
    } catch (\Exception $e) {
        Log::error('Guest token generation failed: ' . $e->getMessage());
        return ResponseHelper::error('Failed to generate guest token', 500);
    }
}

}
