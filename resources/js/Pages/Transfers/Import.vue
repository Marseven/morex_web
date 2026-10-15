<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowUpTrayIcon, TrashIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    accounts: { type: Array, default: () => [] },
})

const defaultFrom = ref(props.accounts.find(a => a.is_default)?.id || props.accounts[0]?.id || '')
const defaultTo = ref(props.accounts[1]?.id || props.accounts[0]?.id || '')
const rawText = ref('')
const rows = ref([])
const parseError = ref('')

const formatAmount = (n) => new Intl.NumberFormat('fr-FR').format(n || 0)

// --- Parsing CSV ---------------------------------------------------------
function normalizeDate(raw) {
    if (!raw) return ''
    const s = raw.trim()
    let m = s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/)
    if (m) return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`
    m = s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{2,4})$/)
    if (m) {
        let year = m[3]
        if (year.length === 2) year = '20' + year
        return `${year}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`
    }
    return ''
}

function matchAccountId(name, fallback) {
    if (!name) return fallback
    const n = name.trim().toLowerCase()
    const found = props.accounts.find(a => a.name.trim().toLowerCase() === n)
    return found ? found.id : fallback
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
        if (!date || !amount) continue
        out.push({
            date,
            amount,
            from_account_id: matchAccountId(parts[2], defaultFrom.value),
            to_account_id: matchAccountId(parts[3], defaultTo.value),
            description: parts[4] || '',
        })
    }
    if (out.length === 0) {
        parseError.value = 'Aucune ligne valide détectée. Format attendu : date;montant;compte_source;compte_destination;description'
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

const total = computed(() => rows.value.reduce((s, r) => s + (r.amount || 0), 0))
const hasSameAccount = computed(() => rows.value.some(r => r.from_account_id === r.to_account_id))

// --- Import --------------------------------------------------------------
const form = useForm({ rows: [] })

const submit = () => {
    form.rows = rows.value
    form.post('/transfers/import')
}
</script>

<template>
    <Head title="Importer des transferts" />

    <AppLayout>
        <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center gap-2">
                <ArrowUpTrayIcon class="w-5 h-5 text-theme-text-secondary" />
                <h1 class="text-lg font-semibold text-theme-text-primary">Importer des transferts</h1>
            </div>

            <!-- Comptes par défaut -->
            <div class="glass-card p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-1">Depuis (par défaut)</label>
                    <select
                        v-model="defaultFrom"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                    >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-1">Vers (par défaut)</label>
                    <select
                        v-model="defaultTo"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                    >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <p class="sm:col-span-2 text-xs text-theme-text-muted">
                    Utilisés quand la ligne CSV ne précise pas les comptes (ou si le nom ne correspond à aucun compte).
                </p>
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
                    Une ligne par transfert : <code class="text-theme-text-secondary">date ; montant ; compte_source ; compte_destination ; description</code><br>
                    Ex : <code class="text-theme-text-secondary">27/07/2026 ; 250000 ; Compte Courant ; Portefeuille ; Retrait</code>
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
                    <p class="text-sm font-medium text-theme-text-primary">{{ rows.length }} transfert(s)</p>
                    <p class="text-sm text-blue-400">Total : {{ formatAmount(total) }} FCFA</p>
                </div>

                <ul class="divide-y divide-theme-border">
                    <li v-for="(row, i) in rows" :key="i" class="py-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <input v-model="row.date" type="date" class="bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <input v-model.number="row.amount" type="number" min="1" class="w-28 bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-right text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                            <button @click="removeRow(i)" type="button" class="ml-auto p-1 text-theme-text-secondary hover:text-danger" aria-label="Retirer">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="row.from_account_id" class="flex-1 min-w-0 bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none">
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <span class="text-xs text-theme-text-muted">→</span>
                            <select v-model="row.to_account_id" class="flex-1 min-w-0 bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none">
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <input v-model="row.description" type="text" placeholder="Description" class="w-full bg-theme-surface border border-theme-border rounded px-2 py-1 text-xs text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none" />
                        <p v-if="row.from_account_id === row.to_account_id" class="text-xs text-danger">Comptes source et destination identiques — cette ligne sera ignorée.</p>
                    </li>
                </ul>

                <div v-if="form.errors.rows" class="text-xs text-danger">{{ form.errors.rows }}</div>

                <div class="flex gap-3 pt-2">
                    <Link href="/transfers" class="flex-1 text-center py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors">
                        Annuler
                    </Link>
                    <button
                        @click="submit"
                        :disabled="form.processing || hasSameAccount"
                        class="flex-1 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Import…' : `Importer ${rows.length}` }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
