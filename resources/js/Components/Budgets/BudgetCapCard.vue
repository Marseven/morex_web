<script setup>
import { Link } from '@inertiajs/vue3'
import { CheckIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

// Carte « Plafonds » du tableau de bord (design plafonds-alertes, direction A)
const props = defineProps({
    caps: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
    period: { type: Object, default: null },
})

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(Math.abs(amount ?? 0))
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const overCount = computed(() => props.caps.filter((c) => c.status === 'over').length)
const allFine = computed(() => props.caps.length > 0 && props.caps.every((c) => c.status === 'ok'))
const periodLabel = computed(() => {
    if (!props.period) return ''
    if (props.period.end) return `${props.period.is_cycle ? 'cycle' : 'mois'} jusqu'au ${shortDate(props.period.end)}`
    return `cycle depuis le ${shortDate(props.period.start)}`
})
const statusText = (c) => c.status === 'over' ? `dépassé, ${formatAmount(-c.remaining)} au-delà` : c.status === 'warning' ? `attention, reste ${formatAmount(c.remaining)}` : `reste ${formatAmount(c.remaining)}`
</script>

<template>
    <div id="plafonds" class="glass-card">
        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-divider">
            <h2 class="text-sm font-medium text-theme-text-primary">Plafonds <span v-if="periodLabel" class="font-normal text-theme-text-muted">· {{ periodLabel }}</span></h2>
            <Link href="/budgets" class="text-xs text-theme-text-secondary hover:text-[var(--color-accent)] transition-colors">Voir tout</Link>
        </div>

        <div class="p-4 space-y-4">
            <p v-if="!caps.length" class="py-2 text-center text-sm text-theme-text-secondary">
                Aucun plafond défini. <Link href="/budgets" class="underline">Définir un plafond</Link>
            </p>
            <p v-else-if="overCount" role="status" class="text-sm text-red-400">{{ overCount }} plafond(s) dépassé(s)</p>
            <p v-else-if="allFine" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary"><CheckIcon class="w-4 h-4" aria-hidden="true" /> Tout est dans les clous</p>

            <div v-for="cap in caps" :key="cap.id" class="space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm text-theme-text-primary">{{ cap.name }}</span>
                    <span v-if="cap.status === 'over'" class="inline-flex items-center gap-1 text-xs font-medium text-red-400"><XCircleIcon class="w-4 h-4" aria-hidden="true" /> Dépassé · {{ cap.percent }} %</span>
                    <span v-else-if="cap.status === 'warning'" class="inline-flex items-center gap-1 text-xs font-medium text-yellow-400"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> Attention · {{ cap.percent }} %</span>
                    <span v-else class="text-xs text-theme-text-secondary">{{ cap.percent }} %</span>
                </div>
                <div class="h-2 rounded-full bg-theme-surface overflow-hidden" role="progressbar"
                    aria-valuemin="0" :aria-valuemax="cap.budget_limit" :aria-valuenow="Math.min(cap.spent, cap.budget_limit)"
                    :aria-label="`${cap.name}, ${cap.percent} %, ${statusText(cap)} francs CFA`">
                    <div class="h-full rounded-full" :class="cap.status === 'over' ? 'bg-danger' : cap.status === 'warning' ? 'bg-warning' : 'bg-[var(--color-accent)]'" :style="{ width: `${cap.progress}%` }"></div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-x-3 text-xs text-theme-text-muted">
                    <span>
                        {{ formatAmount(cap.spent) }} / {{ formatAmount(cap.budget_limit) }}
                        · <template v-if="cap.remaining < 0">+{{ formatAmount(cap.remaining) }} au-delà</template><template v-else>reste {{ formatAmount(cap.remaining) }}</template>
                    </span>
                    <Link v-if="cap.status !== 'ok'" :href="`/transactions?category_id=${cap.id}`" class="py-2 underline text-theme-text-secondary" :aria-label="`Voir les opérations ${cap.name}`">Voir les opérations</Link>
                </div>
            </div>

            <div v-if="caps.length" class="pt-3 border-t border-theme-divider">
                <h3 class="text-xs font-medium text-theme-text-secondary mb-2">Alertes du cycle</h3>
                <p v-if="!alerts.length" class="text-xs text-theme-text-muted">Aucune alerte ce cycle.</p>
                <ul v-else class="space-y-1">
                    <li v-for="a in alerts" :key="a.id" class="flex flex-wrap items-center gap-x-2 text-xs text-theme-text-secondary">
                        <XCircleIcon v-if="a.threshold === 100" class="w-4 h-4 text-red-400" aria-hidden="true" />
                        <ExclamationTriangleIcon v-else class="w-4 h-4 text-yellow-400" aria-hidden="true" />
                        <time :datetime="a.created_at">{{ shortDate(a.created_at) }}</time>
                        <span class="text-theme-text-primary">{{ a.category }}</span>
                        <span>{{ a.threshold === 100 ? 'Dépassé' : '80 %' }}</span>
                        <span>{{ formatAmount(a.spent) }} / {{ formatAmount(a.limit) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
