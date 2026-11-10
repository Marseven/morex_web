<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowLeftIcon, ArrowUpIcon, ArrowDownIcon, CheckIcon } from '@heroicons/vue/24/outline'
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
    account: { type: Object, required: true },
    lastReconciliation: { type: Object, default: null },
    uncleared: { type: Array, default: () => [] },
    olderCount: { type: Number, default: 0 },
})

const formatAmount = (amount) => new Intl.NumberFormat('fr-FR').format(amount)
const formatDate = (iso) => new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
const longDate = (iso) => new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })
const label = (tx) => tx.beneficiary || tx.description || tx.category || 'Opération'

const form = useForm({
    statement_balance: null,
    cleared_ids: props.uncleared.map((tx) => tx.id),
    note: '',
})

// RM-5 : une opération non cochée n'est pas encore sur le relevé, elle n'est pas un écart.
const pendingOnStatement = computed(() =>
    props.uncleared
        .filter((tx) => !form.cleared_ids.includes(tx.id))
        .reduce((sum, tx) => sum + tx.signed_amount, 0),
)
const hasStatement = computed(() => Number.isInteger(form.statement_balance))
const difference = computed(() =>
    hasStatement.value ? form.statement_balance - (props.account.balance - pendingOnStatement.value) : 0,
)
const allChecked = computed(() => form.cleared_ids.length === props.uncleared.length)

const toggleAll = () => {
    form.cleared_ids = allChecked.value ? [] : props.uncleared.map((tx) => tx.id)
}

// Annonce vocale de l'écart, sans bavardage à chaque frappe
const announcement = ref('')
let timer
watch([difference, hasStatement], () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        if (!hasStatement.value) return
        announcement.value = difference.value === 0
            ? 'Écart nul'
            : `Écart : ${difference.value > 0 ? 'plus' : 'moins'} ${formatAmount(Math.abs(difference.value))} francs, à justifier`
    }, 500)
})

const confirming = ref(false)
const confirmButton = ref(null)
const cancelButton = ref(null)
const validateButton = ref(null)

// Piège de focus du dialogue : Tab et Maj+Tab restent sur ses deux boutons
const trapFocus = (event) => {
    const target = document.activeElement === confirmButton.value ? cancelButton.value : confirmButton.value
    event.preventDefault()
    target?.focus()
}

const askValidation = async () => {
    if (!hasStatement.value) return
    if (difference.value === 0) return submit()
    confirming.value = true
    await nextTick()
    confirmButton.value?.focus()
}

const cancelConfirm = () => {
    confirming.value = false
    nextTick(() => validateButton.value?.focus())
}

const submit = () => {
    confirming.value = false
    form.post(`/accounts/${props.account.id}/reconciliations`, { preserveScroll: true })
}
</script>

