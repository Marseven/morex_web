<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler } from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import {
    ChartBarIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    BanknotesIcon,
    CalculatorIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler)

const props = defineProps({
    monthlyData: { type: Array, default: () => [] },
    categoryBreakdown: { type: Array, default: () => [] },
    goalsProgress: { type: Array, default: () => [] },
    budgetComparison: { type: Array, default: () => [] },
    debtSummary: { type: Object, default: () => ({}) },
    savingsRate: { type: Object, default: () => ({}) },
    currentPeriod: { type: String, default: 'month' },
    currentYear: { type: Number, default: 2026 },
    availableYears: { type: Array, default: () => [] },
})

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount || 0)

const periods = [
    { value: 'day', label: 'Jour' },
    { value: 'week', label: 'Semaine' },
    { value: 'month', label: 'Mois' },
    { value: 'year', label: 'Année' },
]

const changePeriod = (period) => {
    router.get('/analytics', { period, year: props.currentYear }, { preserveState: true })
}

const changeYear = (year) => {
    router.get('/analytics', { period: props.currentPeriod, year }, { preserveState: true })
}

const exportReport = (format) => {
    window.location.href = `/analytics/export?period=${props.currentPeriod}&format=${format}`
}

// Chart configurations
const isDark = computed(() => {
    return !document.documentElement.classList.contains('light')
})

const gridColor = computed(() => isDark.value ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)')
const textColor = computed(() => isDark.value ? '#71717A' : '#6B7280')

const monthlyChartData = computed(() => ({
    labels: props.monthlyData.map(d => d.month),
    datasets: [
        {
            label: 'Revenus',
            data: props.monthlyData.map(d => d.income),
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
        },
        {
            label: 'Dépenses',
            data: props.monthlyData.map(d => d.expense),
            borderColor: '#EF4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.4,
        },
    ],
}))

const monthlyChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', labels: { color: textColor.value } },
    },
    scales: {
        x: { grid: { color: gridColor.value }, ticks: { color: textColor.value } },
        y: { grid: { color: gridColor.value }, ticks: { color: textColor.value } },
    },
}))

const categoryChartData = computed(() => ({
    labels: props.categoryBreakdown.slice(0, 6).map(c => c.name),
    datasets: [{
        data: props.categoryBreakdown.slice(0, 6).map(c => c.amount),
        backgroundColor: props.categoryBreakdown.slice(0, 6).map(c => c.color),
        borderWidth: 0,
    }],
}))

const categoryChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right', labels: { color: textColor.value, boxWidth: 12 } },
    },
}))

const budgetChartData = computed(() => ({
    labels: props.budgetComparison.map(b => b.name),
    datasets: [
        {
            label: 'Budget',
            data: props.budgetComparison.map(b => b.budget),
            backgroundColor: 'rgba(113, 113, 122, 0.5)',
        },
        {
            label: 'Dépensé',
            data: props.budgetComparison.map(b => b.spent),
            backgroundColor: props.budgetComparison.map(b => b.isOverBudget ? '#EF4444' : '#10B981'),
        },
    ],
}))

const budgetChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', labels: { color: textColor.value } },
    },
    scales: {
        x: { grid: { color: gridColor.value }, ticks: { color: textColor.value } },
        y: { grid: { color: gridColor.value }, ticks: { color: textColor.value } },
    },
}))
</script>

