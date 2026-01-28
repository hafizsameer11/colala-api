<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * Get all roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * Scope to filter by module
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}

