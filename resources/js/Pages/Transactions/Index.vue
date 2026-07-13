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
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    transactions: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const localFilters = ref({
    type: props.filters.type || '',
    category_id: props.filters.category_id || '',
    account_id: props.filters.account_id || '',
    search: props.filters.search || '',
})

let searchTimeout = null
const onSearchInput = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 400)
}

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

            <!-- Search & Filters -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3">
                <!-- Search -->
                <div class="relative w-full sm:flex-1 sm:min-w-[200px] sm:max-w-sm">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-theme-text-muted pointer-events-none" />
                    <input
                        v-model="localFilters.search"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Rechercher..."
                        class="w-full bg-theme-surface border border-theme-border rounded-lg pl-9 pr-4 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
                    />
                </div>

                <!-- Filter selects (répartis, 2 par ligne sur mobile) -->
                <div class="flex flex-wrap items-center gap-3">
                    <select
                        v-model="localFilters.type"
                        @change="applyFilters"
                        class="flex-1 sm:flex-none min-w-[140px] bg-theme-surface border border-theme-border rounded-lg px-3 py-2 text-sm text-theme-text-primary input-glow focus:ring-0 outline-none transition-all duration-200"
                    >
                        <option value="">Tous types</option>
                        <option value="expense">Dépenses</option>
                        <option value="income">Revenus</option>
                        <option value="transfer">Transferts</option>
                    </select>
                    <select
                        v-model="localFilters.category_id"
                        @change="applyFilters"
                        class="flex-1 sm:flex-none min-w-[140px] bg-theme-surface border border-theme-border rounded-lg px-3 py-2 text-sm text-theme-text-primary input-glow focus:ring-0 outline-none transition-all duration-200"
                    >
                        <option value="">Toutes catégories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                    <select
                        v-model="localFilters.account_id"
                        @change="applyFilters"
                        class="flex-1 sm:flex-none min-w-[140px] bg-theme-surface border border-theme-border rounded-lg px-3 py-2 text-sm text-theme-text-primary input-glow focus:ring-0 outline-none transition-all duration-200"
                    >
                        <option value="">Tous comptes</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">
                            {{ account.name }}
                        </option>
                    </select>
                </div>
                <button
                    v-if="Object.values(localFilters).some(v => v)"
                    @click="clearFilters"
                    class="flex items-center gap-1 text-xs text-theme-text-secondary hover:text-[var(--color-accent)] transition-colors self-start"
                >
                    <XMarkIcon class="w-3.5 h-3.5" />
                    Effacer
                </button>
            </div>

            <!-- Transactions Table -->
            <div class="glass-card">
                <div v-if="transactions.data.length === 0" class="px-4 py-12 text-center">
                    <p class="text-sm text-theme-text-secondary mb-4">Aucune transaction</p>
                    <Link href="/transactions/create" class="text-sm text-theme-text-primary hover:underline">
                        Ajouter une transaction
                    </Link>
                </div>

                <template v-else>
                <!-- Liste en cartes (mobile) : pas de scroll horizontal, troncature propre -->
                <ul class="sm:hidden divide-y divide-theme-border">
                    <li v-for="tx in transactions.data" :key="tx.id" class="flex items-center gap-3 px-4 py-3">
                        <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: tx.category?.color || '#71717A' }"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-theme-text-primary truncate">{{ tx.beneficiary || tx.category?.name || 'Transaction' }}</p>
                            <p class="text-xs text-theme-text-muted truncate">{{ formatDate(tx.date) }} · {{ tx.category?.name || 'Sans catégorie' }}</p>
                        </div>
                        <div class="flex flex-col items-end flex-shrink-0">
                            <span
                                class="text-sm font-medium whitespace-nowrap"
                                :class="{
                                    'text-success': tx.type === 'income',
                                    'text-blue-400': tx.type === 'transfer',
                                    'text-theme-text-primary': tx.type === 'expense',
                                }"
                            >
                                {{ tx.type === 'income' ? '+' : tx.type === 'transfer' ? '' : '-' }}{{ formatAmount(tx.amount) }}
                            </span>
                            <div class="flex items-center gap-0.5 mt-1">
                                <Link :href="`/transactions/${tx.id}/edit`" class="p-1.5 -my-1 rounded-md text-theme-text-secondary hover:text-theme-text-primary" title="Modifier" aria-label="Modifier">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </Link>
                                <button @click="deleteTransaction(tx)" class="p-1.5 -my-1 rounded-md text-theme-text-secondary hover:text-danger" title="Supprimer" aria-label="Supprimer">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- Tableau (tablette / desktop) -->
                <div class="hidden sm:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-theme-border">
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Transaction</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden md:table-cell">Compte</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <tr v-for="tx in transactions.data" :key="tx.id" class="hover:bg-theme-surface transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: tx.category?.color || '#71717A' }"></div>
                                    <div class="min-w-0">
                                        <p class="text-sm text-theme-text-primary">{{ tx.beneficiary || tx.category?.name || 'Transaction' }}</p>
                                        <p class="text-xs text-theme-text-muted">{{ tx.category?.name || 'Sans catégorie' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-secondary hidden md:table-cell">
                                {{ tx.account?.name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-secondary">
                                {{ formatDate(tx.date) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span
                                    class="text-sm font-medium"
                                    :class="{
                                        'text-success': tx.type === 'income',
                                        'text-blue-400': tx.type === 'transfer',
                                        'text-theme-text-primary': tx.type === 'expense',
                                    }"
                                >
                                    {{ tx.type === 'income' ? '+' : tx.type === 'transfer' ? '' : '-' }}{{ formatAmount(tx.amount) }}
                                    <span v-if="tx.type === 'transfer'" class="text-xs text-theme-text-muted block">transfert</span>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="`/transactions/${tx.id}/edit`"
                                        class="p-1.5 rounded-md text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface transition-colors"
                                        title="Modifier"
                                        aria-label="Modifier"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
                                    <button
                                        @click="deleteTransaction(tx)"
                                        class="p-1.5 rounded-md text-theme-text-secondary hover:text-danger hover:bg-theme-surface transition-colors"
                                        title="Supprimer"
                                        aria-label="Supprimer"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                </template>
            </div>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1">
                <!-- Mobile : pager compact (évite le débordement horizontal) -->
                <div class="flex sm:hidden items-center justify-between gap-2">
                    <Link
                        v-if="transactions.prev_page_url"
                        :href="transactions.prev_page_url"
                        preserve-scroll
                        class="px-3 py-1.5 text-sm rounded-md border border-theme-border text-theme-text-secondary hover:text-theme-text-primary"
                    >Précédent</Link>
                    <span v-else class="px-3 py-1.5 text-sm text-theme-text-muted opacity-50">Précédent</span>

                    <span class="text-sm text-theme-text-secondary">Page {{ transactions.current_page }} / {{ transactions.last_page }}</span>

                    <Link
                        v-if="transactions.next_page_url"
                        :href="transactions.next_page_url"
                        preserve-scroll
                        class="px-3 py-1.5 text-sm rounded-md border border-theme-border text-theme-text-secondary hover:text-theme-text-primary"
                    >Suivant</Link>
                    <span v-else class="px-3 py-1.5 text-sm text-theme-text-muted opacity-50">Suivant</span>
                </div>

                <!-- Desktop : numéros de page -->
                <div class="hidden sm:flex items-center justify-center gap-1 flex-wrap">
                    <template v-for="(link, index) in transactions.links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 text-sm rounded-md transition-colors"
                            :class="link.active ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text' : 'text-theme-text-secondary hover:text-theme-text-primary'"
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-3 py-1 text-sm text-theme-text-muted"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
