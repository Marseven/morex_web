<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { WalletIcon } from '@heroicons/vue/24/outline'
import { ref } from 'vue'

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

// Reconciliation toggle
const showDetails = ref(false)

// Adjust modal
const adjustingAccount = ref(null)
const adjustForm = useForm({
    balance: 0,
})

const openAdjust = (account) => {
    adjustingAccount.value = account
    adjustForm.balance = account.balance
}

const closeAdjust = () => {
    adjustingAccount.value = null
    adjustForm.reset()
}

const submitAdjust = () => {
    adjustForm.post(`/accounts/${adjustingAccount.value.id}/adjust-balance`, {
        onSuccess: () => closeAdjust(),
    })
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
                <div class="flex items-center gap-2">
                    <button
                        @click="showDetails = !showDetails"
                        class="px-3 py-1.5 text-sm border border-theme-border rounded-md text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                    >
                        {{ showDetails ? 'Vue simple' : 'Réconciliation' }}
                    </button>
                    <Link
                        href="/accounts/create"
                        class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                    >
                        + Nouveau
                    </Link>
                </div>
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

                <!-- Simple view -->
                <table v-else-if="!showDetails" class="w-full">
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
                                    <button
                                        @click="openAdjust(account)"
                                        class="text-xs text-theme-text-secondary hover:text-theme-text-primary"
                                    >
                                        Ajuster
                                    </button>
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

                <!-- Reconciliation view -->
                <div v-else class="divide-y divide-theme-border">
                    <div v-for="account in accounts" :key="account.id" class="p-4 hover:bg-theme-surface transition-colors">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: account.color || '#fff' }"></div>
                                <div>
                                    <p class="text-sm font-medium text-theme-text-primary">{{ account.name }}</p>
                                    <span class="text-xs text-theme-text-muted">{{ accountTypeLabels[account.type] || account.type }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="openAdjust(account)"
                                    class="px-2 py-1 text-xs border border-theme-border rounded text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                                >
                                    Ajuster
                                </button>
                                <Link
                                    :href="`/accounts/${account.id}/edit`"
                                    class="px-2 py-1 text-xs border border-theme-border rounded text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                                >
                                    Modifier
                                </Link>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div>
                                <p class="text-theme-text-muted mb-1">Solde initial</p>
                                <p class="text-theme-text-secondary">{{ formatAmount(account.initial_balance) }}</p>
                            </div>
                            <div>
                                <p class="text-theme-text-muted mb-1">Revenus</p>
                                <p class="text-green-400">+{{ formatAmount(account.income_total || 0) }}</p>
                            </div>
                            <div>
                                <p class="text-theme-text-muted mb-1">Dépenses</p>
                                <p class="text-red-400">-{{ formatAmount(account.expense_total || 0) }}</p>
                            </div>
                            <div>
                                <p class="text-theme-text-muted mb-1">Transferts</p>
                                <p class="text-blue-400">
                                    {{ account.transfers_in_total ? '+' + formatAmount(account.transfers_in_total) : '' }}
                                    {{ account.transfers_in_total && account.transfers_out_total ? ' / ' : '' }}
                                    {{ account.transfers_out_total ? '-' + formatAmount(account.transfers_out_total) : '' }}
                                    {{ !account.transfers_in_total && !account.transfers_out_total ? '0' : '' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-theme-border">
                            <div>
                                <span class="text-xs text-theme-text-muted">Solde actuel :</span>
                                <span class="text-sm font-medium text-theme-text-primary ml-1">{{ formatAmount(account.balance) }} FCFA</span>
                            </div>
                            <div v-if="account.ecart !== 0" class="flex items-center gap-2">
                                <span class="text-xs text-yellow-400">Écart : {{ formatAmount(account.ecart) }} FCFA</span>
                            </div>
                            <div v-else>
                                <span class="text-xs text-green-400">Solde OK</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Adjust Balance Modal -->
        <Teleport to="body">
            <div v-if="adjustingAccount" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="closeAdjust"></div>
                <div class="relative bg-theme-card border border-theme-border rounded-lg p-6 w-full max-w-sm mx-4">
                    <h3 class="text-sm font-semibold text-theme-text-primary mb-1">Ajuster le solde</h3>
                    <p class="text-xs text-theme-text-muted mb-4">{{ adjustingAccount.name }}</p>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-theme-text-secondary mb-1">Solde actuel</label>
                            <p class="text-sm text-theme-text-muted">{{ formatAmount(adjustingAccount.balance) }} FCFA</p>
                        </div>

                        <div>
                            <label class="block text-xs text-theme-text-secondary mb-1">Solde réel (FCFA)</label>
                            <input
                                v-model.number="adjustForm.balance"
                                type="number"
                                class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                                @keyup.enter="submitAdjust"
                            />
                            <p v-if="adjustForm.errors.balance" class="text-xs text-red-400 mt-1">{{ adjustForm.errors.balance }}</p>
                        </div>

                        <div v-if="adjustForm.balance !== adjustingAccount.balance">
                            <p class="text-xs text-theme-text-muted">
                                Différence :
                                <span :class="adjustForm.balance > adjustingAccount.balance ? 'text-green-400' : 'text-red-400'">
                                    {{ adjustForm.balance > adjustingAccount.balance ? '+' : '' }}{{ formatAmount(adjustForm.balance - adjustingAccount.balance) }} FCFA
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4">
                        <button
                            @click="closeAdjust"
                            class="flex-1 py-2 text-sm text-theme-text-secondary border border-theme-border rounded-md hover:text-theme-text-primary transition-colors"
                        >
                            Annuler
                        </button>
                        <button
                            @click="submitAdjust"
                            :disabled="adjustForm.processing || adjustForm.balance === adjustingAccount.balance"
                            class="flex-1 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                        >
                            Ajuster
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
