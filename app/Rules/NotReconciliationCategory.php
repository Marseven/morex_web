<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Les catégories d'écart de rapprochement sont réservées aux opérations créées par le rapprochement
 * (module dette-qualite). Une opération d'écart existante peut garder la sienne.
 */
final class NotReconciliationCategory implements ValidationRule
{
    public function __construct(private readonly ?string $currentCategoryId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && in_array($value, Category::RECONCILIATION_IDS, true) && $value !== $this->currentCategoryId) {
            $fail('Cette catégorie est réservée aux écarts de rapprochement.');
        }
    }
}
