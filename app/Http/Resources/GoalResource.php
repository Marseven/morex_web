<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'target_amount' => $this->target_amount,
            'formatted_target' => number_format($this->target_amount, 0, ',', ' ') . ' FCFA',
            'current_amount' => $this->current_amount,
            'formatted_current' => number_format($this->current_amount, 0, ',', ' ') . ' FCFA',
            'remaining_amount' => $this->remaining_amount,
            'formatted_remaining' => number_format($this->remaining_amount, 0, ',', ' ') . ' FCFA',
            'progress_percentage' => round($this->progress_percentage, 1),
            'target_date' => $this->target_date?->format('Y-m-d'),
            'days_remaining' => $this->days_remaining,
            'status' => $this->status,
            'is_completed' => $this->is_completed,
            'color' => $this->color,
            'icon' => $this->icon,
            'account' => new AccountResource($this->whenLoaded('account')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
