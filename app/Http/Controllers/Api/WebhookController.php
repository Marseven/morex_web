<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SmsParser;
use App\Services\SmsReconciler;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function sms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'sender' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', 'uuid'],
        ]);

        $parser = new SmsParser;
        $result = $parser->parse($validated['message'], $validated['sender'] ?? null);

        if ($result === null) {
            return response()->json([
                'message' => 'SMS non reconnu comme transaction.',
                'parsed' => false,
            ], 422);
        }

        $user = $request->user();

        // Un même SMS (renvoi, double lecture) ne crée qu'une transaction
        $fingerprint = hash('sha256', mb_strtolower(trim((string) ($validated['sender'] ?? '')))."\n".trim($validated['message']));
        $existing = $this->findBySmsFingerprint($user->id, $fingerprint);
        if ($existing) {
            return $this->duplicateResponse($existing);
        }

        // Determine account: use provided account_id or try to match by source
        // Sécurité : le compte fourni DOIT appartenir à l'utilisateur (anti-IDOR)
        $accountId = null;
        if (! empty($validated['account_id'])) {
            $accountId = $user->accounts()
                ->whereKey($validated['account_id'])
                ->value('id');
            if (! $accountId) {
                return response()->json([
                    'message' => 'Compte invalide.',
                    'parsed' => true,
                ], 403);
            }
        }
        $accountId ??= self::accountIdForSource($user, $result['source'] ?? null);

        if (! $accountId) {
            // Fall back to default account
            $account = $user->accounts()->where('is_default', true)->first()
                ?? $user->accounts()->first();
            $accountId = $account?->id;
        }

        if (! $accountId) {
            return response()->json([
                'message' => 'Aucun compte disponible.',
                'parsed' => true,
                'result' => $result,
            ], 422);
        }

        // Module sms-iphone : une opération déjà saisie (notes du jour) est pointée au lieu d'être dupliquée
        $reconciled = app(SmsReconciler::class)->reconcile(Account::findOrFail($accountId), $result, now()->toDateString(), $fingerprint);
        if ($reconciled['status'] === SmsReconciler::MATCHED) {
            return response()->json(['message' => 'Opération déjà saisie : pointée.', 'parsed' => true, 'matched' => true]
                + ($this->isFullToken($request) ? ['transaction_id' => $reconciled['transaction_id']] : []));
        }
        if ($reconciled['status'] === SmsReconciler::ALREADY_IMPORTED) {
            return response()->json(['message' => 'Déjà importé depuis un relevé.', 'parsed' => true, 'duplicate' => true]);
        }

        $transaction = new Transaction([
            'amount' => $result['amount'],
            'type' => $result['is_income'] ? 'income' : 'expense',
            'account_id' => $accountId,
            'beneficiary' => $result['beneficiary'],
            'description' => "SMS {$result['source']}".($result['reference'] ?? ''),
            'date' => now()->toDateString(),
            'status' => Transaction::STATUS_PENDING,
        ]);
        $transaction->user_id = $user->id;
        $transaction->sms_fingerprint = $fingerprint;

        try {
            $transaction->save();
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés du même SMS : l'index unique tranche
            return $this->duplicateResponse($this->findBySmsFingerprint($user->id, $fingerprint));
        }

        // Jeton du Raccourci (restreint) : réponse minimale, sans identifiant ni contenu analysé
        return response()->json(['message' => 'Transaction creee en attente de validation.', 'parsed' => true]
            + ($this->isFullToken($request) ? ['transaction_id' => $transaction->id, 'result' => $result] : []), 201);
    }

    private function findBySmsFingerprint(int $userId, string $fingerprint): ?Transaction
    {
        return Transaction::withTrashed()
            ->where('user_id', $userId)
            ->where('sms_fingerprint', $fingerprint)
            ->first();
    }

    private function duplicateResponse(Transaction $transaction): JsonResponse
    {
        return response()->json([
            'message' => 'SMS deja enregistre.',
            'parsed' => true,
            'duplicate' => true,
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * P13 (module dette-qualite) : un compte n'est retrouvé par son nom qu'avec une source non vide,
     * jokers LIKE échappés. Sans source, l'appelant retombe sur le compte par défaut.
     */
    public static function accountIdForSource(User $user, ?string $source): ?string
    {
        $source = mb_strtolower(trim((string) $source));
        if ($source === '') {
            return null;
        }
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $source);

        return $user->accounts()->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
            ->orderBy('created_at')->value('id'); // plusieurs correspondances : toujours la même
    }

    private function isFullToken(Request $request): bool
    {
        $token = $request->user()?->currentAccessToken();

        return ! $token instanceof \Laravel\Sanctum\PersonalAccessToken || $token->can('*');
    }
}
