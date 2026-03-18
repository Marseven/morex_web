<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->transfers()
            ->with(['fromAccount', 'toAccount']);

        if ($request->has('account_id')) {
            $accountId = $request->account_id;
            $query->where(function ($q) use ($accountId) {
                $q->where('from_account_id', $accountId)
                  ->orWhere('to_account_id', $accountId);
            });
        }

        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $transfers = $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return TransferResource::collection($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'from_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'to_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}", 'different:from_account_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
        ]);

        $transfer = $request->user()->transfers()->create($validated);

        return response()->json(
            new TransferResource($transfer->load(['fromAccount', 'toAccount'])),
            201
        );
    }

    public function storeMultiHop(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'hops' => ['required', 'array', 'min:1'],
            'hops.*.from_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'hops.*.to_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'hops.*.amount' => ['required', 'integer', 'min:1'],
            'hops.*.description' => ['nullable', 'string', 'max:1000'],
            'hops.*.date' => ['required', 'date'],
        ]);

        // Validate that consecutive hops connect (to_account_id[n] == from_account_id[n+1])
        $hops = $validated['hops'];
        for ($i = 0; $i < count($hops) - 1; $i++) {
            if ($hops[$i]['to_account_id'] !== $hops[$i + 1]['from_account_id']) {
                return response()->json([
                    'message' => "Les sauts doivent se connecter: le compte d'arrivee du saut " . ($i + 1) . " doit etre le compte de depart du saut " . ($i + 2) . ".",
                    'errors' => ['hops' => ["Connexion invalide entre les sauts " . ($i + 1) . " et " . ($i + 2)]],
                ], 422);
            }
        }

        $transfers = [];

        DB::transaction(function () use ($request, $hops, &$transfers) {
            foreach ($hops as $hop) {
                $transfers[] = $request->user()->transfers()->create($hop);
            }
        });

        return response()->json([
            'message' => count($transfers) . ' transferts crees.',
            'transfers' => TransferResource::collection(
                collect($transfers)->map(fn ($t) => $t->load(['fromAccount', 'toAccount']))
            ),
        ], 201);
    }

    public function show(Request $request, Transfer $transfer): TransferResource
    {
        $this->authorize('view', $transfer);

        return new TransferResource($transfer->load(['fromAccount', 'toAccount']));
    }

    public function update(Request $request, Transfer $transfer): TransferResource
    {
        $this->authorize('update', $transfer);

        $userId = $request->user()->id;

        $validated = $request->validate([
            'from_account_id' => ['sometimes', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'to_account_id' => ['sometimes', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['sometimes', 'date'],
        ]);

        $transfer->update($validated);

        return new TransferResource($transfer->fresh()->load(['fromAccount', 'toAccount']));
    }

    public function destroy(Request $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('delete', $transfer);

        $transfer->delete();

        return response()->json(null, 204);
    }
}
