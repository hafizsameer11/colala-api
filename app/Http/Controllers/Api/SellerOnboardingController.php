<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerStartRequest;
use App\Http\Requests\Level1ProfileMediaRequest;
use App\Http\Requests\Level1CategoriesSocialRequest;
use App\Http\Requests\Level2BusinessDetailsRequest;
use App\Http\Requests\Level2DocumentsRequest;
use App\Http\Requests\Level3PhysicalStoreRequest;
use App\Http\Requests\Level3UtilityBillRequest;
use App\Http\Requests\Level3AddAddressRequest;
use App\Http\Requests\Level3AddDeliveryRequest;
use App\Http\Requests\Level3ThemeRequest;
use App\Models\{Post, Product, Service, User, Store, StoreSocialLink, StoreBusinessDetail, StoreAddress, StoreDeliveryPricing, StoreOnboardingStep, StoreReview};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;

class SellerOnboardingController extends Controller
{
    /* ---------------- Helpers ---------------- */

    private function markDone(Store $store, int $level, string $key): void
    {
        StoreOnboardingStep::updateOrCreate(
            ['store_id'=>$store->id, 'key'=>$key],
            ['level'=>$level, 'status'=>'done', 'completed_at'=>now()]
        );

        $total = StoreOnboardingStep::where('store_id',$store->id)->count();
        $done  = StoreOnboardingStep::where('store_id',$store->id)->where('status','done')->count();
        $percent = $total ? (int) floor($done * 100 / $total) : $store->onboarding_percent;

        $store->update([
            'onboarding_level'   => max($store->onboarding_level, $level),
            'onboarding_percent' => $percent,
        ]);
    }

    private function ok(Store $store, string $message, array $extra = [])
    {
        return response()->json([
            'status'   => true,
            'message'  => $message,
            'progress' => [
                'level'   => $store->onboarding_level,
                'percent' => $store->onboarding_percent,
                'status'  => $store->onboarding_status,
            ],
            ...$extra
        ]);
    }

    /* ---------------- Level 1.1 – PUBLIC start ---------------- */
    public function start(SellerStartRequest $request)
    {

        $user = User::create([
            'full_name' => $request->store_name,
            'email'     => $request->store_email,
            'phone'     => $request->store_phone,
            'password'  => Hash::make($request->password),
            'role'      => 'seller',
        ]);

        $store = Store::create([
            'user_id'        => $user->id,
            'store_name'     => $request->store_name,
            'store_email'    => $request->store_email,
            'store_phone'    => $request->store_phone,
            'store_location' => $request->store_location,
            'referral_code'  => $request->referral_code,
        ]);

        if ($request->hasFile('profile_image')) {
            $store->profile_image = $request->file('profile_image')->store("stores/{$store->id}", 'public');
        }
        if ($request->hasFile('banner_image')) {
            $store->banner_image  = $request->file('banner_image')->store("stores/{$store->id}", 'public');
        }
        $store->save();

        if ($request->filled('categories')) {
            $store->categories()->sync($request->categories);
        }
        if ($request->filled('social_links')) {
            foreach ($request->social_links as $link) {
                StoreSocialLink::create([
                    'store_id' => $store->id,
                    'type'     => $link['type'],
                    'url'      => $link['url'],
                ]);
            }
        }

        // Create placeholders for all steps so percent works out-of-box
        $stepKeys = [
            ['level'=>1,'key'=>'level1.basic'],
            ['level'=>1,'key'=>'level1.profile_media'],
            ['level'=>1,'key'=>'level1.categories_social'],
            ['level'=>2,'key'=>'level2.business_details'],
            ['level'=>2,'key'=>'level2.documents'],
            ['level'=>3,'key'=>'level3.physical_store'],
            ['level'=>3,'key'=>'level3.utility_bill'],
            ['level'=>3,'key'=>'level3.addresses'],
            ['level'=>3,'key'=>'level3.delivery_pricing'],
            ['level'=>3,'key'=>'level3.theme'],
        ];
        foreach ($stepKeys as $sk) {
            StoreOnboardingStep::firstOrCreate(['store_id'=>$store->id,'key'=>$sk['key']], $sk);
        }

        $this->markDone($store, 1, 'level1.basic');
        $token=$user->createToken('auth_token')->plainTextToken;

        return $this->ok($store, 'Level 1.1 saved', ['store_id'=>$store->id,
'token'=>$token]);
    }

