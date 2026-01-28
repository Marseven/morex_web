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

// Routes protégées (nécessitent authentification)
Route::middleware('auth:sanctum')->group(function () {
    // Utilisateur
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Comptes
    Route::apiResource('accounts', AccountController::class);
    Route::post('/accounts/reorder', [AccountController::class, 'reorder']);

    // Catégories
    Route::apiResource('categories', CategoryController::class);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);

    // Transactions
    Route::apiResource('transactions', TransactionController::class);
    Route::get('/transactions-stats', [TransactionController::class, 'stats']);

    // Objectifs
    Route::apiResource('goals', GoalController::class);
    Route::post('/goals/{goal}/contribute', [GoalController::class, 'addContribution']);

    // Dettes et créances
    Route::apiResource('debts', DebtController::class);
    Route::post('/debts/{debt}/payment', [DebtController::class, 'payment']);
    Route::get('/debts-stats', [DebtController::class, 'stats']);

    // Transactions récurrentes
    Route::apiResource('recurring-transactions', RecurringTransactionController::class);
    Route::post('/recurring-transactions/{recurring_transaction}/generate', [RecurringTransactionController::class, 'generate']);
    Route::post('/recurring-transactions-process-due', [RecurringTransactionController::class, 'processDue']);

    // Statistiques globales (Dashboard mobile)
    Route::get('/stats/dashboard', [StatsController::class, 'dashboard']);
    Route::get('/stats/monthly', [StatsController::class, 'monthly']);
    Route::get('/stats/trends', [StatsController::class, 'trends']);

    // Synchronisation
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
});
