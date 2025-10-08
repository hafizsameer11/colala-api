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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
}
