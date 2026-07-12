<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    ArrowsRightLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    transfers: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const localFilters = ref({
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
    router.get('/transfers', localFilters.value, { preserveState: true })
}

const clearFilters = () => {
    localFilters.value = { account_id: '' }
    router.get('/transfers')
}

const deleteTransfer = (transfer) => {
    if (confirm('Supprimer ce transfert ? Les soldes seront recalculés.')) {
        router.delete(`/transfers/${transfer.id}`)
    }
}
</script>

<template>
    <Head title="Transferts" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <ArrowsRightLeftIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Transferts</h1>
                </div>
                <Link
                    href="/transfers/create"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                >
                    + Nouveau
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <select
                    v-model="localFilters.account_id"
                    @change="applyFilters"
                    class="bg-theme-card border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                >
                    <option value="">Tous les comptes</option>
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

            <!-- Transfers Table -->
            <div class="glass-card">
                <div v-if="transfers.data.length === 0" class="px-4 py-12 text-center">
                    <ArrowsRightLeftIcon class="w-10 h-10 text-theme-text-muted mx-auto mb-3" />
                    <p class="text-sm text-theme-text-secondary mb-4">Aucun transfert</p>
                    <Link href="/transfers/create" class="text-sm text-theme-text-primary hover:underline">
                        Créer un transfert
                    </Link>
                </div>

                <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead>
                        <tr class="border-b border-theme-border">
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">De</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Vers</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden sm:table-cell">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider hidden md:table-cell">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <tr v-for="transfer in transfers.data" :key="transfer.id" class="hover:bg-theme-surface transition-colors">
                            <td class="px-4 py-3">
                                <p class="text-sm text-theme-text-primary">{{ transfer.from_account?.name || '?' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-theme-text-primary">{{ transfer.to_account?.name || '?' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-secondary hidden sm:table-cell">
                                {{ formatDate(transfer.date) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-muted hidden md:table-cell">
                                {{ transfer.description || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-sm font-medium text-blue-400">{{ formatAmount(transfer.amount) }} FCFA</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/transfers/${transfer.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">
                                        Modifier
                                    </Link>
                                    <button @click="deleteTransfer(transfer)" class="text-xs text-theme-text-secondary hover:text-danger">
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="transfers.last_page > 1" class="flex items-center justify-center gap-1">
                <template v-for="(link, index) in transfers.links" :key="index">
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
    </AppLayout>
</template>
