<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Aperçu de saisie en lot : rien n'est enregistré (module saisie-lot). */
final class PreviewNotesRequest extends FormRequest
{
    public const MAX_LINES = 300;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:20000', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && count(preg_split('/\R/u', trim($value)) ?: []) > self::MAX_LINES) {
                    $fail('NOTES_TOO_LONG : '.self::MAX_LINES.' lignes maximum. Collez-les en deux fois.');
                }
            }],
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
            'default_account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')
                ->where('user_id', $this->user()?->id)
                ->whereNull('deleted_at')],
        ];
    }

    public function notes(): string
    {
        return (string) $this->validated('notes');
    }

    public function referenceDate(): CarbonImmutable
    {
        $date = $this->validated('reference_date');

        return $date ? CarbonImmutable::parse($date) : CarbonImmutable::today();
    }

    public function defaultAccount(): ?Account
    {
        $id = $this->validated('default_account_id');

        return $id
            ? Account::whereKey($id)->where('user_id', $this->user()->id)->first()
            : Account::where('user_id', $this->user()->id)->orderByDesc('is_default')->orderBy('order_index')->first();
    }
}
