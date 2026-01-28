<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'beneficiary' => $this->beneficiary,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'frequency_label' => $this->frequency_label,
            'day_of_month' => $this->day_of_month,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'last_generated_date' => $this->last_generated_date?->format('Y-m-d'),
            'next_due_date' => $this->next_due_date?->format('Y-m-d'),
            'remaining_occurrences' => $this->remaining_occurrences,
            'is_active' => $this->is_active,
            'is_due' => $this->is_due,
            'days_until_due' => $this->days_until_due,
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
