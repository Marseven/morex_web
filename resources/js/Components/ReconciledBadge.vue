<script setup>
import { Link } from '@inertiajs/vue3'
import { CheckIcon, ClockIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

// Badge de rapprochement (design rapprochement) : icône + texte, jamais la couleur seule.
const props = defineProps({
    accountId: { type: String, required: true },
    lastReconciledAt: { type: String, default: null },
})

const days = computed(() => {
    if (!props.lastReconciledAt) return null
    const ms = Date.now() - new Date(props.lastReconciledAt.replace(' ', 'T')).getTime()
    return Math.max(0, Math.floor(ms / 86400000))
})
const stale = computed(() => days.value !== null && days.value > 7)
const text = computed(() => {
    if (days.value === null) return 'Jamais rapproché'
    if (days.value === 0) return "Rapproché aujourd'hui"
    return `Rapproché il y a ${days.value} j`
})
</script>

<template>
    <Link
        :href="`/accounts/${accountId}/reconcile`"
        class="inline-flex items-center gap-1 text-xs hover:underline"
        :class="stale ? 'text-yellow-400' : 'text-theme-text-muted'"
        :aria-label="`${text}. Rapprocher ce compte`"
    >
        <QuestionMarkCircleIcon v-if="days === null" class="w-3.5 h-3.5" aria-hidden="true" />
        <ClockIcon v-else-if="stale" class="w-3.5 h-3.5" aria-hidden="true" />
        <CheckIcon v-else class="w-3.5 h-3.5" aria-hidden="true" />
        <span :class="{ underline: days === null }">{{ text }}</span>
    </Link>
</template>
