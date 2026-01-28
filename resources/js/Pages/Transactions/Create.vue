<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const form = useForm({
    amount: '',
    type: 'expense',
    category_id: '',
    account_id: props.accounts[0]?.id || '',
    beneficiary: '',
    description: '',
    date: new Date().toISOString().split('T')[0],
    transfer_to_account_id: '',
})

const filteredCategories = computed(() => {
    return props.categories.filter(cat =>
        (form.type === 'expense' && cat.type === 'expense') ||
        (form.type === 'income' && cat.type === 'income')
    )
})

const otherAccounts = computed(() => {
    return props.accounts.filter(acc => acc.id !== form.account_id)
})

const submit = () => {
    form.post('/transactions')
}
</script>

<template>
    <Head title="Nouvelle transaction" />

    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/transactions" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">Nouvelle transaction</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Type -->
                <div class="flex gap-1 p-1 bg-theme-card border border-theme-border rounded-md">
                    <button
                        type="button"
                        @click="form.type = 'expense'"
                        class="flex-1 py-2 text-sm rounded transition-colors"
                        :class="form.type === 'expense' ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium' : 'text-theme-text-secondary'"
                    >
                        Dépense
                    </button>
                    <button
                        type="button"
                        @click="form.type = 'income'"
                        class="flex-1 py-2 text-sm rounded transition-colors"
                        :class="form.type === 'income' ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium' : 'text-theme-text-secondary'"
                    >
                        Revenu
                    </button>
                    <button
                        type="button"
                        @click="form.type = 'transfer'"
                        class="flex-1 py-2 text-sm rounded transition-colors"
                        :class="form.type === 'transfer' ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium' : 'text-theme-text-secondary'"
                    >
                        Transfert
                    </button>
                </div>

                <div class="bg-theme-card border border-theme-border rounded-lg p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant (FCFA)</label>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            required
                            min="1"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-3 text-xl font-semibold text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                            placeholder="0"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                            {{ form.type === 'transfer' ? 'Depuis' : 'Compte' }}
                        </label>
                        <select
                            v-model="form.account_id"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="form.type === 'transfer'">
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Vers</label>
                        <select
                            v-model="form.transfer_to_account_id"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="account in otherAccounts" :key="account.id" :value="account.id">
                                {{ account.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="form.type !== 'transfer'">
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Catégorie</label>
                        <select
                            v-model="form.category_id"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option value="">Sans catégorie</option>
                            <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Date</label>
                        <input
                            v-model="form.date"
                            type="date"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                    </div>

                    <div v-if="form.type !== 'transfer'">
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                            {{ form.type === 'expense' ? 'Bénéficiaire' : 'Source' }}
                        </label>
                        <input
                            v-model="form.beneficiary"
                            type="text"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                            :placeholder="form.type === 'expense' ? 'Ex: Carrefour' : 'Ex: Employeur'"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Note</label>
                        <input
                            v-model="form.description"
                            type="text"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                            placeholder="Optionnel"
                        />
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link href="/transactions" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
                        Annuler
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                    >
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
