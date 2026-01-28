<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'icon' => $this->icon,
            'color' => $this->color,
            'parent_id' => $this->parent_id,
            'order_index' => $this->order_index,
            'is_system' => $this->is_system,
            'budget_limit' => $this->budget_limit,
            'formatted_budget' => $this->budget_limit
                ? number_format($this->budget_limit, 0, ',', ' ') . ' FCFA'
                : null,
            'spent_this_month' => $this->spent_this_month,
            'budget_progress' => $this->budget_progress,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
