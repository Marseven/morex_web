<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    ArrowsRightLeftIcon,
    PencilSquareIcon,
    TrashIcon,
    FunnelIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    transactions: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const localFilters = ref({
    type: props.filters.type || '',
    account_id: props.filters.account_id || '',
})

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    })
}

const applyFilters = () => {
    router.get('/transactions', localFilters.value, { preserveState: true })
}

const clearFilters = () => {
    localFilters.value = {}
    router.get('/transactions')
}

const deleteTransaction = (tx) => {
    if (confirm('Supprimer cette transaction ?')) {
        router.delete(`/transactions/${tx.id}`)
    }
}
</script>

<template>
    <Head title="Transactions" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <ArrowsRightLeftIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Transactions</h1>
                </div>
                <Link
                    href="/transactions/create"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                >
                    + Nouvelle
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <select
                    v-model="localFilters.type"
                    @change="applyFilters"
                    class="bg-theme-card border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                >
                    <option value="">Tous types</option>
                    <option value="expense">Dépenses</option>
                    <option value="income">Revenus</option>
                    <option value="transfer">Transferts</option>
                </select>
                <select
                    v-model="localFilters.account_id"
                    @change="applyFilters"
                    class="bg-theme-card border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                >
                    <option value="">Tous comptes</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ account.name }}
                    </option>
                </select>
                <button
                    v-if="Object.values(localFilters).some(v => v)"
                    @click="clearFilters"
                    class="text-xs text-theme-text-secondary hover:text-theme-text-primary"
                >
                    Effacer
                </button>
            </div>

            <!-- Transactions Table -->
            <div class="bg-theme-card border border-theme-border rounded-lg">
                <div v-if="transactions.data.length === 0" class="px-4 py-12 text-center">
                    <p class="text-sm text-theme-text-secondary mb-4">Aucune transaction</p>
                    <Link href="/transactions/create" class="text-sm text-theme-text-primary hover:underline">
                        Ajouter une transaction
                    </Link>
                </div>

                <table v-else class="w-full">
                    <thead>
                        <tr class="border-b border-theme-border">
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Transaction</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden md:table-cell">Compte</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden sm:table-cell">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <tr v-for="tx in transactions.data" :key="tx.id" class="hover:bg-theme-surface transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: tx.category?.color || '#71717A' }"></div>
                                    <div>
                                        <p class="text-sm text-theme-text-primary">{{ tx.beneficiary || tx.category?.name || 'Transaction' }}</p>
                                        <p class="text-xs text-theme-text-muted">{{ tx.category?.name || 'Sans catégorie' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-secondary hidden md:table-cell">
                                {{ tx.account?.name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-secondary hidden sm:table-cell">
                                {{ formatDate(tx.date) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span
                                    class="text-sm font-medium"
                                    :class="tx.type === 'income' ? 'text-success' : 'text-theme-text-primary'"
                                >
                                    {{ tx.type === 'income' ? '+' : '-' }}{{ formatAmount(tx.amount) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/transactions/${tx.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                        Modifier
                                    </Link>
                                    <button @click="deleteTransaction(tx)" class="text-xs text-theme-text-secondary hover:text-danger">
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1" class="flex justify-center gap-1">
                <Link
                    v-for="page in transactions.last_page"
                    :key="page"
                    :href="`/transactions?page=${page}`"
                    class="px-3 py-1 text-sm rounded-md transition-colors"
                    :class="page === transactions.current_page ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text' : 'text-theme-text-secondary hover:text-theme-text-primary'"
                    preserve-scroll
                >
                    {{ page }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
