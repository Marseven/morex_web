<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ApplyStatementRequest;
use App\Http\Requests\PreviewStatementRequest;
use App\Models\Account;
use App\Models\Category;
use App\Services\StatementMatcher;
use App\Services\StatementParser;
use App\Services\StatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Écran web « Relevé » d'un compte (module releves-sms). */
final class StatementController extends Controller
{
    public function show(Request $request, Account $account): Response
    {
        $this->authorize('update', $account);
        $user = $request->user();

        return Inertia::render('Accounts/Statement', [
            'account' => $account->only(['id', 'name', 'balance']),
            'categories' => Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('is_system', true))
                ->whereNotIn('id', [...Category::RECONCILIATION_IDS, Category::BANK_FEES_ID])
                ->orderBy('order_index')
                ->get(['id', 'name', 'type']),
            'today' => now()->toDateString(),
        ]);
    }

    public function preview(PreviewStatementRequest $request, Account $account, StatementParser $parser, StatementMatcher $matcher): JsonResponse
    {
        $entries = $parser->parse($request->text(), $account->name, $request->referenceDate());

        return response()->json(['data' => $matcher->match($account, $entries)]);
    }

    public function store(ApplyStatementRequest $request, Account $account, StatementService $statements): JsonResponse
    {
        return response()->json(['data' => $statements->apply($account, $request->lines())]);
    }
}
