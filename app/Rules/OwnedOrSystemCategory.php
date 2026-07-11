<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide qu'une category_id référence soit une catégorie de l'utilisateur courant,
 * soit une catégorie système (user_id = NULL). Empêche de rattacher une transaction
 * à la catégorie privée d'un autre utilisateur (fuite de données / IDOR).
 */
class OwnedOrSystemCategory implements ValidationRule
{
    public function __construct(private int $userId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $exists = Category::whereKey($value)
            ->where(function ($q) {
                $q->where('user_id', $this->userId)->orWhereNull('user_id');
            })
            ->exists();

        if (!$exists) {
            $fail('La catégorie sélectionnée est invalide.');
        }
    }
}
