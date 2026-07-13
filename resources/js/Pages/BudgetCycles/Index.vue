<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { CalendarIcon, ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { ref, computed } from 'vue'

const props = defineProps({
    activeCycle: { type: Object, default: null },
    closures: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    suggestedIncome: { type: Number, default: 0 },
    savingsTargetRate: { type: Number, default: 25 },
})

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

const startingNewCycle = ref(false)
const closingCycle = ref(false)

// Modale d'ajustement des budgets par catégorie au lancement d'un cycle
const showStartModal = ref(false)
const budgetForm = ref([])
const expectedIncome = ref(0)

const openStartModal = () => {
    budgetForm.value = props.categories.map(c => ({
        id: c.id,
        name: c.name,
        color: c.color,
        budget_limit: c.budget_limit ?? 0,
    }))
    expectedIncome.value = props.suggestedIncome || 0
    showStartModal.value = true
}

const totalBudgetPreview = computed(() =>
    budgetForm.value.reduce((sum, c) => sum + (Number(c.budget_limit) || 0), 0)
)

// Cohérence entrées/sorties : épargne prévue = revenu prévu − budgets de dépenses.
const plannedSavings = computed(() => (Number(expectedIncome.value) || 0) - totalBudgetPreview.value)
const plannedSavingsRate = computed(() => {
    const income = Number(expectedIncome.value) || 0
    if (income <= 0) return 0
    return Math.round((plannedSavings.value / income) * 100)
})
const isOverBudget = computed(() => plannedSavings.value < 0)

const confirmStartCycle = () => {
    startingNewCycle.value = true
    router.post('/budget-cycles/start', {
        expected_income: Number(expectedIncome.value) || 0,
        budgets: budgetForm.value.map(c => ({
            id: c.id,
            budget_limit: Number(c.budget_limit) || 0,
        })),
    }, {
        onFinish: () => {
            startingNewCycle.value = false
            showStartModal.value = false
        },
    })
}

const closeCycle = () => {
    if (!confirm(`Clôturer le cycle "${props.activeCycle.period_name}" ?`)) {
        return
    }

    closingCycle.value = true
    router.post('/budget-cycles/close', {}, {
        onFinish: () => closingCycle.value = false
    })
}
</script>

<template>
    <Head title="Cycles Budgétaires" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <CalendarIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Cycles Budgétaires</h1>
                </div>
                <button
                    v-if="!activeCycle"
                    @click="openStartModal"
                    :disabled="startingNewCycle"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                >
                    {{ startingNewCycle ? 'Démarrage...' : '+ Nouveau cycle' }}
                </button>
            </div>

            <!-- Cycle actif -->
            <div v-if="activeCycle" class="glass-card p-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs text-theme-text-muted uppercase tracking-wider mb-1">Cycle actif</p>
                        <h2 class="text-lg font-semibold text-theme-text-primary">{{ activeCycle.period_name }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="closeCycle"
                            :disabled="closingCycle"
                            class="px-3 py-1.5 text-sm border border-theme-border rounded-md text-theme-text-secondary hover:text-theme-text-primary transition-colors disabled:opacity-50"
                        >
                            {{ closingCycle ? 'Clôture...' : 'Clôturer' }}
                        </button>
                        <button
                            @click="openStartModal"
                            :disabled="startingNewCycle"
                            class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                        >
                            {{ startingNewCycle ? 'Démarrage...' : 'Nouveau cycle' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-theme-text-muted mb-1">Début</p>
                        <p class="text-theme-text-primary">{{ formatDate(activeCycle.start_date) }}</p>
                    </div>
                    <div v-if="activeCycle.expected_income">
                        <p class="text-theme-text-muted mb-1">Revenu prévu</p>
                        <p class="text-theme-text-primary">{{ formatAmount(activeCycle.expected_income) }} FCFA</p>
                    </div>
                    <div>
                        <p class="text-theme-text-muted mb-1">Budget total</p>
                        <p class="text-theme-text-primary">{{ formatAmount(activeCycle.total_budget) }} FCFA</p>
                    </div>
                    <div>
                        <p class="text-theme-text-muted mb-1">Dépensé</p>
                        <p class="text-theme-text-primary">{{ formatAmount(activeCycle.total_spent) }} FCFA</p>
                    </div>
                </div>
            </div>

            <!-- Message si pas de cycle actif -->
            <div v-else class="glass-card p-8 text-center">
                <ArrowPathIcon class="w-12 h-12 text-theme-text-muted mx-auto mb-3" />
                <p class="text-sm text-theme-text-secondary mb-4">Aucun cycle budgétaire actif</p>
                <button
                    @click="openStartModal"
                    :disabled="startingNewCycle"
                    class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                >
                    {{ startingNewCycle ? 'Démarrage...' : 'Démarrer un cycle' }}
                </button>
            </div>

            <!-- Historique des clôtures -->
            <div v-if="closures.length > 0">
                <h2 class="text-sm font-semibold text-theme-text-primary mb-3">Historique des clôtures</h2>
                <div class="glass-card divide-y divide-theme-border">
                    <div v-for="closure in closures" :key="closure.id" class="p-4 hover:bg-theme-surface transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-theme-text-primary mb-2">{{ closure.period_name }}</h3>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                    <div>
                                        <p class="text-theme-text-muted mb-1">Revenus</p>
                                        <p class="text-green-400">+{{ formatAmount(closure.total_income) }} FCFA</p>
                                    </div>
                                    <div>
                                        <p class="text-theme-text-muted mb-1">Dépenses</p>
                                        <p class="text-red-400">-{{ formatAmount(closure.total_spent) }} FCFA</p>
                                    </div>
                                    <div>
                                        <p class="text-theme-text-muted mb-1">Épargné</p>
                                        <p :class="closure.total_saved >= 0 ? 'text-green-400' : 'text-red-400'">
                                            {{ closure.total_saved >= 0 ? '+' : '' }}{{ formatAmount(closure.total_saved) }} FCFA
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <span v-if="closure.total_saved >= 0" class="px-2 py-1 text-xs bg-green-400/10 text-green-400 rounded">
                                    Excédent
                                </span>
                                <span v-else class="px-2 py-1 text-xs bg-red-400/10 text-red-400 rounded">
                                    Déficit
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modale : ajustement des budgets par catégorie au lancement du cycle -->
        <div
            v-if="showStartModal"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showStartModal = false"></div>

            <div class="relative glass-card w-full sm:max-w-lg max-h-[90vh] flex flex-col rounded-t-2xl sm:rounded-2xl">
                <!-- En-tête -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                    <div>
                        <h2 class="text-base font-semibold text-theme-text-primary">Nouveau cycle budgétaire</h2>
                        <p v-if="activeCycle" class="text-xs text-theme-text-muted mt-0.5">
                            Le cycle actuel sera clôturé automatiquement.
                        </p>
                    </div>
                    <button @click="showStartModal = false" class="text-theme-text-secondary hover:text-theme-text-primary">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Liste des catégories avec budget éditable -->
                <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                    <!-- Revenu prévu du cycle -->
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-1">Revenu prévu du cycle</label>
                        <div class="relative">
                            <input
                                v-model.number="expectedIncome"
                                type="number"
                                min="0"
                                class="w-full bg-theme-surface border border-theme-border rounded-md pl-3 pr-12 py-2 text-sm text-right text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-theme-text-muted pointer-events-none">FCFA</span>
                        </div>
                    </div>

                    <div class="h-px bg-theme-divider"></div>

                    <p class="text-xs text-theme-text-secondary">Ajustez le budget alloué à chaque catégorie pour ce cycle.</p>

                    <div v-if="budgetForm.length === 0" class="text-sm text-theme-text-muted py-6 text-center">
                        Aucune catégorie de dépense.
                    </div>

                    <div
                        v-for="cat in budgetForm"
                        :key="cat.id"
                        class="flex items-center gap-3"
                    >
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: cat.color || '#71717A' }"></div>
                        <label class="flex-1 min-w-0 text-sm text-theme-text-primary truncate">{{ cat.name }}</label>
                        <div class="relative flex-shrink-0 w-36">
                            <input
                                v-model.number="cat.budget_limit"
                                type="number"
                                min="0"
                                class="w-full bg-theme-surface border border-theme-border rounded-md pl-3 pr-12 py-2 text-sm text-right text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-theme-text-muted pointer-events-none">FCFA</span>
                        </div>
                    </div>
                </div>

                <!-- Pied : cohérence entrées/sorties + actions -->
                <div class="border-t border-theme-border px-4 py-3 space-y-3">
                    <div class="space-y-1.5 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-theme-text-secondary">Budget dépenses</span>
                            <span class="text-theme-text-primary">{{ formatAmount(totalBudgetPreview) }} FCFA</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-theme-text-secondary">Épargne prévue</span>
                            <span class="font-semibold" :class="isOverBudget ? 'text-danger' : 'text-success'">
                                {{ isOverBudget ? '' : '+' }}{{ formatAmount(plannedSavings) }} FCFA
                                <span class="text-xs font-normal text-theme-text-muted">({{ plannedSavingsRate }}%)</span>
                            </span>
                        </div>
                        <!-- Repère : cible d'épargne / dépassement -->
                        <p v-if="isOverBudget" class="text-xs text-danger">
                            ⚠️ Vos budgets dépassent le revenu prévu. Réduisez des catégories pour équilibrer.
                        </p>
                        <p v-else-if="plannedSavingsRate < savingsTargetRate" class="text-xs text-warning">
                            Épargne sous la cible de {{ savingsTargetRate }}%. Réduisez des dépenses pour l'atteindre.
                        </p>
                        <p v-else class="text-xs text-success">
                            👍 Épargne au-dessus de la cible de {{ savingsTargetRate }}%.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="showStartModal = false"
                            class="flex-1 py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors"
                        >
                            Annuler
                        </button>
                        <button
                            @click="confirmStartCycle"
                            :disabled="startingNewCycle"
                            class="flex-1 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                        >
                            {{ startingNewCycle ? 'Démarrage...' : 'Démarrer le cycle' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
