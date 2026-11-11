<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { CheckCircleIcon, ExclamationTriangleIcon, InformationCircleIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline'

// Bilan du cycle et proposition du suivant (design bilan-mensuel, direction A : récit en cartes)
const props = defineProps({
    review: { type: Object, required: true },
})

const r = computed(() => props.review)
const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(Math.abs(n ?? 0))
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''

const isActive = computed(() => r.value.cycle.status === 'active')
const savingsOk = computed(() => r.value.savings_rate >= r.value.savings_target_rate)
const savingsBar = computed(() => Math.max(0, Math.min(100, Math.round(r.value.savings_rate * 100 / Math.max(1, r.value.savings_target_rate * 2)))))
const discretionaryOver = computed(() => r.value.discretionary.budget > 0 && r.value.discretionary.spent > r.value.discretionary.budget)
const overruns = computed(() => r.value.categories.filter((c) => c.status === 'over'))
const others = computed(() => r.value.categories.filter((c) => c.status !== 'over'))
const showAll = ref(false)
const proposal = computed(() => r.value.proposal)
const changed = computed(() => proposal.value.categories.filter((c) => c.rule !== 'unchanged'))
const launchHref = computed(() => `/budget-cycles?proposal=${r.value.cycle.id}`)
</script>

<template>
    <Head title="Bilan du cycle" />

    <AppLayout>
        <div class="max-w-2xl mx-auto space-y-4 pb-28">
            <div class="flex items-center gap-2">
                <Link href="/budget-cycles" class="inline-flex items-center justify-center w-11 h-11 -ml-3 text-theme-text-secondary hover:text-theme-text-primary" aria-label="Retour aux cycles">
                    <ChevronLeftIcon class="w-5 h-5" aria-hidden="true" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-theme-text-primary">Bilan du cycle</h1>
                    <p class="text-xs text-theme-text-muted">
                        {{ shortDate(r.cycle.start) }} → {{ shortDate(r.cycle.end) }} ·
                        <span>{{ isActive ? 'En cours' : 'Clôturé' }}</span>
                    </p>
                </div>
            </div>

            <p v-if="isActive" role="status" class="flex items-center gap-2 text-sm text-theme-text-secondary glass-card px-4 py-3">
                <InformationCircleIcon class="w-5 h-5 shrink-0" aria-hidden="true" /> Cycle en cours : chiffres provisoires au {{ shortDate(r.cycle.end) }}.
            </p>

            <!-- Épargne -->
            <section class="glass-card p-4 space-y-3" aria-labelledby="savings-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="savings-title" class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">Épargne</h2>
                    <span v-if="savingsOk" class="inline-flex items-center gap-1 text-xs font-medium text-success"><CheckCircleIcon class="w-4 h-4" aria-hidden="true" /> Cible atteinte</span>
                    <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-warning"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> Sous la cible</span>
                </div>
                <p class="text-2xl font-semibold tabular-nums text-theme-text-primary">{{ r.savings < 0 ? '−' : '' }}{{ formatAmount(r.savings) }} <span class="text-sm font-normal text-theme-text-muted">FCFA</span></p>
                <div class="h-2 rounded-full bg-theme-surface overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="savingsBar"
                    :aria-label="`Taux d'épargne ${r.savings_rate} %, cible ${r.savings_target_rate} %`">
                    <div class="h-full rounded-full" :class="savingsOk ? 'bg-success' : 'bg-warning'" :style="{ width: `${savingsBar}%` }"></div>
                </div>
                <p class="text-sm text-theme-text-secondary tabular-nums">{{ r.savings_rate }} % des revenus · cible {{ r.savings_target_rate }} %</p>
                <dl class="space-y-1 text-sm tabular-nums">
                    <div class="flex justify-between gap-2"><dt class="text-theme-text-secondary">Revenus</dt><dd class="text-theme-text-primary">{{ formatAmount(r.income) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-theme-text-secondary">Dépenses perso</dt><dd class="text-theme-text-primary">{{ formatAmount(r.personal_spent) }} / {{ formatAmount(r.personal_budget) }}</dd></div>
                    <div v-if="r.goal" class="flex justify-between gap-2">
                        <dt class="text-theme-text-secondary">{{ r.goal.name }}</dt>
                        <dd class="text-theme-text-primary">versé {{ formatAmount(r.goal.month_paid) }}<template v-if="r.goal.month_target"> / {{ formatAmount(r.goal.month_target) }}</template></dd>
                    </div>
                </dl>
            </section>

            <!-- Discrétionnaire -->
            <section class="glass-card p-4 space-y-1" aria-labelledby="disc-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="disc-title" class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">Discrétionnaire</h2>
                    <span v-if="discretionaryOver" class="inline-flex items-center gap-1 text-xs font-medium text-warning"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> Dépassé</span>
                    <span v-else-if="r.discretionary.budget > 0" class="inline-flex items-center gap-1 text-xs font-medium text-success"><CheckCircleIcon class="w-4 h-4" aria-hidden="true" /> Dans le plafond</span>
                </div>
                <p v-if="r.discretionary.spent || r.discretionary.budget" class="text-base tabular-nums text-theme-text-primary">
                    {{ formatAmount(r.discretionary.spent) }}<template v-if="r.discretionary.budget"> / {{ formatAmount(r.discretionary.budget) }}</template>
                    <span v-if="discretionaryOver" class="text-sm text-theme-text-muted"> (+{{ formatAmount(r.discretionary.spent - r.discretionary.budget) }})</span>
                </p>
                <p v-else class="text-sm text-theme-text-muted">Aucune catégorie discrétionnaire. Cochez-les dans <Link href="/budgets" class="underline">Budgets</Link>.</p>
            </section>

            <!-- Catégories -->
            <section class="glass-card p-4 space-y-3" aria-labelledby="cat-title">
                <h2 id="cat-title" class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">Catégories</h2>
                <p v-if="!overruns.length" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary"><CheckCircleIcon class="w-4 h-4" aria-hidden="true" /> Aucun dépassement</p>
                <ul class="space-y-3" aria-label="Catégories du cycle">
                    <li v-for="c in [...overruns, ...(showAll ? others : [])]" :key="c.id" class="space-y-0.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm text-theme-text-primary">{{ c.name }}</span>
                            <span v-if="c.status === 'over'" class="inline-flex items-center gap-1 text-xs font-medium text-danger"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> Dépassé</span>
                            <span v-else-if="c.status === 'ok'" class="inline-flex items-center gap-1 text-xs text-theme-text-secondary"><CheckCircleIcon class="w-4 h-4" aria-hidden="true" /> Dans le plafond</span>
                            <span v-else class="text-xs text-theme-text-muted">Sans plafond</span>
                        </div>
                        <p class="flex flex-wrap justify-between gap-x-3 text-xs tabular-nums text-theme-text-muted">
                            <span>{{ formatAmount(c.spent) }}<template v-if="c.limit"> / {{ formatAmount(c.limit) }}</template></span>
                            <span v-if="c.status === 'over'">Dépassé de {{ formatAmount(c.diff) }}</span>
                            <span v-else-if="c.status === 'ok'">Reste {{ formatAmount(-c.diff) }}</span>
                        </p>
                    </li>
                </ul>
                <button v-if="others.length" type="button" @click="showAll = !showAll" :aria-expanded="showAll"
                    class="min-h-[44px] w-full rounded-md border border-theme-border text-sm text-theme-text-secondary">
                    {{ showAll ? 'Masquer' : `Tout afficher (${others.length})` }}
                </button>
            </section>

            <!-- À part -->
            <section class="glass-card p-4 space-y-1" aria-labelledby="aside-title">
                <h2 id="aside-title" class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">À part (hors budget perso)</h2>
                <dl class="space-y-1 text-sm tabular-nums">
                    <div class="flex justify-between gap-2"><dt class="text-theme-text-secondary">Pro</dt><dd class="text-theme-text-primary">{{ formatAmount(r.pro) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-theme-text-secondary">Transit net</dt><dd class="text-theme-text-primary">{{ r.transit_net > 0 ? '+' : r.transit_net < 0 ? '−' : '' }}{{ formatAmount(r.transit_net) }}</dd></div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-theme-text-secondary">Hors liste</dt>
                        <dd :class="r.out_of_list.count ? 'text-warning' : 'text-theme-text-primary'">
                            <ExclamationTriangleIcon v-if="r.out_of_list.count" class="inline w-4 h-4" aria-hidden="true" />
                            {{ r.out_of_list.count }} achat{{ r.out_of_list.count > 1 ? 's' : '' }} · {{ formatAmount(r.out_of_list.total) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Proposition -->
            <section class="glass-card p-4 space-y-3" aria-labelledby="prop-title">
                <h2 id="prop-title" class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">Proposition · cycle suivant</h2>
                <p v-if="!proposal.has_history" class="text-sm text-theme-text-secondary">
                    Pas assez d'historique pour proposer des plafonds : ajustez-les au lancement.
                </p>
                <template v-else>
                    <div class="flex justify-between gap-2 text-sm tabular-nums">
                        <span class="text-theme-text-secondary">Revenu attendu (moyenne de {{ proposal.cycles }} cycles)</span>
                        <span class="text-theme-text-primary">{{ formatAmount(proposal.expected_income) }}</span>
                    </div>
                    <ul class="space-y-2" aria-label="Plafonds proposés">
                        <li v-for="c in proposal.categories" :key="c.id" class="space-y-0.5">
                            <div class="flex justify-between gap-2 text-sm tabular-nums">
                                <span class="text-theme-text-primary">{{ c.name }}</span>
                                <span class="text-theme-text-primary">
                                    <template v-if="c.current !== null && c.current !== c.proposed"><s class="text-theme-text-muted">{{ formatAmount(c.current) }}</s> → </template>{{ formatAmount(c.proposed) }}
                                </span>
                            </div>
                            <p class="text-xs text-theme-text-muted">{{ c.reason }}</p>
                        </li>
                    </ul>
                    <p v-if="!changed.length" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary"><CheckCircleIcon class="w-4 h-4" aria-hidden="true" /> Plafonds maintenus</p>
                    <p v-if="proposal.over_budget" role="status" class="flex items-start gap-2 text-sm text-warning">
                        <ExclamationTriangleIcon class="w-5 h-5 shrink-0" aria-hidden="true" />
                        Plafonds {{ formatAmount(proposal.total) }} au-dessus de revenu − épargne visée ({{ formatAmount(proposal.expected_income - proposal.savings_target) }}).
                    </p>
                </template>
            </section>
        </div>

        <!-- Action principale, toujours visible -->
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-theme-border bg-theme-card/95 backdrop-blur px-4 pt-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <div class="max-w-2xl mx-auto">
                <p v-if="!r.cycle.is_latest" class="text-xs text-theme-text-muted mb-2 text-center">Un cycle plus récent existe déjà.</p>
                <Link :href="launchHref"
                    class="flex items-center justify-center w-full min-h-[48px] rounded-md text-sm font-medium"
                    :class="r.cycle.is_latest ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text' : 'border border-theme-border text-theme-text-primary'">
                    Lancer le cycle suivant
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
