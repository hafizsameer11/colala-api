<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Mail\WelcomeSellerMail;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreSocialLink;
use App\Models\StoreBusinessDetail;
use App\Models\StoreAddress;
use App\Models\StoreDeliveryPricing;
use App\Models\StoreOnboardingStep;
use App\Models\Category;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminSellerCreationController extends Controller
{
    protected $userService;
    protected $walletService;

    public function __construct(UserService $userService, WalletService $walletService)
    {
        $this->userService = $userService;
        $this->walletService = $walletService;
    }

    /**
     * Level 1: Complete Store Information (Basic + Media + Categories + Social)
     */
    public function level1Complete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_name' => 'required|string|max:255',
                'store_email' => 'required|email|unique:users,email|unique:stores,store_email',
                'store_phone' => 'required|string|max:20',
                'password' => 'required|string|min:8',
                'store_location' => 'nullable|string|max:255',
                'referral_code' => 'nullable|string|max:50',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'show_phone_on_profile' => 'boolean',
                'categories' => 'nullable|array',
                'categories.*' => 'exists:categories,id',
                'social_links' => 'nullable|array',
                'social_links.*.type' => 'required_with:social_links|string|in:instagram,facebook,twitter,linkedin,youtube',
                'social_links.*.url' => 'required_with:social_links|url'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'full_name' => $request->store_name,
                'email' => $request->store_email,
                'phone' => $request->store_phone,
                'password' => Hash::make($request->password),
                'role' => 'seller',
                'is_active' => true
            ]);

            // Create wallet for user
            $this->walletService->create([
                'user_id' => $user->id,
                'shopping_balance' => 0,
                'reward_balance' => 0,
                'referral_balance' => 0,
                'loyality_points' => 0
            ]);

            // Create store
            $store = Store::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'store_email' => $request->store_email,
                'store_phone' => $request->store_phone,
                'store_location' => $request->store_location,
                'referral_code' => $request->referral_code,
                'show_phone_on_profile' => $request->boolean('show_phone_on_profile', true)
            ]);

            // Handle file uploads
            if ($request->hasFile('profile_image')) {
                $store->profile_image = $request->file('profile_image')->store("stores/{$store->id}", 'public');
            }
            if ($request->hasFile('banner_image')) {
                $store->banner_image = $request->file('banner_image')->store("stores/{$store->id}", 'public');
            }
            $store->save();

            // Handle categories
            if ($request->filled('categories')) {
                $store->categories()->sync($request->categories);
            }

            // Handle social links
            if ($request->filled('social_links')) {
                foreach ($request->social_links as $link) {
                    StoreSocialLink::create([
                        'store_id' => $store->id,
                        'type' => $link['type'],
                        'url' => $link['url']
                    ]);
                }
            }

            // Create onboarding steps
            $this->createOnboardingSteps($store);

            // Mark all level 1 steps as done
            $this->markDone($store, 1, 'level1.basic');
            $this->markDone($store, 1, 'level1.profile_media');
            $this->markDone($store, 1, 'level1.categories_social');

            // Send welcome email to seller
            try {
                Mail::to($user->email)->send(new WelcomeSellerMail($store->store_name));
            } catch (\Exception $e) {
                // Log error but don't fail creation if email fails
                \Illuminate\Support\Facades\Log::error('Failed to send welcome email to seller: ' . $e->getMessage());
            }

            DB::commit();

            return ResponseHelper::success([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'store_email' => $store->store_email,
                'store_phone' => $store->store_phone,
                'profile_image' => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                'banner_image' => $store->banner_image ? asset('storage/' . $store->banner_image) : null,
                'categories' => $store->categories,
                'social_links' => $store->socialLinks,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 1 completed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 1: Basic Store Information (Legacy - for backward compatibility)
     */
    public function level1Basic(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_name' => 'required|string|max:255',
                'store_email' => 'required|email|unique:users,email|unique:stores,store_email',
                'store_phone' => 'required|string|max:20',
                'password' => 'required|string|min:8',
                'store_location' => 'nullable|string|max:255',
                'referral_code' => 'nullable|string|max:50',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'show_phone_on_profile' => 'boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'full_name' => $request->store_name,
                'email' => $request->store_email,
                'phone' => $request->store_phone,
                'password' => Hash::make($request->password),
                'role' => 'seller',
                'is_active' => true
            ]);

            // Create wallet for user
            $this->walletService->create([
                'user_id' => $user->id,
                'shopping_balance' => 0,
                'reward_balance' => 0,
                'referral_balance' => 0,
                'loyality_points' => 0
            ]);

            // Create store
            $store = Store::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'store_email' => $request->store_email,
                'store_phone' => $request->store_phone,
                'store_location' => $request->store_location,
                'referral_code' => $request->referral_code,
                'show_phone_on_profile' => $request->boolean('show_phone_on_profile', true)
            ]);

            // Handle file uploads
            if ($request->hasFile('profile_image')) {
                $store->profile_image = $request->file('profile_image')->store("stores/{$store->id}", 'public');
            }
            if ($request->hasFile('banner_image')) {
                $store->banner_image = $request->file('banner_image')->store("stores/{$store->id}", 'public');
            }
            $store->save();

            // Create onboarding steps
            $this->createOnboardingSteps($store);

            // Mark level 1 basic as done
            $this->markDone($store, 1, 'level1.basic');

            // Send welcome email to seller
            try {
                Mail::to($user->email)->send(new WelcomeSellerMail($store->store_name));
            } catch (\Exception $e) {
                // Log error but don't fail creation if email fails
                Log::error('Failed to send welcome email to seller: ' . $e->getMessage());
            }

            DB::commit();

            return ResponseHelper::success([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'store_email' => $store->store_email,
                'store_phone' => $store->store_phone,
                'profile_image' => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                'banner_image' => $store->banner_image ? asset('storage/' . $store->banner_image) : null,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 1 basic information saved successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 1: Profile Media
     */
    public function level1ProfileMedia(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            if ($request->hasFile('profile_image')) {
                $store->profile_image = $request->file('profile_image')->store("stores/{$store->id}", 'public');
            }
            if ($request->hasFile('banner_image')) {
                $store->banner_image = $request->file('banner_image')->store("stores/{$store->id}", 'public');
            }
            $store->save();

            $this->markDone($store, 1, 'level1.profile_media');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'profile_image' => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                'banner_image' => $store->banner_image ? asset('storage/' . $store->banner_image) : null,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 1 profile media saved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 1: Categories and Social Links
     */
    public function level1CategoriesSocial(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'categories' => 'nullable|array',
                'categories.*' => 'exists:categories,id',
                'social_links' => 'nullable|array',
                'social_links.*.type' => 'required_with:social_links|string|in:instagram,facebook,twitter,linkedin,youtube',
                'social_links.*.url' => 'required_with:social_links|url'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            // Sync categories
            if ($request->filled('categories')) {
                $store->categories()->sync($request->categories);
            }

            // Handle social links
            if ($request->filled('social_links')) {
                StoreSocialLink::where('store_id', $store->id)->delete();
                foreach ($request->social_links as $link) {
                    StoreSocialLink::create([
                        'store_id' => $store->id,
                        'type' => $link['type'],
                        'url' => $link['url']
                    ]);
                }
            }

            $this->markDone($store, 1, 'level1.categories_social');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'categories' => $store->categories,
                'social_links' => $store->socialLinks,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 1 categories and social links saved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 2: Complete Business Information (Details + Documents)
     */
    public function level2Complete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'business_name' => 'required|string|max:255',
                'business_type' => 'required|string|max:100', // Will be mapped to enum values
                'nin_number' => 'required|string|max:20',
                'cac_number' => 'nullable|string|max:50',
                'nin_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048', // Reduced from 5MB to 2MB
                'cac_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048', // Reduced from 5MB to 2MB
                'utility_bill' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048', // Reduced from 5MB to 2MB
                'store_video' => 'nullable|file|mimes:mp4,avi,mov|max:5120' // Reduced from 10MB to 5MB
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $payload = [];

            // Business details
            $payload['registered_name'] = $request->business_name;
            $payload['business_type'] = $this->mapBusinessType($request->business_type);
            $payload['nin_number'] = $request->nin_number;
            $payload['cac_number'] = $request->cac_number;

            // Handle document uploads
            foreach (['nin_document', 'cac_document', 'utility_bill', 'store_video'] as $field) {
                if ($request->hasFile($field)) {
                    $payload[$field] = $request->file($field)->store("stores/{$store->id}", 'public');
                }
            }

            StoreBusinessDetail::updateOrCreate(['store_id' => $store->id], $payload);

            // Mark all level 2 steps as done
            $this->markDone($store, 2, 'level2.business_details');
            $this->markDone($store, 2, 'level2.documents');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'business_details' => StoreBusinessDetail::where('store_id', $store->id)->first(),
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 2 completed successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 2: Business Details (Legacy - for backward compatibility)
     */
    public function level2BusinessDetails(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'business_name' => 'required|string|max:255',
                'business_type' => 'required|string|max:100', // Will be mapped to enum values
                'nin_number' => 'required|string|max:20',
                'cac_number' => 'nullable|string|max:50'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            StoreBusinessDetail::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'registered_name' => $request->business_name,
                    'business_type' => $this->mapBusinessType($request->business_type),
                    'nin_number' => $request->nin_number,
                    'cac_number' => $request->cac_number
                ]
            );

            $this->markDone($store, 2, 'level2.business_details');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'business_details' => StoreBusinessDetail::where('store_id', $store->id)->first(),
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 2 business details saved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 2: Documents Upload
     */
    public function level2Documents(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'nin_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048', // Reduced from 5MB to 2MB
                'cac_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048', // Reduced from 5MB to 2MB
                'utility_bill' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048', // Reduced from 5MB to 2MB
                'store_video' => 'nullable|file|mimes:mp4,avi,mov|max:5120' // Reduced from 10MB to 5MB
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $payload = [];

            // Handle document uploads
            foreach (['nin_document', 'cac_document', 'utility_bill', 'store_video'] as $field) {
                if ($request->hasFile($field)) {
                    $payload[$field] = $request->file($field)->store("stores/{$store->id}", 'public');
                }
            }

            if (!empty($payload)) {
                StoreBusinessDetail::updateOrCreate(['store_id' => $store->id], $payload);
            }

            $this->markDone($store, 2, 'level2.documents');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'documents' => StoreBusinessDetail::where('store_id', $store->id)->first(),
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 2 documents uploaded successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 3: Complete Store Setup (Physical + Utility + Addresses + Delivery + Theme)
     */
    public function level3Complete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'has_physical_store' => 'required|boolean',
                'store_video' => 'nullable|file|mimes:mp4,avi,mov|max:10240',
                'utility_bill' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
                'theme_color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'addresses' => 'nullable|array',
                'addresses.*.state' => 'required_with:addresses|string|max:100',
                'addresses.*.local_government' => 'required_with:addresses|string|max:100',
                'addresses.*.full_address' => 'required_with:addresses|string|max:500',
                'addresses.*.is_main' => 'boolean',
                'addresses.*.opening_hours' => 'nullable|array',
                'delivery_pricing' => 'nullable|array',
                'delivery_pricing.*.state' => 'required_with:delivery_pricing|string|max:100',
                'delivery_pricing.*.local_government' => 'required_with:delivery_pricing|string|max:100',
                'delivery_pricing.*.variant' => 'required_with:delivery_pricing|string|max:50', // Will be mapped to enum values
                'delivery_pricing.*.price' => 'required_with:delivery_pricing|numeric|min:0',
                'delivery_pricing.*.is_free' => 'boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $data = ['has_physical_store' => $request->boolean('has_physical_store')];

            // Handle physical store video
            if ($request->hasFile('store_video')) {
                $data['store_video'] = $request->file('store_video')->store("stores/{$store->id}", 'public');
            }

            // Handle utility bill
            if ($request->hasFile('utility_bill')) {
                $data['utility_bill'] = $request->file('utility_bill')->store("stores/{$store->id}", 'public');
            }

            // Update business details
            StoreBusinessDetail::updateOrCreate(['store_id' => $store->id], $data);

            // Update theme color
            $store->update(['theme_color' => $request->theme_color]);

            // Handle addresses
            if ($request->filled('addresses')) {
                // Clear existing addresses
                StoreAddress::where('store_id', $store->id)->delete();
                
                foreach ($request->addresses as $addressData) {
                    StoreAddress::create([
                        'store_id' => $store->id,
                        'state' => $addressData['state'],
                        'local_government' => $addressData['local_government'],
                        'full_address' => $addressData['full_address'],
                        'is_main' => $addressData['is_main'] ?? false,
                        'opening_hours' => $addressData['opening_hours'] ?? []
                    ]);
                }
            }

            // Handle delivery pricing
            if ($request->filled('delivery_pricing')) {
                // Clear existing delivery pricing
                StoreDeliveryPricing::where('store_id', $store->id)->delete();
                
                foreach ($request->delivery_pricing as $pricingData) {
                    StoreDeliveryPricing::create([
                        'store_id' => $store->id,
                        'state' => $pricingData['state'],
                        'local_government' => $pricingData['local_government'],
                        'variant' => $this->mapDeliveryVariant($pricingData['variant']),
                        'price' => $pricingData['price'],
                        'is_free' => $pricingData['is_free'] ?? false
                    ]);
                }
            }

            // Mark all level 3 steps as done
            $this->markDone($store, 3, 'level3.physical_store');
            $this->markDone($store, 3, 'level3.utility_bill');
            $this->markDone($store, 3, 'level3.addresses');
            $this->markDone($store, 3, 'level3.delivery_pricing');
            $this->markDone($store, 3, 'level3.theme');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'has_physical_store' => $data['has_physical_store'],
                'store_video' => $data['store_video'] ?? null,
                'utility_bill' => $data['utility_bill'] ?? null,
                'theme_color' => $store->theme_color,
                'addresses' => $store->addresses,
                'delivery_pricing' => $store->deliveryPricing,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 3 completed successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 3: Physical Store (Legacy - for backward compatibility)
     */
    public function level3PhysicalStore(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'has_physical_store' => 'required|boolean',
                'store_video' => 'nullable|file|mimes:mp4,avi,mov|max:10240'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            $data = ['has_physical_store' => $request->boolean('has_physical_store')];
            if ($request->hasFile('store_video')) {
                $data['store_video'] = $request->file('store_video')->store("stores/{$store->id}", 'public');
            }

            StoreBusinessDetail::updateOrCreate(['store_id' => $store->id], $data);

            $this->markDone($store, 3, 'level3.physical_store');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'has_physical_store' => $data['has_physical_store'],
                'store_video' => $data['store_video'] ?? null,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 3 physical store information saved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 3: Utility Bill
     */
    public function level3UtilityBill(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'utility_bill' => 'required|file|mimes:pdf,jpeg,png,jpg|max:5120'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            $path = $request->file('utility_bill')->store("stores/{$store->id}", 'public');
            StoreBusinessDetail::updateOrCreate(['store_id' => $store->id], ['utility_bill' => $path]);

            $this->markDone($store, 3, 'level3.utility_bill');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'utility_bill' => $path,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 3 utility bill uploaded successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 3: Add Store Address
     */
    public function level3AddAddress(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'state' => 'required|string|max:100',
                'local_government' => 'required|string|max:100',
                'full_address' => 'required|string|max:500',
                'is_main' => 'boolean',
                'opening_hours' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            StoreAddress::create([
                'store_id' => $store->id,
                'state' => $request->state,
                'local_government' => $request->local_government,
                'full_address' => $request->full_address,
                'is_main' => $request->boolean('is_main'),
                'opening_hours' => $request->opening_hours ?? []
            ]);

            $this->markDone($store, 3, 'level3.addresses');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'address' => StoreAddress::where('store_id', $store->id)->latest()->first(),
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 3 address added successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 3: Add Delivery Pricing
     */
    public function level3AddDelivery(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'state' => 'required|string|max:100',
                'local_government' => 'required|string|max:100',
                'variant' => 'required|string|max:50', // Will be mapped to enum values
                'price' => 'required|numeric|min:0',
                'is_free' => 'boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);

            StoreDeliveryPricing::create([
                'store_id' => $store->id,
                'state' => $request->state,
                'local_government' => $request->local_government,
                'variant' => $this->mapDeliveryVariant($request->variant),
                'price' => $request->price,
                'is_free' => $request->boolean('is_free')
            ]);

            $this->markDone($store, 3, 'level3.delivery_pricing');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'delivery_pricing' => StoreDeliveryPricing::where('store_id', $store->id)->latest()->first(),
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 3 delivery pricing added successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Level 3: Theme Selection
     */
    public function level3Theme(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'theme_color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $store->update(['theme_color' => $request->theme_color]);

            $this->markDone($store, 3, 'level3.theme');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'theme_color' => $store->theme_color,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Level 3 theme selected successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Set Approval Status
     */
    public function setApprovalStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'approval_status' => 'required|string|in:pending_review,approved,rejected'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $store->update(['onboarding_status' => $request->approval_status]);

            return ResponseHelper::success([
                'store_id' => $store->id,
                'approval_status' => $store->onboarding_status,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Approval status updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get Store Progress
     */
    public function getProgress(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::with(['user', 'businessDetails', 'addresses', 'deliveryPricing', 'socialLinks', 'categories'])->findOrFail($request->store_id);
            
            $steps = StoreOnboardingStep::where('store_id', $store->id)
                ->orderBy('level')
                ->get(['key', 'status', 'completed_at', 'rejection_reason'])
                ->map(function ($step) {
                    return [
                        'key' => $step->key,
                        'status' => $step->status,
                        'completed_at' => $step->completed_at,
                        'rejection_reason' => $step->rejection_reason,
                        'is_rejected' => $step->status === 'rejected'
                    ];
                });

            return ResponseHelper::success([
                'store' => [
                    'id' => $store->id,
                    'store_name' => $store->store_name,
                    'store_email' => $store->store_email,
                    'store_phone' => $store->store_phone,
                    'profile_image' => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                    'banner_image' => $store->banner_image ? asset('storage/' . $store->banner_image) : null,
                    'theme_color' => $store->theme_color,
                    'onboarding_status' => $store->onboarding_status
                ],
                'user' => [
                    'id' => $store->user->id,
                    'full_name' => $store->user->full_name,
                    'email' => $store->user->email,
                    'phone' => $store->user->phone,
                    'is_active' => $store->user->is_active
                ],
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ],
                'steps' => $steps,
                'business_details' => $store->businessDetails,
                'addresses' => $store->addresses,
                'delivery_pricing' => $store->deliveryPricing,
                'social_links' => $store->socialLinks,
                'categories' => $store->categories
            ], 'Store progress retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Reject a specific onboarding field with rejection reason
     */
    public function rejectField(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'field_key' => 'required|string|in:level1.basic,level1.profile_media,level1.categories_social,level2.business_details,level2.documents,level3.physical_store,level3.utility_bill,level3.addresses,level3.delivery_pricing,level3.theme',
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::with('user')->findOrFail($request->store_id);
            
            // Find the onboarding step
            $step = StoreOnboardingStep::where('store_id', $store->id)
                ->where('key', $request->field_key)
                ->firstOrFail();

            // Field key to human-readable name mapping
            $fieldNames = [
                'level1.basic' => 'Basic Information',
                'level1.profile_media' => 'Profile & Banner Images',
                'level1.categories_social' => 'Categories & Social Links',
                'level2.business_details' => 'Business Details',
                'level2.documents' => 'Business Documents',
                'level3.physical_store' => 'Physical Store Information',
                'level3.utility_bill' => 'Utility Bill',
                'level3.addresses' => 'Store Addresses',
                'level3.delivery_pricing' => 'Delivery Pricing',
                'level3.theme' => 'Theme Color',
            ];

            $fieldName = $fieldNames[$request->field_key] ?? $request->field_key;

            // Update step to rejected status with reason
            $step->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'completed_at' => null // Clear completion date when rejected
            ]);

            // Recalculate progress percentage (exclude rejected from done count)
            $total = StoreOnboardingStep::where('store_id', $store->id)->count();
            $done = StoreOnboardingStep::where('store_id', $store->id)
                ->where('status', 'done')
                ->count();
            $percent = $total ? (int) floor($done * 100 / $total) : $store->onboarding_percent;

            $store->update([
                'onboarding_percent' => $percent,
            ]);

            // Send notification to seller
            if ($store->user) {
                $title = 'Onboarding Field Rejected';
                $content = "Your {$fieldName} has been rejected. Reason: {$request->rejection_reason}. Please review and resubmit.";
                
                UserNotificationHelper::notify(
                    $store->user->id,
                    $title,
                    $content,
                    [
                        'type' => 'onboarding_field_rejected',
                        'store_id' => $store->id,
                        'field_key' => $request->field_key,
                        'field_name' => $fieldName,
                        'rejection_reason' => $request->rejection_reason,
                    ]
                );
            }

            return ResponseHelper::success([
                'store_id' => $store->id,
                'field_key' => $step->key,
                'status' => $step->status,
                'rejection_reason' => $step->rejection_reason,
                'progress' => [
                    'level' => $store->onboarding_level,
                    'percent' => $store->onboarding_percent,
                    'status' => $store->onboarding_status
                ]
            ], 'Field rejected successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get All Categories
     */
    public function getAllCategories()
    {
        try {
            $categories = Category::select('id', 'title', 'image')->get();
            
            return ResponseHelper::success([
                'categories' => $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'image_url' => $category->image ? asset('storage/' . $category->image) : null
                    ];
                })
            ], 'Categories retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get Store Addresses
     */
    public function getStoreAddresses(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $addresses = $store->addresses()->orderByDesc('is_main')->get();

            return ResponseHelper::success([
                'store_id' => $store->id,
                'addresses' => $addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'state' => $address->state,
                        'local_government' => $address->local_government,
                        'full_address' => $address->full_address,
                        'is_main' => $address->is_main,
                        'opening_hours' => $address->opening_hours,
                        'created_at' => $address->created_at->format('d-m-Y H:i:s')
                    ];
                })
            ], 'Store addresses retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update Store Address
     */
    public function updateStoreAddress(Request $request, $addressId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'state' => 'required|string|max:100',
                'local_government' => 'required|string|max:100',
                'full_address' => 'required|string|max:500',
                'is_main' => 'boolean',
                'opening_hours' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $address = StoreAddress::where('store_id', $store->id)->findOrFail($addressId);

            $address->update([
                'state' => $request->state,
                'local_government' => $request->local_government,
                'full_address' => $request->full_address,
                'is_main' => $request->boolean('is_main'),
                'opening_hours' => $request->opening_hours ?? []
            ]);

            return ResponseHelper::success([
                'address' => [
                    'id' => $address->id,
                    'state' => $address->state,
                    'local_government' => $address->local_government,
                    'full_address' => $address->full_address,
                    'is_main' => $address->is_main,
                    'opening_hours' => $address->opening_hours,
                    'updated_at' => $address->updated_at->format('d-m-Y H:i:s')
                ]
            ], 'Store address updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete Store Address
     */
    public function deleteStoreAddress(Request $request, $addressId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $address = StoreAddress::where('store_id', $store->id)->findOrFail($addressId);
            $address->delete();

            return ResponseHelper::success(null, 'Store address deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get Store Delivery Pricing
     */
    public function getStoreDeliveryPricing(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $deliveryPricing = $store->deliveryPricing()->get();

            return ResponseHelper::success([
                'store_id' => $store->id,
                'delivery_pricing' => $deliveryPricing->map(function ($pricing) {
                    return [
                        'id' => $pricing->id,
                        'state' => $pricing->state,
                        'local_government' => $pricing->local_government,
                        'variant' => $this->mapDeliveryVariant($pricing->variant),
                        'price' => $pricing->price,
                        'formatted_price' => 'N' . number_format($pricing->price, 0),
                        'is_free' => $pricing->is_free,
                        'created_at' => $pricing->created_at->format('d-m-Y H:i:s')
                    ];
                })
            ], 'Store delivery pricing retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update Store Delivery Pricing
     */
    public function updateStoreDeliveryPricing(Request $request, $pricingId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'state' => 'required|string|max:100',
                'local_government' => 'required|string|max:100',
                'variant' => 'required|string|max:50', // Will be mapped to enum values
                'price' => 'required|numeric|min:0',
                'is_free' => 'boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $pricing = StoreDeliveryPricing::where('store_id', $store->id)->findOrFail($pricingId);

            $pricing->update([
                'state' => $request->state,
                'local_government' => $request->local_government,
                'variant' => $this->mapDeliveryVariant($request->variant),
                'price' => $request->price,
                'is_free' => $request->boolean('is_free')
            ]);

            return ResponseHelper::success([
                'delivery_pricing' => [
                    'id' => $pricing->id,
                    'state' => $pricing->state,
                    'local_government' => $pricing->local_government,
                    'variant' => $this->mapDeliveryVariant($pricing->variant),
                    'price' => $pricing->price,
                    'formatted_price' => 'N' . number_format($pricing->price, 0),
                    'is_free' => $pricing->is_free,
                    'updated_at' => $pricing->updated_at->format('d-m-Y H:i:s')
                ]
            ], 'Store delivery pricing updated successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete Store Delivery Pricing
     */
    public function deleteStoreDeliveryPricing(Request $request, $pricingId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $store = Store::findOrFail($request->store_id);
            $pricing = StoreDeliveryPricing::where('store_id', $store->id)->findOrFail($pricingId);
            $pricing->delete();

            return ResponseHelper::success(null, 'Store delivery pricing deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Helper: Mark onboarding step as done
     */
    private function markDone(Store $store, int $level, string $key): void
    {
        StoreOnboardingStep::updateOrCreate(
            ['store_id' => $store->id, 'key' => $key],
            ['level' => $level, 'status' => 'done', 'completed_at' => now()]
        );

        $total = StoreOnboardingStep::where('store_id', $store->id)->count();
        $done = StoreOnboardingStep::where('store_id', $store->id)->where('status', 'done')->count();
        $percent = $total ? (int) floor($done * 100 / $total) : $store->onboarding_percent;

        $store->update([
            'onboarding_level' => max($store->onboarding_level, $level),
            'onboarding_percent' => $percent
        ]);
    }

    /**
     * Helper: Create all onboarding steps
     */
    private function createOnboardingSteps(Store $store): void
    {
        $stepKeys = [
            ['level' => 1, 'key' => 'level1.basic'],
            ['level' => 1, 'key' => 'level1.profile_media'],
            ['level' => 1, 'key' => 'level1.categories_social'],
            ['level' => 2, 'key' => 'level2.business_details'],
            ['level' => 2, 'key' => 'level2.documents'],
            ['level' => 3, 'key' => 'level3.physical_store'],
            ['level' => 3, 'key' => 'level3.utility_bill'],
            ['level' => 3, 'key' => 'level3.addresses'],
            ['level' => 3, 'key' => 'level3.delivery_pricing'],
            ['level' => 3, 'key' => 'level3.theme']
        ];

        foreach ($stepKeys as $sk) {
            StoreOnboardingStep::firstOrCreate(
                ['store_id' => $store->id, 'key' => $sk['key']],
                $sk
            );
        }
    }

    /**
     * Map business type to database enum values
     */
    private function mapBusinessType($businessType)
    {
        $mapping = [
            'Limited Liability Company' => 'LTD',
            'Limited Liability' => 'LTD',
            'LTD' => 'LTD',
            'Ltd' => 'LTD',
            'Limited' => 'LTD',
            'Business Name' => 'BN',
            'BN' => 'BN',
            'Sole Proprietorship' => 'BN',
            'Partnership' => 'BN',
            'Individual' => 'BN'
        ];

        return $mapping[$businessType] ?? 'BN'; // Default to BN if not found
    }

    /**
     * Map delivery variant to database enum values
     */
    private function mapDeliveryVariant($variant)
    {
        $mapping = [
            'light' => 'light',
            'medium' => 'medium',
            'heavy' => 'heavy',
            'Light' => 'light',
            'Medium' => 'medium',
            'Heavy' => 'heavy',
            'LIGHT' => 'light',
            'MEDIUM' => 'medium',
            'HEAVY' => 'heavy',
            'small' => 'light',
            'large' => 'heavy',
            'standard' => 'medium',
            'express' => 'light',
            'regular' => 'medium',
            'bulk' => 'heavy'
        ];

        return $mapping[$variant] ?? 'medium'; // Default to medium if not found
    }
}
