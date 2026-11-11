<script setup>
import { CheckIcon } from '@heroicons/vue/24/outline'

// Nature d'une catégorie (design revenus-transit, direction A)
const model = defineModel({ type: String, default: 'regular' })
defineProps({
    error: { type: String, default: '' },
})

const natures = [
    { value: 'regular', label: 'Courante', help: 'Revenus et dépenses du quotidien' },
    { value: 'transit', label: 'En transit', help: 'Tontine, remboursement, avance : hors revenus, dépenses et plafonds' },
    { value: 'pro', label: 'Pro (MRTECH)', help: 'Comptée, mais affichée à part du budget perso' },
]
</script>

<template>
    <fieldset :aria-describedby="error ? 'nature-error' : undefined">
        <legend class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nature</legend>
        <div class="space-y-2">
            <label
                v-for="n in natures"
                :key="n.value"
                class="flex items-center gap-3 min-h-[56px] px-3 py-2 rounded-md border cursor-pointer transition-colors focus-within:ring-2 focus-within:ring-white/60"
                :class="model === n.value ? 'border-white bg-theme-surface' : 'border-theme-border'"
            >
                <input v-model="model" type="radio" name="nature" :value="n.value" class="sr-only" />
                <span class="flex-1">
                    <span class="block text-sm text-theme-text-primary">{{ n.label }}</span>
                    <span class="block text-xs text-theme-text-muted">{{ n.help }}</span>
                </span>
                <CheckIcon v-if="model === n.value" class="w-5 h-5 text-theme-text-primary" aria-hidden="true" />
            </label>
        </div>
        <p v-if="error" id="nature-error" role="alert" class="text-xs text-red-400 mt-1">Choisissez une nature valide.</p>
    </fieldset>
</template>
