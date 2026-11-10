<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

const props = defineProps({
    goal: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
})

const form = useForm({
    name: props.goal.name,
    type: props.goal.type,
    target_amount: props.goal.target_amount,
    current_amount: props.goal.current_amount,
    target_date: props.goal.target_date ? props.goal.target_date.split('T')[0] : '',
    account_id: props.goal.account_id || null,
    status: props.goal.status,
    color: props.goal.color || '#FFFFFF',
    icon: props.goal.icon || 'target',
})

// Suivi sur un compte (module suivi-objectifs) : la progression suit le solde du compte
const isTracked = computed(() => !!props.goal.tracked_since)
const trackForm = useForm({
    account_id: props.goal.account_id || props.accounts.find((a) => /epargne/i.test(a.name.normalize('NFD').replace(/\p{Diacritic}/gu, '')))?.id || props.accounts[0]?.id || '',
    start_amount: props.goal.current_amount,
    monthly_target: props.goal.monthly_target ?? null,
})
const trackedAccount = computed(() => props.accounts.find((a) => a.id === props.goal.account_id))
const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount ?? 0)
const startTracking = () => trackForm.post(`/goals/${props.goal.id}/track`, { preserveScroll: true })
const stopTracking = () => {
    if (confirm(`La progression redeviendra manuelle, figée à ${formatAmount(props.goal.current_amount)} FCFA. Arrêter le suivi ?`)) {
        router.delete(`/goals/${props.goal.id}/track`, { preserveScroll: true })
    }
}

const goalTypes = [
    { value: 'emergency_fund', label: 'Fonds d\'urgence' },
    { value: 'savings', label: 'Épargne' },
    { value: 'debt', label: 'Remboursement' },
    { value: 'investment', label: 'Investissement' },
    { value: 'custom', label: 'Personnalisé' },
]

const statuses = [
    { value: 'active', label: 'En cours' },
    { value: 'completed', label: 'Complété' },
    { value: 'cancelled', label: 'Annulé' },
]

const colors = ['#FFFFFF', '#71717A', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899']

const submit = () => {
    form.put(`/goals/${props.goal.id}`)
}
</script>

<template>
    <Head title="Modifier l'objectif" />

    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/goals" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">Modifier l'objectif</h1>
            </div>

            <!-- Validation Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                <p class="text-sm text-red-400 font-medium mb-2">Erreurs de validation :</p>
                <ul class="list-disc list-inside text-sm text-red-400 space-y-1">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="glass-card p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nom</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Type</label>
                        <select
                            v-model="form.type"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option v-for="type in goalTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Statut</label>
                        <select
                            v-model="form.status"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant cible (FCFA)</label>
                        <input
                            v-model.number="form.target_amount"
                            type="number"
                            required
                            min="1"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-3 text-xl font-semibold text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                    </div>

                    <div>
                        <label for="current_amount" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant actuel (FCFA)</label>
                        <input
                            id="current_amount"
                            v-model.number="form.current_amount"
                            type="number"
                            required
                            min="0"
                            :readonly="isTracked"
                            :aria-describedby="isTracked ? 'current-help' : undefined"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                        <p v-if="isTracked" id="current-help" class="mt-1 text-xs text-theme-text-muted">Calculé à partir du solde de « {{ trackedAccount?.name }} ».</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Date cible</label>
                        <input
                            v-model="form.target_date"
                            type="date"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Couleur</label>
                        <div class="flex gap-2">
                            <button
                                v-for="color in colors"
                                :key="color"
                                type="button"
                                @click="form.color = color"
                                class="w-7 h-7 rounded-md border-2 transition-all"
                                :class="form.color === color ? 'border-white scale-110' : 'border-transparent'"
                                :style="{ backgroundColor: color }"
                            ></button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link href="/goals" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
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

            <!-- Suivre sur un compte (module suivi-objectifs) -->
            <section class="glass-card p-6 mt-6 space-y-4" aria-labelledby="tracking-title">
                <h2 id="tracking-title" class="text-sm font-semibold text-theme-text-primary">
                    {{ isTracked ? 'Suivi sur un compte' : 'Suivre sur un compte' }}
                    <span v-if="isTracked" class="ml-2 text-xs font-normal text-green-400">Actif</span>
                </h2>

                <template v-if="isTracked">
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-theme-text-muted">Compte</dt><dd class="text-theme-text-primary">{{ trackedAccount?.name }}</dd>
                        <dt class="text-theme-text-muted">Suivi depuis</dt><dd class="text-theme-text-primary">{{ new Date(goal.tracked_since).toLocaleDateString('fr-FR') }}</dd>
                        <dt class="text-theme-text-muted">Départ</dt><dd class="text-theme-text-primary">{{ formatAmount(goal.tracked_start_amount) }} FCFA</dd>
                        <dt class="text-theme-text-muted">Versement visé</dt><dd class="text-theme-text-primary">{{ goal.monthly_target ? `${formatAmount(goal.monthly_target)} FCFA/mois` : '—' }}</dd>
                    </dl>
                    <div class="flex justify-end">
                        <button type="button" class="px-4 py-2 min-h-[44px] text-sm text-theme-text-secondary border border-theme-border rounded-md hover:text-theme-text-primary" @click="stopTracking">
                            Arrêter le suivi
                        </button>
                    </div>
                </template>

                <form v-else class="space-y-4" @submit.prevent="startTracking">
                    <p class="text-xs text-theme-text-muted">La progression sera calculée à partir du solde du compte : les versements et transferts entrants comptent, les retraits se déduisent.</p>
                    <p v-if="!accounts.length" class="text-sm text-theme-text-secondary">Aucun compte disponible. Créez d'abord un compte.</p>
                    <div>
                        <label for="track_account" class="block text-xs text-theme-text-secondary mb-1">Compte</label>
                        <select id="track_account" v-model="trackForm.account_id" required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary outline-none focus:border-white">
                            <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                        <p v-if="trackForm.errors.account_id" class="mt-1 text-xs text-red-400">Ce compte n'est pas disponible.</p>
                    </div>
                    <div>
                        <label for="start_amount" class="block text-xs text-theme-text-secondary mb-1">Montant de départ (FCFA)</label>
                        <input id="start_amount" v-model.number="trackForm.start_amount" type="number" min="0" step="1" required aria-describedby="start-help"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary outline-none focus:border-white" />
                        <p id="start-help" class="mt-1 text-xs text-theme-text-muted">Le montant déjà épargné aujourd'hui pour cet objectif.</p>
                        <p v-if="trackForm.errors.start_amount" class="mt-1 text-xs text-red-400">Entrez un montant entier, sans décimales.</p>
                    </div>
                    <div>
                        <label for="monthly_target" class="block text-xs text-theme-text-secondary mb-1">Versement mensuel visé (FCFA, facultatif)</label>
                        <input id="monthly_target" v-model.number="trackForm.monthly_target" type="number" min="0" step="1"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary outline-none focus:border-white" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="trackForm.processing || !accounts.length"
                            class="px-4 py-2 min-h-[44px] text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50">
                            {{ trackForm.processing ? 'Enregistrement…' : 'Suivre ce compte' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
