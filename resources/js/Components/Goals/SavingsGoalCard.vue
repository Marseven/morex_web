<script setup>
import { Link } from '@inertiajs/vue3'
import { CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

// Carte « Objectif » du tableau de bord (design suivi-objectifs, direction A)
const props = defineProps({
    goal: { type: Object, required: true },
})

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount ?? 0)
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const periodLabel = computed(() => props.goal.period_is_cycle ? `Ce cycle (depuis le ${shortDate(props.goal.period_start)})` : 'Ce mois-ci')
const targetLabel = computed(() => props.goal.target_date ? `pour le ${shortDate(props.goal.target_date)}` : '')
</script>

<template>
    <div class="glass-card">
        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-divider">
            <h2 class="text-sm font-medium text-theme-text-primary">Objectif</h2>
            <Link :href="`/goals/${goal.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">Voir</Link>
        </div>
        <div class="p-4 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-theme-text-primary">{{ goal.name }}</span>
                <span v-if="goal.is_achieved" class="inline-flex items-center gap-1 text-xs font-medium text-green-400">
                    <CheckIcon class="w-4 h-4" aria-hidden="true" /> Atteint
                </span>
                <span v-else class="text-sm font-medium text-theme-text-primary">{{ goal.percent }} %</span>
            </div>
            <div class="h-2 rounded-full bg-theme-surface overflow-hidden" role="progressbar"
                aria-valuemin="0" :aria-valuemax="goal.target_amount" :aria-valuenow="goal.current_amount"
                :aria-label="`${goal.name}, ${goal.percent} % de ${formatAmount(goal.target_amount)} francs CFA`">
                <div class="h-full bg-[var(--color-accent)]" :style="{ width: `${goal.percent}%` }"></div>
            </div>
            <p class="text-xs text-theme-text-secondary">{{ formatAmount(goal.current_amount) }} / {{ formatAmount(goal.target_amount) }} FCFA</p>

            <template v-if="!goal.is_achieved">
                <!-- Versement du mois -->
                <p v-if="goal.month_status === 'none'" class="text-sm text-theme-text-secondary">
                    Versé {{ periodLabel.toLowerCase() }} : {{ formatAmount(goal.month_paid) }} FCFA
                </p>
                <p v-else class="flex flex-wrap items-center gap-x-2 text-sm">
                    <span class="text-theme-text-secondary">{{ periodLabel }}</span>
                    <span class="text-theme-text-primary">{{ formatAmount(goal.month_paid) }} / {{ formatAmount(goal.month_target) }}</span>
                    <span v-if="goal.month_status === 'achieved'" class="inline-flex items-center gap-1 text-green-400"><CheckIcon class="w-4 h-4" aria-hidden="true" /> Atteint</span>
                    <span v-else-if="goal.month_status === 'late'" class="inline-flex items-center gap-1 text-yellow-400"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> En retard : reste {{ formatAmount(goal.month_remaining) }}</span>
                    <span v-else class="text-theme-text-secondary">Reste {{ formatAmount(goal.month_remaining) }}</span>
                </p>

                <!-- Projection -->
                <p v-if="goal.required_per_month !== null" class="text-sm" :class="goal.on_track ? 'text-theme-text-secondary' : 'text-yellow-400'">
                    <template v-if="goal.on_track">Rythme suffisant {{ targetLabel }}</template>
                    <template v-else-if="goal.month_target">{{ formatAmount(goal.required_per_month) }} FCFA/mois à verser {{ targetLabel }}</template>
                    <template v-else>Il faudrait {{ formatAmount(goal.required_per_month) }} FCFA/mois {{ targetLabel }}</template>
                </p>
            </template>
        </div>
    </div>
</template>