<template>
    <Head :title="`Rapprocher ${account.name}`" />

    <AppLayout>
        <div class="px-4 py-6 lg:px-6 pb-28 lg:pb-6">
            <Link href="/accounts" class="inline-flex items-center gap-1 text-sm text-theme-text-secondary hover:text-theme-text-primary">
                <ArrowLeftIcon class="w-4 h-4" /> Comptes
            </Link>
            <h1 class="mt-2 text-lg font-semibold text-theme-text-primary">Rapprocher {{ account.name }}</h1>
            <p class="text-xs text-theme-text-muted">
                {{ lastReconciliation ? `Dernier rapprochement le ${longDate(lastReconciliation.reconciled_at)}` : 'Premier rapprochement : il servira de point de départ.' }}
            </p>

            <div
                v-if="form.errors.cleared_ids"
                role="alert"
                class="mt-4 flex items-center justify-between gap-3 rounded-md border border-red-400/40 bg-red-400/10 p-3 text-sm text-red-400"
            >
                <span>Une opération ne correspond plus à ce compte. Rechargez la page.</span>
                <Link :href="`/accounts/${account.id}/reconcile`" class="underline">Recharger</Link>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,22rem)_1fr] lg:items-start">
                <!-- Colonne gauche : relevé, écart, validation (fixe sur desktop) -->
                <section class="glass-card p-4 space-y-4 lg:sticky lg:top-4" aria-labelledby="statement-title">
                    <h2 id="statement-title" class="sr-only">Relevé</h2>
                    <div>
                        <label for="statement_balance" class="block text-xs text-theme-text-secondary mb-1">Solde du relevé (FCFA)</label>
                        <input
                            id="statement_balance"
                            v-model.number="form.statement_balance"
                            type="number"
                            inputmode="numeric"
                            step="1"
                            autofocus
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary focus:border-white focus:ring-0 outline-none"
                            :aria-invalid="!!form.errors.statement_balance"
                            aria-describedby="statement-error"
                        />
                        <p id="statement-error" v-if="form.errors.statement_balance" class="text-xs text-red-400 mt-1">
                            Le solde doit être un nombre entier.
                        </p>
                    </div>

                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-theme-text-muted">Solde Morex</dt>
                            <dd class="text-theme-text-primary">{{ formatAmount(account.balance) }} FCFA</dd>
                        </div>
                        <div v-if="pendingOnStatement !== 0" class="flex justify-between">
                            <dt class="text-theme-text-muted">Pas encore sur le relevé</dt>
                            <dd class="text-theme-text-secondary">{{ pendingOnStatement > 0 ? '+' : '' }}{{ formatAmount(pendingOnStatement) }} FCFA</dd>
                        </div>
                    </dl>

                    <div role="status" class="rounded-md border border-theme-border bg-theme-surface p-3">
                        <p v-if="!hasStatement" class="text-sm text-theme-text-muted">Saisissez le solde du relevé.</p>
                        <p v-else-if="difference === 0" class="flex items-center gap-1.5 text-sm text-theme-text-primary">
                            <CheckIcon class="w-4 h-4" /> Écart nul : tout correspond.
                        </p>
                        <p v-else class="flex items-center gap-1.5 text-sm" :class="difference > 0 ? 'text-green-400' : 'text-red-400'">
                            <ArrowUpIcon v-if="difference > 0" class="w-4 h-4" aria-hidden="true" />
                            <ArrowDownIcon v-else class="w-4 h-4" aria-hidden="true" />
                            Écart {{ difference > 0 ? '+' : '−' }}{{ formatAmount(Math.abs(difference)) }} FCFA à justifier
                        </p>
                    </div>
                    <p class="sr-only" aria-live="polite">{{ announcement }}</p>

                    <div>
                        <label for="note" class="block text-xs text-theme-text-secondary mb-1">Note (facultatif)</label>
                        <input id="note" v-model="form.note" maxlength="500" type="text"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-white focus:ring-0 outline-none" />
                    </div>

                    <!-- Desktop : bouton dans la colonne ; mobile : barre fixe en bas -->
                    <button
                        ref="validateButton"
                        type="button"
                        class="hidden lg:block w-full py-2 text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50"
                        :disabled="!hasStatement || form.processing"
                        @click="askValidation"
                    >
                        Valider le rapprochement
                    </button>
                </section>

                <!-- Colonne droite : opérations à pointer -->
                <section class="glass-card" aria-labelledby="uncleared-title">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-theme-border">
                        <h2 id="uncleared-title" class="text-sm font-semibold text-theme-text-primary">
                            Opérations non pointées ({{ uncleared.length }})
                        </h2>
                        <button v-if="uncleared.length" type="button" class="text-xs text-theme-text-secondary hover:text-theme-text-primary" @click="toggleAll">
                            {{ allChecked ? 'Tout décocher' : 'Tout cocher' }}
                        </button>
                    </div>

                    <p v-if="!uncleared.length" class="px-4 py-6 text-sm text-theme-text-muted">
                        Tout est pointé. Vérifiez que le solde du relevé correspond à celui de Morex.
                    </p>

                    <ul v-else class="divide-y divide-theme-border">
                        <li v-for="tx in uncleared" :key="tx.id" class="flex items-center gap-3 px-4 py-2 min-h-[44px]">
                            <input
                                :id="`tx-${tx.id}`"
                                v-model="form.cleared_ids"
                                :value="tx.id"
                                type="checkbox"
                                class="h-5 w-5 rounded border-theme-border"
                                :aria-label="`Pointer : ${label(tx)}, ${longDate(tx.date)}, ${tx.signed_amount < 0 ? 'moins' : 'plus'} ${formatAmount(Math.abs(tx.signed_amount))} francs`"
                            />
                            <label :for="`tx-${tx.id}`" class="min-w-0 flex-1 cursor-pointer">
                                <span class="block text-sm text-theme-text-primary truncate">{{ label(tx) }}</span>
                                <span class="block text-xs text-theme-text-muted">{{ formatDate(tx.date) }}<span v-if="tx.category"> · {{ tx.category }}</span></span>
                            </label>
                            <span class="text-sm whitespace-nowrap" :class="tx.signed_amount < 0 ? 'text-theme-text-primary' : 'text-green-400'">
                                {{ tx.signed_amount < 0 ? '−' : '+' }}{{ formatAmount(Math.abs(tx.signed_amount)) }}
                            </span>
                            <Link :href="`/transactions/${tx.id}/edit`" class="text-xs text-theme-text-secondary hover:text-theme-text-primary underline">corriger</Link>
                        </li>
                    </ul>
                    <p v-if="uncleared.length" class="px-4 py-2 text-xs text-theme-text-muted border-t border-theme-border">
                        Cochées : {{ form.cleared_ids.length }} / {{ uncleared.length }}
                    </p>
                    <p v-if="olderCount" class="px-4 py-2 text-xs text-theme-text-muted border-t border-theme-border">
                        {{ olderCount }} opération(s) plus ancienne(s) seront considérées comme pointées (point de départ).
                    </p>
                </section>
            </div>
        </div>

        <!-- Mobile : écart et validation toujours visibles -->
        <div class="lg:hidden fixed inset-x-0 bottom-0 z-40 border-t border-theme-border bg-theme-surface px-4 py-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <div class="flex items-center gap-3">
                <span class="flex-1 text-sm" :class="!hasStatement || difference === 0 ? 'text-theme-text-muted' : difference > 0 ? 'text-green-400' : 'text-red-400'">
                    <template v-if="!hasStatement">Saisissez le relevé</template>
                    <template v-else-if="difference === 0">Écart nul</template>
                    <template v-else>Écart {{ difference > 0 ? '▲ +' : '▼ −' }}{{ formatAmount(Math.abs(difference)) }}</template>
                </span>
                <button
                    type="button"
                    class="px-4 py-2 min-h-[44px] text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text disabled:opacity-50"
                    :disabled="!hasStatement || form.processing"
                    @click="askValidation"
                >
                    Valider
                </button>
            </div>
        </div>

        <!-- Confirmation d'un écart non nul -->
        <div v-if="confirming" class="fixed inset-0 z-50 flex items-center justify-center" @keydown.esc="cancelConfirm" @keydown.tab="trapFocus">
            <div class="absolute inset-0 bg-black/50" @click="cancelConfirm"></div>
            <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text" class="relative glass-card p-6 w-full max-w-sm mx-4">
                <h3 id="confirm-title" class="text-sm font-semibold text-theme-text-primary">Créer une opération d'écart ?</h3>
                <p id="confirm-text" class="mt-2 text-sm text-theme-text-secondary">
                    Une opération de {{ difference > 0 ? '+' : '−' }}{{ formatAmount(Math.abs(difference)) }} FCFA
                    ({{ difference > 0 ? 'revenu' : 'dépense' }}, catégorie « Écart de rapprochement », datée d'aujourd'hui) sera enregistrée.
                    Elle ne compte pas dans vos budgets.
                </p>
                <div class="flex gap-2 mt-4">
                    <button ref="cancelButton" type="button" class="flex-1 py-2 text-sm text-theme-text-secondary border border-theme-border rounded-md" @click="cancelConfirm">Annuler</button>
                    <button ref="confirmButton" type="button" class="flex-1 py-2 text-sm font-medium rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text" @click="submit">Confirmer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
