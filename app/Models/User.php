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
        'is_disabled',
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
            'is_disabled' => 'boolean',
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

    // ----------------- RBAC Relationships -----------------
    /**
     * Get all admin role assignments for this user
     */
    public function adminRoles()
    {
        return $this->hasMany(AdminRole::class);
    }

    /**
     * Get all roles assigned to this user
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'admin_roles')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()
            ->where('slug', $roleSlug)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()
            ->whereIn('slug', $roleSlugs)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissionSlugs): bool
    {
        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', function ($query) use ($permissionSlugs) {
                $query->whereIn('slug', $permissionSlugs);
            })
            ->exists();
    }

    /**
     * Get all permissions for this user (from all their roles)
     */
    public function getAllPermissions(): array
    {
        return $this->roles()
            ->where('is_active', true)
            ->with('permissions')
            ->get()
            ->flatMap(function ($role) {
                return $role->permissions;
            })
            ->unique('id')
            ->pluck('slug')
            ->toArray();
    }

    /**
     * Get all stores assigned to this user as Account Officer
     */
    public function assignedVendors()
    {
        return $this->hasMany(Store::class, 'account_officer_id');
    }

    /**
     * Scope to get only admin users
     */
    public function scopeAdmins($query)
    {
        // Get all active admin role slugs from the roles table dynamically
        $adminRoles = Role::active()->pluck('slug')->toArray();

        return $query->whereIn('role', $adminRoles)
            ->orWhereHas('roles');
    }

    /**
     * Scope a query to only include users with account_officer role
     */
    public function scopeAccountOfficers($query)
    {
        return $query->whereHas('roles', function($q) {
            $q->where('slug', 'account_officer');
        });
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
