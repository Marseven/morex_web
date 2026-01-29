<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    ChartPieIcon,
    BanknotesIcon,
    CurrencyDollarIcon,
    CalculatorIcon,
    ExclamationTriangleIcon,
    LockClosedIcon,
    ClockIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    currentMonthClosed: { type: Boolean, default: false },
    closures: { type: Array, default: () => [] },
    currentMonth: { type: Object, default: () => ({}) },
})

const isClosing = ref(false)
const showConfirmModal = ref(false)

const closeMonth = () => {
    isClosing.value = true
    router.post('/budgets/close-month', {}, {
        onFinish: () => {
            isClosing.value = false
            showConfirmModal.value = false
        }
    })
}

const getMonthName = (month, year) => {
    const months = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ]
    return months[month - 1] + ' ' + year
}

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount || 0)
}

const expenseCategories = props.categories.filter(c => c.type === 'expense')
const incomeCategories = props.categories.filter(c => c.type === 'income')

const deleteCategory = (cat) => {
    if (cat.is_system) {
        alert('Les catégories système ne peuvent pas être supprimées.')
        return
    }
    if (confirm('Supprimer cette catégorie ?')) {
        router.delete(`/budgets/${cat.id}`)
    }
}

const getBudgetProgress = (cat) => {
    if (!cat.budget_limit || cat.budget_limit === 0) return 0
    return Math.min(100, ((cat.spent_this_month || 0) / cat.budget_limit) * 100)
}

const totalBudget = expenseCategories.reduce((sum, c) => sum + (c.budget_limit || 0), 0)
const totalSpent = expenseCategories.reduce((sum, c) => sum + (c.spent_this_month || 0), 0)
const categoriesWithBudget = expenseCategories.filter(c => c.budget_limit > 0)
const overBudgetCount = categoriesWithBudget.filter(c => (c.spent_this_month || 0) > c.budget_limit).length
</script>