    /* ---------------- Level 1.2 ---------------- */
    public function level1ProfileMedia(Level1ProfileMediaRequest $request)
    {
        $store = $request->user()->store;

        if ($request->hasFile('profile_image')) {
            $store->profile_image = $request->file('profile_image')->store("stores/{$store->id}", 'public');
        }
        if ($request->hasFile('banner_image')) {
            $store->banner_image  = $request->file('banner_image')->store("stores/{$store->id}", 'public');
        }
        $store->save();

        $this->markDone($store, 1, 'level1.profile_media');
        return $this->ok($store, 'Level 1.2 saved');
    }

    /* ---------------- Level 1.3 ---------------- */
    public function level1Categories(Level1CategoriesSocialRequest $request)
    {
        $store = $request->user()->store;

        if ($request->filled('categories')) {
            $store->categories()->sync($request->categories);
        }
        if ($request->filled('social_links')) {
            StoreSocialLink::where('store_id', $store->id)->delete();
            foreach ($request->social_links as $link) {
                StoreSocialLink::create([
                    'store_id' => $store->id,
                    'type'     => $link['type'],
                    'url'      => $link['url'],
                ]);
            }
        }

        $this->markDone($store, 1, 'level1.categories_social');
        return $this->ok($store, 'Level 1 completed');
    }

    /* ---------------- Level 2.1 ---------------- */
    public function level2Business(Level2BusinessDetailsRequest $request)
    {
        $store = $request->user()->store;
        $data  = $request->validated();

        StoreBusinessDetail::updateOrCreate(['store_id'=>$store->id], $data);

        $this->markDone($store, 2, 'level2.business_details');
        return $this->ok($store, 'Level 2.1 saved');
    }

    /* ---------------- Level 2.2 ---------------- */
    public function level2Documents(Level2DocumentsRequest $request)
    {
        $store = $request->user()->store;
        $payload = [];

        foreach (['nin_document','cac_document','utility_bill','store_video'] as $f) {
            if ($request->hasFile($f)) {
                $payload[$f] = $request->file($f)->store("stores/{$store->id}", 'public');
            }
        }

        if ($payload) {
            StoreBusinessDetail::updateOrCreate(['store_id'=>$store->id], $payload);
        }

        $this->markDone($store, 2, 'level2.documents');
        return $this->ok($store, 'Level 2 completed');
    }

    /* ---------------- Level 3.1 ---------------- */
    public function level3Physical(Level3PhysicalStoreRequest $request)
    {
        $store = $request->user()->store;

        $data = ['has_physical_store' => (bool)$request->has_physical_store];
        if ($request->hasFile('store_video')) {
            $data['store_video'] = $request->file('store_video')->store("stores/{$store->id}", 'public');
        }

        StoreBusinessDetail::updateOrCreate(['store_id'=>$store->id], $data);

        $this->markDone($store, 3, 'level3.physical_store');
        return $this->ok($store, 'Level 3.1 saved');
    }

    /* ---------------- Level 3.2 ---------------- */
    public function level3Utility(Level3UtilityBillRequest $request)
    {
        $store = $request->user()->store;

        $path = $request->file('utility_bill')->store("stores/{$store->id}", 'public');
        StoreBusinessDetail::updateOrCreate(['store_id'=>$store->id], ['utility_bill'=>$path]);

        $this->markDone($store, 3, 'level3.utility_bill');
        return $this->ok($store, 'Level 3.2 saved');
    }

    /* ---------------- Level 3.3 (CRUD) ---------------- */
   public function addAddress(Level3AddAddressRequest $request)
{
    // Get the authenticated user's store
    $store = $request->user()->store;

    // Create a new store address with the validated data
    StoreAddress::create([
        'store_id'         => $store->id,
        'state'            => $request->input('state'),
        'local_government' => $request->input('local_government'),
        'full_address'     => $request->input('full_address'),
        'is_main'          => $request->boolean('is_main'),
        'opening_hours'    => $request->input('opening_hours', []),
    ]);

    // Mark onboarding step as done 
    $this->markDone($store, 3, 'level3.addresses');

    return $this->ok($store, 'Address added successfully.');
}


