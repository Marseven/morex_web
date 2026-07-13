<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    transfer: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
})

// <input type="date"> exige yyyy-MM-dd, sinon le champ s'affiche vide.
const toDateInput = (value) => (value ? String(value).slice(0, 10) : '')

const form = useForm({
    from_account_id: props.transfer.from_account_id,
    to_account_id: props.transfer.to_account_id,
    amount: props.transfer.amount,
    description: props.transfer.description || '',
    date: toDateInput(props.transfer.date),
})

const toAccounts = computed(() => {
    return props.accounts.filter(acc => acc.id !== form.from_account_id)
})

const fromAccounts = computed(() => {
    return props.accounts.filter(acc => acc.id !== form.to_account_id)
})

const submit = () => {
    form.put(`/transfers/${props.transfer.id}`)
}
</script>

<template>
    <Head title="Modifier le transfert" />

    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/transfers" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">Modifier le transfert</h1>
            </div>

            <!-- Validation Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
                <p class="text-sm text-red-400 font-medium mb-2">Erreurs de validation :</p>
                <ul class="list-disc list-inside text-sm text-red-400 space-y-1">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="glass-card p-4 space-y-4">
                    <!-- Montant -->
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

                    <!-- From Account -->
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Depuis</label>
                        <select
                            v-model="form.from_account_id"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option value="" disabled>Sélectionner un compte</option>
                            <option v-for="account in fromAccounts" :key="account.id" :value="account.id">
                                {{ account.name }}
                            </option>
                        </select>
                    </div>

                    <!-- To Account -->
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Vers</label>
                        <select
                            v-model="form.to_account_id"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option value="" disabled>Sélectionner un compte</option>
                            <option v-for="account in toAccounts" :key="account.id" :value="account.id">
                                {{ account.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Date</label>
                        <input
                            v-model="form.date"
                            type="date"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Description</label>
                        <input
                            v-model="form.description"
                            type="text"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                            placeholder="Optionnel"
                        />
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link href="/transfers" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
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
