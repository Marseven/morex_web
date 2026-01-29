<?php

namespace App\Http\Middleware;

use App\Models\BudgetCycle;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only('id', 'name', 'email', 'phone', 'avatar', 'theme'),
                    'two_factor_enabled' => $request->user()->hasTwoFactorEnabled(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'currentDate' => now()->format('d/m/Y'),
            'currentBudgetPeriod' => fn () => $this->getCurrentBudgetPeriod($request),
        ];
    }

    private function getCurrentBudgetPeriod(Request $request): ?string
    {
        if (!$request->user()) {
            return null;
        }

        $activeCycle = BudgetCycle::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        return $activeCycle?->period_name;
    }
}
