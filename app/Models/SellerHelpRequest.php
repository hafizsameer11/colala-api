<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerHelpRequest extends Model
{
    protected $fillable = [
        'service_type',
        'fee',
        'notes',
        'email',
        'phone',
        'full_name',
        'status',
        'assigned_to',
        'admin_notes',
        'completed_at'
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
