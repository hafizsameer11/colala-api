<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRegisterStep1Request;
use App\Http\Requests\SellerRegisterStep2Request;
use App\Http\Requests\SellerRegisterStep3Request;
use App\Models\Store;
use App\Models\StoreAddress;
use App\Models\StoreBusinessDetail;
use App\Models\StoreDeliveryPricing;
use App\Models\StoreSocialLink;
use App\Models\User;
use App\Models\SellerHelpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SellerRegistrationController extends Controller
{
    public function registerStep1(SellerRegisterStep1Request $request)
    {
        $user = User::create([
            'full_name' => $request->store_name,
            'email'     => $request->store_email,
            'phone'     => $request->store_phone,
            'password'  => Hash::make($request->password),
            'role'      => 'seller'
        ]);

        $store = Store::create([
            'user_id'        => $user->id,
            'store_name'     => $request->store_name,
            'store_email'    => $request->store_email,
            'store_phone'    => $request->store_phone,
            'store_location' => $request->store_location,
            'referral_code'  => $request->referral_code,
        ]);

        // Assign store to user
        $user->update(['store_id' => $store->id]);

        // ✅ Attach categories
        if ($request->has('categories') && is_array($request->categories)) {
            $store->categories()->sync($request->categories);
        }
        if ($request->has('profile_image')) {
            $store->profile_image = $request->file('profile_image')->store("stores/{$store->id}", 'public');
            $store->save();
        }
        if ($request->has('banner_image')) {
            $store->banner_image = $request->file('banner_image')->store("stores/{$store->id}", 'public');
            $store->save();
        }

        // ✅ Store social links
        if ($request->has('social_links')) {
            foreach ($request->social_links as $link) {
                StoreSocialLink::create([
                    'store_id' => $store->id,
                    'type'     => $link['type'],
                    'url'      => $link['url']
                ]);
            }
        }

        return response()->json([
            'status'   => true,
            'message'  => 'Step 1 completed',
            'store_id' => $store->id
        ]);
    }

    public function registerStep2(SellerRegisterStep2Request $request, $storeId)
    {
        $store = Store::findOrFail($storeId);

        $data = $request->validated();

        // handle file uploads
        foreach (['nin_document', 'cac_document', 'utility_bill', 'store_video'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store("stores/{$store->id}", 'public');
            }
        }

        $business = StoreBusinessDetail::updateOrCreate(
            ['store_id' => $store->id],
            $data
        );

        return response()->json([
            'status' => true,
            'message' => 'Step 2 completed',
            'business_details' => $business
        ]);
    }

    public function registerStep3(SellerRegisterStep3Request $request, $storeId)
    {
        $store = Store::findOrFail($storeId);

        if ($request->has('addresses')) {
            foreach ($request->addresses as $addr) {
                StoreAddress::create([
                    'store_id'        => $store->id,
                    'state'           => $addr['state'],
                    'local_government' => $addr['local_government'],
                    'full_address'    => $addr['full_address'],
                    'is_main'         => $addr['is_main'] ?? false,
                    'opening_hours'   => $addr['opening_hours'] ?? []
                ]);
            }
        }

        if ($request->has('delivery_pricing')) {
            foreach ($request->delivery_pricing as $pricing) {
                StoreDeliveryPricing::create([
                    'store_id'        => $store->id,
                    'state'           => $pricing['state'],
                    'local_government' => $pricing['local_government'],
                    'variant'         => $pricing['variant'],
                    'price'           => $pricing['price'] ?? null,
                    'is_free'         => $pricing['is_free'] ?? false
                ]);
            }
        }

        if ($request->theme_color) {
            $store->update(['theme_color' => $request->theme_color]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Step 3 completed, seller registration pending approval'
        ]);
    }

    /**
     * Submit a help request for seller signup (No authentication required)
     */
    public function submitHelpRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_type' => 'required|in:store_setup,profile_media,business_docs,store_config,complete_setup,custom',
            'fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'full_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $helpRequest = SellerHelpRequest::create([
                'service_type' => $request->service_type,
                'fee' => $request->fee ?? $this->getDefaultFee($request->service_type),
                'notes' => $request->notes,
                'email' => $request->email,
                'phone' => $request->phone,
                'full_name' => $request->full_name,
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Your help request has been submitted successfully. Our team will contact you shortly.',
                'data' => [
                    'id' => $helpRequest->id,
                    'service_type' => $helpRequest->service_type,
                    'fee' => $helpRequest->fee,
                    'status' => $helpRequest->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit help request. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default fee for service type
     */
    private function getDefaultFee(string $serviceType): ?float
    {
        $serviceFees = [
            'store_setup' => 5000,
            'profile_media' => 3000,
            'business_docs' => 7000,
            'store_config' => 4000,
            'complete_setup' => 15000,
            'custom' => null,
        ];

        return $serviceFees[$serviceType] ?? null;
    }
}
