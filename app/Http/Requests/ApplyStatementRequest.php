<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use App\Rules\OwnedOrSystemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation d'un relevé : une décision par ligne (rapprocher, créer, ignorer). */
final class ApplyStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:500'],
            'lines.*.date' => ['required', 'date_format:Y-m-d'],
            'lines.*.amount' => ['required', 'integer', 'not_in:0', 'between:-999999999999,999999999999'],
            'lines.*.fee' => ['required', 'integer', 'min:0', 'max:999999999'],
            'lines.*.label' => ['nullable', 'string', 'max:255'],
            'lines.*.reference' => ['nullable', 'string', 'max:64'],
            'lines.*.action' => ['required', Rule::in(['match', 'create', 'ignore'])],
            'lines.*.transaction_id' => ['required_if:lines.*.action,match', 'nullable', 'uuid'],
            'lines.*.category_id' => ['required_if:lines.*.action,create', 'nullable', 'uuid',
                Rule::notIn([...Category::RECONCILIATION_IDS, Category::BANK_FEES_ID]),
                new OwnedOrSystemCategory($this->user()->id)],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        return array_values($this->validated('lines'));
    }
}
