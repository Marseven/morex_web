<script setup>
import { Link } from '@inertiajs/vue3'
import { CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

// Carte « Achats prévus » du tableau de bord (design achats-prevus, direction A)
const props = defineProps({
    summary: { type: Object, required: true },
    period: { type: Object, default: null },
})

const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(Math.abs(n ?? 0))
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const periodLabel = computed(() => {
    if (!props.period) return ''
    if (props.period.end) return `${props.period.is_cycle ? 'cycle' : 'mois'} jusqu'au ${shortDate(props.period.end)}`
    return `cycle depuis le ${shortDate(props.period.start)}`
})
const s = computed(() => props.summary)
const empty = computed(() => !s.value.planned_count && !s.value.out_of_list_count && !s.value.late_count)
const progress = computed(() => s.value.planned_total ? Math.min(100, Math.round(s.value.spent_total * 100 / s.value.planned_total)) : 0)
const allDone = computed(() => s.value.planned_count > 0 && s.value.bought_count === s.value.planned_count)
</script>

<template>
    <section id="achats" class="glass-card" aria-labelledby="purchases-title">
        <div class="flex items-center justify-between px-4 py-3 border-b border-theme-divider">
            <h2 id="purchases-title" class="text-sm font-medium text-theme-text-primary">Achats prévus <span v-if="periodLabel" class="font-normal text-theme-text-muted">· {{ periodLabel }}</span></h2>
            <Link href="/purchases" class="py-3 -my-3 text-xs text-theme-text-secondary hover:text-[var(--color-accent)] transition-colors">Voir tout</Link>
        </div>

        <div class="p-4 space-y-2">
            <p v-if="empty" class="py-2 text-center text-sm text-theme-text-secondary">
                Aucun achat prévu ce cycle. <Link href="/purchases" class="underline">Prévoir un achat</Link>
            </p>
            <template v-else>
                <template v-if="s.planned_count">
                    <p class="text-sm text-theme-text-primary">{{ s.bought_count }}/{{ s.planned_count }} faits</p>
                    <div class="h-2 rounded-full bg-theme-surface overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress"
                        :aria-label="`${s.bought_count} achats faits sur ${s.planned_count}, ${formatAmount(s.spent_total)} sur ${formatAmount(s.planned_total)} francs CFA`">
                        <div class="h-full rounded-full bg-[var(--color-accent)]" :style="{ width: `${progress}%` }"></div>
                    </div>
                    <p class="text-xs text-theme-text-muted">{{ formatAmount(s.spent_total) }} / {{ formatAmount(s.planned_total) }}<template v-if="s.planned_total > s.spent_total"> · reste {{ formatAmount(s.planned_total - s.spent_total) }}</template></p>
                    <p v-if="allDone" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary"><CheckIcon class="w-4 h-4" aria-hidden="true" /> Tout est acheté</p>
                </template>
                <p v-if="s.out_of_list_count" class="flex items-center gap-1 text-sm text-warning">
                    <ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> {{ s.out_of_list_count }} hors liste · {{ formatAmount(s.out_of_list_total) }}
                </p>
                <p v-else class="text-xs text-theme-text-muted">Aucun achat hors liste</p>
                <p v-if="s.late_count" class="flex items-center gap-1 text-sm text-red-400">
                    <ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> {{ s.late_count }} en retard
                </p>
            </template>
        </div>
    </section>
</template>
