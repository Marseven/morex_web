<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Pull : Récupère tous les changements depuis last_sync
     *
     * GET /api/sync/pull?last_sync=2026-01-28T10:00:00Z
     */
    public function pull(Request $request): JsonResponse
    {
        $user = $request->user();
        $lastSync = $request->query('last_sync');

        // Si pas de last_sync, on envoie tout
        $since = $lastSync ? Carbon::parse($lastSync) : Carbon::createFromTimestamp(0);

        // Récupérer les données modifiées (incluant les supprimées avec withTrashed)
        $accounts = Account::withTrashed()
            ->where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        $categories = Category::withTrashed()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id'); // Catégories système
            })
            ->where('updated_at', '>', $since)
            ->get();

        $transactions = Transaction::withTrashed()
            ->where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        $goals = Goal::withTrashed()
            ->where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        $debts = Debt::withTrashed()
            ->where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        $recurringTransactions = RecurringTransaction::withTrashed()
            ->where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        $budgetCycles = BudgetCycle::where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        return response()->json([
            'accounts' => $accounts->map(fn($a) => $this->formatForSync($a)),
            'categories' => $categories->map(fn($c) => $this->formatForSync($c)),
            'transactions' => $transactions->map(fn($t) => $this->formatForSync($t)),
            'goals' => $goals->map(fn($g) => $this->formatForSync($g)),
            'debts' => $debts->map(fn($d) => $this->formatForSync($d)),
            'recurring_transactions' => $recurringTransactions->map(fn($r) => $this->formatForSync($r)),
            'budget_cycles' => $budgetCycles->map(fn($b) => $this->formatBudgetCycleForSync($b)),
            'sync_timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Push : Reçoit les changements du mobile et les applique
     *
     * POST /api/sync/push
     * Body: { changes: [...], last_sync: "..." }
     */
    public function push(Request $request): JsonResponse
    {
        $user = $request->user();
        $changes = $request->input('changes', []);

        $results = [
            'processed' => [],
            'conflicts' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($changes as $change) {
                $result = $this->processChange($user, $change);

                if ($result['status'] === 'success') {
                    $results['processed'][] = $result;
                } elseif ($result['status'] === 'conflict') {
                    $results['conflicts'][] = $result;
                } else {
                    $results['errors'][] = $result;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sync push error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur lors de la synchronisation',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'results' => $results,
            'sync_timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Formate un modèle pour la sync (inclut deleted_at)
     */
    private function formatForSync($model): array
    {
        $data = $model->toArray();
        $data['is_deleted'] = $model->trashed();
        return $data;
    }

    /**
     * Formate un cycle budgétaire pour la sync (dates en ISO)
     */
    private function formatBudgetCycleForSync(BudgetCycle $cycle): array
    {
        return [
            'id' => $cycle->id,
            'start_date' => $cycle->start_date?->toIso8601String(),
            'end_date' => $cycle->end_date?->toIso8601String(),
            'period_name' => $cycle->period_name,
            'total_budget' => $cycle->total_budget,
            'total_spent' => $cycle->total_spent,
            'status' => $cycle->status,
            'trigger_transaction_id' => $cycle->trigger_transaction_id,
            'created_at' => $cycle->created_at?->toIso8601String(),
            'updated_at' => $cycle->updated_at?->toIso8601String(),
            'is_deleted' => false,
        ];
    }

    /**
     * Traite un changement individuel
     */
    private function processChange($user, array $change): array
    {
        $type = $change['type'] ?? null;
        $action = $change['action'] ?? null;
        $localId = $change['local_id'] ?? null;
        $serverId = $change['server_id'] ?? null;
        $data = $change['data'] ?? [];
        $clientUpdatedAt = isset($change['updated_at'])
            ? Carbon::parse($change['updated_at'])
            : now();

        $modelClass = $this->getModelClass($type);

        if (!$modelClass) {
            return [
                'local_id' => $localId,
                'type' => $type,
                'status' => 'error',
                'message' => "Type inconnu: $type",
            ];
        }

        try {
            $result = match ($action) {
                'create' => $this->handleCreate($user, $modelClass, $localId, $data),
                'update' => $this->handleUpdate($user, $modelClass, $serverId, $localId, $data, $clientUpdatedAt),
                'delete' => $this->handleDelete($user, $modelClass, $serverId, $localId, $clientUpdatedAt),
                default => [
                    'local_id' => $localId,
                    'status' => 'error',
                    'message' => "Action inconnue: $action",
                ],
            };

            // Ajouter le type à la réponse pour que le mobile puisse identifier la table
            $result['type'] = $type;

            return $result;
        } catch (\Exception $e) {
            return [
                'local_id' => $localId,
                'type' => $type,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retourne la classe du modèle selon le type
     */
    private function getModelClass(string $type): ?string
    {
        return match ($type) {
            'account' => Account::class,
            'category' => Category::class,
            'transaction' => Transaction::class,
            'goal' => Goal::class,
            'debt' => Debt::class,
            'recurring_transaction' => RecurringTransaction::class,
            default => null,
        };
    }

    /**
     * Gère la création d'un élément
     */
    private function handleCreate($user, string $modelClass, ?string $localId, array $data): array
    {
        // Retirer les champs non-fillable
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

        // Ajouter user_id
        $data['user_id'] = $user->id;

        // Vérifier si un élément similaire existe déjà pour éviter les doublons
        $existingModel = $this->findExistingModel($user, $modelClass, $data);

        if ($existingModel) {
            // Mettre à jour l'existant au lieu de créer un doublon
            unset($data['user_id']);
            $existingModel->fill($data);
            $existingModel->save();

            return [
                'local_id' => $localId,
                'server_id' => $existingModel->id,
                'status' => 'success',
                'action' => 'linked', // Indique qu'on a lié à un existant
            ];
        }

        $model = $modelClass::create($data);

        return [
            'local_id' => $localId,
            'server_id' => $model->id,
            'status' => 'success',
            'action' => 'created',
        ];
    }

    /**
     * Cherche un modèle existant qui correspondrait aux données
     */
    private function findExistingModel($user, string $modelClass, array $data)
    {
        $query = $modelClass::where('user_id', $user->id);

        // Critères de correspondance selon le type de modèle
        switch ($modelClass) {
            case Account::class:
                // Un compte avec le même nom
                if (!empty($data['name'])) {
                    return $query->where('name', $data['name'])->first();
                }
                break;

            case Category::class:
                // Une catégorie avec le même nom et type
                if (!empty($data['name']) && !empty($data['type'])) {
                    return $query->where('name', $data['name'])
                                ->where('type', $data['type'])
                                ->first();
                }
                break;

            case Goal::class:
                // Un objectif avec le même nom
                if (!empty($data['name'])) {
                    return $query->where('name', $data['name'])->first();
                }
                break;

            case Debt::class:
                // Une dette avec le même nom
                if (!empty($data['name'])) {
                    return $query->where('name', $data['name'])->first();
                }
                break;

            case RecurringTransaction::class:
                // Transaction récurrente avec même montant, compte, catégorie et fréquence
                if (!empty($data['amount']) && !empty($data['account_id']) && !empty($data['frequency'])) {
                    return $query->where('amount', $data['amount'])
                                ->where('account_id', $data['account_id'])
                                ->where('frequency', $data['frequency'])
                                ->first();
                }
                break;

            case Transaction::class:
                // Les transactions normales peuvent être dupliquées (même montant, même jour = possible)
                // On ne déduplique pas les transactions sauf si elles ont exactement les mêmes données
                if (!empty($data['amount']) && !empty($data['date']) && !empty($data['account_id'])) {
                    return $query->where('amount', $data['amount'])
                                ->where('date', $data['date'])
                                ->where('account_id', $data['account_id'])
                                ->where('category_id', $data['category_id'] ?? null)
                                ->where('beneficiary', $data['beneficiary'] ?? null)
                                ->where('description', $data['description'] ?? null)
                                ->first();
                }
                break;
        }

        return null;
    }

    /**
     * Gère la mise à jour d'un élément (last-write-wins)
     */
    private function handleUpdate($user, string $modelClass, ?string $serverId, ?string $localId, array $data, Carbon $clientUpdatedAt): array
    {
        if (!$serverId) {
            return [
                'local_id' => $localId,
                'status' => 'error',
                'message' => 'server_id requis pour update',
            ];
        }

        $model = $modelClass::withTrashed()
            ->where('id', $serverId)
            ->where('user_id', $user->id)
            ->first();

        if (!$model) {
            return [
                'local_id' => $localId,
                'server_id' => $serverId,
                'status' => 'error',
                'message' => 'Élément non trouvé',
            ];
        }

        // Last-write-wins : si le serveur a été modifié après le client, conflit
        if ($model->updated_at > $clientUpdatedAt) {
            return [
                'local_id' => $localId,
                'server_id' => $serverId,
                'status' => 'conflict',
                'message' => 'Le serveur a une version plus récente',
                'server_data' => $this->formatForSync($model),
            ];
        }

        // Appliquer les changements
        unset($data['id'], $data['user_id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
        $model->fill($data);
        $model->save();

        // Si était supprimé et qu'on update, on restore
        if ($model->trashed()) {
            $model->restore();
        }

        return [
            'local_id' => $localId,
            'server_id' => $serverId,
            'status' => 'success',
            'action' => 'updated',
        ];
    }

    /**
     * Gère la suppression d'un élément (soft delete)
     */
    private function handleDelete($user, string $modelClass, ?string $serverId, ?string $localId, Carbon $clientUpdatedAt): array
    {
        if (!$serverId) {
            return [
                'local_id' => $localId,
                'status' => 'error',
                'message' => 'server_id requis pour delete',
            ];
        }

        $model = $modelClass::withTrashed()
            ->where('id', $serverId)
            ->where('user_id', $user->id)
            ->first();

        if (!$model) {
            // Déjà supprimé ou n'existe pas, c'est OK
            return [
                'local_id' => $localId,
                'server_id' => $serverId,
                'status' => 'success',
                'action' => 'already_deleted',
            ];
        }

        // Last-write-wins pour delete aussi
        if ($model->updated_at > $clientUpdatedAt && !$model->trashed()) {
            return [
                'local_id' => $localId,
                'server_id' => $serverId,
                'status' => 'conflict',
                'message' => 'Le serveur a une version plus récente',
                'server_data' => $this->formatForSync($model),
            ];
        }

        $model->delete(); // Soft delete

        return [
            'local_id' => $localId,
            'server_id' => $serverId,
            'status' => 'success',
            'action' => 'deleted',
        ];
    }
}
