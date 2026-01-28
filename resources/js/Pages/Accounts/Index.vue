<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { WalletIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    totalBalance: { type: Number, default: 0 },
})

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount)
}

const accountTypeLabels = {
    current: 'Courant',
    checking: 'Courant',
    savings: 'Épargne',
    cash: 'Espèces',
    credit: 'Crédit',
    investment: 'Investissement',
}

const deleteAccount = (account) => {
    if (confirm(`Supprimer le compte "${account.name}" ?`)) {
        router.delete(`/accounts/${account.id}`)
    }
}
</script>

<template>
    <Head title="Comptes" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <WalletIcon class="w-5 h-5 text-theme-text-secondary" />
                    <div>
                        <h1 class="text-lg font-semibold text-theme-text-primary">Comptes</h1>
                        <p class="text-sm text-theme-text-secondary">Solde total : {{ formatAmount(totalBalance) }} FCFA</p>
                    </div>
                </div>
                <Link
                    href="/accounts/create"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                >
                    + Nouveau
                </Link>
            </div>

            <div class="bg-theme-card border border-theme-border rounded-lg">
                <div v-if="accounts.length === 0" class="px-4 py-12 text-center">
                    <p class="text-sm text-theme-text-secondary mb-4">Aucun compte</p>
                    <Link
                        href="/accounts/create"
                        class="text-sm text-theme-text-primary hover:underline"
                    >
                        Créer un compte
                    </Link>
                </div>

                <table v-else class="w-full">
                    <thead>
                        <tr class="border-b border-theme-border">
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Compte</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Solde</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <tr v-for="account in accounts" :key="account.id" class="hover:bg-theme-surface transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: account.color || '#fff' }"></div>
                                    <div>
                                        <p class="text-sm text-theme-text-primary">{{ account.name }}</p>
                                        <span v-if="account.is_default" class="text-xs text-theme-text-muted">Par défaut</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-theme-text-secondary">
                                {{ accountTypeLabels[account.type] || account.type }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <p class="text-sm font-medium text-theme-text-primary">{{ formatAmount(account.balance) }}</p>
                                <p class="text-xs text-theme-text-muted">FCFA</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/accounts/${account.id}/edit`"
                                        class="text-xs text-theme-text-secondary hover:text-theme-text-primary"
                                    >
                                        Modifier
                                    </Link>
                                    <button
                                        @click="deleteAccount(account)"
                                        class="text-xs text-theme-text-secondary hover:text-danger"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
