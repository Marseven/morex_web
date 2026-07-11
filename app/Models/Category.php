<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'icon',
        'color',
        'parent_id',
        'order_index',
        'budget_limit',
    ];

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'is_system' => 'boolean',
            'budget_limit' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Montant dépensé sur la catégorie ce mois-ci.
     *
     * Si une requête l'a déjà calculé (withSum aliasé dans CategoryController, sur les dates
     * du cycle budgétaire actif), on réutilise cette valeur. Sinon on calcule le mois calendaire
     * courant à la volée — ainsi le modèle est utilisable seul (ex. tests, accès direct).
     */
    public function getSpentThisMonthAttribute($value): int
    {
        if ($value !== null) {
            return (int) $value;
        }

        return (int) $this->transactions()
            ->where('type', 'expense')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');
    }

    public function getBudgetProgressAttribute(): float
    {
        if (!$this->budget_limit || $this->budget_limit === 0) {
            return 0;
        }
        $spent = $this->spent_this_month ?? 0;
        return min(100, ($spent / $this->budget_limit) * 100);
    }
}
