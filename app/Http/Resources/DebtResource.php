<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'initial_amount' => $this->initial_amount,
            'current_amount' => $this->current_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'progress_percentage' => round($this->progress_percentage, 1),
            'formatted_initial_amount' => number_format($this->initial_amount, 0, ',', ' ') . ' FCFA',
            'formatted_current_amount' => number_format($this->current_amount, 0, ',', ' ') . ' FCFA',
            'due_date' => $this->due_date?->toDateString(),
            'days_until_due' => $this->days_until_due,
            'is_overdue' => $this->is_overdue,
            'description' => $this->description,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
