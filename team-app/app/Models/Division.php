<?php

// Dibuat dari project_team_management_laravel.sql (tabel `divisions`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $table = 'divisions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(DivisionMember::class, 'division_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'division_members', 'division_id', 'user_id')->withTimestamps();
    }
}
