<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    ChartPieIcon,
    BanknotesIcon,
    CurrencyDollarIcon,
    CalculatorIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    categories: { type: Array, default: () => [] },
})

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
        </div>
    </AppLayout>
</template>
