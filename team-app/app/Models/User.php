<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /** Permissions already read within one request (null = not read yet). */
    private ?array $permissionCache = null;

    private ?array $roleCache = null;

    private ?array $companyCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_members', 'user_id', 'company_id')
            ->withPivot('is_active', 'joined_on')->withTimestamps();
    }

    public function divisionMemberships(): HasMany
    {
        return $this->hasMany(DivisionMember::class, 'user_id');
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'user_id');
    }

    /** Role slugs of the user (super_admin, manager, member). */
    public function roleSlugs(): array
    {
        return $this->roleCache ??= DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->pluck('roles.slug')->all();
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roleSlugs(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** Permission slugs from roles -> role_permissions -> permissions (source SQL). */
    public function permissionSlugs(): array
    {
        return $this->permissionCache ??= DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $this->id)
            ->distinct()->pluck('permissions.slug')->all();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->isSuperAdmin() || in_array($slug, $this->permissionSlugs(), true);
    }

    /** Active user = has an active profile (user_profiles.is_active = 1). */
    public function isActive(): bool
    {
        return (bool) DB::table('user_profiles')->where('user_id', $this->id)->value('is_active');
    }

    /** IDs of the companies where the user is an active member. */
    public function activeCompanyIds(): array
    {
        return $this->companyCache ??= DB::table('company_members')->where('user_id', $this->id)->where('is_active', 1)->pluck('company_id')->all();
    }

    public function forgetPermissions(): void
    {
        $this->permissionCache = null;
        $this->roleCache = null;
        $this->companyCache = null;
    }

    public function initials(): string
    {
        $section = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return mb_strtoupper(mb_substr($section[0] ?? '?', 0, 1).mb_substr($section[1] ?? '', 0, 1));
    }
}
