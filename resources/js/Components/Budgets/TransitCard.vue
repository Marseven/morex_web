<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowDownIcon, ArrowUpIcon, CheckIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

// Carte « En transit » du tableau de bord (design revenus-transit, direction A) :
// tontines, avances et remboursements, hors revenus et dépenses.
const props = defineProps({
    transit: { type: Object, required: true },
    period: { type: Object, default: null },
})

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(Math.abs(amount ?? 0))
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const periodLabel = computed(() => {
    if (!props.period) return ''
    if (props.period.end) return `${props.period.is_cycle ? 'cycle' : 'mois'} jusqu'au ${shortDate(props.period.end)}`
    return `cycle depuis le ${shortDate(props.period.start)}`
})
const empty = computed(() => !props.transit.in && !props.transit.out)
const netText = computed(() => {
    const n = props.transit.net
    if (n > 0) return 'Reçu plus que sorti'
    if (n < 0) return 'Sorti plus que reçu'
    return 'Équilibré'
})
const netLabel = computed(() => {
    const n = props.transit.net
    const sign = n > 0 ? 'plus ' : n < 0 ? 'moins ' : ''
    return `Net en transit, ${sign}${formatAmount(n)} francs CFA, ${netText.value}`
})
</script>

<template>
    <section class="glass-card" aria-labelledby="transit-title">
        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-divider">
            <h2 id="transit-title" class="text-sm font-medium text-theme-text-primary">En transit <span v-if="periodLabel" class="font-normal text-theme-text-muted">· {{ periodLabel }}</span></h2>
            <Link href="/transactions?nature=transit" class="py-3 -my-3 text-xs text-theme-text-secondary hover:text-[var(--color-accent)] transition-colors">Voir tout</Link>
        </div>

        <div class="p-4 space-y-2">
            <p v-if="empty" class="py-2 text-center text-sm text-theme-text-secondary">Aucun mouvement en transit ce cycle.</p>
            <template v-else>
                <div class="flex items-center justify-between text-sm">
                    <span class="inline-flex items-center gap-1 text-theme-text-secondary"><ArrowUpIcon class="w-4 h-4" aria-hidden="true" /> Sorti</span>
                    <span class="text-theme-text-primary">{{ formatAmount(transit.out) }} F</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="inline-flex items-center gap-1 text-theme-text-secondary"><ArrowDownIcon class="w-4 h-4" aria-hidden="true" /> Revenu</span>
                    <span class="text-theme-text-primary">{{ formatAmount(transit.in) }} F</span>
                </div>
                <div class="pt-2 border-t border-theme-divider flex flex-wrap items-center justify-between gap-x-3">
                    <span class="text-sm text-theme-text-secondary">Net</span>
                    <p class="text-base font-semibold" :class="transit.net > 0 ? 'text-success' : transit.net < 0 ? 'text-red-400' : 'text-theme-text-primary'" :aria-label="netLabel">
                        <CheckIcon v-if="transit.net === 0" class="inline w-4 h-4" aria-hidden="true" />
                        {{ transit.net > 0 ? '+' : transit.net < 0 ? '−' : '' }}{{ formatAmount(transit.net) }} F
                        <span class="text-xs font-normal text-theme-text-muted">· {{ netText }}</span>
                    </p>
                </div>
                <p class="text-xs text-theme-text-muted">Hors revenus et dépenses.</p>
            </template>
        </div>
    </section>
</template>
