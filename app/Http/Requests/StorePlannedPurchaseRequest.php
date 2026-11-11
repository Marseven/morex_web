<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\OwnedOrSystemCategory;
use Illuminate\Foundation\Http\FormRequest;

/** Prévoir un achat (module achats-prevus 5d), pour ce cycle ou le suivant. */
final class StorePlannedPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'estimated_amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'category_id' => ['nullable', 'uuid', new OwnedOrSystemCategory((int) $this->user()?->id)],
            'when' => ['required', 'in:current,next'],
        ];
    }
}
