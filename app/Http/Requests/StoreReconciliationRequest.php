<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'statement_balance' => ['required', 'integer', 'between:-1000000000000,1000000000000'],
            'cleared_ids' => ['sometimes', 'array', 'max:1000'],
            'cleared_ids.*' => ['bail', 'string', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function statementBalance(): int
    {
        return (int) $this->validated('statement_balance');
    }

    /** @return list<string> */
    public function clearedIds(): array
    {
        return array_values($this->validated('cleared_ids', []));
    }

    public function note(): ?string
    {
        $note = trim(strip_tags((string) $this->validated('note', '')));

        return $note === '' ? null : $note;
    }
}
