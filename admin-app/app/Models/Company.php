<?php

// Generated from project_team_management_laravel.sql (table `companies`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class, 'company_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'company_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'company_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMember::class, 'company_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_members', 'company_id', 'user_id')->withTimestamps();
    }
}
