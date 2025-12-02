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
        'is_active',
        'last_login_at',
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
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
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

    // RBAC Relationships
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    public function adminActivityLogs()
    {
        return $this->hasMany(AdminActivityLog::class, 'admin_id');
    }

    // RBAC Methods
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('is_super_admin', true)->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check direct permissions
        if ($this->permissions()->where('slug', $permissionSlug)->exists()) {
            return true;
        }

        // Check permissions via roles
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check direct permissions
        if ($this->permissions()->whereIn('slug', $permissionSlugs)->exists()) {
            return true;
        }

        // Check permissions via roles
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlugs) {
                $query->whereIn('slug', $permissionSlugs);
            })
            ->exists();
    }

    public function getAllPermissions()
    {
        if ($this->isSuperAdmin()) {
            return Permission::all();
        }

        // Get permissions from roles
        $rolePermissions = $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');

        // Get direct permissions
        $directPermissions = $this->permissions;

        // Merge and return unique permissions
        return $rolePermissions->merge($directPermissions)->unique('id');
    }

    public function canManageAdmins(): bool
    {
        return $this->hasPermission('admins.manage_roles') || $this->isSuperAdmin();
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isAdmin(): bool
    {
        // Check if user has any admin role or admin permission
        return $this->roles()->exists() || 
               $this->permissions()->exists() || 
               $this->role === 'admin' ||
               $this->isSuperAdmin();
    }
    

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}