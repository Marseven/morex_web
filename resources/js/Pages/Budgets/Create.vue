<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
    name: '',
    type: 'expense',
    color: '#FFFFFF',
    icon: 'tag',
    budget_limit: '',
})

const colors = ['#FFFFFF', '#71717A', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899']

const submit = () => {
    form.post('/budgets')
}
</script>

<template>
    <Head title="Nouvelle catégorie" />

    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/budgets" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">Nouvelle catégorie</h1>
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
                </div>

                <div class="bg-theme-card border border-theme-border rounded-lg p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nom</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none"
                            placeholder="Ex: Alimentation"
                        />
                    </div>

                    <div v-if="form.type === 'expense'">
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Budget mensuel (FCFA)</label>
                        <input
                            v-model.number="form.budget_limit"
                            type="number"
                            min="0"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none"
                            placeholder="Optionnel"
                        />
                        <p class="text-xs text-theme-text-muted mt-1">Laissez vide si pas de limite</p>
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
                    <Link href="/budgets" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
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
