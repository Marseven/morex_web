<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { CalendarIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import { ref } from 'vue'

const props = defineProps({
    activeCycle: { type: Object, default: null },
    closures: { type: Array, default: () => [] },
})

const formatAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR').format(amount)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

const startingNewCycle = ref(false)
const closingCycle = ref(false)

const startNewCycle = () => {
    if (!confirm('Démarrer un nouveau cycle budgétaire ? Le cycle actuel sera clôturé automatiquement.')) {
        return
    }

    startingNewCycle.value = true
    router.post('/budget-cycles/start', {}, {
        onFinish: () => startingNewCycle.value = false
    })
}

const closeCycle = () => {
    if (!confirm(`Clôturer le cycle "${props.activeCycle.period_name}" ?`)) {
        return
    }

    closingCycle.value = true
    router.post('/budget-cycles/close', {}, {
        onFinish: () => closingCycle.value = false
    })
}
</script>

<template>
    <Head title="Cycles Budgétaires" />

    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <CalendarIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h1 class="text-lg font-semibold text-theme-text-primary">Cycles Budgétaires</h1>
                </div>
                <button
                    v-if="!activeCycle"
                    @click="startNewCycle"
                    :disabled="startingNewCycle"
                    class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                >
                    {{ startingNewCycle ? 'Démarrage...' : '+ Nouveau cycle' }}
                </button>
            </div>

            <!-- Cycle actif -->
            <div v-if="activeCycle" class="glass-card p-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs text-theme-text-muted uppercase tracking-wider mb-1">Cycle actif</p>
                        <h2 class="text-lg font-semibold text-theme-text-primary">{{ activeCycle.period_name }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="closeCycle"
                            :disabled="closingCycle"
                            class="px-3 py-1.5 text-sm border border-theme-border rounded-md text-theme-text-secondary hover:text-theme-text-primary transition-colors disabled:opacity-50"
                        >
                            {{ closingCycle ? 'Clôture...' : 'Clôturer' }}
                        </button>
                        <button
                            @click="startNewCycle"
                            :disabled="startingNewCycle"
                            class="px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                        >
                            {{ startingNewCycle ? 'Démarrage...' : 'Nouveau cycle' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-theme-text-muted mb-1">Début</p>
                        <p class="text-theme-text-primary">{{ formatDate(activeCycle.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-theme-text-muted mb-1">Budget total</p>
                        <p class="text-theme-text-primary">{{ formatAmount(activeCycle.total_budget) }} FCFA</p>
                    </div>
                    <div>
                        <p class="text-theme-text-muted mb-1">Dépensé</p>
                        <p class="text-theme-text-primary">{{ formatAmount(activeCycle.total_spent) }} FCFA</p>
                    </div>
                </div>
            </div>

            <!-- Message si pas de cycle actif -->
            <div v-else class="glass-card p-8 text-center">
                <ArrowPathIcon class="w-12 h-12 text-theme-text-muted mx-auto mb-3" />
                <p class="text-sm text-theme-text-secondary mb-4">Aucun cycle budgétaire actif</p>
                <button
                    @click="startNewCycle"
                    :disabled="startingNewCycle"
                    class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                >
                    {{ startingNewCycle ? 'Démarrage...' : 'Démarrer un cycle' }}
                </button>
            </div>

            <!-- Historique des clôtures -->
            <div v-if="closures.length > 0">
                <h2 class="text-sm font-semibold text-theme-text-primary mb-3">Historique des clôtures</h2>
                <div class="glass-card divide-y divide-theme-border">
                    <div v-for="closure in closures" :key="closure.id" class="p-4 hover:bg-theme-surface transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-theme-text-primary mb-2">{{ closure.period_name }}</h3>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                    <div>
                                        <p class="text-theme-text-muted mb-1">Revenus</p>
                                        <p class="text-green-400">+{{ formatAmount(closure.total_income) }} FCFA</p>
                                    </div>
                                    <div>
                                        <p class="text-theme-text-muted mb-1">Dépenses</p>
                                        <p class="text-red-400">-{{ formatAmount(closure.total_spent) }} FCFA</p>
                                    </div>
                                    <div>
                                        <p class="text-theme-text-muted mb-1">Épargné</p>
                                        <p :class="closure.total_saved >= 0 ? 'text-green-400' : 'text-red-400'">
                                            {{ closure.total_saved >= 0 ? '+' : '' }}{{ formatAmount(closure.total_saved) }} FCFA
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <span v-if="closure.total_saved >= 0" class="px-2 py-1 text-xs bg-green-400/10 text-green-400 rounded">
                                    Excédent
                                </span>
                                <span v-else class="px-2 py-1 text-xs bg-red-400/10 text-red-400 rounded">
                                    Déficit
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
