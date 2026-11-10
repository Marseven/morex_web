<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { ArrowLeftIcon, CheckIcon, ExclamationTriangleIcon, XMarkIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline'
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    today: { type: String, required: true },
})

const MAX_LINES = 300
const MAX_CHARS = 20000
const example = 'Vendredi 16 oct\n1500 taxi\n25000+7000 night club\nEntrée :\n110000 - Freelance'

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount)
const shortDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : ''
const longDate = (iso) => iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }) : ''

// ① Coller
const step = ref('paste')
const notes = ref('')
const referenceDate = ref(props.today)
const defaultAccountId = ref(props.accounts.find((a) => a.is_default)?.id ?? props.accounts[0]?.id ?? null)
const lineCount = computed(() => (notes.value.trim() ? notes.value.trim().split(/\r?\n/).length : 0))
const tooLong = computed(() => lineCount.value > MAX_LINES || notes.value.length > MAX_CHARS)

// ② Vérifier
const rows = ref([])
const analysing = ref(false)
const saving = ref(false)
const error = ref('')
const filter = ref('all')
const corrections = new Map() // clé ligne|montant|libellé → { category_id, account_id, removed }

const rowKey = (r) => `${r.line}|${r.amount}|${r.label ?? ''}|${r.index}`
const categoriesFor = (type) => props.categories.filter((c) => c.type === type)
const statusOf = (r) => {
    if (r.status === 'unparsed') return 'unparsed'
    return r.label && r.category_id ? 'ok' : 'incomplete'
}

const visibleRows = computed(() => rows.value.filter((r) => !r.removed))
const savable = computed(() => visibleRows.value.filter((r) => statusOf(r) !== 'unparsed'))
const counts = computed(() => ({
    all: visibleRows.value.length,
    incomplete: visibleRows.value.filter((r) => statusOf(r) === 'incomplete').length,
    unparsed: visibleRows.value.filter((r) => statusOf(r) === 'unparsed').length,
    duplicate: visibleRows.value.filter((r) => r.possible_duplicate).length,
}))
const totals = computed(() => ({
    expense: savable.value.filter((r) => r.type === 'expense').reduce((s, r) => s + r.amount, 0),
    income: savable.value.filter((r) => r.type === 'income').reduce((s, r) => s + r.amount, 0),
}))
// Catégorie obligatoire : rien ne part tant qu'une ligne enregistrable est à compléter
const canSave = computed(() => savable.value.length > 0 && counts.value.incomplete === 0 && !saving.value)
const filteredRows = computed(() => visibleRows.value.filter((r) => {
    if (filter.value === 'incomplete') return statusOf(r) === 'incomplete'
    if (filter.value === 'unparsed') return statusOf(r) === 'unparsed'
    if (filter.value === 'duplicate') return r.possible_duplicate
    return true
}))
const summary = computed(() => step.value === 'review'
    ? `${counts.value.all} lignes, ${counts.value.all - counts.value.incomplete - counts.value.unparsed} prêtes, ${counts.value.incomplete} à compléter, ${counts.value.unparsed} non comprises`
    : '')

const remember = (r) => corrections.set(`${r.line}|${r.amount}|${r.label ?? ''}`, { category_id: r.category_id, account_id: r.account_id, removed: !!r.removed })

const analyse = async () => {
    if (!lineCount.value || tooLong.value) return
    analysing.value = true
    error.value = ''
    try {
        const { data } = await axios.post('/transactions/notes/preview', {
            notes: notes.value,
            reference_date: referenceDate.value,
            default_account_id: defaultAccountId.value,
        })
        rows.value = data.data.lines.map((line, index) => {
            const kept = corrections.get(`${line.line}|${line.amount}|${line.label ?? ''}`)
            return { ...line, index, ...(kept ?? {}) }
        })
        filter.value = 'all'
        step.value = 'review'
        await nextTick()
        document.querySelector('[data-incomplete="true"] select')?.focus()
    } catch (e) {
        const status = e.response?.status
        error.value = status === 429
            ? "Trop d'analyses, réessayez dans une minute."
            : status === 422
                ? Object.values(e.response.data.errors ?? {}).flat()[0] ?? 'Notes invalides.'
                : 'Connexion perdue. Réessayez.'
    } finally {
        analysing.value = false
    }
}

const removeRow = (r) => {
    r.removed = true
    remember(r)
}

