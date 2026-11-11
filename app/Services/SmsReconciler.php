<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\StatementEntry;
use App\Models\Account;
use App\Models\StatementLine;

/**
 * Module sms-iphone (3b) : un SMS bancaire passe par le moteur de rapprochement du module 3a.
 * Correspondance unique → l'opération déjà saisie est pointée (ligne de relevé « sms ») ;
 * sinon, l'appelant crée l'opération en attente comme avant.
 */
final class SmsReconciler
{
    public const MATCHED = 'matched';

    public const ALREADY_IMPORTED = 'already_imported';

    public const NO_MATCH = 'no_match';

    public function __construct(
        private readonly StatementMatcher $matcher,
        private readonly StatementService $statements,
    ) {}

    /**
     * @param  array{amount: int, is_income: bool, beneficiary: ?string, reference: ?string}  $sms  résultat de SmsParser
     * @return array{status: string, transaction_id: ?string}
     */
    public function reconcile(Account $account, array $sms, string $date): array
    {
        $amount = $sms['is_income'] ? (int) $sms['amount'] : -(int) $sms['amount'];
        $entry = new StatementEntry(0, $date, $amount, 0, $sms['beneficiary'] ?? null, $sms['reference'] ?? null, 'ok', '');
        $row = $this->matcher->match($account, [$entry])['lines'][0];

        if ($row['status'] === 'already_imported') {
            return ['status' => self::ALREADY_IMPORTED, 'transaction_id' => null];
        }
        if ($row['status'] !== 'matched') {
            return ['status' => self::NO_MATCH, 'transaction_id' => null];
        }

        try {
            $this->statements->apply($account, [[
                'action' => 'match',
                'transaction_id' => $row['transaction_id'],
                'date' => $date,
                'amount' => $amount,
                'fee' => (int) ($row['fee'] ?? 0),
                'label' => $sms['beneficiary'] ?? null,
                'reference' => $sms['reference'] ?? null,
            ]], StatementLine::SOURCE_SMS);
        } catch (\Illuminate\Validation\ValidationException) {
            // Opération rapprochée entre-temps : l'appelant retombe sur l'opération en attente
            return ['status' => self::NO_MATCH, 'transaction_id' => null];
        }

        return ['status' => self::MATCHED, 'transaction_id' => $row['transaction_id']];
    }
}