    public function deleteAddress($id, Request $request)
    {
        $store = $request->user()->store;
        StoreAddress::where('store_id',$store->id)->where('id',$id)->delete();
        return $this->ok($store, 'Address deleted');
    }

    /* ---------------- Level 3.4 (CRUD) ---------------- */
    public function addDelivery(Level3AddDeliveryRequest $request)
    {
        $store = $request->user()->store;

        StoreDeliveryPricing::create([
            'store_id'         => $store->id,
            'state'            => $request->state,
            'local_government' => $request->local_government,
            'variant'          => $request->variant,
            'price'            => $request->price,
            'is_free'          => (bool)$request->is_free,
        ]);

        $this->markDone($store, 3, 'level3.delivery_pricing');
        return $this->ok($store, 'Delivery price added');
    }

    public function deleteDelivery($id, Request $request)
    {
        $store = $request->user()->store;
        StoreDeliveryPricing::where('store_id',$store->id)->where('id',$id)->delete();
        return $this->ok($store, 'Delivery price deleted');
    }

    /* ---------------- Level 3.5 ---------------- */
    public function level3Theme(Level3ThemeRequest $request)
    {
        $store = $request->user()->store;
        $store->update(['theme_color' => $request->theme_color]);

        $this->markDone($store, 3, 'level3.theme');
        return $this->ok($store, 'Level 3 completed');
    }

    /* ---------------- Progress + Submit ---------------- */
    public function progress(Request $request)
    {
        $store = $request->user()->store;
        $steps = StoreOnboardingStep::where('store_id',$store->id)
                ->orderBy('level')->get(['key','status','completed_at']);
        return response()->json([
            'status'  => true,
            'level'   => $store->onboarding_level,
            'percent' => $store->onboarding_percent,
            'status_label' => $store->onboarding_status,
            'steps'   => $steps,
        ]);
    }

