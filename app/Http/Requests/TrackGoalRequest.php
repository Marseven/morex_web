<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Lier un objectif à un compte (module suivi-objectifs 5a). */
final class TrackGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('goal')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $this->user()?->id)->whereNull('deleted_at')],
            'start_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'monthly_target' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }
}
