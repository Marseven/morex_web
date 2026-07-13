<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowUpTrayIcon, TrashIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const accountId = ref(props.accounts.find(a => a.is_default)?.id || props.accounts[0]?.id || '')
const rawText = ref('')
const rows = ref([])
const parseError = ref('')

const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(n || 0)

// --- Parsing CSV ---------------------------------------------------------
function normalizeDate(raw) {
    if (!raw) return ''
    const s = raw.trim()
    // yyyy-mm-dd
    let m = s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/)
    if (m) return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`
    // dd/mm/yyyy ou dd-mm-yyyy (ou yy)
    m = s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{2,4})$/)
    if (m) {
        let year = m[3]
        if (year.length === 2) year = '20' + year
        return `${year}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`
    }
    return ''
}

function matchCategoryId(name) {
    if (!name) return ''
    const n = name.trim().toLowerCase()
    const found = props.categories.find(c => c.name.trim().toLowerCase() === n)
    return found ? found.id : ''
}

function parse() {
    parseError.value = ''
    const lines = rawText.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean)
    const out = []
    for (const line of lines) {
        const sep = line.includes(';') ? ';' : ','
        const parts = line.split(sep).map(p => p.trim().replace(/^"|"$/g, ''))
        const date = normalizeDate(parts[0])
        const amount = parseInt(String(parts[1] ?? '').replace(/[^\d]/g, ''), 10)
        if (!date || !amount) continue // ignore en-tête / lignes invalides
        const typeRaw = (parts[2] || '').toLowerCase()
        const type = /revenu|income|credit|entr/.test(typeRaw) ? 'income' : 'expense'
        out.push({
            date,
            amount,
            type,
            beneficiary: parts[3] || '',
            description: parts[4] || '',
            category_id: matchCategoryId(parts[5]),
        })
    }
    if (out.length === 0) {
        parseError.value = 'Aucune ligne valide détectée. Format attendu : date;montant;type;bénéficiaire;description;catégorie'
    }
    rows.value = out
}

function onFile(e) {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => { rawText.value = String(reader.result || ''); parse() }
    reader.readAsText(file)
}

function removeRow(i) { rows.value.splice(i, 1) }

const total = computed(() => rows.value.reduce((s, r) => s + (r.type === 'income' ? r.amount : -r.amount), 0))

// --- Import --------------------------------------------------------------
const form = useForm({ account_id: '', rows: [] })

const submit = () => {
    form.account_id = accountId.value
    form.rows = rows.value
    form.post('/transactions/import')
}
</script>

<template>
    <Head title="Importer un relevé" />

    <AppLayout>
        <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center gap-2">
                <ArrowUpTrayIcon class="w-5 h-5 text-theme-text-secondary" />
                <h1 class="text-lg font-semibold text-theme-text-primary">Importer un relevé</h1>
            </div>

            <!-- Compte cible -->
            <div class="glass-card p-4 space-y-3">
                <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Compte à créditer/débiter</label>
                <select
                    v-model="accountId"
                    class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                >
                    <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
            </div>

            <!-- Saisie CSV -->
            <div class="glass-card p-4 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Données (CSV)</label>
                    <label class="inline-flex items-center gap-1 text-xs text-theme-text-secondary hover:text-theme-text-primary cursor-pointer">
                        <DocumentTextIcon class="w-4 h-4" /> Charger un fichier
                        <input type="file" accept=".csv,text/csv,text/plain" class="hidden" @change="onFile" />
                    </label>
                </div>
                <p class="text-xs text-theme-text-muted">
                    Une ligne par transaction : <code class="text-theme-text-secondary">date ; montant ; type ; bénéficiaire ; description ; catégorie</code><br>
                    Ex : <code class="text-theme-text-secondary">26/06/2026 ; 5945 ; expense ; Netflix ; Abonnement ; Abonnements</code>
                </p>
                <textarea
                    v-model="rawText"
                    rows="6"
                    placeholder="Collez vos lignes ici…"
                    class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary font-mono focus:border-theme-text-primary focus:ring-0 outline-none"
                ></textarea>
                <div class="flex items-center gap-3">
                    <button
                        @click="parse"
                        type="button"
                        class="px-4 py-2 text-sm border border-theme-border rounded-md text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                    >
                        Analyser
                    </button>
                    <p v-if="parseError" class="text-xs text-danger">{{ parseError }}</p>
                </div>
            </div>

            <!-- Revue -->
            <div v-if="rows.length > 0" class="glass-card p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-theme-text-primary">{{ rows.length }} transaction(s)</p>
                    <p class="text-sm" :class="total >= 0 ? 'text-success' : 'text-danger'">
                        Net : {{ total >= 0 ? '+' : '' }}{{ formatAmount(total) }} FCFA
                    </p>
                </div>

                <ul class="divide-y divide-theme-border">
                    <li v-for="(row, i) in rows" :key="i" class="py-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <input v-model="row.date" type="date" class="bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <select v-model="row.type" class="bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none">
                                <option value="expense">Dépense</option>
                                <option value="income">Revenu</option>
                            </select>
                            <input v-model.number="row.amount" type="number" min="1" class="w-24 bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-right text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <button @click="removeRow(i)" type="button" class="ml-auto p-1 text-theme-text-secondary hover:text-danger" aria-label="Retirer">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="row.beneficiary" type="text" placeholder="Bénéficiaire" class="flex-1 min-w-0 bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <select v-model="row.category_id" class="flex-1 min-w-0 bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none">
                                <option value="">Sans catégorie</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </li>
                </ul>

                <div v-if="form.errors.rows || form.errors.account_id" class="text-xs text-danger">
                    {{ form.errors.rows || form.errors.account_id }}
                </div>

                <div class="flex gap-3 pt-2">
                    <Link href="/transactions" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
                        Annuler
                    </Link>
                    <button
                        @click="submit"
                        :disabled="form.processing || !accountId"
                        class="flex-1 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Import…' : `Importer ${rows.length}` }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
