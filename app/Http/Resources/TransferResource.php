<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_account' => new AccountResource($this->whenLoaded('fromAccount')),
            'to_account' => new AccountResource($this->whenLoaded('toAccount')),
            'from_account_id' => $this->from_account_id,
            'to_account_id' => $this->to_account_id,
            'amount' => $this->amount,
            'formatted_amount' => number_format($this->amount, 0, ',', ' ') . ' FCFA',
            'description' => $this->description,
            'date' => $this->date->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
