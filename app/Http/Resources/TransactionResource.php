<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'signed_amount' => $this->signed_amount,
            'type' => $this->type,
            'beneficiary' => $this->beneficiary,
            'description' => $this->description,
            'date' => $this->date->format('Y-m-d'),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'account' => new AccountResource($this->whenLoaded('account')),
            'transfer_to_account' => new AccountResource($this->whenLoaded('transferToAccount')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
