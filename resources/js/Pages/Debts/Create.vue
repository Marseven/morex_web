<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
    name: '',
    type: 'debt',
    initial_amount: '',
    current_amount: '',
    due_date: '',
    description: '',
    contact_name: '',
    contact_phone: '',
    color: '#EF4444',
})

const colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#71717A', '#FFFFFF']

const submit = () => {
    if (!form.current_amount) {
        form.current_amount = form.initial_amount
    }
    form.post('/debts')
}
</script>

<template>
    <Head :title="form.type === 'debt' ? 'Nouvelle dette' : 'Nouvelle créance'" />
    <AppLayout>
        <div class="max-w-xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <Link href="/debts" class="text-theme-text-secondary hover:text-theme-text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-lg font-semibold text-theme-text-primary">{{ form.type === 'debt' ? 'Nouvelle dette' : 'Nouvelle créance' }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Type -->
                <div class="flex gap-1 p-1 bg-theme-card border border-theme-border rounded-md">
                    <button type="button" @click="form.type = 'debt'" class="flex-1 py-2 text-sm rounded transition-colors" :class="form.type === 'debt' ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium' : 'text-theme-text-secondary'">
                        Je dois (Dette)
                    </button>
                    <button type="button" @click="form.type = 'credit'" class="flex-1 py-2 text-sm rounded transition-colors" :class="form.type === 'credit' ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium' : 'text-theme-text-secondary'">
                        On me doit (Créance)
                    </button>
                </div>

                <div class="bg-theme-card border border-theme-border rounded-lg p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">{{ form.type === 'debt' ? 'Pour quoi ?' : 'De quoi ?' }}</label>
                        <input v-model="form.name" type="text" required class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-theme-text-primary focus:ring-0 outline-none" :placeholder="form.type === 'debt' ? 'Ex: Prêt personnel' : 'Ex: Prêt à un ami'" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant total (FCFA)</label>
                        <input v-model.number="form.initial_amount" type="number" required min="1" class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-3 text-xl font-semibold text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" placeholder="0" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant restant (FCFA)</label>
                        <input v-model.number="form.current_amount" type="number" min="0" class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" :placeholder="form.initial_amount || 'Même que le total'" />
                        <p class="text-xs text-theme-text-muted mt-1">Laissez vide si identique au montant total</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Date d'échéance</label>
                        <input v-model="form.due_date" type="date" class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                        <p class="text-xs text-theme-text-muted mt-1">Optionnel</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">{{ form.type === 'debt' ? 'Créancier' : 'Débiteur' }}</label>
                        <input v-model="form.contact_name" type="text" class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-theme-text-primary focus:ring-0 outline-none" placeholder="Nom de la personne ou entreprise" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Téléphone</label>
                        <input v-model="form.contact_phone" type="tel" class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-theme-text-primary focus:ring-0 outline-none" placeholder="+241 77 12 34 56" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Note</label>
                        <textarea v-model="form.description" rows="2" class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-theme-text-primary focus:ring-0 outline-none resize-none" placeholder="Détails supplémentaires..."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Couleur</label>
                        <div class="flex gap-2">
                            <button v-for="color in colors" :key="color" type="button" @click="form.color = color" class="w-7 h-7 rounded-md border-2 transition-all" :class="form.color === color ? 'border-theme-text-primary scale-110' : 'border-transparent'" :style="{ backgroundColor: color }"></button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link href="/debts" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">Annuler</Link>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50">Créer</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