const save = () => {
    if (!canSave.value) return
    saving.value = true
    router.post('/transactions/import', {
        account_id: defaultAccountId.value,
        rows: savable.value.map((r) => ({
            date: r.date,
            amount: r.amount,
            type: r.type,
            beneficiary: r.label,
            category_id: r.category_id,
            account_id: r.account_id,
        })),
    }, {
        onError: () => { error.value = 'Compte ou catégorie introuvable. Rechargez la page.' },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="Saisie en lot" />

    <AppLayout>
        <div class="px-4 py-6 lg:px-6 pb-28 lg:pb-6 max-w-5xl">
            <Link href="/transactions" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary hover:text-theme-text-primary">
                <ArrowLeftIcon class="w-4 h-4" /> Transactions
            </Link>
            <h1 class="mt-2 text-lg font-semibold text-theme-text-primary">Saisie en lot</h1>
            <ol class="mt-1 flex gap-3 text-xs text-theme-text-muted" aria-label="Étapes">
                <li :aria-current="step === 'paste' ? 'step' : undefined" :class="{ 'text-theme-text-primary font-medium': step === 'paste' }">① Coller</li>
                <li :aria-current="step === 'review' ? 'step' : undefined" :class="{ 'text-theme-text-primary font-medium': step === 'review' }">② Vérifier</li>
            </ol>

            <p v-if="error" role="alert" class="mt-4 rounded-md border border-red-400/40 bg-red-400/10 p-3 text-sm text-red-400">{{ error }}</p>
            <p class="sr-only" aria-live="polite">{{ summary }}</p>

            <!-- ① Coller -->
            <section v-if="step === 'paste'" class="mt-4 glass-card p-4 space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="reference_date" class="block text-xs text-theme-text-secondary mb-1">Date de référence</label>
                        <input id="reference_date" v-model="referenceDate" type="date"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary outline-none focus:border-white" />
                    </div>
                    <div>
                        <label for="default_account" class="block text-xs text-theme-text-secondary mb-1">Compte par défaut</label>
                        <select id="default_account" v-model="defaultAccountId"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary outline-none focus:border-white">
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-xs text-theme-text-secondary mb-1">Vos notes</label>
                    <textarea id="notes" v-model="notes" rows="12" :placeholder="example" aria-describedby="notes-help"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base font-mono text-theme-text-primary outline-none focus:border-white"
                        :aria-invalid="tooLong"></textarea>
                    <p id="notes-help" class="mt-1 text-xs" :class="tooLong ? 'text-red-400' : 'text-theme-text-muted'">
                        {{ lineCount }} / {{ MAX_LINES }} lignes · {{ notes.length }} / {{ MAX_CHARS }} caractères
                        <span v-if="tooLong"> — collez-les en deux fois.</span>
                    </p>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="px-4 py-2 min-h-[44px] text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50"
                        :disabled="!lineCount || tooLong || analysing" :aria-busy="analysing" @click="analyse">
                        {{ analysing ? 'Analyse…' : 'Analyser' }}
                    </button>
                </div>
            </section>

            <!-- ② Vérifier -->
            <section v-else class="mt-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button type="button" class="text-sm text-theme-text-secondary hover:text-theme-text-primary" @click="step = 'paste'">‹ Modifier le texte</button>
                    <p class="text-sm text-theme-text-secondary">
                        Dépenses <span class="text-theme-text-primary">−{{ formatAmount(totals.expense) }}</span> ·
                        Revenus <span class="text-green-400">+{{ formatAmount(totals.income) }}</span> FCFA
                    </p>
                </div>

                <div class="flex gap-2 overflow-x-auto" role="group" aria-label="Filtrer les lignes">
                    <button v-for="f in [
                        { key: 'all', label: 'Tout', count: counts.all },
                        { key: 'incomplete', label: '⚠ À compléter', count: counts.incomplete },
                        { key: 'unparsed', label: '✕ Non comprise', count: counts.unparsed },
                        { key: 'duplicate', label: '⧉ Déjà saisie ?', count: counts.duplicate },
                    ]" :key="f.key" type="button" :aria-pressed="filter === f.key"
                        class="px-3 py-1.5 min-h-[44px] sm:min-h-0 whitespace-nowrap rounded-full border text-xs"
                        :class="filter === f.key ? 'border-white text-theme-text-primary' : 'border-theme-border text-theme-text-secondary'"
                        @click="filter = f.key">
                        {{ f.label }} {{ f.count }}
                    </button>
                </div>

                <p v-if="!savable.length && !counts.unparsed" class="glass-card p-4 text-sm text-theme-text-muted">
                    Aucune opération reconnue. Vérifiez le format.
                    <button type="button" class="underline" @click="step = 'paste'">Modifier le texte</button>
                </p>

                <ul class="glass-card divide-y divide-theme-border">
                    <li v-for="r in filteredRows" :key="rowKey(r)" class="p-3 grid gap-2 sm:grid-cols-[8.5rem_4rem_6rem_minmax(0,1fr)_11rem_10rem_2rem] sm:items-center"
                        :data-incomplete="statusOf(r) === 'incomplete'">
                        <span class="inline-flex items-center gap-1 text-xs"
                            :class="{ 'text-green-400': statusOf(r) === 'ok', 'text-yellow-400': statusOf(r) === 'incomplete', 'text-red-400': statusOf(r) === 'unparsed' }">
                            <CheckIcon v-if="statusOf(r) === 'ok'" class="w-4 h-4" aria-hidden="true" />
                            <ExclamationTriangleIcon v-else-if="statusOf(r) === 'incomplete'" class="w-4 h-4" aria-hidden="true" />
                            <XMarkIcon v-else class="w-4 h-4" aria-hidden="true" />
                            {{ statusOf(r) === 'ok' ? 'OK' : statusOf(r) === 'incomplete' ? 'À compléter' : 'Non comprise' }}
                            <span v-if="r.possible_duplicate" class="inline-flex items-center gap-0.5 text-theme-text-muted" title="Une opération identique existe déjà">
                                <DocumentDuplicateIcon class="w-3.5 h-3.5" aria-hidden="true" /> déjà saisie ?
                            </span>
                        </span>

                        <template v-if="statusOf(r) === 'unparsed'">
                            <span class="sm:col-span-5 text-sm text-theme-text-secondary border border-dashed border-red-400/40 rounded px-2 py-1">
                                Ligne {{ r.line }} : « {{ r.raw }} » — exclue
                            </span>
                            <button type="button" class="text-xs underline text-theme-text-secondary" @click="step = 'paste'">Modifier</button>
                        </template>

                        <template v-else>
                            <!-- iPhone : date, montant et libellé sur une ligne ; desktop : trois colonnes -->
                            <div class="flex items-baseline gap-3 min-w-0 sm:contents">
                                <span class="text-sm text-theme-text-secondary">{{ shortDate(r.date) }}</span>
                                <span class="text-sm whitespace-nowrap" :class="r.type === 'income' ? 'text-green-400' : 'text-theme-text-primary'">
                                    {{ r.type === 'income' ? '+' : '−' }}{{ formatAmount(r.amount) }}
                                </span>
                                <span class="text-sm text-theme-text-primary truncate">{{ r.label || '(sans libellé)' }}</span>
                            </div>
                            <select v-model="r.category_id" @change="remember(r)"
                                :aria-label="`Catégorie, ligne ${r.line} : ${r.label ?? 'sans libellé'}, ${longDate(r.date)}`"
                                class="w-full bg-theme-surface border rounded-md px-2 py-2 text-base sm:text-sm text-theme-text-primary outline-none focus:border-white"
                                :class="r.category_id ? 'border-theme-border' : 'border-yellow-400'">
                                <option :value="null" disabled>Choisir…</option>
                                <option v-for="c in categoriesFor(r.type)" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <select v-model="r.account_id" @change="remember(r)"
                                :aria-label="`Compte, ligne ${r.line} : ${r.label ?? 'sans libellé'}`"
                                class="w-full bg-theme-surface border border-theme-border rounded-md px-2 py-2 text-base sm:text-sm text-theme-text-primary outline-none focus:border-white">
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <button type="button" class="justify-self-start sm:justify-self-center p-2 text-theme-text-secondary hover:text-red-400"
                                :aria-label="`Retirer la ligne ${r.line} : ${r.label ?? ''} ${formatAmount(r.amount)}`" @click="removeRow(r)">
                                <XMarkIcon class="w-4 h-4" aria-hidden="true" />
                            </button>
                        </template>
                    </li>
                </ul>

                <div class="hidden lg:flex items-center justify-end gap-3">
                    <p v-if="counts.incomplete" class="text-sm text-yellow-400">{{ counts.incomplete }} catégorie(s) à choisir avant d'enregistrer.</p>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50"
                        :disabled="!canSave" @click="save">
                        {{ saving ? 'Enregistrement…' : `Enregistrer ${savable.length} opération(s)` }}
                    </button>
                </div>
            </section>
        </div>

        <!-- iPhone : totaux et validation toujours visibles -->
        <div v-if="step === 'review'" class="lg:hidden fixed inset-x-0 bottom-0 z-40 border-t border-theme-border bg-theme-surface px-4 py-3"
            style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <div class="flex items-center gap-3">
                <span class="flex-1 text-xs text-theme-text-secondary">
                    <template v-if="counts.incomplete"><span class="text-yellow-400">⚠ {{ counts.incomplete }} à compléter</span></template>
                    <template v-else>−{{ formatAmount(totals.expense) }} · +{{ formatAmount(totals.income) }}</template>
                </span>
                <button type="button" class="px-4 py-2 min-h-[44px] text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50"
                    :disabled="!canSave" @click="save">
                    Enregistrer {{ savable.length }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
