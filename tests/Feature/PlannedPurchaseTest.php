<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\PlannedPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PlannedPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module achats-prevus (5d) : RM-1 à RM-5.
 */
class PlannedPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $clothes;

    private PlannedPurchaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $this->app->instance(PushNotifier::class, new class implements PushNotifier
        {
            public function sendToUser(User $user, array $payload): int
            {
                return 0;
            }
        });
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(2000000)->create();
        $this->clothes = Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Habillement', 'type' => 'expense']);
        $this->service = app(PlannedPurchaseService::class);
    }

    private function spend(int $amount, ?Category $category = null, array $extra = []): Transaction
    {
        $tx = new Transaction($extra + [
            'amount' => $amount, 'type' => 'expense', 'category_id' => ($category ?? $this->clothes)->id,
            'account_id' => $this->account->id, 'date' => now()->toDateString(), 'beneficiary' => 'boutique',
        ]);
        $tx->user_id = $this->user->id;
        $tx->save();

        return $tx;
    }

    private function plan(string $name = 'Chaussures', int $amount = 45000, ?string $for = null): PlannedPurchase
    {
        return $this->service->plan($this->user, ['name' => $name, 'estimated_amount' => $amount, 'category_id' => $this->clothes->id, 'planned_for' => $for]);
    }

    public function test_a_purchase_is_planned_for_the_current_period_by_default(): void
    {
        $purchase = $this->plan();

        $this->assertSame(['2026-11-01', 'planned'], [$purchase->planned_for->toDateString(), $purchase->status]);
    }

    public function test_postponing_targets_the_day_after_the_cycle_end(): void
    {
        $cycle = new BudgetCycle(['start_date' => '2026-10-27', 'end_date' => '2026-11-26', 'period_name' => 'Nov', 'status' => 'active']);
        $cycle->user_id = $this->user->id;
        $cycle->save();

        $purchase = $this->service->postpone($this->user, ['name' => 'Montre', 'estimated_amount' => 60000, 'category_id' => $this->clothes->id]);

        $this->assertSame('2026-11-27', $purchase->planned_for->toDateString());
    }

    public function test_postponing_without_a_known_end_targets_next_month(): void
    {
        $purchase = $this->service->postpone($this->user, ['name' => 'Montre', 'estimated_amount' => 60000]);

        $this->assertSame('2026-12-01', $purchase->planned_for->toDateString());
    }

    public function test_only_big_regular_unlinked_expenses_are_out_of_list(): void
    {
        $transit = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $transit->forceFill(['nature' => 'transit'])->save();
        $big = $this->spend(60000);
        $this->spend(29999);
        $this->spend(80000, $transit);
        $this->spend(90000, extra: ['status' => Transaction::STATUS_PENDING]);
        $linked = $this->spend(45000);
        $this->service->link($this->plan(), $linked);

        $this->assertSame([$big->id], $this->service->outOfList($this->user)->pluck('id')->all());
    }

    public function test_linking_marks_the_purchase_bought(): void
    {
        $purchase = $this->plan();
        $tx = $this->spend(47000);

        $this->service->link($purchase, $tx);

        $purchase->refresh();
        $this->assertSame([PlannedPurchase::STATUS_BOUGHT, $tx->id, false], [$purchase->status, $purchase->transaction_id, $purchase->unplanned]);
    }

    public function test_an_operation_cannot_be_linked_twice(): void
    {
        $tx = $this->spend(45000);
        $this->service->link($this->plan(), $tx);

        $this->expectException(\DomainException::class);
        $this->service->link($this->plan('Sac'), $tx);
    }

    public function test_accepting_an_unplanned_purchase_keeps_a_trace(): void
    {
        $tx = $this->spend(60000);

        $this->service->acceptUnplanned($tx);

        $this->assertTrue(PlannedPurchase::where('transaction_id', $tx->id)->value('unplanned'));
        $this->assertCount(0, $this->service->outOfList($this->user));
        $this->assertSame(1, $this->service->summary($this->user)['unplanned_count']);
    }

    public function test_candidates_match_open_purchases_of_the_same_category_up_to_now(): void
    {
        $this->plan('Chaussures');
        $this->plan('Veste', 70000, '2026-12-01'); // cycle suivant : pas encore permis
        $other = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);

        $this->assertSame(['Chaussures'], $this->service->openPurchases($this->user)->pluck('name')->all());
        unset($other);
    }

    public function test_summary_counts_the_period(): void
    {
        $this->service->link($this->plan('Chaussures', 45000), $this->spend(47000));
        $this->plan('Sac', 35000);
        $this->spend(60000);

        $summary = $this->service->summary($this->user);

        $this->assertSame([2, 1, 80000, 47000, 1, 60000], [
            $summary['planned_count'], $summary['bought_count'], $summary['planned_total'],
            $summary['spent_total'], $summary['out_of_list_count'], $summary['out_of_list_total'],
        ]);
    }

    public function test_late_purchases_come_from_previous_periods(): void
    {
        $this->plan('Lunettes', 50000, '2026-10-01');

        $this->assertSame(['Lunettes'], $this->service->summary($this->user)['late']->pluck('name')->all());
    }

    // --- Page et saisie ---

    private function entry(array $extra = []): array
    {
        return $extra + ['amount' => 47000, 'type' => 'expense', 'category_id' => $this->clothes->id,
            'account_id' => $this->account->id, 'date' => now()->toDateString(), 'beneficiary' => 'Chaussures'];
    }

    public function test_the_page_lists_the_period_for_its_owner(): void
    {
        $this->plan();
        $this->get('/purchases')->assertRedirect('/login');

        $this->actingAs($this->user)->get('/purchases')
            ->assertInertia(fn ($page) => $page
                ->component('Purchases/Index')
                ->where('purchases.0.name', 'Chaussures')
                ->where('summary.planned_count', 1)
                ->where('threshold', 30000)
            );
    }

    public function test_purchases_are_planned_now_or_postponed_from_the_page(): void
    {
        $this->actingAs($this->user)->post('/purchases', ['name' => 'Sac', 'estimated_amount' => 35000, 'category_id' => $this->clothes->id, 'when' => 'current'])
            ->assertRedirect('/purchases');
        $this->actingAs($this->user)->post('/purchases', ['name' => 'Montre', 'estimated_amount' => 60000, 'when' => 'next'])
            ->assertRedirect('/purchases');

        $this->assertSame(['Sac' => '2026-11-01', 'Montre' => '2026-12-01'],
            PlannedPurchase::orderBy('name', 'desc')->get()->mapWithKeys(fn ($p) => [$p->name => $p->planned_for->toDateString()])->all());
    }

    public function test_invalid_purchases_are_refused(): void
    {
        $foreign = Category::factory()->create();

        $this->actingAs($this->user)->post('/purchases', ['name' => 'X', 'estimated_amount' => 0, 'when' => 'current'])
            ->assertSessionHasErrors('estimated_amount');
        $this->actingAs($this->user)->post('/purchases', ['name' => 'X', 'estimated_amount' => 100, 'category_id' => $foreign->id, 'when' => 'later'])
            ->assertSessionHasErrors(['category_id', 'when']);
    }

    public function test_only_the_owner_cancels_a_purchase(): void
    {
        $purchase = $this->plan();

        $this->actingAs(User::factory()->create())->delete('/purchases/'.$purchase->id)->assertForbidden();
        $this->actingAs($this->user)->delete('/purchases/'.$purchase->id)->assertRedirect('/purchases');

        $this->assertSame(PlannedPurchase::STATUS_CANCELLED, $purchase->fresh()->status);
    }

    public function test_the_entry_form_receives_candidates_and_the_threshold(): void
    {
        $purchase = $this->plan();

        $this->actingAs($this->user)->get('/transactions/create?planned_purchase_id='.$purchase->id)
            ->assertInertia(fn ($page) => $page
                ->where('plannedPurchases.0.id', $purchase->id)
                ->where('purchaseThreshold', 30000)
                ->where('prefill.planned_purchase_id', $purchase->id)
            );
    }

    public function test_saving_with_a_planned_purchase_links_it(): void
    {
        $purchase = $this->plan();

        $this->actingAs($this->user)->post('/transactions', $this->entry(['planned_purchase_id' => $purchase->id]))->assertRedirect();

        $this->assertSame(PlannedPurchase::STATUS_BOUGHT, $purchase->fresh()->status);
        $this->assertNotNull($purchase->fresh()->transaction_id);
    }

    public function test_buying_anyway_records_an_assumed_out_of_list_purchase(): void
    {
        $this->actingAs($this->user)->post('/transactions', $this->entry(['amount' => 60000, 'out_of_list' => 'accept']))->assertRedirect();

        $this->assertSame(1, PlannedPurchase::where('unplanned', true)->count());
        $this->assertCount(0, $this->service->outOfList($this->user));
    }

    public function test_a_foreign_or_bought_purchase_cannot_be_linked(): void
    {
        $other = User::factory()->create();
        $foreign = $this->service->plan($other, ['name' => 'X', 'estimated_amount' => 50000]);
        $bought = $this->plan('Sac');
        $this->service->link($bought, $this->spend(40000));

        foreach ([$foreign, $bought] as $purchase) {
            $this->actingAs($this->user)->post('/transactions', $this->entry(['planned_purchase_id' => $purchase->id]))
                ->assertSessionHasErrors('planned_purchase_id');
        }
    }

    public function test_the_dashboard_shows_the_purchases_card(): void
    {
        $this->plan();
        $this->spend(60000);

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('purchases.planned_count', 1)
                ->where('purchases.out_of_list_total', 60000)
            );
    }

    public function test_the_page_sends_local_dates_not_utc(): void
    {
        $this->service->postpone($this->user, ['name' => 'Sac', 'estimated_amount' => 30000]);
        $this->service->link($this->plan('Livre', 12000), $this->spend(12000));

        $this->actingAs($this->user)->get('/purchases')
            ->assertInertia(fn ($page) => $page
                ->where('purchases.0.planned_for', '2026-11-01')
                ->where('purchases.0.bought_on', '2026-11-11')
                ->where('purchases.1.planned_for', '2026-12-01')
            );
    }

    // --- Revue de code ---

    public function test_a_purchase_bought_meanwhile_cannot_be_linked_again(): void
    {
        $purchase = $this->plan();
        $stale = PlannedPurchase::find($purchase->id); // lu avant que l'autre envoi ne l'achète
        $this->service->link($purchase, $this->spend(45000));

        $this->expectException(\DomainException::class);
        $this->service->link($stale, $this->spend(46000));
    }

    public function test_a_purchase_bought_between_display_and_save_returns_a_validation_error(): void
    {
        $purchase = $this->plan();
        // Un autre envoi achète l'achat entre la validation et le lien
        Transaction::created(function (Transaction $t) use ($purchase) {
            if ($t->amount === 47000) {
                PlannedPurchase::whereKey($purchase->id)->update(['status' => PlannedPurchase::STATUS_BOUGHT]);
            }
        });

        $this->actingAs($this->user)->post('/transactions', $this->entry(['planned_purchase_id' => $purchase->id]))
            ->assertSessionHasErrors('planned_purchase_id');
        $this->assertSame(0, Transaction::where('amount', 47000)->count());
    }

    public function test_deleting_the_operation_reopens_the_purchase(): void
    {
        $purchase = $this->plan();
        $tx = $this->spend(45000);
        $this->service->link($purchase, $tx);
        $assumed = $this->service->acceptUnplanned($other = $this->spend(60000));

        $tx->delete();
        $other->delete();

        $purchase->refresh();
        $this->assertSame([PlannedPurchase::STATUS_PLANNED, null], [$purchase->status, $purchase->transaction_id]);
        $this->assertSame(PlannedPurchase::STATUS_CANCELLED, $assumed->fresh()->status);
    }

    public function test_an_operation_turned_into_income_releases_its_purchase(): void
    {
        $purchase = $this->plan();
        $tx = $this->spend(45000);
        $this->service->link($purchase, $tx);

        $tx->update(['type' => 'income']);

        $this->assertSame(PlannedPurchase::STATUS_PLANNED, $purchase->fresh()->status);
    }

    public function test_orphans_left_by_the_sync_are_released(): void
    {
        $purchase = $this->plan();
        $tx = $this->spend(45000);
        $this->service->link($purchase, $tx);
        Transaction::withoutEvents(fn () => $tx->delete());

        $this->service->releaseOrphans($this->user);

        $this->assertSame(PlannedPurchase::STATUS_PLANNED, $purchase->fresh()->status);
    }

    public function test_a_bought_purchase_cannot_be_cancelled(): void
    {
        $purchase = $this->plan();
        $this->service->link($purchase, $this->spend(45000));

        $this->actingAs($this->user)->delete('/purchases/'.$purchase->id)->assertStatus(422);
        $this->delete('/purchases/'.$purchase->id);
        $this->assertSame(PlannedPurchase::STATUS_BOUGHT, $purchase->fresh()->status);
    }

    public function test_guests_and_strangers_cannot_cancel(): void
    {
        $purchase = $this->plan();

        $this->delete('/purchases/'.$purchase->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->delete('/purchases/'.$purchase->id)->assertForbidden();
        $this->assertSame(PlannedPurchase::STATUS_PLANNED, $purchase->fresh()->status);
    }

    public function test_buying_anyway_leaves_no_trace_under_the_threshold_or_outside_regular(): void
    {
        $pro = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $pro->forceFill(['nature' => 'pro'])->save();

        $this->actingAs($this->user)->post('/transactions', $this->entry(['amount' => 20000, 'out_of_list' => 'accept']));
        $this->actingAs($this->user)->post('/transactions', $this->entry(['amount' => 90000, 'category_id' => $pro->id, 'out_of_list' => 'accept']));

        $this->assertSame(0, PlannedPurchase::count());
    }

    public function test_unplanned_purchases_of_another_period_are_not_counted(): void
    {
        $this->service->acceptUnplanned($this->spend(60000, extra: ['date' => '2026-12-05']));

        $this->assertSame(0, $this->service->summary($this->user)['unplanned_count']);
    }
}
