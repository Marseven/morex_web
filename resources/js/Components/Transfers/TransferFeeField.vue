<script setup>
import { computed } from 'vue'
import { ExclamationCircleIcon } from '@heroicons/vue/24/outline'

// Champ « Frais » et effet sur les soldes (design transferts-frais, direction A)
const fee = defineModel({ type: [Number, String], default: 0 })
const props = defineProps({
    amount: { type: [Number, String], default: 0 },
    fromAccount: { type: Object, default: null },
    toAccount: { type: Object, default: null },
    suggested: { type: Boolean, default: false },
    editing: { type: Boolean, default: false },
    error: { type: String, default: '' },
})
const emit = defineEmits(['touched'])

const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(n || 0)
const amountValue = computed(() => Number(props.amount) || 0)
const feeValue = computed(() => Number(fee.value) || 0)
const localError = computed(() => {
    if (props.error) return props.error
    if (feeValue.value < 0) return 'Les frais ne peuvent pas être négatifs.'
    if (amountValue.value > 0 && feeValue.value > amountValue.value) {
        return `Les frais (${formatAmount(feeValue.value)}) ne peuvent pas dépasser le montant (${formatAmount(amountValue.value)}).`
    }
    return ''
})
const help = computed(() => {
    if (props.editing && feeValue.value === 0) return 'Avec 0, la dépense « Frais bancaires » liée sera supprimée.'
    if (props.suggested && feeValue.value > 0 && props.fromAccount && props.toAccount) {
        return `Repris du dernier transfert ${props.fromAccount.name} → ${props.toAccount.name}. Modifiable.`
    }
    return 'Frais facturés par l’opérateur (retrait, GIMAC). Ils deviennent une dépense « Frais bancaires ».'
})
const noFee = () => {
    fee.value = 0
    emit('touched')
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <label for="transfer-fee" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Frais (FCFA)</label>
            <div class="flex gap-2">
                <input
                    id="transfer-fee"
                    v-model.number="fee"
                    type="number"
                    inputmode="numeric"
                    min="0"
                    :aria-invalid="localError ? 'true' : 'false'"
                    aria-describedby="transfer-fee-help transfer-fee-error"
                    class="flex-1 min-h-11 bg-theme-surface border rounded-md px-3 py-2 text-base text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                    :class="localError ? 'border-danger' : 'border-theme-border'"
                    @input="emit('touched')"
                />
                <button type="button" @click="noFee" class="min-h-11 px-3 rounded-md border border-theme-border text-sm text-theme-text-secondary whitespace-nowrap">Sans frais</button>
            </div>
            <p id="transfer-fee-help" class="text-sm text-theme-text-muted mt-1">{{ help }}</p>
            <p v-if="localError" id="transfer-fee-error" role="alert" class="flex items-center gap-1 text-sm text-danger mt-1">
                <ExclamationCircleIcon class="w-4 h-4 shrink-0" aria-hidden="true" /> {{ localError }}
            </p>
        </div>

        <div v-if="fromAccount && toAccount && amountValue > 0" class="glass-card p-3 space-y-1" aria-live="polite">
            <p class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">Effet sur les soldes</p>
            <template v-if="!localError">
                <p class="flex justify-between gap-2 text-sm tabular-nums">
                    <span class="text-theme-text-secondary">{{ fromAccount.name }}</span>
                    <span class="text-theme-text-primary" :aria-label="`${fromAccount.name} : baisse de ${formatAmount(amountValue + feeValue)} francs CFA`">−{{ formatAmount(amountValue + feeValue) }}</span>
                </p>
                <p class="flex justify-between gap-2 text-sm tabular-nums">
                    <span class="text-theme-text-secondary">{{ toAccount.name }}</span>
                    <span class="text-theme-text-primary" :aria-label="`${toAccount.name} : hausse de ${formatAmount(amountValue)} francs CFA`">+{{ formatAmount(amountValue) }}</span>
                </p>
                <p v-if="feeValue > 0" class="text-xs text-theme-text-muted">dont frais {{ formatAmount(feeValue) }}, classés « Frais bancaires »</p>
            </template>
            <p v-else class="text-sm text-theme-text-muted">Corrigez les frais pour voir l'effet sur les soldes.</p>
        </div>
    </div>
</template>
