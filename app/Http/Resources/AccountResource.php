<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'initial_balance' => $this->initial_balance,
            'balance' => $this->balance,
            'formatted_balance' => number_format($this->balance, 0, ',', ' ') . ' FCFA',
            'color' => $this->color,
            'icon' => $this->icon,
            'is_default' => $this->is_default,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
