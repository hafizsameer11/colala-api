<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all permissions for this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Get all users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_roles');
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }

    /**
     * Assign a permission to this role
     */
    public function assignPermission(int $permissionId): void
    {
        if (!$this->permissions()->where('permission_id', $permissionId)->exists()) {
            $this->permissions()->attach($permissionId);
        }
    }

    /**
     * Revoke a permission from this role
     */
    public function revokePermission(int $permissionId): void
    {
        $this->permissions()->detach($permissionId);
    }

    /**
     * Scope to get only active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

