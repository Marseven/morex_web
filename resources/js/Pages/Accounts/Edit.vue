<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    account: { type: Object, required: true },
})

const form = useForm({
    name: props.account.name,
    type: props.account.type,
    initial_balance: props.account.initial_balance,
    color: props.account.color || '#FFFFFF',
    icon: props.account.icon || 'wallet',
    is_default: props.account.is_default,
})

const accountTypes = [
    { value: 'current', label: 'Compte Courant' },
    { value: 'savings', label: 'Épargne' },
    { value: 'cash', label: 'Espèces' },
    { value: 'credit', label: 'Crédit' },
    { value: 'investment', label: 'Investissement' },
]

const colors = ['#FFFFFF', '#71717A', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899']

const submit = () => {
    form.put(`/accounts/${props.account.id}`)
}
</script>

<template>
    <Head title="Modifier le compte" />

    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/accounts" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">Modifier le compte</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-theme-card border border-theme-border rounded-lg p-4 space-y-4">
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
                            <option v-for="type in accountTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Solde initial (FCFA)</label>
                        <input
                            v-model.number="form.initial_balance"
                            type="number"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                        />
                        <p class="text-xs text-theme-text-muted mt-1">Le solde sera recalculé</p>
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

                    <div class="flex items-center gap-2">
                        <input
                            id="is_default"
                            v-model="form.is_default"
                            type="checkbox"
                            class="w-4 h-4 bg-theme-surface border-theme-border rounded text-theme-text-primary focus:ring-0"
                        />
                        <label for="is_default" class="text-sm text-theme-text-secondary">Compte par défaut</label>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link href="/accounts" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
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
