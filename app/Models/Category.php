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

    /** Catégories système des opérations d'écart de rapprochement (migration 2026_11_11_000003). */
    public const RECONCILIATION_EXPENSE_ID = '0b9a3c52-7e1d-4f6a-9c2e-5d8f1a7b3e01';

    public const RECONCILIATION_INCOME_ID = '0b9a3c52-7e1d-4f6a-9c2e-5d8f1a7b3e02';

    public const RECONCILIATION_IDS = [self::RECONCILIATION_EXPENSE_ID, self::RECONCILIATION_INCOME_ID];

    /** Catégorie système des frais bancaires et mobile money (migration 2026_11_12_000002). */
    public const BANK_FEES_ID = '0b9a3c52-7e1d-4f6a-9c2e-5d8f1a7b3e03';

    /** Nature (module revenus-transit) : transit = hors revenus, dépenses et plafonds ; pro = compté, mais à part du budget perso. */
    public const NATURE_REGULAR = 'regular';

    public const NATURE_TRANSIT = 'transit';

    public const NATURE_PRO = 'pro';

    public const NATURES = [self::NATURE_REGULAR, self::NATURE_TRANSIT, self::NATURE_PRO];

    protected $fillable = [
        'name',
        'type',
        'nature',
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
        if (! $this->budget_limit || $this->budget_limit === 0) {
            return 0;
        }
        $spent = $this->spent_this_month ?? 0;

        return min(100, ($spent / $this->budget_limit) * 100);
    }
}
