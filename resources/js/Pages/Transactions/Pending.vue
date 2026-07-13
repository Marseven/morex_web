<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { BellAlertIcon, CheckIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

// category choisie par transaction (pré-remplie avec la catégorie actuelle)
const chosenCategory = reactive(
    Object.fromEntries(props.transactions.map(t => [t.id, t.category_id || '']))
)

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount)

const formatDate = (date) => new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
})

const validate = (tx) => {
    router.post(`/transactions/${tx.id}/validate`, {
        category_id: chosenCategory[tx.id] || null,
    }, { preserveScroll: true })
}

const deleteTransaction = (tx) => {
    if (confirm('Supprimer cette transaction ?')) {
        router.delete(`/transactions/${tx.id}`, { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="À valider" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center gap-2">
                <BellAlertIcon class="w-5 h-5 text-theme-text-secondary" />
                <h1 class="text-lg font-semibold text-theme-text-primary">Transactions à valider</h1>
            </div>

            <p class="text-sm text-theme-text-secondary">
                Ces transactions ont été détectées automatiquement (SMS). Vérifiez la catégorie puis validez, ou supprimez celles qui ne vous concernent pas.
            </p>

            <div class="glass-card">
                <div v-if="transactions.length === 0" class="px-4 py-12 text-center">
                    <CheckIcon class="w-10 h-10 text-success mx-auto mb-3" />
                    <p class="text-sm text-theme-text-secondary">Rien à valider — vous êtes à jour.</p>
                </div>

                <ul v-else class="divide-y divide-theme-border">
                    <li v-for="tx in transactions" :key="tx.id" class="p-4 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-theme-text-primary truncate">{{ tx.beneficiary || 'Transaction' }}</p>
                                <p class="text-xs text-theme-text-muted truncate">
                                    {{ formatDate(tx.date) }} · {{ tx.account?.name }}
                                    <span v-if="tx.description"> · {{ tx.description }}</span>
                                </p>
                            </div>
                            <span
                                class="text-sm font-medium whitespace-nowrap flex-shrink-0"
                                :class="tx.type === 'income' ? 'text-success' : 'text-theme-text-primary'"
                            >
                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatAmount(tx.amount) }}
                            </span>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <select
                                v-model="chosenCategory[tx.id]"
                                class="flex-1 bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                            >
                                <option value="">Sans catégorie</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="validate(tx)"
                                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-3 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                                >
                                    <CheckIcon class="w-4 h-4" /> Valider
                                </button>
                                <Link
                                    :href="`/transactions/${tx.id}/edit`"
                                    class="p-2 rounded-md text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface transition-colors"
                                    title="Modifier"
                                    aria-label="Modifier"
                                >
                                    <PencilSquareIcon class="w-4 h-4" />
                                </Link>
                                <button
                                    @click="deleteTransaction(tx)"
                                    class="p-2 rounded-md text-theme-text-secondary hover:text-danger hover:bg-theme-surface transition-colors"
                                    title="Supprimer"
                                    aria-label="Supprimer"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
