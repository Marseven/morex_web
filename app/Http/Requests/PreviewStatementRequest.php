<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Aperçu d'un relevé collé : lecture + correspondance, rien n'est enregistré. */
final class PreviewStatementRequest extends FormRequest
{
    public const MAX_LINES = 500;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:20000', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && count(preg_split('/\R/u', trim($value)) ?: []) > self::MAX_LINES) {
                    $fail('Relevé trop long : '.self::MAX_LINES.' lignes maximum.');
                }
            }],
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function text(): string
    {
        return (string) $this->validated('text');
    }

    public function referenceDate(): CarbonImmutable
    {
        $date = $this->validated('reference_date');

        return $date ? CarbonImmutable::parse($date) : CarbonImmutable::today();
    }
}
