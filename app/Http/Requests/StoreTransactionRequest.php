<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlannedPurchase;
use App\Rules\NotReconciliationCategory;
use App\Rules\OwnedOrSystemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Saisie web d'une opération (module dette-qualite), avec l'achat prévu associé (module achats-prevus). */
final class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = (int) $this->user()?->id;
        $ownAccount = Rule::exists('accounts', 'id')->where('user_id', $userId)->whereNull('deleted_at');

        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'type' => ['required', 'in:expense,income,transfer'],
            'category_id' => ['nullable', 'uuid', new OwnedOrSystemCategory($userId), new NotReconciliationCategory],
            'account_id' => ['required', 'uuid', $ownAccount],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'transfer_to_account_id' => ['nullable', 'required_if:type,transfer', 'uuid', $ownAccount, 'different:account_id'],
            'planned_purchase_id' => ['nullable', 'uuid', Rule::exists('planned_purchases', 'id')
                ->where('user_id', $userId)->where('status', PlannedPurchase::STATUS_PLANNED)->whereNull('deleted_at')],
            'out_of_list' => ['nullable', 'in:accept'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['transfer_to_account_id.required_if' => 'Le compte de destination est requis pour un transfert.'];
    }
}
