<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\RecurringTransactionController;
use App\Http\Controllers\Api\BudgetCycleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes pour l'API mobile Morex
| Authentification via Laravel Sanctum
|
*/

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge']);

// Routes protégées (nécessitent authentification)
Route::middleware('auth:sanctum')->group(function () {
    // Utilisateur
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Comptes
    Route::apiResource('accounts', AccountController::class)->names('api.accounts');
    Route::post('/accounts/reorder', [AccountController::class, 'reorder'])->name('api.accounts.reorder');

    // Catégories
    Route::apiResource('categories', CategoryController::class)->names('api.categories');
    Route::post('/categories/reorder', [CategoryController::class, 'reorder'])->name('api.categories.reorder');

    // Transactions
    Route::apiResource('transactions', TransactionController::class)->names('api.transactions');
    Route::get('/transactions-stats', [TransactionController::class, 'stats'])->name('api.transactions.stats');

    // Objectifs
    Route::apiResource('goals', GoalController::class)->names('api.goals');
    Route::post('/goals/{goal}/contribute', [GoalController::class, 'addContribution'])->name('api.goals.contribute');

    // Dettes et créances
    Route::apiResource('debts', DebtController::class)->names('api.debts');
    Route::post('/debts/{debt}/payment', [DebtController::class, 'payment'])->name('api.debts.payment');
    Route::get('/debts-stats', [DebtController::class, 'stats'])->name('api.debts.stats');

    // Transactions récurrentes
    Route::apiResource('recurring-transactions', RecurringTransactionController::class)->names('api.recurring-transactions');
    Route::post('/recurring-transactions/{recurring_transaction}/generate', [RecurringTransactionController::class, 'generate'])->name('api.recurring-transactions.generate');
    Route::post('/recurring-transactions-process-due', [RecurringTransactionController::class, 'processDue'])->name('api.recurring-transactions.process-due');

    // Statistiques globales (Dashboard mobile)
    Route::get('/stats/dashboard', [StatsController::class, 'dashboard']);
    Route::get('/stats/monthly', [StatsController::class, 'monthly']);
    Route::get('/stats/trends', [StatsController::class, 'trends']);

    // Synchronisation
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);

    // Cycles budgétaires
    Route::get('/budget-settings', [BudgetCycleController::class, 'getSettings']);
    Route::put('/budget-settings', [BudgetCycleController::class, 'updateSettings']);
    Route::get('/budget-cycles', [BudgetCycleController::class, 'index']);
    Route::get('/budget-cycles/active', [BudgetCycleController::class, 'active']);
    Route::post('/budget-cycles/start', [BudgetCycleController::class, 'start']);
    Route::post('/budget-cycles/close', [BudgetCycleController::class, 'close']);
    Route::post('/budget-cycles/check-salary', [BudgetCycleController::class, 'checkSalaryTrigger']);
});
