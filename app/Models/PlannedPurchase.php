<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Achat prévu (module achats-prevus 5d). */
class PlannedPurchase extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_BOUGHT = 'bought';

    public const STATUS_CANCELLED = 'cancelled';

    /** user_id, statut et lien sont posés par le service, jamais par une saisie. */
    protected $fillable = ['name', 'estimated_amount', 'category_id', 'planned_for'];

    protected function casts(): array
    {
        return [
            'estimated_amount' => 'integer',
            'planned_for' => 'date',
            'unplanned' => 'boolean',
            'bought_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
