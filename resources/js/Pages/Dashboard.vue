<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    WalletIcon,
    ArrowsRightLeftIcon,
    FlagIcon,
    CreditCardIcon,
    ChartPieIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    totalBalance: { type: Number, default: 0 },
    periodVariation: { type: Number, default: 0 },
    incomeForPeriod: { type: Number, default: 0 },
    expenseForPeriod: { type: Number, default: 0 },
    accounts: { type: Array, default: () => [] },
    recentTransactions: { type: Array, default: () => [] },
    activeGoals: { type: Array, default: () => [] },
    activeDebts: { type: Array, default: () => [] },
    budgetCategories: { type: Array, default: () => [] },
    currentPeriod: { type: String, default: 'month' },
    periodLabel: { type: String, default: 'Ce mois' },
})

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
    })
}

const periods = [
    { value: 'day', label: 'Jour' },
    { value: 'week', label: 'Semaine' },
    { value: 'month', label: 'Mois' },
    { value: 'year', label: 'Année' },
]

const changePeriod = (period) => {
    router.get('/', { period }, { preserveState: true })
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header with Period Filter -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-theme-text-primary">Vue d'ensemble</h1>
                    <p class="text-sm text-theme-text-secondary">{{ periodLabel }}</p>
                </div>
                <div class="flex gap-1 p-1 bg-theme-card border border-theme-border rounded-md">
                    <button
                        v-for="period in periods"
                        :key="period.value"
                        @click="changePeriod(period.value)"
                        class="px-3 py-1.5 text-xs rounded transition-colors"
                        :class="currentPeriod === period.value
                            ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium'
                            : 'text-theme-text-secondary hover:text-theme-text-primary'"
                    >
                        {{ period.label }}
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <BanknotesIcon class="w-4 h-4 text-theme-text-secondary" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Solde total</p>
                    </div>
                    <p class="text-xl font-semibold text-theme-text-primary">{{ formatAmount(totalBalance) }}</p>
                    <p class="text-xs text-theme-text-muted mt-0.5">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <component :is="periodVariation >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-4 h-4" :class="periodVariation >= 0 ? 'text-success' : 'text-danger'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">{{ periodLabel }}</p>
                    </div>
                    <p class="text-xl font-semibold" :class="periodVariation >= 0 ? 'text-success' : 'text-danger'">
                        {{ periodVariation >= 0 ? '+' : '' }}{{ formatAmount(periodVariation) }}
                    </p>
                    <p class="text-xs text-theme-text-muted mt-0.5">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ArrowTrendingUpIcon class="w-4 h-4 text-success" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Revenus</p>
                    </div>
                    <p class="text-xl font-semibold text-success">{{ formatAmount(incomeForPeriod) }}</p>
                    <p class="text-xs text-theme-text-muted mt-0.5">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ArrowTrendingDownIcon class="w-4 h-4 text-danger" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Dépenses</p>
                    </div>
                    <p class="text-xl font-semibold text-danger">{{ formatAmount(expenseForPeriod) }}</p>
                    <p class="text-xs text-theme-text-muted mt-0.5">FCFA</p>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Accounts -->
                    <div class="bg-theme-card border border-theme-border rounded-lg">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                            <div class="flex items-center gap-2">
                                <WalletIcon class="w-4 h-4 text-theme-text-secondary" />
                                <h2 class="text-sm font-medium text-theme-text-primary">Comptes</h2>
                            </div>
                            <Link href="/accounts" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                Voir tout
                            </Link>
                        </div>
                        <div class="divide-y divide-theme-border">
                            <div v-if="accounts.length === 0" class="px-4 py-8 text-center text-sm text-theme-text-secondary">
                                Aucun compte
                            </div>
                            <div
                                v-for="account in accounts"
                                :key="account.id"
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: account.color || '#71717A' }"></div>
                                    <span class="text-sm text-theme-text-primary">{{ account.name }}</span>
                                </div>
                                <span class="text-sm font-medium text-theme-text-primary">{{ formatAmount(account.balance) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="bg-theme-card border border-theme-border rounded-lg">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                            <div class="flex items-center gap-2">
                                <ArrowsRightLeftIcon class="w-4 h-4 text-theme-text-secondary" />
                                <h2 class="text-sm font-medium text-theme-text-primary">Transactions récentes</h2>
                            </div>
                            <Link href="/transactions" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                Voir tout
                            </Link>
                        </div>
                        <div class="divide-y divide-theme-border">
                            <div v-if="recentTransactions.length === 0" class="px-4 py-8 text-center text-sm text-theme-text-secondary">
                                Aucune transaction
                            </div>
                            <Link
                                v-for="tx in recentTransactions"
                                :key="tx.id"
                                :href="`/transactions/${tx.id}/edit`"
                                class="flex items-center justify-between px-4 py-3 hover:bg-theme-surface transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: tx.category?.color || '#71717A' }"></div>
                                    <div>
                                        <p class="text-sm text-theme-text-primary">{{ tx.beneficiary || tx.category?.name || 'Transaction' }}</p>
                                        <p class="text-xs text-theme-text-muted">{{ formatDate(tx.date) }}</p>
                                    </div>
                                </div>
                                <span
                                    class="text-sm font-medium"
                                    :class="tx.type === 'income' ? 'text-success' : 'text-theme-text-primary'"
                                >
                                    {{ tx.type === 'income' ? '+' : '-' }}{{ formatAmount(tx.amount) }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="space-y-6">
                    <!-- Goals -->
                    <div class="bg-theme-card border border-theme-border rounded-lg">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                            <div class="flex items-center gap-2">
                                <FlagIcon class="w-4 h-4 text-theme-text-secondary" />
                                <h2 class="text-sm font-medium text-theme-text-primary">Objectifs</h2>
                            </div>
                            <Link href="/goals" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                Voir tout
                            </Link>
                        </div>
                        <div class="p-4 space-y-4">
                            <div v-if="!activeGoals || activeGoals.length === 0" class="py-4 text-center text-sm text-theme-text-secondary">
                                Aucun objectif actif
                            </div>
                            <div v-for="goal in activeGoals" :key="goal.id">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-theme-text-primary">{{ goal.name }}</span>
                                    <span class="text-xs text-theme-text-secondary">
                                        {{ Math.round((goal.current_amount / goal.target_amount) * 100) }}%
                                    </span>
                                </div>
                                <div class="h-1.5 bg-theme-surface rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-success rounded-full"
                                        :style="{ width: Math.min(100, (goal.current_amount / goal.target_amount) * 100) + '%' }"
                                    ></div>
                                </div>
                                <p class="text-xs text-theme-text-muted mt-1">
                                    {{ formatAmount(goal.current_amount) }} / {{ formatAmount(goal.target_amount) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Debts -->
                    <div class="bg-theme-card border border-theme-border rounded-lg">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                            <div class="flex items-center gap-2">
                                <CreditCardIcon class="w-4 h-4 text-theme-text-secondary" />
                                <h2 class="text-sm font-medium text-theme-text-primary">Dettes & Créances</h2>
                            </div>
                            <Link href="/debts" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                Voir tout
                            </Link>
                        </div>
                        <div class="p-4 space-y-3">
                            <div v-if="!activeDebts || activeDebts.length === 0" class="py-4 text-center text-sm text-theme-text-secondary">
                                Aucune dette/créance
                            </div>
                            <div v-for="debt in activeDebts" :key="debt.id" class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-theme-text-primary">{{ debt.name }}</p>
                                    <p class="text-xs" :class="debt.type === 'debt' ? 'text-danger' : 'text-success'">
                                        {{ debt.type === 'debt' ? 'Je dois' : 'On me doit' }}
                                    </p>
                                </div>
                                <span class="text-sm font-medium" :class="debt.type === 'debt' ? 'text-danger' : 'text-success'">
                                    {{ formatAmount(debt.current_amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Budgets -->
                    <div class="bg-theme-card border border-theme-border rounded-lg">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                            <div class="flex items-center gap-2">
                                <ChartPieIcon class="w-4 h-4 text-theme-text-secondary" />
                                <h2 class="text-sm font-medium text-theme-text-primary">Budgets</h2>
                            </div>
                            <Link href="/budgets" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                Voir tout
                            </Link>
                        </div>
                        <div class="p-4 space-y-4">
                            <div v-if="!budgetCategories || budgetCategories.length === 0" class="py-4 text-center text-sm text-theme-text-secondary">
                                Aucun budget défini
                            </div>
                            <div v-for="cat in budgetCategories" :key="cat.id">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-theme-text-primary">{{ cat.name }}</span>
                                    <span
                                        class="text-xs"
                                        :class="cat.progress > 90 ? 'text-danger' : 'text-theme-text-secondary'"
                                    >
                                        {{ Math.round(cat.progress) }}%
                                    </span>
                                </div>
                                <div class="h-1.5 bg-theme-surface rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full"
                                        :class="cat.progress > 90 ? 'bg-danger' : cat.progress > 70 ? 'bg-warning' : 'bg-success'"
                                        :style="{ width: Math.min(100, cat.progress) + '%' }"
                                    ></div>
                                </div>
                                <p class="text-xs text-theme-text-muted mt-1">
                                    {{ formatAmount(cat.spent) }} / {{ formatAmount(cat.budget_limit) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
