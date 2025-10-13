<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
        'password',
        'role',
        'store_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
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
        return $this->hasMany(Referral::class);
    }

    public function referralEarning()
    {
        return $this->hasOne(ReferralEarning::class);
    }

    /**
     * Get user's store relationships
     */
    public function storeUsers()
    {
        return $this->hasMany(StoreUser::class);
    }

    /**
     * Get stores where user is a member
     */
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_users')
            ->withPivot(['role', 'permissions', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Check if user has access to a store
     */
    public function hasStoreAccess(int $storeId): bool
    {
        return $this->storeUsers()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->exists();
    }

    /**
     * Get user's role in a specific store
     */
    public function getStoreRole(int $storeId): ?string
    {
        $storeUser = $this->storeUsers()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        return $storeUser?->role;
    }

    /**
     * Check if user has permission in a store
     */
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
}
