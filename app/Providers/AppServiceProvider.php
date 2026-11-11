<?php

namespace App\Providers;

use App\Contracts\PushNotifier;
use App\Models\BudgetCycle;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\BudgetAlertService;
use App\Services\PlannedPurchaseService;
use App\Services\SmsShortcutService;
use App\Services\TransferService;
use App\Services\WebPushNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushNotifier::class, WebPushNotifier::class);
        $this->app->scoped(BudgetAlertService::class);
        $this->app->bind(WebPush::class, fn () => WebPushNotifier::makeClient());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rotation du jeton (module dette-qualite-mobile) : compteur propre, distinct du throttle du groupe API
        RateLimiter::for('token-rotate', fn (Request $request) => Limit::perMinute(6)->by('token-rotate:'.($request->user()?->id ?: $request->ip())));

        // Garde-fou : un APP_DEBUG=true oublié dans le .env de production exposerait traces et chemins.
        if ($this->app->environment('production')) {
            config(['app.debug' => false]);
        }

        // Plafonds (module plafonds-alertes) : toute dépense confirmée recalcule le plafond de sa catégorie.
        // La synchro désactive ces événements et vérifie tout l'utilisateur après validation.
        // Un cycle créé, clôturé ou supprimé change la période des plafonds
        BudgetCycle::saved(fn () => app(BudgetAlertService::class)->forgetPeriods());
        BudgetCycle::deleted(fn () => app(BudgetAlertService::class)->forgetPeriods());

        // Achats prévus : une opération supprimée ou qui n'est plus une dépense libère son achat
        Transaction::deleted(fn (Transaction $t) => app(PlannedPurchaseService::class)->releaseFor($t));
        Transaction::updated(function (Transaction $t) {
            if ($t->wasChanged('type') && $t->type !== 'expense') {
                app(PlannedPurchaseService::class)->releaseFor($t);
            }
        });

        // Jeton du Raccourci SMS (module sms-iphone) : pas d'expiration globale (le Raccourci ne sait pas se renouveler),
        // mais révocable et réservé au webhook. Les autres jetons gardent l'expiration de config/sanctum.php.
        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid): bool {
            if ($token->name === SmsShortcutService::TOKEN_NAME && ! $token->can('*')) {
                return $token->tokenable !== null && ! ($token->expires_at?->isPast() ?? false);
            }

            return $isValid;
        });

        // Frais de transfert (module transferts-frais) : transfert et dépense liée restent alignés
        Transaction::deleted(fn (Transaction $t) => app(TransferService::class)->onFeeTransactionDeleted($t));
        Transaction::updated(fn (Transaction $t) => app(TransferService::class)->onFeeTransactionUpdated($t));
        Transfer::restored(fn (Transfer $t) => app(TransferService::class)->restoreFee($t));

        Transaction::saved(function (Transaction $transaction) {
            $relevant = $transaction->wasRecentlyCreated
                || $transaction->wasChanged(['amount', 'category_id', 'status', 'date', 'type']);

            if ($relevant
                && $transaction->type === 'expense'
                && $transaction->status !== Transaction::STATUS_PENDING
                && $transaction->category_id !== null
                && $transaction->user !== null) {
                app(BudgetAlertService::class)->checkCategory($transaction->user, $transaction->category_id);
            }
        });
    }
}