    public function submitForReview(Request $request)
    {
        $store = $request->user()->store;
        $store->update(['onboarding_status' => 'pending_review']);
        return $this->ok($store, 'Submitted for review');
    }



private function url(?string $path) {
    return $path ? Storage::url($path) : null;
}

/** One-shot summary for dashboard/profile screens */
public function overview(Request $req)
{
    $store = $req->user()->store()->with([
        'businessDetails',
        'addresses',
        'deliveryPricing',
        'socialLinks',
        'categories:id,title,image',
        'banners'
    ])->firstOrFail();
        $products=Product::where('store_id',$store->id)->with('images','reviews')->get();
        $posts=Post::where('user_id',$store->user_id)->latest()->get();
        $services=Service::where('store_id',$store->id)->with('media')->get();
        $storeReveiws=StoreReview::where('store_id',$store->id)->with('user')->get();
    return response()->json([
        'status' => true,
        'store'  => [
            'id'            => $store->id,
            'name'          => $store->store_name,
            'email'         => $store->store_email,
            'phone'         => $store->store_phone,
            'location'      => $store->store_location,
            'theme_color'   => $store->theme_color,
            'profile_image' => $this->url($store->profile_image),
            'banner_image'  => $this->url($store->banner_image),
            'permotaional_banners'=>$store->banners,
            'categories'    => $store->categories->map(fn($c)=>[
                                'id'=>$c->id,'title'=>$c->title,'image_url'=>$this->url($c->image)
                              ]),
                                  // ✅ Add stats
            'followers_count' => $store->followers_count,
            'total_sold'      => $store->total_sold,
            'average_rating'  => $store->average_rating,
            'social_links'  => $store->socialLinks->map->only(['id','type','url']),
            'products'=>$products,
            'posts'=>$posts,
            'services'=>$services,
            'storeReveiws'=>$storeReveiws
        ],
        'business' => optional($store->businessDetails)->only([
            'registered_name','business_type','nin_number','bn_number','cac_number','has_physical_store'
        ]) + [
            'nin_document_url' => $this->url(optional($store->businessDetails)->nin_document),
            'cac_document_url' => $this->url(optional($store->businessDetails)->cac_document),
            'utility_bill_url' => $this->url(optional($store->businessDetails)->utility_bill),
            'store_video_url'  => $this->url(optional($store->businessDetails)->store_video),
        ],
        'addresses' => $store->addresses->map->only(['id','state','local_government','full_address','is_main','opening_hours']),
        'delivery'  => $store->deliveryPricing->map->only(['id','state','local_government','variant','price','is_free']),
        'progress'  => [
            'level'   => $store->onboarding_level,
            'percent' => $store->onboarding_percent,
            'status'  => $store->onboarding_status,
        ],
    ]);
}

/** Level 1 edit screen: images + categories + socials */
public function level1Data(Request $req)
{
    $store = $req->user()->store()->with(['socialLinks','categories:id'])->firstOrFail();

    return response()->json([
        'status'=>true,
        'profile_image' => $this->url($store->profile_image),
        'banner_image'  => $this->url($store->banner_image),
        'selected_category_ids' => $store->categories->pluck('id'),
        'social_links'  => $store->socialLinks->map->only(['id','type','url']),
    ]);
}

/** Level 2 edit screen: business text + doc urls */
public function level2Data(Request $req)
{
    $store = $req->user()->store()->with('businessDetails')->firstOrFail();
    $b = $store->businessDetails;

    return response()->json([
        'status'=>true,
        'business' => [
            'registered_name' => optional($b)->registered_name,
            'business_type'   => optional($b)->business_type,
            'nin_number'      => optional($b)->nin_number,
            'bn_number'       => optional($b)->bn_number,
            'cac_number'      => optional($b)->cac_number,
            'nin_document_url' => $this->url(optional($b)->nin_document),
            'cac_document_url' => $this->url(optional($b)->cac_document),
        ],
    ]);
}

/** Level 3 edit screen: physical store flag/video + utility + lists + theme */
public function level3Data(Request $req)
{
    $store = $req->user()->store()->with(['businessDetails','addresses','deliveryPricing'])->firstOrFail();
    $b = $store->businessDetails;

    return response()->json([
        'status'=>true,
        'physical_store' => [
            'has_physical_store' => (bool) optional($b)->has_physical_store,
            'store_video_url'    => $this->url(optional($b)->store_video),
            'utility_bill_url'   => $this->url(optional($b)->utility_bill),
        ],
        'addresses' => $store->addresses->map->only(['id','state','local_government','full_address','is_main','opening_hours']),
        'delivery'  => $store->deliveryPricing->map->only(['id','state','local_government','variant','price','is_free']),
        'theme_color' => $store->theme_color,
    ]);
}

/** Lists for modals/pickers */
public function listAddresses(Request $req)
{
    $store = $req->user()->store;
    return response()->json([
        'status'=>true,
        'items'=>$store->addresses()->orderByDesc('is_main')->get(['id','state','local_government','full_address','is_main','opening_hours'])
    ]);
}

public function listDelivery(Request $req)
{
    $store = $req->user()->store;
    return response()->json([
        'status'=>true,
        'items'=>$store->deliveryPricing()->get(['id','state','local_government','variant','price','is_free'])
    ]);
}

public function listSocialLinks(Request $req)
{
    $store = $req->user()->store;
    return response()->json([
        'status'=>true,
        'items'=>$store->socialLinks()->get(['id','type','url'])
    ]);
}

public function listSelectedCategories(Request $req)
{
    $store = $req->user()->store()->with('categories:id,title')->firstOrFail();
    return response()->json([
        'status'=>true,
        'selected'=>$store->categories->map->only(['id','title'])
    ]);
}

public function listAllCategories()
{
    $all = Category::query()->get(['id','title','image']);
    return response()->json([
        'status'=>true,
        'items'=>$all->map(fn($c)=>[
            'id'=>$c->id,
            'title'=>$c->title,
            'image_url'=>$this->url($c->image),
        ])
    ]);
}
}
