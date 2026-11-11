<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MonthlyReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module bilan-mensuel (8) : RM-1 à RM-5.
 */
class MonthlyReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $salary;

    private Category $outings;

    private Category $food;

    private Category $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        $this->app->instance(PushNotifier::class, new class implements PushNotifier
        {
            public function sendToUser(User $user, array $payload): int
            {
                return 0;
            }
        });
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(5000000)->create();
        $this->salary = $this->category('Salaire', 'income');
        $this->outings = $this->category('Sorties/Loisirs', 'expense', 170000, true);
        $this->food = $this->category('Alimentation', 'expense', 120000);
        $this->subscriptions = $this->category('Abonnements', 'expense', 90000);
    }

    private function category(string $name, string $type, ?int $limit = null, bool $discretionary = false): Category
    {
        $c = Category::factory()->create(['user_id' => $this->user->id, 'name' => $name, 'type' => $type, 'budget_limit' => $limit]);
        $c->forceFill(['discretionary' => $discretionary])->save();

        return $c;
    }

    private function add(string $type, int $amount, Category $category, string $date): void
    {
        $tx = new Transaction(['amount' => $amount, 'type' => $type, 'category_id' => $category->id, 'account_id' => $this->account->id, 'date' => $date]);
        $tx->user_id = $this->user->id;
        $tx->save();
    }

    private function cycle(string $start, ?string $end, string $status = 'closed'): BudgetCycle
    {
        $cycle = new BudgetCycle(['start_date' => $start, 'end_date' => $end, 'period_name' => $start, 'status' => $status]);
        $cycle->user_id = $this->user->id;
        $cycle->save();

        return $cycle;
    }

    /** Trois cycles : sorties dépassées 2 fois, abonnements toujours sous 70 %, alimentation dans les clous. */
    private function history(): BudgetCycle
    {
        foreach ([['2026-08-25', '2026-09-24', 230000], ['2026-09-25', '2026-10-24', 299000], ['2026-10-25', '2026-11-24', 150000]] as [$start, $end, $outings]) {
            $this->cycle($start, $end, $start === '2026-10-25' ? 'active' : 'closed');
            $this->add('income', 1000000, $this->salary, $start);
            $this->add('expense', $outings, $this->outings, $start);
            $this->add('expense', 100000, $this->food, $start);
            $this->add('expense', 41000, $this->subscriptions, $start);
        }

        return BudgetCycle::where('status', 'active')->sole();
    }

    public function test_the_review_compares_personal_spending_with_the_budget(): void
    {
        $review = app(MonthlyReviewService::class)->review($this->user, $this->history());

        $this->assertSame([1000000, 291000, 380000, 709000], [$review['income'], $review['personal_spent'], $review['personal_budget'], $review['savings']]);
        $this->assertSame(70.9, $review['savings_rate']);
        $this->assertSame(['spent' => 150000, 'budget' => 170000], $review['discretionary']);
    }

    public function test_overruns_come_first(): void
    {
        $cycle = $this->history();
        $this->add('expense', 60000, $this->food, '2026-11-10'); // 160 000 / 120 000

        $review = app(MonthlyReviewService::class)->review($this->user, $cycle);

        $this->assertSame(['Alimentation', 'over', 40000], [$review['categories'][0]['name'], $review['categories'][0]['status'], $review['categories'][0]['diff']]);
    }

    public function test_the_proposal_follows_the_three_last_cycles(): void
    {
        $free = $this->category('Cadeaux', 'expense');
        $this->add('expense', 20500, $free, '2026-09-01');

        $proposal = collect(app(MonthlyReviewService::class)->review($this->user, $this->history())['proposal']['categories'])->keyBy('name');

        $this->assertSame([170000, 'kept'], [$proposal['Sorties/Loisirs']['proposed'], $proposal['Sorties/Loisirs']['rule']]);
        $this->assertSame([41000, 'lowered'], [$proposal['Abonnements']['proposed'], $proposal['Abonnements']['rule']]);
        $this->assertSame([120000, 'unchanged'], [$proposal['Alimentation']['proposed'], $proposal['Alimentation']['rule']]);
        $this->assertSame([11000, 'new'], [$proposal['Cadeaux']['proposed'], $proposal['Cadeaux']['rule']]);
    }

    public function test_the_expected_income_is_the_recent_average(): void
    {
        $this->add('income', 300000, $this->salary, '2026-10-01'); // freelance d'un cycle clôturé
        $this->add('income', 900000, $this->salary, '2026-11-15'); // cycle en cours : hors moyenne

        $this->assertSame(1150000, app(MonthlyReviewService::class)->review($this->user, $this->history())['proposal']['expected_income']);
    }

    public function test_reading_the_review_changes_nothing(): void
    {
        app(MonthlyReviewService::class)->review($this->user, $this->history());

        $this->assertSame(90000, $this->subscriptions->fresh()->budget_limit);
    }

    public function test_without_history_there_is_no_proposal_line(): void
    {
        $cycle = $this->cycle('2026-11-01', null, 'active');

        $review = app(MonthlyReviewService::class)->review($this->user, $cycle);

        $this->assertSame(0, $review['proposal']['expected_income']);
        $this->assertSame([], collect($review['proposal']['categories'])->where('rule', 'new')->values()->all());
    }

    public function test_the_page_is_reserved_to_the_owner(): void
    {
        $cycle = $this->history();

        $this->get("/budget-cycles/{$cycle->id}/bilan")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get("/budget-cycles/{$cycle->id}/bilan")->assertForbidden();
        $this->actingAs($this->user)->get("/budget-cycles/{$cycle->id}/bilan")
            ->assertInertia(fn ($page) => $page->component('BudgetCycles/Bilan')->where('review.income', 1000000));
    }

    public function test_starting_a_cycle_never_changes_a_system_category(): void
    {
        $system = Category::factory()->create(['user_id' => null, 'is_system' => true, 'type' => 'expense', 'budget_limit' => 5000]);

        $this->actingAs($this->user)->post('/budget-cycles/start', [
            'budgets' => [['id' => $system->id, 'budget_limit' => 999999], ['id' => $this->food->id, 'budget_limit' => 130000]],
        ])->assertRedirect();

        $this->assertSame([5000, 130000], [$system->fresh()->budget_limit, $this->food->fresh()->budget_limit]);
    }

    public function test_the_discretionary_flag_is_set_from_the_category_form(): void
    {
        $this->actingAs($this->user)->put('/budgets/'.$this->food->id, ['name' => 'Alimentation', 'type' => 'expense', 'discretionary' => true])
            ->assertRedirect();
        $this->assertTrue($this->food->fresh()->discretionary);

        $this->actingAs($this->user)->put('/budgets/'.$this->food->id, ['name' => 'Alimentation', 'type' => 'expense', 'discretionary' => 'peut-être'])
            ->assertSessionHasErrors('discretionary');
    }

    public function test_the_launch_window_is_prefilled_from_an_owned_review_only(): void
    {
        $cycle = $this->history();
        $system = Category::factory()->create(['user_id' => null, 'is_system' => true, 'type' => 'expense']);
        $stranger = User::factory()->create();

        $this->actingAs($this->user)->get('/budget-cycles?proposal='.$cycle->id)
            ->assertInertia(fn ($page) => $page
                ->where('proposal.expected_income', 1000000)
                ->where('categories', fn ($cats) => collect($cats)->pluck('id')->doesntContain($system->id))
            );
        $this->actingAs($stranger)->get('/budget-cycles?proposal='.$cycle->id)
            ->assertInertia(fn ($page) => $page->where('proposal', null));
    }

    // --- Revue de code ---

    public function test_an_unused_cap_is_never_lowered_to_zero(): void
    {
        $unused = $this->category('Coiffeur', 'expense', 15000);

        $proposal = collect(app(MonthlyReviewService::class)->review($this->user, $this->history())['proposal']['categories'])->keyBy('name');

        $this->assertSame([15000, 'unchanged'], [$proposal['Coiffeur']['proposed'], $proposal['Coiffeur']['rule']]);
        unset($unused);
    }

    public function test_a_lowered_cap_never_rises(): void
    {
        $tiny = $this->category('Presse', 'expense', 1500);
        foreach (['2026-08-26', '2026-09-26'] as $date) {
            $this->add('expense', 200, $tiny, $date);
        }

        $line = collect(app(MonthlyReviewService::class)->review($this->user, $this->history())['proposal']['categories'])->firstWhere('name', 'Presse');

        $this->assertLessThanOrEqual(1500, $line['proposed']);
    }

    public function test_the_running_cycle_never_triggers_a_lowering(): void
    {
        $cycle = $this->cycle('2026-10-25', null, 'active');
        $this->cycle('2026-09-25', '2026-10-24');
        $this->add('expense', 10000, $this->subscriptions, '2026-10-01');

        $line = collect(app(MonthlyReviewService::class)->review($this->user, $cycle)['proposal']['categories'])->firstWhere('name', 'Abonnements');

        $this->assertSame('unchanged', $line['rule']); // un seul cycle clôturé : pas assez pour baisser
    }

    public function test_spending_outside_own_categories_shows_as_other(): void
    {
        $fees = Category::factory()->create(['user_id' => null, 'is_system' => true, 'type' => 'expense', 'name' => 'Frais bancaires']);
        $cycle = $this->history();
        $this->add('expense', 2500, $fees, '2026-11-02');

        $review = app(MonthlyReviewService::class)->review($this->user, $cycle);

        $this->assertSame($review['personal_spent'], collect($review['categories'])->sum('spent'));
        $this->assertSame(2500, collect($review['categories'])->firstWhere('id', null)['spent']);
    }

    public function test_a_cycle_without_income_has_a_zero_rate(): void
    {
        $cycle = $this->cycle('2026-11-01', null, 'active');
        $this->add('expense', 5000, $this->food, '2026-11-02');

        $review = app(MonthlyReviewService::class)->review($this->user, $cycle);

        $this->assertSame([0, -5000, 0.0], [$review['income'], $review['savings'], $review['savings_rate']]);
    }
}
