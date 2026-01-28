<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
})

const form = useForm({
    name: '',
    type: 'savings',
    target_amount: '',
    current_amount: 0,
    target_date: '',
    account_id: '',
    color: '#FFFFFF',
    icon: 'target',
})

const goalTypes = [
    { value: 'savings', label: 'Épargne' },
    { value: 'debt', label: 'Remboursement' },
    { value: 'investment', label: 'Investissement' },
    { value: 'custom', label: 'Personnalisé' },
]

const colors = ['#FFFFFF', '#71717A', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899']

const submit = () => {
    form.post('/goals')
}
</script>

<template>
    <Head title="Nouvel objectif" />

    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/goals" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">Nouvel objectif</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nom</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none"
                            placeholder="Ex: Fonds d'urgence"
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
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant cible (FCFA)</label>
                        <input
                            v-model.number="form.target_amount"
                            type="number"
                            required
                            min="1"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-3 text-xl font-semibold text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                            placeholder="0"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Déjà épargné (FCFA)</label>
                        <input
                            v-model.number="form.current_amount"
                            type="number"
                            min="0"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                        <p class="text-xs text-theme-text-muted mt-1">Montant déjà épargné vers cet objectif</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Date cible</label>
                        <input
                            v-model="form.target_date"
                            type="date"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                        <p class="text-xs text-theme-text-muted mt-1">Optionnel</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Compte associé</label>
                        <select
                            v-model="form.account_id"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        >
                            <option value="">Aucun</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.name }}
                            </option>
                        </select>
                        <p class="text-xs text-theme-text-muted mt-1">Optionnel - pour le suivi automatique</p>
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
                        Créer
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
