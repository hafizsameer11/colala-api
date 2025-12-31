<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ActivityHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\OtpMail;
use App\Mail\WelcomeBuyerMail;
use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use App\Models\UserNotification;
use App\Models\Subscription;
use App\Models\User;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    protected $userService, $walletService;
    public function __construct(UserService $userService, WalletService $walletService)
    {
        $this->userService = $userService;
        $this->walletService = $walletService;
    }
    public function sellerLogin(Request $request)
    {
        try {
            $data = $request->all();
            $user = $this->userService->sellerLogin($data);
            $token = $user->createToken('auth_token')->plainTextToken;
            return ResponseHelper::success($user, "Seller login successfully");
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function register(RegisterRequest $registerRequest)
    {

        try {
            $data = $registerRequest->validated();
            $data['password'] = Hash::make($data['password']);
            $data['user_code'] =   $this->userService->createUserCode($data['full_name']);
            $user = $this->userService->create($data);
            $wallet = $this->walletService->create(['user_id' => $user->id]);
            $otp = rand(1000, 9999);
            $user->otp = $otp;
            $user->save();

            // Send OTP email
            Mail::to($user->email)->send(new OtpMail($otp));

            // Send welcome email for buyers
            if ($user->role === 'buyer') {
                Mail::to($user->email)->send(new WelcomeBuyerMail($user->full_name));
            }

            return ResponseHelper::success($user, "OTP sent successfully");
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
            $token = $user->createToken('auth_token')->plainTextToken;
            $activity = ActivityHelper::log($user->id, "user login");
            $respone =
                [
                    'user' => $user,
                    'store' => $user->store,
                    'token' => $token
                ];
            //check if user have wallet otherwise creste wallet
            if (!$user->wallet) {
                $wallet = $this->walletService->create(['user_id' => $user->id]);
            }

            // Send login notification (in-app + push)
            UserNotificationHelper::notify(
                $user->id,
                'Login Successful',
                'You have successfully logged in to your account.',
                ['type' => 'login', 'timestamp' => now()->toIso8601String()]
            );

            return ResponseHelper::success($respone, "user login successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function sellerLoginNormal(LoginRequest $loginRequest)
    {
        try {
            $data = $loginRequest->validated();
            $user = $this->userService->sellerLogin($data);
            $token = $user->createToken('auth_token')->plainTextToken;
            $activity = ActivityHelper::log($user->id, "user login");
            $respone =
                [
                    'user' => $user,
                    'store' => $user->store,
                    'token' => $token
                ];
            //check if user have wallet otherwise creste wallet
            if (!$user->wallet) {
                $wallet = $this->walletService->create(['user_id' => $user->id]);
            }

            // Send login notification (in-app + push)
            UserNotificationHelper::notify(
                $user->id,
                'Login Successful',
                'You have successfully logged in to your account.',
                ['type' => 'login', 'timestamp' => now()->toIso8601String()]
            );

            return ResponseHelper::success($respone, "user login successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function adminLogin(LoginRequest $loginRequest)
    {
        try {
            $data = $loginRequest->validated();
            $user = $this->userService->adminLogin($data);
            $token = $user->createToken('auth_token')->plainTextToken;
            $activity = ActivityHelper::log($user->id, "user login");
            $respone =
                [
                    'user' => $user,
                    'store' => $user->store,
                    'token' => $token
                ];
            //check if user have wallet otherwise creste wallet
            if (!$user->wallet) {
                $wallet = $this->walletService->create(['user_id' => $user->id]);
            }

            // Send login notification (in-app + push)
            UserNotificationHelper::notify(
                $user->id,
                'Login Successful',
                'You have successfully logged in to your account.',
                ['type' => 'login', 'timestamp' => now()->toIso8601String()]
            );

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
     * Get authenticated user's plan with complete subscription details
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

            // Get user's store
            $store = $user->store;
            $subscription = null;
            $needsRenewal = false;
            $isExpired = false;

            if ($store) {
                // Get active subscription for the store
                $subscription = Subscription::with('plan')
                    ->where('store_id', $store->id)
                    ->where('status', 'active')
                    ->latest()
                    ->first();

                if ($subscription) {
                    // Check if subscription has expired
                    $today = Carbon::today();
                    // end_date is already a Carbon instance due to model cast
                    $endDate = $subscription->end_date instanceof \Carbon\Carbon
                        ? $subscription->end_date
                        : Carbon::parse($subscription->end_date);

                    if ($endDate->lt($today)) {
                        // Subscription has expired - update user plan to basic
                        $user->plan = 'basic';
                        $user->save();

                        // Update subscription status to expired
                        $subscription->status = 'expired';
                        $subscription->save();

                        $isExpired = true;
                        $needsRenewal = true;
                    } elseif ($endDate->lte($today->copy()->addDays(7))) {
                        // Subscription expires within 7 days - needs renewal
                        $needsRenewal = true;
                    }
                }
            }

            // If no active subscription or expired, ensure user plan is basic
            if (!$subscription || $isExpired) {
                if ($user->plan !== 'basic') {
                    $user->plan = 'basic';
                    $user->save();
                }
            }

            return ResponseHelper::success([
                'plan' => $user->plan ?? 'basic',
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'is_free_trial_claimed' => (bool)($user->is_free_trial_claimed ?? false),
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'plan_id' => $subscription->plan_id,
                    'plan_name' => $subscription->plan ? $subscription->plan->name : null,
                    'plan_price' => $subscription->plan ? $subscription->plan->price : null,
                    'start_date' => $subscription->start_date ? $subscription->start_date->format('Y-m-d') : null,
                    'end_date' => $subscription->end_date ? $subscription->end_date->format('Y-m-d') : null,
                    'status' => $subscription->status,
                    'payment_method' => $subscription->payment_method,
                    'payment_status' => $subscription->payment_status,

                    'transaction_ref' => $subscription->transaction_ref,
                    'created_at' => $subscription->created_at ? $subscription->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $subscription->updated_at ? $subscription->updated_at->format('Y-m-d H:i:s') : null,
                ] : null,
                'needs_renewal' => $needsRenewal,
                'is_expired' => $isExpired,
                'days_until_expiry' => $subscription && !$isExpired
                    ? max(0, ($subscription->end_date instanceof \Carbon\Carbon
                        ? $subscription->end_date
                        : Carbon::parse($subscription->end_date))->diffInDays(Carbon::today(), false))
                    : null,
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

    /**
     * Delete user account (old method - kept for backward compatibility)
     */
    public function deleteAccount($id)
    {
        try {
            $user = User::findOrFail($id);
            // $user->delete();
            $user->update(['is_active' => false]);
            $store = Store::where('user_id', $id)->first();
            if ($store) {
                $store->update(['visibility' => 0]);
                $products = Product::where('store_id', $store->id)->get();
                foreach ($products as $product) {
                    $product->update(['visibility' => 0]);
                }
                $services = Service::where('store_id', $store->id)->get();
                foreach ($services as $service) {
                    $service->update(['visibility' => 0]);
                }
            }
            return ResponseHelper::success(null, 'Account deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Delete authenticated user's own account
     */
    public function deleteMyAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ResponseHelper::error('User not authenticated', 401);
            }

            DB::beginTransaction();

            // Check if user has active orders or transactions
            $hasActiveOrders = $user->orders()
                ->whereIn('payment_status', ['pending', 'paid'])
                ->exists();
            
            // Check store orders if user is a seller
            if ($user->store) {
                $hasActiveOrders = $hasActiveOrders || $user->store->orders()
                    ->whereIn('status', ['pending', 'accepted', 'processing', 'out_for_delivery'])
                    ->exists();
            }

            $hasTransactions = $user->transactions()
                ->whereIn('status', ['pending', 'successful'])
                ->exists();

            // If user has active orders or transactions, deactivate instead of delete
            if ($hasActiveOrders || $hasTransactions) {
                // Deactivate account
                $user->update(['is_active' => false]);
                
                // Hide store and related items if seller
                if ($user->store) {
                    $store = $user->store;
                    $store->update(['visibility' => 0]);
                    
                    // Hide products
                    Product::where('store_id', $store->id)->update(['visibility' => 0]);
                    
                    // Hide services
                    Service::where('store_id', $store->id)->update(['visibility' => 0]);
                }

                // Revoke all tokens
                $user->tokens()->delete();

                DB::commit();

                return ResponseHelper::success(null, 'Account deactivated successfully. Your account has been deactivated due to active orders or transactions.');
            }

            // If no active orders/transactions, proceed with soft delete
            // Delete profile picture if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Handle store if user is a seller
            if ($user->store) {
                $store = $user->store;
                
                // Delete store images
                if ($store->profile_image) {
                    Storage::disk('public')->delete($store->profile_image);
                }
                if ($store->banner_image) {
                    Storage::disk('public')->delete($store->banner_image);
                }

                // Hide store and related items
                $store->update(['visibility' => 0]);
                Product::where('store_id', $store->id)->update(['visibility' => 0]);
                Service::where('store_id', $store->id)->update(['visibility' => 0]);
            }

            // Revoke all tokens before deletion
            $user->tokens()->delete();

            // Soft delete user (cascade will handle related records)
            $user->delete();

            DB::commit();

            return ResponseHelper::success(null, 'Account deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Account deletion error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            return ResponseHelper::error('Failed to delete account: ' . $e->getMessage(), 500);
        }
    }
}
