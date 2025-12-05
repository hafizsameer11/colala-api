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
        'plan',
        'is_free_trial_claimed',
        'expo_push_token',
        'visibility'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected static function booted()
{
    static::addGlobalScope('visible', function ($query) {
        $query->where('visibility', 1);
    });
}

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
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

    /**
     * Check if user is currently online
     * User is considered online if last_seen_at is within the last 5 minutes
     */
    public function isOnline(int $onlineThresholdMinutes = 5): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInMinutes(now()) <= $onlineThresholdMinutes;
    }

    /**
     * Get formatted last seen time
     */
    public function getLastSeenFormatted(): ?string
    {
        if (!$this->last_seen_at) {
            return null;
        }

        $diffInMinutes = $this->last_seen_at->diffInMinutes(now());

        if ($diffInMinutes < 1) {
            return 'Just now';
        } elseif ($diffInMinutes < 60) {
            return $diffInMinutes . ' minutes ago';
        } elseif ($diffInMinutes < 1440) { // Less than 24 hours
            $hours = floor($diffInMinutes / 60);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diffInMinutes < 10080) { // Less than 7 days
            $days = floor($diffInMinutes / 1440);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return $this->last_seen_at->format('d M Y, h:i A');
        }
    }

    /**
     * Get online status with formatted last seen
     */
    public function getOnlineStatus(int $onlineThresholdMinutes = 5): array
    {
        return [
            'is_online' => $this->isOnline($onlineThresholdMinutes),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'last_seen_formatted' => $this->getLastSeenFormatted(),
        ];
    }
}
