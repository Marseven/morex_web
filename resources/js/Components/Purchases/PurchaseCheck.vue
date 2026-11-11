<script setup>
import { computed, ref } from 'vue'
import { ExclamationTriangleIcon, ShoppingBagIcon, CheckIcon } from '@heroicons/vue/24/outline'

// Bloc « achat prévu / hors liste » de la saisie (design achats-prevus, direction A).
// L'app avertit, elle ne bloque pas : le parent demande seulement un choix explicite avant l'envoi.
const props = defineProps({
    amount: { type: Number, default: 0 },
    categoryId: { type: String, default: '' },
    purchases: { type: Array, default: () => [] },
    nextStart: { type: String, default: '' },
})
const purchaseId = defineModel('purchaseId', { type: String, default: '' })
const outOfList = defineModel('outOfList', { type: String, default: '' })
const emit = defineEmits(['postpone'])

const title = ref(null)
const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(n || 0)
const nextLabel = computed(() => props.nextStart ? new Date(`${props.nextStart}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : '')
const candidates = computed(() => props.purchases.filter((p) => !p.category_id || p.category_id === props.categoryId))
const noneOfThem = ref(false)
const showWarning = computed(() => !candidates.value.length || noneOfThem.value)
const decided = computed(() => !!purchaseId.value || outOfList.value === 'accept')

const choose = (id) => {
    noneOfThem.value = id === ''
    purchaseId.value = id
    outOfList.value = ''
}
const buyAnyway = () => {
    purchaseId.value = ''
    outOfList.value = 'accept'
}

defineExpose({
    decided,
    focus: () => title.value?.focus(),
})
</script>

<template>
    <div role="status" class="rounded-md border p-4 space-y-3" :class="showWarning ? 'border-warning/40 bg-warning/10' : 'border-theme-border bg-theme-surface'">
        <template v-if="!showWarning">
            <fieldset>
                <legend ref="title" tabindex="-1" class="flex items-center gap-2 text-sm font-medium text-theme-text-primary outline-none mb-2">
                    <ShoppingBagIcon class="w-5 h-5" aria-hidden="true" />
                    {{ candidates.length === 1 ? `C'est l'achat prévu « ${candidates[0].name} » ?` : "C'est un achat prévu ?" }}
                </legend>
                <div class="space-y-2">
                    <label v-for="p in candidates" :key="p.id" class="flex items-center gap-3 min-h-[44px] px-3 rounded-md border cursor-pointer focus-within:ring-2 focus-within:ring-white/60"
                        :class="purchaseId === p.id ? 'border-white' : 'border-theme-border'">
                        <input type="radio" name="planned_purchase" class="sr-only" :checked="purchaseId === p.id" @change="choose(p.id)" />
                        <span class="flex-1 text-sm text-theme-text-primary">{{ p.name }} <span class="text-theme-text-muted">· {{ formatAmount(p.estimated_amount) }}</span></span>
                        <CheckIcon v-if="purchaseId === p.id" class="w-5 h-5" aria-hidden="true" />
                    </label>
                    <label class="flex items-center gap-3 min-h-[44px] px-3 rounded-md border border-theme-border cursor-pointer focus-within:ring-2 focus-within:ring-white/60">
                        <input type="radio" name="planned_purchase" class="sr-only" :checked="noneOfThem" @change="choose('')" />
                        <span class="flex-1 text-sm text-theme-text-secondary">{{ candidates.length === 1 ? 'Non, autre achat' : "Aucun d'eux" }}</span>
                    </label>
                </div>
            </fieldset>
        </template>

        <template v-else>
            <p ref="title" tabindex="-1" class="flex items-center gap-2 text-sm font-medium text-warning outline-none">
                <ExclamationTriangleIcon class="w-5 h-5" aria-hidden="true" /> Hors liste
            </p>
            <p class="text-sm text-theme-text-secondary">
                {{ formatAmount(amount) }} FCFA n'est pas prévu. La règle dit d'attendre le cycle suivant.
            </p>
            <p v-if="outOfList === 'accept'" class="flex items-center gap-2 text-sm text-theme-text-primary">
                <CheckIcon class="w-5 h-5" aria-hidden="true" /> Hors liste confirmé : la dépense sera enregistrée.
            </p>
            <div v-else class="flex flex-col gap-2">
                <button type="button" @click="emit('postpone')" class="min-h-[44px] w-full rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium">
                    Reporter au cycle suivant<span v-if="nextLabel"> ({{ nextLabel }})</span>
                </button>
                <button type="button" @click="buyAnyway" class="min-h-[44px] w-full rounded-md border border-theme-border text-sm text-theme-text-primary">
                    Acheter quand même
                </button>
            </div>
        </template>
    </div>
</template>
