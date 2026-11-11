<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ShoppingBagIcon, CheckIcon, ClockIcon, ExclamationTriangleIcon, PlusIcon } from '@heroicons/vue/24/outline'

// Page « Achats prévus » (design achats-prevus, direction A)
const props = defineProps({
    purchases: { type: Array, default: () => [] },
    outOfList: { type: Array, default: () => [] },
    late: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    period: { type: Object, default: null },
    nextStart: { type: String, default: '' },
    categories: { type: Array, default: () => [] },
    threshold: { type: Number, default: 30000 },
})

const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(n || 0)
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const periodLabel = computed(() => {
    if (!props.period) return ''
    if (props.period.end) return `${props.period.is_cycle ? 'Cycle' : 'Mois'} jusqu'au ${shortDate(props.period.end)}`
    return `Cycle depuis le ${shortDate(props.period.start)}`
})

const inPeriod = (p) => !props.period?.end || p.planned_for <= props.period.end
const current = computed(() => props.purchases.filter((p) => !p.unplanned && inPeriod(p)))
const upcoming = computed(() => props.purchases.filter((p) => !p.unplanned && !inPeriod(p)))
const assumed = computed(() => props.purchases.filter((p) => p.unplanned))
const progress = computed(() => props.summary.planned_total ? Math.min(100, Math.round(props.summary.spent_total * 100 / props.summary.planned_total)) : 0)
const allDone = computed(() => props.summary.planned_count > 0 && props.summary.bought_count === props.summary.planned_count)

const showForm = ref(false)
const form = useForm({ name: '', estimated_amount: '', category_id: '', when: 'current' })
const submit = () => form.post('/purchases', { onSuccess: () => { form.reset(); showForm.value = false } })
const cancel = (p) => {
    if (confirm(`Annuler l'achat prévu « ${p.name} » ?`)) router.delete(`/purchases/${p.id}`)
}
const buyHref = (p) => `/transactions/create?planned_purchase_id=${p.id}`
</script>

