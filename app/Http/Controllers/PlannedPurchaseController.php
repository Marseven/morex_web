<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePlannedPurchaseRequest;
use App\Models\Category;
use App\Models\PlannedPurchase;
use App\Services\PlannedPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Page « Achats prévus » (module achats-prevus 5d, design A). */
final class PlannedPurchaseController extends Controller
{
    public function __construct(private readonly PlannedPurchaseService $purchases) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        [$from, $to, $isCycle] = $this->purchases->period($user);
        $summary = $this->purchases->summary($user);

        return Inertia::render('Purchases/Index', [
            'purchases' => PlannedPurchase::with('category:id,name', 'transaction:id,amount,date')
                ->where('user_id', $user->id)
                ->where('status', '!=', PlannedPurchase::STATUS_CANCELLED)
                ->where('planned_for', '>=', $from->toDateString())
                ->orderBy('planned_for')->orderBy('created_at')
                ->limit(100)->get(),
            'outOfList' => $this->purchases->outOfList($user)->load('category:id,name')->take(50)->values(),
            'late' => $summary['late']->load('category:id,name'),
            'summary' => collect($summary)->except('late'),
            'period' => ['start' => $from->toDateString(), 'end' => $to?->toDateString(), 'is_cycle' => $isCycle],
            'nextStart' => $this->purchases->nextPeriodStart($user)->toDateString(),
            'categories' => Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('is_system', true))
                ->where('type', 'expense')->where('nature', Category::NATURE_REGULAR)
                ->whereNotIn('id', [...Category::RECONCILIATION_IDS, Category::BANK_FEES_ID])
                ->orderBy('order_index')->get(['id', 'name']),
            'threshold' => PlannedPurchaseService::THRESHOLD,
        ]);
    }

    public function store(StorePlannedPurchaseRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'estimated_amount', 'category_id']);

        if ($request->validated('when') === 'next') {
            $purchase = $this->purchases->postpone($request->user(), $data);

            return redirect()->route('purchases.index')
                ->with('success', 'Reporté : ajouté aux achats prévus du '.$purchase->planned_for->format('d/m').'.');
        }

        $this->purchases->plan($request->user(), $data);

        return redirect()->route('purchases.index')->with('success', 'Achat prévu ajouté.');
    }

    /** « Annuler » : l'achat sort de la liste, la trace reste. */
    public function destroy(PlannedPurchase $purchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);
        abort_if($purchase->status === PlannedPurchase::STATUS_BOUGHT, 422, 'ALREADY_BOUGHT');

        $purchase->forceFill(['status' => PlannedPurchase::STATUS_CANCELLED])->save();

        return redirect()->route('purchases.index')->with('success', 'Achat prévu annulé.');
    }
}
