<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'role',
        'permissions',
        'is_active',
        'invited_at',
        'joined_at',
        'invited_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role && $this->is_active;
    }

    /**
     * Get role-based permissions
     */
    public function getRolePermissions(): array
    {
        $rolePermissions = [
            'admin' => [
                'manage_products',
                'manage_orders',
                'manage_customers',
                'manage_analytics',
                'manage_settings',
                'manage_users',
                'manage_inventory',
                'manage_promotions',
            ],
            'manager' => [
                'manage_products',
                'manage_orders',
                'manage_customers',
                'manage_analytics',
                'manage_inventory',
                'manage_promotions',
            ],
            'staff' => [
                'manage_orders',
                'manage_customers',
                'view_analytics',
            ],
        ];

        return $rolePermissions[$this->role] ?? [];
    }

    /**
     * Get all permissions (role + custom)
     */
    public function getAllPermissions(): array
    {
        $rolePermissions = $this->getRolePermissions();
        $customPermissions = $this->permissions ?? [];
        
        return array_unique(array_merge($rolePermissions, $customPermissions));
    }

    /**
     * Check if user can access store
     */
    public function canAccessStore(): bool
    {
        return $this->is_active && $this->joined_at !== null;
    }
}
