<script setup>
import { computed, nextTick, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PurchaseCheck from '@/Components/Purchases/PurchaseCheck.vue'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    plannedPurchases: { type: Array, default: () => [] },
    purchaseThreshold: { type: Number, default: 30000 },
    nextPeriodStart: { type: String, default: '' },
    prefill: { type: Object, default: null },
})

const form = useForm({
    amount: props.prefill?.amount ?? '',
    type: 'expense',
    category_id: props.prefill?.category_id ?? '',
    account_id: props.accounts[0]?.id || '',
    beneficiary: props.prefill?.beneficiary ?? '',
    description: '',
    date: new Date().toISOString().split('T')[0],
    transfer_to_account_id: '',
    planned_purchase_id: props.prefill?.planned_purchase_id ?? '',
    out_of_list: '',
})

// Achats prévus (module achats-prevus) : bloc évalué à la sortie du champ montant, pas à chaque frappe
const committedAmount = ref(Number(form.amount) || 0)
const commitAmount = () => { committedAmount.value = Number(form.amount) || 0 }
const purchaseCheck = ref(null)
// Le transit et le pro ne sont pas concernés par la règle (module revenus-transit)
const isRegularCategory = computed(() => {
    const cat = props.categories.find((c) => c.id === form.category_id)
    return !cat || !cat.nature || cat.nature === 'regular'
})
const showPurchaseCheck = computed(() => form.type === 'expense' && isRegularCategory.value && committedAmount.value >= props.purchaseThreshold)
const postpone = () => {
    router.post('/purchases', {
        name: form.beneficiary || form.description || 'Achat reporté',
        estimated_amount: Number(form.amount),
        category_id: form.category_id || null,
        when: 'next',
    })
}

const filteredCategories = computed(() => {
    return props.categories.filter(cat =>
        (form.type === 'expense' && cat.type === 'expense') ||
        (form.type === 'income' && cat.type === 'income')
    )
})

const otherAccounts = computed(() => {
    return props.accounts.filter(acc => acc.id !== form.account_id)
})

const submit = async () => {
    commitAmount()
    await nextTick()
    // Filet : une dépense au-dessus du seuil demande un choix explicite (jamais un blocage)
    if (showPurchaseCheck.value && purchaseCheck.value && !purchaseCheck.value.decided) {
        purchaseCheck.value.$el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        purchaseCheck.value.focus()
        return
    }
    form.transform((data) => ({
        ...data,
        planned_purchase_id: showPurchaseCheck.value ? (data.planned_purchase_id || null) : null,
        out_of_list: showPurchaseCheck.value ? (data.out_of_list || null) : null,
    })).post('/transactions')
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

            <!-- Validation Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                <p class="text-sm text-red-400 font-medium mb-2">Erreurs de validation :</p>
                <ul class="list-disc list-inside text-sm text-red-400 space-y-1">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
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

                <div class="glass-card p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant (FCFA)</label>
                        <input
                            v-model.number="form.amount"
                            @blur="commitAmount"
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

                    <PurchaseCheck
                        v-if="showPurchaseCheck"
                        ref="purchaseCheck"
                        v-model:purchase-id="form.planned_purchase_id"
                        v-model:out-of-list="form.out_of_list"
                        :amount="committedAmount"
                        :category-id="form.category_id"
                        :purchases="plannedPurchases"
                        :next-start="nextPeriodStart"
                        @postpone="postpone"
                    />

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
