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
        'user_id',
        'name',
        'type',
        'icon',
        'color',
        'parent_id',
        'order_index',
        'is_system',
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

    public function getSpentThisMonthAttribute(): int
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
    }

    public function getBudgetProgressAttribute(): float
    {
        if (!$this->budget_limit || $this->budget_limit === 0) {
            return 0;
        }
        return min(100, ($this->spent_this_month / $this->budget_limit) * 100);
    }
}
