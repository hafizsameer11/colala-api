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
        return $this->belongsTo(Store::class);
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
     * Check if user is a seller (has a store)
     */
    public function isSeller(): bool
    {
        return !is_null($this->store_id);
    }

    /**
     * Check if user owns a specific store
     */
    public function ownsStore(int $storeId): bool
    {
        return $this->store_id === $storeId;
    }

    /**
     * Get user's store ID
     */
    public function getStoreId(): ?int
    {
        return $this->store_id;
    }
}
