<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;   // add
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class User extends Authenticatable implements AuditableContract, MustVerifyEmail   // change
{
    use Auditable;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'subscription_tier',
        'profile_image',
        'phone',
        'default_address_line_1',
        'default_address_line_2',
        'default_city',
        'default_province',
        'default_postal_code',
        'default_country',
        'is_active',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected array $auditExclude = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function twoFactorSecret()
    {
        return $this->hasOne(TwoFactorSecret::class);
    }

    public function twoFactorChallenges()
    {
        return $this->hasMany(TwoFactorChallenge::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AIConversation::class);
    }

    public function aiMessages()
    {
        return $this->hasMany(AIMessage::class);
    }

    public function aiUsageLogs()
    {
        return $this->hasMany(AIUsageLog::class);
    }

    public function aiAuditEvents()
    {
        return $this->hasMany(AIAuditEvent::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isPremium(): bool
    {
        return $this->subscription_tier === 'premium';
    }
}
