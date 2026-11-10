<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { ArrowLeftIcon, ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { computed, nextTick, reactive, ref } from 'vue'

const props = defineProps({
    account: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    today: { type: String, required: true },
})

const example = '24/10/2026 21:10\nPaiement de 15500F DIGITECH pour repas. Frais 547F. TID: CI241024.2110.B22222\n\nou un CSV : Date;Libellé;Débit;Crédit'
const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(Math.abs(amount))
const signed = (amount) => `${amount < 0 ? '−' : '+'}${formatAmount(amount)}`
const signedTx = (tx) => `${tx.type === 'expense' ? '−' : '+'}${formatAmount(tx.amount)}`
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const categoriesFor = (amount) => props.categories.filter((c) => c.type === (amount < 0 ? 'expense' : 'income'))

// ① Coller
const step = ref('paste')
const text = ref('')
const referenceDate = ref(props.today)
const analysing = ref(false)
const saving = ref(false)
const error = ref('')
const summary = ref(null)

// ② Vérifier : lignes, opérations absentes, décisions (clé = index de ligne)
const lines = ref([])
const missing = ref([])
const decisions = reactive({})
const open = reactive({ to_validate: true, no_transaction: true, matched: false, unparsed: false, already: false, missing: false })

const byStatus = (status) => lines.value.filter((l) => l.status === status)
const groups = computed(() => ({
    to_validate: byStatus('to_validate'),
    no_transaction: byStatus('no_transaction'),
    matched: byStatus('matched'),
    unparsed: byStatus('unparsed'),
    already: byStatus('already_imported'),
}))
const withFees = computed(() => groups.value.matched.filter((l) => l.fee > 0).length)

const undecided = computed(() => {
    let count = 0
    for (const l of groups.value.to_validate) {
        const d = decisions[l.index]
        if (!d?.choice || (d.choice === 'create' && !d.category_id)) count++
    }
    for (const l of groups.value.no_transaction) {
        const d = decisions[l.index]
        if (d.choice === 'create' && !d.category_id) count++
    }
    return count
})
const toSend = computed(() => groups.value.matched.length + groups.value.to_validate.length + groups.value.no_transaction.length)
const canValidate = computed(() => toSend.value > 0 && undecided.value === 0 && !saving.value)

const analyse = async () => {
    analysing.value = true
    error.value = ''
    summary.value = null
    try {
        const { data } = await axios.post(`/accounts/${props.account.id}/statement/preview`, { text: text.value, reference_date: referenceDate.value })
        lines.value = data.data.lines
        missing.value = data.data.missing
        Object.keys(decisions).forEach((k) => delete decisions[k])
        for (const l of lines.value) {
            if (l.status === 'to_validate') decisions[l.index] = { choice: null, category_id: null }
            if (l.status === 'no_transaction') decisions[l.index] = { choice: 'create', category_id: l.suggested_category_id }
        }
        open.to_validate = groups.value.to_validate.length > 0
        open.no_transaction = groups.value.no_transaction.length > 0
        step.value = 'review'
        await nextTick()
        document.querySelector('[data-group-open="true"] input, [data-group-open="true"] select')?.focus()
    } catch (e) {
        error.value = e.response?.status === 429
            ? "Trop d'analyses, réessayez dans une minute."
            : Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? 'Connexion perdue. Réessayez.'
    } finally {
        analysing.value = false
    }
}

const payloadLine = (l, action, extra = {}) => ({
    date: l.date, amount: l.amount, fee: extra.fee ?? l.fee, label: l.label, reference: l.reference, action,
    transaction_id: extra.transaction_id ?? null, category_id: extra.category_id ?? null,
})

const validate = async () => {
    if (!canValidate.value) return
    const payload = groups.value.matched.map((l) => payloadLine(l, 'match', { transaction_id: l.transaction_id }))
    for (const l of groups.value.to_validate) {
        const d = decisions[l.index]
        const candidate = l.candidates.find((c) => c.id === d.choice)
        payload.push(candidate
            ? payloadLine(l, 'match', { transaction_id: candidate.id, fee: candidate.fee })
            : payloadLine(l, d.choice, { category_id: d.category_id }))
    }
    for (const l of groups.value.no_transaction) {
        const d = decisions[l.index]
        payload.push(payloadLine(l, d.choice, { category_id: d.category_id }))
    }

    saving.value = true
    error.value = ''
    try {
        const { data } = await axios.post(`/accounts/${props.account.id}/statement`, { lines: payload })
        summary.value = data.data
        step.value = 'done'
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? 'Enregistrement impossible. Réessayez.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Head :title="`Relevé ${account.name}`" />

    <AppLayout>
        <div class="px-4 py-6 lg:px-6 pb-28 lg:pb-6 max-w-5xl">
            <Link href="/accounts" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary hover:text-theme-text-primary">
                <ArrowLeftIcon class="w-4 h-4" /> Comptes
            </Link>
            <h1 class="mt-2 text-lg font-semibold text-theme-text-primary">Relevé · {{ account.name }}</h1>
            <ol class="mt-1 flex gap-3 text-xs text-theme-text-muted" aria-label="Étapes">
                <li :aria-current="step === 'paste' ? 'step' : undefined" :class="{ 'text-theme-text-primary font-medium': step === 'paste' }">① Coller</li>
                <li :aria-current="step !== 'paste' ? 'step' : undefined" :class="{ 'text-theme-text-primary font-medium': step !== 'paste' }">② Vérifier</li>
            </ol>

            <p v-if="error" role="alert" class="mt-4 rounded-md border border-red-400/40 bg-red-400/10 p-3 text-sm text-red-400">{{ error }}</p>

            <!-- Récap après validation -->
            <section v-if="step === 'done' && summary" role="status" class="mt-4 glass-card p-4 space-y-2">
                <p class="text-sm text-theme-text-primary">
                    {{ summary.matched }} rapprochée(s) · {{ summary.fees_count }} frais pour {{ formatAmount(summary.fees_total) }} FCFA ·
                    {{ summary.created }} opération(s) créée(s) · {{ summary.ignored }} ignorée(s).
                </p>
                <div class="flex gap-3 text-sm">
                    <Link href="/accounts" class="underline text-theme-text-secondary">Retour aux comptes</Link>
                    <button type="button" class="underline text-theme-text-secondary" @click="step = 'paste'; text = ''">Coller un autre relevé</button>
                </div>
            </section>

            <!-- ① Coller -->
            <section v-if="step === 'paste'" class="mt-4 glass-card p-4 space-y-4">
                <div class="max-w-xs">
                    <label for="reference_date" class="block text-xs text-theme-text-secondary mb-1">Date des SMS sans date</label>
                    <input id="reference_date" v-model="referenceDate" type="date"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary outline-none focus:border-white" />
                </div>
                <div>
                    <label for="statement" class="block text-xs text-theme-text-secondary mb-1">Relevé (SMS collés, séparés par une ligne vide, ou CSV)</label>
                    <textarea id="statement" v-model="text" rows="12" :placeholder="example"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base font-mono text-theme-text-primary outline-none focus:border-white"></textarea>
                    <p class="mt-1 text-xs text-theme-text-muted">{{ text.length }} / 20 000 caractères · le texte n'est pas conservé.</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="px-4 py-2 min-h-[44px] text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50"
                        :disabled="!text.trim() || analysing" :aria-busy="analysing" @click="analyse">
                        {{ analysing ? 'Analyse…' : 'Analyser' }}
                    </button>
                </div>
            </section>

            <!-- ② Vérifier -->
            <section v-if="step === 'review'" class="mt-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button type="button" class="text-sm text-theme-text-secondary hover:text-theme-text-primary" @click="step = 'paste'">‹ Modifier le texte</button>
                    <p class="text-xs text-theme-text-secondary" aria-live="polite">
                        {{ lines.length }} lignes · {{ groups.matched.length }} rapprochées · {{ groups.to_validate.length }} à valider ·
                        {{ groups.no_transaction.length }} sans opération · {{ groups.unparsed.length }} non comprise(s)
                    </p>
                </div>

                <p v-if="!groups.to_validate.length && !groups.no_transaction.length && groups.matched.length" class="glass-card p-3 text-sm text-theme-text-primary">
                    ✓ Tout correspond : il ne reste qu'à valider.
                </p>

                <!-- À valider -->
                <div v-if="groups.to_validate.length" class="glass-card" :data-group-open="open.to_validate">
                    <button type="button" class="w-full flex items-center gap-2 p-3 min-h-[44px] text-left text-sm font-medium text-yellow-400" :aria-expanded="open.to_validate" @click="open.to_validate = !open.to_validate">
                        <component :is="open.to_validate ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" aria-hidden="true" /> ⚠ À valider ({{ groups.to_validate.length }})
                    </button>
                    <ul v-show="open.to_validate" class="divide-y divide-theme-border border-t border-theme-border">
                        <li v-for="l in groups.to_validate" :key="l.index" class="p-3 space-y-2">
                            <p class="text-sm text-theme-text-primary">Relevé {{ shortDate(l.date) }} · {{ l.label }} · <strong>{{ signed(l.amount) }}</strong></p>
                            <fieldset class="space-y-1">
                                <legend class="sr-only">Opération correspondant à {{ l.label }} du {{ shortDate(l.date) }}</legend>
                                <label v-for="c in l.candidates" :key="c.id" class="flex items-center gap-2 min-h-[44px] text-sm text-theme-text-secondary">
                                    <input v-model="decisions[l.index].choice" type="radio" :name="`line-${l.index}`" :value="c.id" />
                                    {{ c.label }} {{ signedTx(c) }} ({{ shortDate(c.date) }})<span v-if="c.fee"> + frais {{ formatAmount(c.fee) }}</span>
                                </label>
                                <label class="flex items-center gap-2 min-h-[44px] text-sm text-theme-text-secondary">
                                    <input v-model="decisions[l.index].choice" type="radio" :name="`line-${l.index}`" value="create" /> Aucune : créer l'opération
                                </label>
                                <select v-if="decisions[l.index].choice === 'create'" v-model="decisions[l.index].category_id" :aria-label="`Catégorie pour ${l.label}`"
                                    class="ml-6 bg-theme-surface border border-theme-border rounded-md px-2 py-2 text-base sm:text-sm text-theme-text-primary">
                                    <option :value="null" disabled>Choisir une catégorie…</option>
                                    <option v-for="c in categoriesFor(l.amount)" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <label class="flex items-center gap-2 min-h-[44px] text-sm text-theme-text-secondary">
                                    <input v-model="decisions[l.index].choice" type="radio" :name="`line-${l.index}`" value="ignore" /> Ignorer cette ligne
                                </label>
                            </fieldset>
                        </li>
                    </ul>
                </div>

                <!-- Sans opération -->
                <div v-if="groups.no_transaction.length" class="glass-card" :data-group-open="open.no_transaction">
                    <button type="button" class="w-full flex items-center gap-2 p-3 min-h-[44px] text-left text-sm font-medium text-theme-text-primary" :aria-expanded="open.no_transaction" @click="open.no_transaction = !open.no_transaction">
                        <component :is="open.no_transaction ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" aria-hidden="true" /> ＋ Sans opération ({{ groups.no_transaction.length }})
                    </button>
                    <ul v-show="open.no_transaction" class="divide-y divide-theme-border border-t border-theme-border">
                        <li v-for="l in groups.no_transaction" :key="l.index" class="p-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_12rem_10rem] sm:items-center">
                            <p class="text-sm text-theme-text-primary">{{ shortDate(l.date) }} · {{ l.label || 'Sans libellé' }} · <strong>{{ signed(l.amount) }}</strong><span v-if="l.fee" class="text-theme-text-muted"> + frais {{ formatAmount(l.fee) }}</span></p>
                            <select v-model="decisions[l.index].category_id" :disabled="decisions[l.index].choice !== 'create'" :aria-label="`Catégorie pour ${l.label}`"
                                class="bg-theme-surface border rounded-md px-2 py-2 text-base sm:text-sm text-theme-text-primary disabled:opacity-40"
                                :class="decisions[l.index].choice === 'create' && !decisions[l.index].category_id ? 'border-yellow-400' : 'border-theme-border'">
                                <option :value="null" disabled>Choisir…</option>
                                <option v-for="c in categoriesFor(l.amount)" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <div class="flex gap-2" role="radiogroup" :aria-label="`Décision pour ${l.label}`">
                                <label class="flex items-center gap-1 text-sm text-theme-text-secondary"><input v-model="decisions[l.index].choice" type="radio" :name="`nt-${l.index}`" value="create" /> Créer</label>
                                <label class="flex items-center gap-1 text-sm text-theme-text-secondary"><input v-model="decisions[l.index].choice" type="radio" :name="`nt-${l.index}`" value="ignore" /> Ignorer</label>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Rapprochées -->
                <div v-if="groups.matched.length" class="glass-card">
                    <button type="button" class="w-full flex items-center gap-2 p-3 min-h-[44px] text-left text-sm font-medium text-green-400" :aria-expanded="open.matched" @click="open.matched = !open.matched">
                        <component :is="open.matched ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" aria-hidden="true" /> ✓ Rapprochées ({{ groups.matched.length }}<span v-if="withFees">, dont {{ withFees }} avec frais</span>)
                    </button>
                    <ul v-show="open.matched" class="divide-y divide-theme-border border-t border-theme-border">
                        <li v-for="l in groups.matched" :key="l.index" class="p-3 text-sm">
                            <p class="text-theme-text-muted">Relevé {{ shortDate(l.date) }} · {{ l.label }} · {{ signed(l.amount) }}</p>
                            <p class="text-theme-text-primary">Morex {{ shortDate(l.transaction?.date) }} · {{ l.transaction?.label }} · {{ l.transaction ? signedTx(l.transaction) : '' }}<span v-if="l.fee" class="text-theme-text-secondary"> + Frais {{ formatAmount(l.fee) }} (créés)</span><span v-else class="text-theme-text-muted"> · montants égaux</span></p>
                        </li>
                    </ul>
                </div>

                <!-- Non comprises / déjà importées / absentes -->
                <div v-if="groups.unparsed.length" class="glass-card">
                    <button type="button" class="w-full flex items-center gap-2 p-3 min-h-[44px] text-left text-sm font-medium text-red-400" :aria-expanded="open.unparsed" @click="open.unparsed = !open.unparsed">
                        <component :is="open.unparsed ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" aria-hidden="true" /> ✕ Non comprises ({{ groups.unparsed.length }}) — exclues
                    </button>
                    <ul v-show="open.unparsed" class="divide-y divide-theme-border border-t border-theme-border">
                        <li v-for="l in groups.unparsed" :key="l.index" class="p-3 text-sm text-theme-text-secondary">« {{ l.excerpt }} »</li>
                    </ul>
                </div>
                <div v-if="groups.already.length" class="glass-card p-3 text-sm text-theme-text-muted">⧉ {{ groups.already.length }} ligne(s) déjà importée(s) — ignorée(s).</div>
                <div v-if="missing.length" class="glass-card">
                    <button type="button" class="w-full flex items-center gap-2 p-3 min-h-[44px] text-left text-sm font-medium text-theme-text-secondary" :aria-expanded="open.missing" @click="open.missing = !open.missing">
                        <component :is="open.missing ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" aria-hidden="true" /> ◌ Dans Morex, absentes du relevé ({{ missing.length }})
                    </button>
                    <ul v-show="open.missing" class="divide-y divide-theme-border border-t border-theme-border">
                        <li v-for="m in missing" :key="m.id" class="p-3 text-sm text-theme-text-secondary">{{ shortDate(m.date) }} · {{ m.label }} · {{ m.type === 'expense' ? '−' : '+' }}{{ formatAmount(m.amount) }} — mauvais compte ou espèces ?</li>
                    </ul>
                </div>

                <div class="hidden lg:flex items-center justify-end gap-3">
                    <p v-if="undecided" class="text-sm text-yellow-400">{{ undecided }} à décider</p>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50" :disabled="!canValidate" @click="validate">
                        {{ saving ? 'Enregistrement…' : `Valider ${toSend} ligne(s)` }}
                    </button>
                </div>
            </section>
        </div>

        <div v-if="step === 'review'" class="lg:hidden fixed inset-x-0 bottom-0 z-40 border-t border-theme-border bg-theme-surface px-4 py-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <div class="flex items-center gap-3">
                <span class="flex-1 text-xs" :class="undecided ? 'text-yellow-400' : 'text-theme-text-secondary'">{{ undecided ? `⚠ ${undecided} à décider` : `${toSend} ligne(s) prêtes` }}</span>
                <button type="button" class="px-4 py-2 min-h-[44px] text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50" :disabled="!canValidate" @click="validate">Valider</button>
            </div>
        </div>
    </AppLayout>
</template>
