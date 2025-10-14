<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreAddress;
use App\Models\Category; // assumed Category model exists
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class StoreManagementController extends Controller
{
    /**
     * Return everything the UI needs to pre-fill the Shop Builder:
     * - existing store (if any) with relations
     * - available categories to pick
     */
    public function builderShow(Request $request)
    {
        $userId = Auth::id();

        $store = Store::with([
            'addresses' => fn($q) => $q->orderByDesc('is_main'),
            'banners',
            'categories:id,title',
            'socialLinks',
            'deliveryPricing'
        ])->where('user_id', $userId)->first();


        // For the picker
        $allCategories = Category::select('id', 'title')->orderBy('title')->get();

        // Shape a clean payload for the app
        return response()->json([
            'store' => $store ? [
                'id'             => $store->id,
                'store_name'     => $store->store_name,
                'store_email'    => $store->store_email,
                'store_phone'    => $store->store_phone,
                'store_location' => $store->store_location,
                'profile_image'  => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                'banner_image'   => $store->banner_image ?  asset('storage/' . $store->banner_image) : null,
                'theme_color'    => $store->theme_color,
                'categories'     => $store->categories->map(fn($c) => ['id' => $c->id, 'title' => $c->title]),
                'followers_count' => $store->followers_count,
                'total_sold'      => $store->total_sold,
                'average_rating'  => $store->average_rating,
                'banners'=>$store->banners,
                'social_links' => $store->socialLinks->map(function ($link) {
                    return [
                        'id' => $link->id,
                        'type' => $link->type,
                        'url' => $link->url,
                    ];
                }),
                'delivery_pricing' => $store->deliveryPricing->map(function ($pricing) {
                    return [
                        'id' => $pricing->id,
                        'state' => $pricing->state,
                        'local_government' => $pricing->local_government,
                        'variant' => $pricing->variant,
                        'price' => $pricing->price,
                        'is_free' => $pricing->is_free,
                    ];
                }),
                'address'        => optional($store->addresses->first(), function ($addr) {
                    return [
                        'state'            => $addr->state,
                        'local_government' => $addr->local_government,
                        'full_address'     => $addr->full_address,
                        'is_main'          => (bool) $addr->is_main,
                        'opening_hours'    => $addr->opening_hours, // array cast
                    ];
                }),
            ] : null,
            'all_categories' => $allCategories,
        ]);
    }

    /**
     * Create or update the store, its main address, and categories.
     * Accepts multipart/form-data for images.
     */
    public function builderUpsert(Request $request)
    {
        $userId = Auth::id();

        // Find existing store (if any)
        $existingStore = Store::where('user_id', $userId)->first();

        // Validation (email unique per stores table, ignoring current store if present)
        $validated = $request->validate([
            'store_name'     => ['required', 'string', 'max:190'],
            'store_email'    => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('stores', 'store_email')->ignore(optional($existingStore)->id)
            ],
            'store_phone'    => ['nullable', 'string', 'max:40'],
            'store_location' => ['nullable', 'string', 'max:190'],
            'theme_color'    => ['nullable', 'string', 'max:20'], // hex or token

            // images: optional
            'profile_image'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banner_image'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],

            // categories: array of IDs
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            // social links
            'social_links' => ['nullable', 'array'],
            'social_links.*.type' => ['required_with:social_links', 'string', 'in:facebook,twitter,instagram,linkedin,youtube,tiktok,website,whatsapp'],
            'social_links.*.url' => ['required_with:social_links', 'url', 'max:500'],

            // delivery pricing
            'delivery_pricing' => ['nullable', 'array'],
            'delivery_pricing.*.state' => ['required_with:delivery_pricing', 'string', 'max:100'],
            'delivery_pricing.*.local_government' => ['nullable', 'string', 'max:100'],
            'delivery_pricing.*.variant' => ['nullable', 'string', 'max:50'],
            'delivery_pricing.*.price' => ['required_with:delivery_pricing', 'numeric', 'min:0'],
            'delivery_pricing.*.is_free' => ['nullable', 'boolean'],

            // optional address block
            'address.state'             => ['nullable', 'string', 'max:190'],
            'address.local_government'  => ['nullable', 'string', 'max:190'],
            'address.full_address'      => ['nullable', 'string', 'max:500'],
            'address.is_main'           => ['nullable', 'boolean'],
            'address.opening_hours'     => ['nullable', 'array'],
        ]);

        // Files (if provided)
        $profilePath = null;
        $bannerPath  = null;

        if ($request->hasFile('profile_image')) {
            $profilePath = $request->file('profile_image')
                ->store('stores/profile_images', 'public');
        }
        if ($request->hasFile('banner_image')) {
            $bannerPath = $request->file('banner_image')
                ->store('stores/banner_images', 'public');
        }

        // Upsert inside a transaction
        $store = DB::transaction(function () use (
            $userId,
            $existingStore,
            $validated,
            $profilePath,
            $bannerPath
        ) {
            // Insert / Update Store
            $storeData = [
                'user_id'        => $userId,
                'store_name'     => $validated['store_name'],
                'store_email'    => $validated['store_email'] ?? null,
                'store_phone'    => $validated['store_phone'] ?? null,
                'store_location' => $validated['store_location'] ?? null,
                'theme_color'    => $validated['theme_color'] ?? null,
            ];

            if ($profilePath) {
                $storeData['profile_image'] = $profilePath;
            }
            if ($bannerPath) {
                $storeData['banner_image']  = $bannerPath;
            }

            if ($existingStore) {
                $existingStore->update($storeData);
                $store = $existingStore;
            } else {
                $store = Store::create($storeData);
            }

            // Sync categories (pivot)
            if (isset($validated['category_ids'])) {
                $store->categories()->sync($validated['category_ids']);
            }

            // Handle social links
            if (isset($validated['social_links'])) {
                // Delete existing social links
                $store->socialLinks()->delete();
                
                // Create new social links
                foreach ($validated['social_links'] as $link) {
                    $store->socialLinks()->create([
                        'type' => $link['type'],
                        'url' => $link['url'],
                    ]);
                }
            }

            // Handle delivery pricing
            if (isset($validated['delivery_pricing'])) {
                // Delete existing delivery pricing
                $store->deliveryPricing()->delete();
                
                // Create new delivery pricing
                foreach ($validated['delivery_pricing'] as $pricing) {
                    $store->deliveryPricing()->create([
                        'state' => $pricing['state'],
                        'local_government' => $pricing['local_government'] ?? null,
                        'variant' => $pricing['variant'] ?? null,
                        'price' => $pricing['price'],
                        'is_free' => $pricing['is_free'] ?? false,
                    ]);
                }
            }

            // Upsert MAIN address (if provided)
            if (isset($validated['address'])) {
                $addr = $validated['address'];

                /** @var StoreAddress|null $main */
                $main = StoreAddress::where('store_id', $store->id)
                    ->where('is_main', true)
                    ->first();

                $addrPayload = [
                    'store_id'         => $store->id,
                    'state'            => $addr['state'] ?? null,
                    'local_government' => $addr['local_government'] ?? null,
                    'full_address'     => $addr['full_address'] ?? null,
                    'is_main'          => isset($addr['is_main']) ? (bool)$addr['is_main'] : true,
                    'opening_hours'    => $addr['opening_hours'] ?? null,
                ];

                if ($main) {
                    $main->update($addrPayload);
                } else {
                    // ensure only one main
                    StoreAddress::where('store_id', $store->id)->update(['is_main' => false]);
                    StoreAddress::create($addrPayload);
                }
            }

            return $store->fresh([
                'addresses' => fn($q) => $q->orderByDesc('is_main'), 
                'categories:id,title',
                'socialLinks',
                'deliveryPricing'
            ]);
        });

        return response()->json([
            'message' => 'Store details saved successfully.',
            'store'   => [
                'id'             => $store->id,
                'store_name'     => $store->store_name,
                'store_email'    => $store->store_email,
                'store_phone'    => $store->store_phone,
                'store_location' => $store->store_location,
                'theme_color'    => $store->theme_color,
                'profile_image'  => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                'banner_image'   => $store->banner_image ?  asset('storage/' . $store->banner_image) : null,
                'categories'     => $store->categories->map(fn($c) => ['id' => $c->id, 'title' => $c->title]),
                'social_links' => $store->socialLinks->map(function ($link) {
                    return [
                        'id' => $link->id,
                        'type' => $link->type,
                        'url' => $link->url,
                    ];
                }),
                'delivery_pricing' => $store->deliveryPricing->map(function ($pricing) {
                    return [
                        'id' => $pricing->id,
                        'state' => $pricing->state,
                        'local_government' => $pricing->local_government,
                        'variant' => $pricing->variant,
                        'price' => $pricing->price,
                        'is_free' => $pricing->is_free,
                    ];
                }),
                'address'        => optional($store->addresses->first(), function ($addr) {
                    return [
                        'state'            => $addr->state,
                        'local_government' => $addr->local_government,
                        'full_address'     => $addr->full_address,
                        'is_main'          => (bool) $addr->is_main,
                        'opening_hours'    => $addr->opening_hours,
                    ];
                }),
            ],
        ], 201);
    }
}
