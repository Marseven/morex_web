<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\SmsParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function sms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
            'sender' => ['nullable', 'string'],
            'account_id' => ['nullable', 'uuid'],
        ]);

        $parser = new SmsParser();
        $result = $parser->parse($validated['message'], $validated['sender'] ?? null);

        if ($result === null) {
            return response()->json([
                'message' => 'SMS non reconnu comme transaction.',
                'parsed' => false,
            ], 422);
        }

        $user = $request->user();

        // Determine account: use provided account_id or try to match by source
        $accountId = $validated['account_id'] ?? null;
        if (!$accountId) {
            // Try to find a matching account by name
            $source = strtolower($result['source'] ?? '');
            $account = $user->accounts()
                ->where(function ($q) use ($source) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$source}%"]);
                })
                ->first();
            $accountId = $account?->id;
        }

        if (!$accountId) {
            // Fall back to default account
            $account = $user->accounts()->where('is_default', true)->first()
                ?? $user->accounts()->first();
            $accountId = $account?->id;
        }

        if (!$accountId) {
            return response()->json([
                'message' => 'Aucun compte disponible.',
                'parsed' => true,
                'result' => $result,
            ], 422);
        }

        $transaction = new Transaction([
            'amount' => $result['amount'],
            'type' => $result['is_income'] ? 'income' : 'expense',
            'account_id' => $accountId,
            'beneficiary' => $result['beneficiary'],
            'description' => "SMS {$result['source']}" . ($result['reference'] ?? ''),
            'date' => now()->toDateString(),
            'status' => 'pending_validation',
        ]);
        $transaction->user_id = $user->id;
        $transaction->save();

        return response()->json([
            'message' => 'Transaction creee en attente de validation.',
            'parsed' => true,
            'transaction_id' => $transaction->id,
            'result' => $result,
        ], 201);
    }
}
