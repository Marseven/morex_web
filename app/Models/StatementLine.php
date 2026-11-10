<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ligne d'un relevé de compte, rapprochée d'une opération saisie (module releves-sms). */
class StatementLine extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_CREATED = 'created';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'account_id',
        'date',
        'amount',
        'fee',
        'label',
        'external_ref',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'fee' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