<template>
    <Head title="Achats prévus" />

    <AppLayout>
        <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <ShoppingBagIcon class="w-5 h-5 text-theme-text-secondary" aria-hidden="true" />
                    <div>
                        <h1 class="text-lg font-semibold text-theme-text-primary">Achats prévus</h1>
                        <p v-if="periodLabel" class="text-xs text-theme-text-muted">{{ periodLabel }}</p>
                    </div>
                </div>
                <button type="button" @click="showForm = !showForm" :aria-expanded="showForm"
                    class="inline-flex items-center gap-1 min-h-[44px] px-4 rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium">
                    <PlusIcon class="w-4 h-4" aria-hidden="true" /> Prévoir
                </button>
            </div>

            <!-- Ajout en ligne -->
            <form v-if="showForm" @submit.prevent="submit" class="glass-card p-4 space-y-4" aria-label="Prévoir un achat">
                <div>
                    <label for="pp-name" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nom</label>
                    <input id="pp-name" v-model="form.name" type="text" required maxlength="120" placeholder="Ex : Chaussures"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary focus:border-white focus:ring-0 outline-none" />
                </div>
                <div>
                    <label for="pp-amount" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Montant estimé (FCFA)</label>
                    <input id="pp-amount" v-model.number="form.estimated_amount" type="number" min="1" required inputmode="numeric"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary focus:border-white focus:ring-0 outline-none" />
                </div>
                <div>
                    <label for="pp-category" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Catégorie</label>
                    <select id="pp-category" v-model="form.category_id"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary focus:border-white focus:ring-0 outline-none">
                        <option value="">Sans catégorie</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <fieldset>
                    <legend class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Période</legend>
                    <div class="flex gap-2">
                        <label v-for="opt in [{ v: 'current', l: 'Ce cycle' }, { v: 'next', l: `Cycle suivant (${shortDate(nextStart)})` }]" :key="opt.v"
                            class="flex-1 flex items-center justify-center gap-2 min-h-[44px] px-3 rounded-md border cursor-pointer text-sm focus-within:ring-2 focus-within:ring-white/60"
                            :class="form.when === opt.v ? 'border-white text-theme-text-primary' : 'border-theme-border text-theme-text-secondary'">
                            <input v-model="form.when" type="radio" name="when" :value="opt.v" class="sr-only" />
                            <CheckIcon v-if="form.when === opt.v" class="w-4 h-4" aria-hidden="true" /> {{ opt.l }}
                        </label>
                    </div>
                </fieldset>
                <p v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-400">Impossible d'enregistrer : vérifiez le nom, le montant et la catégorie.</p>
                <div class="flex gap-3">
                    <button type="button" @click="showForm = false" class="flex-1 min-h-[44px] text-sm text-theme-text-secondary border border-theme-border rounded-md">Annuler</button>
                    <button type="submit" :disabled="form.processing" class="flex-1 min-h-[44px] bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md disabled:opacity-50">Prévoir</button>
                </div>
            </form>

            <!-- Résumé -->
            <div class="glass-card p-4 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-theme-text-secondary">Prévu <strong class="text-theme-text-primary">{{ formatAmount(summary.planned_total) }}</strong> · Fait <strong class="text-theme-text-primary">{{ formatAmount(summary.spent_total) }}</strong></span>
                    <span class="text-theme-text-primary">{{ summary.bought_count }}/{{ summary.planned_count }} faits</span>
                </div>
                <div class="h-2 rounded-full bg-theme-surface overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress"
                    :aria-label="`${summary.bought_count} achats faits sur ${summary.planned_count}, ${formatAmount(summary.spent_total)} sur ${formatAmount(summary.planned_total)} francs CFA`">
                    <div class="h-full rounded-full bg-[var(--color-accent)]" :style="{ width: `${progress}%` }"></div>
                </div>
                <p v-if="allDone" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary"><CheckIcon class="w-4 h-4" aria-hidden="true" /> Tout est acheté ce cycle</p>
            </div>

            <div v-if="!purchases.length && !late.length && !outOfList.length" class="glass-card p-6 text-center space-y-3">
                <p class="text-sm text-theme-text-secondary">Aucun achat prévu ce cycle. Prévoir ce que vous comptez acheter évite les achats impulsifs.</p>
                <button type="button" @click="showForm = true" class="min-h-[44px] px-4 rounded-md border border-theme-border text-sm text-theme-text-primary">Prévoir un achat</button>
            </div>

            <!-- En retard -->
            <section v-if="late.length" aria-labelledby="late-title" class="space-y-2">
                <h2 id="late-title" class="flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-red-400"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> En retard ({{ late.length }}) · prévu au cycle précédent</h2>
                <div v-for="p in late" :key="p.id" class="glass-card p-4 space-y-2">
                    <div class="flex items-center justify-between gap-2"><span class="text-sm text-theme-text-primary">{{ p.name }}</span><span class="text-sm text-theme-text-primary">{{ formatAmount(p.estimated_amount) }}</span></div>
                    <div class="flex gap-2">
                        <Link :href="buyHref(p)" class="flex-1 inline-flex items-center justify-center min-h-[44px] rounded-md border border-theme-border text-sm text-theme-text-primary">Acheté</Link>
                        <button type="button" @click="cancel(p)" class="flex-1 min-h-[44px] rounded-md border border-theme-border text-sm text-theme-text-secondary">Annuler</button>
                    </div>
                </div>
            </section>

            <!-- Ce cycle -->
            <section v-if="current.length" aria-labelledby="current-title" class="space-y-2">
                <h2 id="current-title" class="text-xs font-medium uppercase tracking-wider text-theme-text-secondary">Ce cycle</h2>
                <div v-for="p in current" :key="p.id" class="glass-card p-4 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm text-theme-text-primary">{{ p.name }} <span v-if="p.category" class="text-theme-text-muted">· {{ p.category }}</span></span>
                        <span class="text-sm text-theme-text-primary">{{ formatAmount(p.spent ?? p.estimated_amount) }}</span>
                    </div>
                    <p v-if="p.status === 'bought'" class="inline-flex items-center gap-1 text-xs text-success"><CheckIcon class="w-4 h-4" aria-hidden="true" /> Fait le {{ shortDate(p.bought_on) }}</p>
                    <div v-else class="flex gap-2">
                        <Link :href="buyHref(p)" class="flex-1 inline-flex items-center justify-center min-h-[44px] rounded-md border border-theme-border text-sm text-theme-text-primary">Acheté</Link>
                        <button type="button" @click="cancel(p)" class="flex-1 min-h-[44px] rounded-md border border-theme-border text-sm text-theme-text-secondary">Annuler</button>
                    </div>
                </div>
            </section>

            <!-- Hors liste -->
            <section v-if="outOfList.length || assumed.length" aria-labelledby="out-title" class="space-y-2">
                <h2 id="out-title" class="flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-warning"><ExclamationTriangleIcon class="w-4 h-4" aria-hidden="true" /> Hors liste ({{ outOfList.length + assumed.length }})</h2>
                <div v-for="t in outOfList" :key="t.id" class="glass-card p-4 flex items-center justify-between gap-2">
                    <span class="text-sm text-theme-text-primary">{{ t.label }} <span class="text-theme-text-muted">· {{ shortDate(t.date) }}</span></span>
                    <span class="text-sm text-theme-text-primary">{{ formatAmount(t.amount) }}</span>
                </div>
                <div v-for="p in assumed" :key="p.id" class="glass-card p-4 flex items-center justify-between gap-2">
                    <span class="text-sm text-theme-text-primary">{{ p.name }} <span class="text-theme-text-muted">· acheté hors liste, assumé</span></span>
                    <span class="text-sm text-theme-text-primary">{{ formatAmount(p.estimated_amount) }}</span>
                </div>
            </section>

            <!-- À venir -->
            <section v-if="upcoming.length" aria-labelledby="next-title" class="space-y-2">
                <h2 id="next-title" class="flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-theme-text-secondary"><ClockIcon class="w-4 h-4" aria-hidden="true" /> À venir</h2>
                <div v-for="p in upcoming" :key="p.id" class="glass-card p-4 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-theme-text-primary">{{ p.name }} <span class="text-theme-text-muted">· dès le {{ shortDate(p.planned_for) }}</span></span>
                    <span class="flex items-center gap-3">
                        <span class="text-sm text-theme-text-primary">{{ formatAmount(p.estimated_amount) }}</span>
                        <button type="button" @click="cancel(p)" class="min-h-[44px] px-3 text-sm text-theme-text-secondary underline" :aria-label="`Annuler ${p.name}`">Annuler</button>
                    </span>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
