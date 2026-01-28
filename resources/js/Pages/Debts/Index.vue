<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    CreditCardIcon,
    ArrowDownCircleIcon,
    ArrowUpCircleIcon,
    ScaleIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    debts: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
})

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount || 0)

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

const activeDebts = props.debts.filter(d => d.status === 'active' && d.type === 'debt')
const activeCredits = props.debts.filter(d => d.status === 'active' && d.type === 'credit')
const paidDebts = props.debts.filter(d => d.status === 'paid')

const deleteDebt = (debt) => {
    if (confirm('Supprimer cette entrée ?')) {
        router.delete(`/debts/${debt.id}`)
    }
}

const showPayment = ref(null)
const paymentForm = useForm({ amount: '' })

const openPayment = (debt) => {
    showPayment.value = debt.id
    paymentForm.amount = ''
}

const submitPayment = (debt) => {
    paymentForm.post(`/debts/${debt.id}/payment`, {
        onSuccess: () => { showPayment.value = null }
    })
}

const getProgress = (debt) => {
    if (!debt.initial_amount) return 0
    const paid = debt.initial_amount - debt.current_amount
    return Math.min(100, (paid / debt.initial_amount) * 100)
}
</script>

<template>
    <Head title="Dettes & Créances" />
    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <CreditCardIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Dettes & Créances</h1>
                </div>
                <Link href="/debts/create" class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors">
                    + Ajouter
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ArrowDownCircleIcon class="w-4 h-4 text-danger" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Je dois</p>
                    </div>
                    <p class="text-xl font-semibold text-danger">{{ formatAmount(stats.total_debt) }}</p>
                    <p class="text-xs text-theme-text-muted">{{ stats.active_debts }} dette(s)</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ArrowUpCircleIcon class="w-4 h-4 text-success" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">On me doit</p>
                    </div>
                    <p class="text-xl font-semibold text-success">{{ formatAmount(stats.total_credit) }}</p>
                    <p class="text-xs text-theme-text-muted">{{ stats.active_credits }} créance(s)</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ScaleIcon class="w-4 h-4" :class="stats.total_credit - stats.total_debt >= 0 ? 'text-success' : 'text-danger'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">Position nette</p>
                    </div>
                    <p class="text-xl font-semibold" :class="stats.total_credit - stats.total_debt >= 0 ? 'text-success' : 'text-danger'">
                        {{ formatAmount(stats.total_credit - stats.total_debt) }}
                    </p>
                    <p class="text-xs text-theme-text-muted">FCFA</p>
                </div>
                <div class="bg-theme-card border border-theme-border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <ExclamationCircleIcon class="w-4 h-4" :class="stats.overdue_count > 0 ? 'text-danger' : 'text-success'" />
                        <p class="text-xs text-theme-text-secondary uppercase tracking-wider">En retard</p>
                    </div>
                    <p class="text-xl font-semibold" :class="stats.overdue_count > 0 ? 'text-danger' : 'text-success'">{{ stats.overdue_count }}</p>
                    <p class="text-xs text-theme-text-muted">échéance(s)</p>
                </div>
            </div>

            <!-- Debts (Je dois) -->
            <div v-if="activeDebts.length > 0">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">Je dois</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg divide-y divide-theme-border">
                    <div v-for="debt in activeDebts" :key="debt.id" class="p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="text-sm font-medium text-theme-text-primary">{{ debt.name }}</p>
                                <p class="text-xs text-theme-text-muted">{{ debt.contact_name || 'Pas de contact' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="openPayment(debt)" class="px-2 py-1 text-xs bg-theme-surface border border-theme-border rounded hover:border-theme-text-secondary transition-colors">
                                    Payer
                                </button>
                                <Link :href="`/debts/${debt.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">Modifier</Link>
                                <button @click="deleteDebt(debt)" class="text-xs text-theme-text-secondary hover:text-danger">Supprimer</button>
                            </div>
                        </div>
                        <div v-if="showPayment === debt.id" class="mb-3 flex gap-2">
                            <input v-model.number="paymentForm.amount" type="number" min="1" placeholder="Montant" class="flex-1 bg-theme-surface border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <button @click="submitPayment(debt)" :disabled="paymentForm.processing || !paymentForm.amount" class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 disabled:opacity-50">Payer</button>
                            <button @click="showPayment = null" class="px-3 py-1.5 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md">Annuler</button>
                        </div>
                        <div class="flex items-center justify-between text-xs mb-2">
                            <span class="text-theme-text-secondary">{{ formatAmount(debt.initial_amount - debt.current_amount) }} payé / {{ formatAmount(debt.initial_amount) }}</span>
                            <span class="text-danger">Reste {{ formatAmount(debt.current_amount) }}</span>
                        </div>
                        <div class="w-full h-2 bg-theme-surface rounded-full overflow-hidden">
                            <div class="h-full bg-danger rounded-full" :style="{ width: `${getProgress(debt)}%` }"></div>
                        </div>
                        <p v-if="debt.due_date" class="text-xs text-theme-text-muted mt-2">Échéance : {{ formatDate(debt.due_date) }}</p>
                    </div>
                </div>
            </div>

            <!-- Credits (On me doit) -->
            <div v-if="activeCredits.length > 0">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">On me doit</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg divide-y divide-theme-border">
                    <div v-for="credit in activeCredits" :key="credit.id" class="p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="text-sm font-medium text-theme-text-primary">{{ credit.name }}</p>
                                <p class="text-xs text-theme-text-muted">{{ credit.contact_name || 'Pas de contact' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="openPayment(credit)" class="px-2 py-1 text-xs bg-theme-surface border border-theme-border rounded hover:border-theme-text-secondary transition-colors">
                                    Reçu
                                </button>
                                <Link :href="`/debts/${credit.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary">Modifier</Link>
                                <button @click="deleteDebt(credit)" class="text-xs text-theme-text-secondary hover:text-danger">Supprimer</button>
                            </div>
                        </div>
                        <div v-if="showPayment === credit.id" class="mb-3 flex gap-2">
                            <input v-model.number="paymentForm.amount" type="number" min="1" placeholder="Montant reçu" class="flex-1 bg-theme-surface border border-theme-border rounded-md px-3 py-1.5 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <button @click="submitPayment(credit)" :disabled="paymentForm.processing || !paymentForm.amount" class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 disabled:opacity-50">Enregistrer</button>
                            <button @click="showPayment = null" class="px-3 py-1.5 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md">Annuler</button>
                        </div>
                        <div class="flex items-center justify-between text-xs mb-2">
                            <span class="text-theme-text-secondary">{{ formatAmount(credit.initial_amount - credit.current_amount) }} reçu / {{ formatAmount(credit.initial_amount) }}</span>
                            <span class="text-success">Reste {{ formatAmount(credit.current_amount) }}</span>
                        </div>
                        <div class="w-full h-2 bg-theme-surface rounded-full overflow-hidden">
                            <div class="h-full bg-success rounded-full" :style="{ width: `${getProgress(credit)}%` }"></div>
                        </div>
                        <p v-if="credit.due_date" class="text-xs text-theme-text-muted mt-2">Échéance : {{ formatDate(credit.due_date) }}</p>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="activeDebts.length === 0 && activeCredits.length === 0" class="bg-theme-card border border-theme-border rounded-lg px-4 py-12 text-center">
                <p class="text-sm text-theme-text-secondary mb-4">Aucune dette ou créance active</p>
                <Link href="/debts/create" class="text-sm text-theme-text-primary hover:underline">Ajouter une entrée</Link>
            </div>

            <!-- Paid -->
            <div v-if="paidDebts.length > 0">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-3">Historique (Payé)</h2>
                <div class="bg-theme-card border border-theme-border rounded-lg">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-theme-border">
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Nom</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Montant</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <tr v-for="debt in paidDebts" :key="debt.id">
                                <td class="px-4 py-3 text-sm text-theme-text-primary">{{ debt.name }}</td>
                                <td class="px-4 py-3 text-sm" :class="debt.type === 'debt' ? 'text-danger' : 'text-success'">{{ debt.type === 'debt' ? 'Dette' : 'Créance' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-theme-text-primary">{{ formatAmount(debt.initial_amount) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="deleteDebt(debt)" class="text-xs text-theme-text-secondary hover:text-danger">Supprimer</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
