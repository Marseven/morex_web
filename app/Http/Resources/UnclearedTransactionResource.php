<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Opération à pointer : le montant signé sert au calcul d'écart côté client (RM-5). */
final class UnclearedTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'type' => $this->type,
            'amount' => $this->amount,
            'signed_amount' => $this->type === 'income' ? $this->amount : -$this->amount,
            'beneficiary' => $this->beneficiary,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
        ];
    }
}
