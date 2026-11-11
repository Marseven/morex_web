<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reconciliation */
final class ReconciliationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'statement_balance' => $this->statement_balance,
            'balance_before' => $this->balance_before,
            'difference' => $this->difference,
            'adjustment_transaction_id' => $this->adjustment_transaction_id,
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'note' => $this->note,
            'account_balance' => $this->whenLoaded('account', fn () => $this->account->balance),
        ];
    }
}
