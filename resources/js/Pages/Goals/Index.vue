<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    FlagIcon,
    BanknotesIcon,
    SparklesIcon,
    ClockIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    goals: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
})

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount || 0)
}

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    })
}

const getProgress = (goal) => {
    if (!goal.target_amount || goal.target_amount === 0) return 0
    return Math.min(100, (goal.current_amount / goal.target_amount) * 100)
}

const deleteGoal = (goal) => {
    if (confirm('Supprimer cet objectif ?')) {
        router.delete(`/goals/${goal.id}`)
    }
}

const showContribute = ref(null)
const contributeForm = useForm({ amount: '' })

const openContribute = (goal) => {
    showContribute.value = goal.id
    contributeForm.amount = ''
}

const submitContribute = (goal) => {
    contributeForm.post(`/goals/${goal.id}/contribute`, {
        onSuccess: () => {
            showContribute.value = null
        }
    })
}

const activeGoals = computed(() => props.goals.filter(g => g.status === 'active'))
const completedGoals = computed(() => props.goals.filter(g => g.status === 'completed'))

const getTypeLabel = (type) => {
    const labels = {
        savings: 'Épargne',
        debt: 'Remboursement',
        investment: 'Investissement',
        custom: 'Personnalisé',
    }
    return labels[type] || type
}
</script>

<template>
    <Head title="Objectifs" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <FlagIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Objectifs</h1>
                </div>
                <Link
                    href="/goals/create"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                >
                    + Nouvel objectif
                </Link>
            </div>

            <!-- Analytics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <BanknotesIcon class="w-4 h-4 text-theme-text-secondary" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Objectif total</p>
                    </div>
                    <p class="text-xl font-semibold text-theme-text-primary">{{ formatAmount(stats.total_target) }}</p>
                    <p class="text-xs text-theme-text-muted">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <SparklesIcon class="w-4 h-4 text-success" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Épargne actuelle</p>
                    </div>
                    <p class="text-xl font-semibold text-success">{{ formatAmount(stats.total_current) }}</p>
                    <p class="text-xs text-theme-text-muted">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ClockIcon class="w-4 h-4 text-theme-text-secondary" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">En cours</p>
                    </div>
                    <p class="text-xl font-semibold text-theme-text-primary">{{ stats.active_count || 0 }}</p>
                    <p class="text-xs text-theme-text-muted">objectif(s)</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <CheckCircleIcon class="w-4 h-4 text-success" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Complétés</p>
                    </div>
                    <p class="text-xl font-semibold text-success">{{ stats.completed_count || 0 }}</p>
                    <p class="text-xs text-theme-text-muted">objectif(s)</p>
                </div>
            </div>

            <!-- Active Goals -->
            <div>
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">En cours</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg">
                    <div v-if="activeGoals.length === 0" class="px-4 py-12 text-center">
                        <p class="text-sm text-theme-text-secondary mb-4">Aucun objectif en cours</p>
                        <Link href="/goals/create" class="text-sm text-theme-text-primary hover:underline">
                            Créer un objectif
                        </Link>
                    </div>

                    <div v-else class="divide-y divide-theme-border">
                        <div v-for="goal in activeGoals" :key="goal.id" class="p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: goal.color || '#FFFFFF' }"></div>
                                    <div>
                                        <p class="text-sm font-medium text-theme-text-primary">{{ goal.name }}</p>
                                        <p class="text-xs text-theme-text-muted">{{ getTypeLabel(goal.type) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="openContribute(goal)"
                                        class="px-2 py-1 text-xs bg-theme-surface border border-theme-border rounded hover:border-white transition-colors"
                                    >
                                        + Ajouter
                                    </button>
                                    <Link :href="`/goals/${goal.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                        Modifier
                                    </Link>
                                    <button @click="deleteGoal(goal)" class="text-xs text-theme-text-secondary hover:text-danger">
                                        Supprimer
                                    </button>
                                </div>
                            </div>

                            <!-- Contribute Form -->
                            <div v-if="showContribute === goal.id" class="mb-3 flex gap-2">
                                <input
                                    v-model.number="contributeForm.amount"
                                    type="number"
                                    min="1"
                                    placeholder="Montant"
                                    class="flex-1 bg-theme-surface border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                                />
                                <button
                                    @click="submitContribute(goal)"
                                    :disabled="contributeForm.processing || !contributeForm.amount"
                                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                                >
                                    Ajouter
                                </button>
                                <button
                                    @click="showContribute = null"
                                    class="px-3 py-1.5 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors"
                                >
                                    Annuler
                                </button>
                            </div>

                            <div class="flex items-center justify-between text-xs mb-2">
                                <span class="text-theme-text-secondary">
                                    {{ formatAmount(goal.current_amount) }} / {{ formatAmount(goal.target_amount) }} FCFA
                                </span>
                                <span class="text-theme-text-muted">{{ Math.round(getProgress(goal)) }}%</span>
                            </div>
                            <div class="w-full h-2 bg-theme-surface rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-theme-btn-primary-bg rounded-full transition-all"
                                    :style="{ width: `${getProgress(goal)}%` }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-theme-text-muted mt-2">
                                <span v-if="goal.target_date">Échéance : {{ formatDate(goal.target_date) }}</span>
                                <span v-else>Pas d'échéance</span>
                                <span>Reste {{ formatAmount(goal.target_amount - goal.current_amount) }} FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completed Goals -->
            <div v-if="completedGoals.length > 0">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">Complétés</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-theme-border">
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Objectif</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Montant</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <tr v-for="goal in completedGoals" :key="goal.id" class="hover:bg-theme-surface transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-success"></div>
                                        <div>
                                            <p class="text-sm text-theme-text-primary">{{ goal.name }}</p>
                                            <p class="text-xs text-theme-text-muted">{{ getTypeLabel(goal.type) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-sm font-medium text-success">{{ formatAmount(goal.target_amount) }} FCFA</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="deleteGoal(goal)" class="text-xs text-theme-text-secondary hover:text-danger">
                                        Supprimer
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