<template>
    <Head title="Analytics" />
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <ChartBarIcon class="w-5 h-5 text-theme-text-secondary" />
                    <div>
                        <h1 class="text-lg font-semibold text-theme-text-primary">Analytics</h1>
                        <p class="text-sm text-theme-text-secondary">Analyse détaillée de vos finances</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select @change="changeYear($event.target.value)" :value="currentYear" class="bg-theme-card border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none">
                        <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <div class="flex gap-1 p-1 bg-theme-card border border-theme-border rounded-md">
                        <button v-for="period in periods" :key="period.value" @click="changePeriod(period.value)" class="px-3 py-1.5 text-xs rounded transition-colors" :class="currentPeriod === period.value ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium' : 'text-theme-text-secondary hover:text-theme-text-primary'">
                            {{ period.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ArrowTrendingUpIcon class="w-4 h-4 text-success" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Revenus</p>
                    </div>
                    <p class="text-xl font-semibold text-success">{{ formatAmount(savingsRate.income) }}</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ArrowTrendingDownIcon class="w-4 h-4 text-danger" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Dépenses</p>
                    </div>
                    <p class="text-xl font-semibold text-danger">{{ formatAmount(savingsRate.expense) }}</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <BanknotesIcon class="w-4 h-4" :class="savingsRate.savings >= 0 ? 'text-success' : 'text-danger'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Épargne</p>
                    </div>
                    <p class="text-xl font-semibold" :class="savingsRate.savings >= 0 ? 'text-success' : 'text-danger'">{{ formatAmount(savingsRate.savings) }}</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <CalculatorIcon class="w-4 h-4" :class="savingsRate.rate >= savingsRate.target ? 'text-success' : 'text-warning'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Taux d'épargne</p>
                    </div>
                    <p class="text-xl font-semibold" :class="savingsRate.rate >= savingsRate.target ? 'text-success' : 'text-warning'">{{ savingsRate.rate }}%</p>
                    <p class="text-xs text-theme-text-muted">Objectif: {{ savingsRate.target }}%</p>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Monthly Trend -->
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <h3 class="text-sm font-medium text-theme-text-primary mb-4">Évolution mensuelle {{ currentYear }}</h3>
                    <div class="h-64">
                        <Line :data="monthlyChartData" :options="monthlyChartOptions" />
                    </div>
                </div>

                <!-- Category Breakdown -->
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <h3 class="text-sm font-medium text-theme-text-primary mb-4">Répartition des dépenses</h3>
                    <div class="h-64">
                        <Doughnut v-if="categoryBreakdown.length > 0" :data="categoryChartData" :options="categoryChartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm text-theme-text-secondary">Aucune donnée</div>
                    </div>
                </div>
            </div>

            <!-- Budget vs Actual -->
            <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                <h3 class="text-sm font-medium text-theme-text-primary mb-4">Budget vs Réel (Ce mois)</h3>
                <div class="h-64">
                    <Bar v-if="budgetComparison.length > 0" :data="budgetChartData" :options="budgetChartOptions" />
                    <div v-else class="h-full flex items-center justify-center text-sm text-theme-text-secondary">Aucun budget défini</div>
                </div>
            </div>

            <!-- Goals & Debts Summary -->
            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Goals Progress -->
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-theme-text-primary">Progression des objectifs</h3>
                        <Link href="/goals" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">Voir tout</Link>
                    </div>
                    <div v-if="goalsProgress.length === 0" class="py-8 text-center text-sm text-theme-text-secondary">Aucun objectif actif</div>
                    <div v-else class="space-y-4">
                        <div v-for="goal in goalsProgress" :key="goal.id">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-theme-text-primary">{{ goal.name }}</span>
                                <span class="text-xs text-theme-text-secondary">{{ Math.round(goal.progress) }}%</span>
                            </div>
                            <div class="h-2 bg-theme-surface rounded-full overflow-hidden">
                                <div class="h-full bg-success rounded-full" :style="{ width: `${goal.progress}%` }"></div>
                            </div>
                            <p class="text-xs text-theme-text-muted mt-1">{{ formatAmount(goal.current) }} / {{ formatAmount(goal.target) }} FCFA</p>
                        </div>
                    </div>
                </div>

                <!-- Debt Summary -->
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-theme-text-primary">Situation des dettes</h3>
                        <Link href="/debts" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">Voir tout</Link>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-theme-surface rounded-lg">
                            <p class="text-xs text-theme-text-secondary mb-1">Je dois</p>
                            <p class="text-lg font-semibold text-danger">{{ formatAmount(debtSummary.totalDebt) }}</p>
                            <p class="text-xs text-theme-text-muted">{{ debtSummary.debtCount }} dette(s)</p>
                        </div>
                        <div class="p-3 bg-theme-surface rounded-lg">
                            <p class="text-xs text-theme-text-secondary mb-1">On me doit</p>
                            <p class="text-lg font-semibold text-success">{{ formatAmount(debtSummary.totalCredit) }}</p>
                            <p class="text-xs text-theme-text-muted">{{ debtSummary.creditCount }} créance(s)</p>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-theme-surface rounded-lg">
                        <p class="text-xs text-theme-text-secondary mb-1">Position nette</p>
                        <p class="text-xl font-semibold" :class="debtSummary.netPosition >= 0 ? 'text-success' : 'text-danger'">
                            {{ debtSummary.netPosition >= 0 ? '+' : '' }}{{ formatAmount(debtSummary.netPosition) }} FCFA
                        </p>
                    </div>
                </div>
            </div>

            <!-- Category Details -->
            <div class="bg-theme-card border border-theme-border rounded-lg">
                <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                    <h3 class="text-sm font-medium text-theme-text-primary">Détail par catégorie</h3>
                    <button @click="exportReport('csv')" class="px-3 py-1.5 text-xs bg-theme-surface border border-theme-border rounded-md text-theme-text-secondary hover:text-theme-text-primary transition-colors">
                        Exporter CSV
                    </button>
                </div>
                <div v-if="categoryBreakdown.length === 0" class="px-4 py-8 text-center text-sm text-theme-text-secondary">Aucune dépense pour cette période</div>
                <table v-else class="w-full">
                    <thead>
                        <tr class="border-b border-theme-border">
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Catégorie</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden sm:table-cell">Transactions</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <tr v-for="cat in categoryBreakdown" :key="cat.id" class="hover:bg-theme-surface transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></div>
                                    <span class="text-sm text-theme-text-primary">{{ cat.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-theme-text-secondary hidden sm:table-cell">{{ cat.count }}</td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-theme-text-primary">{{ formatAmount(cat.amount) }}</td>
                            <td class="px-4 py-3 text-right text-sm text-theme-text-secondary">{{ cat.percentage }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