<template>
    <Head title="Budgets & Catégories" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <ChartPieIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Budgets & Catégories</h1>
                </div>
                <Link
                    href="/budgets/create"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                >
                    + Nouvelle catégorie
                </Link>
            </div>

            <!-- Current Month Info & Close Button -->
            <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <ClockIcon class="w-5 h-5 text-theme-text-secondary" />
                        <div>
                            <p class="text-sm font-medium text-theme-text-primary">{{ currentMonth.name }}</p>
                            <p class="text-xs text-theme-text-muted">Période budgétaire en cours</p>
                        </div>
                    </div>
                    <div v-if="currentMonthClosed" class="flex items-center gap-2 text-success">
                        <CheckCircleIcon class="w-5 h-5" />
                        <span class="text-sm font-medium">Clôturé</span>
                    </div>
                    <button
                        v-else
                        @click="showConfirmModal = true"
                        :disabled="categoriesWithBudget.length === 0"
                        class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <LockClosedIcon class="w-4 h-4" />
                        Clôturer le mois
                    </button>
                </div>
            </div>

            <!-- Analytics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <BanknotesIcon class="w-4 h-4 text-theme-text-secondary" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Budget total</p>
                    </div>
                    <p class="text-xl font-semibold text-theme-text-primary">{{ formatAmount(totalBudget) }}</p>
                    <p class="text-xs text-theme-text-muted">FCFA / mois</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <CurrencyDollarIcon class="w-4 h-4 text-theme-text-secondary" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Dépensé ce mois</p>
                    </div>
                    <p class="text-xl font-semibold text-theme-text-primary">{{ formatAmount(totalSpent) }}</p>
                    <p class="text-xs text-theme-text-muted">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <CalculatorIcon class="w-4 h-4" :class="totalBudget - totalSpent >= 0 ? 'text-success' : 'text-danger'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Restant</p>
                    </div>
                    <p class="text-xl font-semibold" :class="totalBudget - totalSpent >= 0 ? 'text-success' : 'text-danger'">
                        {{ formatAmount(totalBudget - totalSpent) }}
                    </p>
                    <p class="text-xs text-theme-text-muted">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ExclamationTriangleIcon class="w-4 h-4" :class="overBudgetCount > 0 ? 'text-danger' : 'text-success'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Dépassements</p>
                    </div>
                    <p class="text-xl font-semibold" :class="overBudgetCount > 0 ? 'text-danger' : 'text-success'">
                        {{ overBudgetCount }}
                    </p>
                    <p class="text-xs text-theme-text-muted">catégorie(s)</p>
                </div>
            </div>

            <!-- Expense Categories -->
            <div>
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">Dépenses</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg">
                    <div v-if="expenseCategories.length === 0" class="px-4 py-8 text-center">
                        <p class="text-sm text-theme-text-secondary">Aucune catégorie de dépense</p>
                    </div>

                    <table v-else class="w-full">
                        <thead>
                            <tr class="border-b border-theme-border">
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Catégorie</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden sm:table-cell">Budget</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden md:table-cell">Progression</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <tr v-for="cat in expenseCategories" :key="cat.id" class="hover:bg-theme-surface transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: cat.color || '#71717A' }"></div>
                                        <div>
                                            <p class="text-sm text-theme-text-primary">{{ cat.name }}</p>
                                            <p v-if="cat.is_system" class="text-xs text-theme-text-muted">Système</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">
                                    <span v-if="cat.budget_limit" class="text-sm text-theme-text-primary">{{ formatAmount(cat.budget_limit) }} FCFA</span>
                                    <span v-else class="text-sm text-theme-text-muted">—</span>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    <div v-if="cat.budget_limit" class="space-y-1">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-theme-text-secondary">{{ formatAmount(cat.spent_this_month) }} / {{ formatAmount(cat.budget_limit) }}</span>
                                            <span :class="getBudgetProgress(cat) > 100 ? 'text-danger' : 'text-theme-text-muted'">
                                                {{ Math.round(getBudgetProgress(cat)) }}%
                                            </span>
                                        </div>
                                        <div class="w-40 h-1.5 bg-theme-surface rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getBudgetProgress(cat) > 100 ? 'bg-danger' : getBudgetProgress(cat) > 80 ? 'bg-warning' : 'bg-theme-btn-primary-bg'"
                                                :style="{ width: `${Math.min(100, getBudgetProgress(cat))}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                    <span v-else class="text-sm text-theme-text-muted">Pas de budget</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="`/budgets/${cat.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                            Modifier
                                        </Link>
                                        <button
                                            v-if="!cat.is_system"
                                            @click="deleteCategory(cat)"
                                            class="text-xs text-theme-text-secondary hover:text-danger"
                                        >
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Income Categories -->
            <div>
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">Revenus</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg">
                    <div v-if="incomeCategories.length === 0" class="px-4 py-8 text-center">
                        <p class="text-sm text-theme-text-secondary">Aucune catégorie de revenu</p>
                    </div>

                    <table v-else class="w-full">
                        <thead>
                            <tr class="border-b border-theme-border">
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Catégorie</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <tr v-for="cat in incomeCategories" :key="cat.id" class="hover:bg-theme-surface transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: cat.color || '#71717A' }"></div>
                                        <div>
                                            <p class="text-sm text-theme-text-primary">{{ cat.name }}</p>
                                            <p v-if="cat.is_system" class="text-xs text-theme-text-muted">Système</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="`/budgets/${cat.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                            Modifier
                                        </Link>
                                        <button
                                            v-if="!cat.is_system"
                                            @click="deleteCategory(cat)"
                                            class="text-xs text-theme-text-secondary hover:text-danger"
                                        >
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historique des clôtures -->
            <div v-if="closures.length > 0">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">Historique des clôtures</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg divide-y divide-theme-border">
                    <div
                        v-for="closure in closures"
                        :key="closure.id"
                        class="px-4 py-3 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <CheckCircleIcon class="w-4 h-4" :class="closure.total_saved >= 0 ? 'text-success' : 'text-danger'" />
                            <div>
                                <p class="text-sm font-medium text-theme-text-primary">{{ getMonthName(closure.month, closure.year) }}</p>
                                <p class="text-xs text-theme-text-muted">
                                    Revenus: {{ formatAmount(closure.total_budget) }} FCFA
                                    · Dépenses: {{ formatAmount(closure.total_spent) }} FCFA
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold" :class="closure.total_saved >= 0 ? 'text-success' : 'text-danger'">
                                {{ closure.total_saved >= 0 ? '+' : '' }}{{ formatAmount(closure.total_saved) }} FCFA
                            </p>
                            <p class="text-xs text-theme-text-muted">{{ closure.total_saved >= 0 ? 'excédent' : 'déficit' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Teleport to="body">
            <div
                v-if="showConfirmModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
                @click.self="showConfirmModal = false"
            >
                <div class="bg-theme-card border border-theme-border rounded-xl max-w-md w-full p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-warning/20 flex items-center justify-center">
                            <ExclamationTriangleIcon class="w-5 h-5 text-warning" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-theme-text-primary">Clôturer le mois</h3>
                            <p class="text-sm text-theme-text-secondary">{{ currentMonth.name }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm text-theme-text-secondary">
                            Cette action va calculer vos économies du mois et les transférer vers le compte "Budget économisé".
                        </p>

                        <div class="bg-theme-surface rounded-lg p-3 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-theme-text-secondary">Budget total</span>
                                <span class="text-theme-text-primary font-medium">{{ formatAmount(totalBudget) }} FCFA</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-theme-text-secondary">Dépensé</span>
                                <span class="text-theme-text-primary font-medium">{{ formatAmount(totalSpent) }} FCFA</span>
                            </div>
                            <div class="border-t border-theme-border pt-2 flex justify-between text-sm">
                                <span class="text-theme-text-secondary font-medium">Économies</span>
                                <span class="font-semibold" :class="totalBudget - totalSpent >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatAmount(Math.max(0, totalBudget - totalSpent)) }} FCFA
                                </span>
                            </div>
                        </div>

                        <p class="text-xs text-theme-text-muted">
                            Cette action est irréversible pour ce mois.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            @click="showConfirmModal = false"
                            class="flex-1 px-4 py-2 border border-theme-border text-theme-text-primary text-sm font-medium rounded-md hover:bg-theme-surface transition-colors"
                        >
                            Annuler
                        </button>
                        <button
                            @click="closeMonth"
                            :disabled="isClosing"
                            class="flex-1 px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="isClosing" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isClosing ? 'Clôture...' : 'Confirmer' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
