<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'user_name',
        'phone',
        'country',
        'state',
        'profile_picture',
        'user_code',
        'referral_code',
        'otp',
        'otp_verified',
        'role',
        'is_active',
        'store_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ✅ Soft delete timestamp column
    protected $dates = ['deleted_at'];

    // ----------------- Relationships -----------------
    public function store()
    {
        return $this->hasOne(Store::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referral_code', 'user_code');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referral_code', 'user_code');
    }

    public function referralEarning()
    {
        return $this->hasOne(ReferralEarning::class);
    }

    public function storeUsers()
    {
        return $this->hasMany(StoreUser::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_users')
            ->withPivot(['role', 'permissions', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function hasStoreAccess(int $storeId): bool
    {
        return $this->storeUsers()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->exists();
    }

    public function getStoreRole(int $storeId): ?string
    {
        $storeUser = $this->storeUsers()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        return $storeUser?->role;
    }

    public function hasStorePermission(int $storeId, string $permission): bool
    {
        $storeUser = $this->storeUsers()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        if (!$storeUser) {
            return false;
        }

        return $storeUser->hasPermission($permission);
    }

    public function userActivities()
    {
        return $this->hasMany(UserActivity::class);
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }
}
