<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

// Routes d'authentification (publiques)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Routes protégées
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Comptes
    Route::resource('accounts', AccountController::class);

    // Transactions
    Route::resource('transactions', TransactionController::class);

    // Budgets (catégories)
    Route::resource('budgets', CategoryController::class)->parameters([
        'budgets' => 'category',
    ]);

    // Objectifs
    Route::resource('goals', GoalController::class);
    Route::post('/goals/{goal}/contribute', [GoalController::class, 'contribute'])->name('goals.contribute');

    // Dettes & Créances
    Route::resource('debts', DebtController::class);
    Route::post('/debts/{debt}/payment', [DebtController::class, 'payment'])->name('debts.payment');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
});
