<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Preference;
use App\Models\ChatMessage;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;



class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'region',
        'password_reset_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isClient()
    {
        return $this->role === 'user';
    }

    // ✅ Add relation
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // ✅ Add accessor for most recent active subscription
    public function getActiveSubscriptionAttribute()
    {
        return $this->subscriptions()
            ->with('plan') // eager load plan
            ->whereDate('end_date', '>=', now())
            ->latest('end_date')
            ->first();
    }

        public function country()
    {
        return $this->belongsTo(Country::class);
    }

        public function preference()
    {
        return $this->hasOne(Preference::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function tokenUsages()
    {
        return $this->hasMany(TokenUsage::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}