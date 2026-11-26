<?php

// Generated from project_team_management_laravel.sql (table `clients`).

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'clients';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    /** Companies that work with this client (table client_company, admin-app migration). None = general: every company may pick it. */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'client_company', 'client_id', 'company_id')->withTimestamps();
    }

    /** Clients a project of this company may use: the company's own clients plus the general ones. */
    public function scopeAvailableFor(Builder $query, int $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereDoesntHave('companies')
            ->orWhereHas('companies', fn (Builder $c) => $c->where('companies.id', $companyId)));
    }

    public function isAvailableFor(int $companyId): bool
    {
        $ids = $this->companies()->pluck('companies.id')->map(fn ($id) => (int) $id);

        return $ids->isEmpty() || $ids->contains($companyId);
    }
}
