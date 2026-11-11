<script setup>
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { CheckIcon, ClipboardDocumentIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

// Raccourci SMS (module sms-iphone) : jeton réservé au webhook, affiché une seule fois
const props = defineProps({
    status: { type: Object, default: () => ({ active: false }) },
    webhookUrl: { type: String, required: true },
})

const page = usePage()
const token = computed(() => page.props.flash?.sms_token || '')
const copied = ref('')
const formatDate = (iso) => iso ? new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'jamais'

const copy = async (value, what) => {
    try {
        await navigator.clipboard.writeText(value)
        copied.value = what
    } catch {
        copied.value = ''
    }
}
// Créer ou remplacer le jeton demande le mot de passe (revue de sécurité du module)
const asking = ref(false)
const form = useForm({ password: '' })
const issue = () => {
    form.post('/profile/sms-token', {
        preserveScroll: true,
        onSuccess: () => { asking.value = false; form.reset() },
    })
}
const revoke = () => {
    if (confirm('Révoquer le jeton ? Le Raccourci ne pourra plus envoyer de SMS.')) router.delete('/profile/sms-token', { preserveScroll: true })
}
</script>

<template>
    <section class="glass-card p-6 space-y-4" aria-labelledby="sms-shortcut-title">
        <div>
            <h2 id="sms-shortcut-title" class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Raccourci SMS (iPhone)</h2>
            <p class="text-sm text-theme-text-muted mt-1">
                Un Raccourci iOS envoie vos SMS bancaires à Morex. Ce jeton ne sert qu'à envoyer des SMS : il ne permet ni de lire ni de modifier vos comptes. Un SMS peut seulement créer une opération en attente, ou pointer une opération de même montant.
            </p>
        </div>

        <p class="text-sm text-theme-text-secondary">
            <template v-if="status.active">Jeton actif · créé le {{ formatDate(status.created_at) }} · dernier SMS : {{ formatDate(status.last_used_at) }}</template>
            <template v-else>Aucun jeton actif.</template>
        </p>

        <div v-if="token" class="rounded-md border border-warning/40 bg-warning/10 p-4 space-y-3" role="status">
            <p class="flex items-start gap-2 text-sm text-warning">
                <ExclamationTriangleIcon class="w-5 h-5 shrink-0" aria-hidden="true" />
                Copiez ce jeton maintenant : il ne sera plus jamais affiché.
            </p>
            <div v-for="item in [{ key: 'token', label: 'Jeton', value: token }, { key: 'url', label: 'URL du webhook', value: webhookUrl }]" :key="item.key" class="space-y-1">
                <p class="text-xs text-theme-text-secondary">{{ item.label }}</p>
                <div class="flex gap-2">
                    <code class="flex-1 min-w-0 break-all rounded bg-theme-surface px-3 py-2 text-sm text-theme-text-primary">{{ item.value }}</code>
                    <button type="button" @click="copy(item.value, item.key)" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-md border border-theme-border text-theme-text-secondary" :aria-label="`Copier : ${item.label}`">
                        <CheckIcon v-if="copied === item.key" class="w-5 h-5" aria-hidden="true" />
                        <ClipboardDocumentIcon v-else class="w-5 h-5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>

        <form v-if="asking" @submit.prevent="issue" class="space-y-2">
            <label for="sms-token-password" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Mot de passe</label>
            <input id="sms-token-password" v-model="form.password" type="password" autocomplete="current-password" required
                :aria-invalid="form.errors.password ? 'true' : 'false'" aria-describedby="sms-token-password-help"
                class="w-full min-h-11 bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-base text-theme-text-primary focus:border-white focus:ring-0 outline-none" />
            <p id="sms-token-password-help" class="text-sm text-theme-text-muted">
                {{ status.active ? "L'ancien jeton cessera de fonctionner : il faudra mettre à jour le Raccourci." : 'Confirmez votre mot de passe pour créer le jeton.' }}
            </p>
            <p v-if="form.errors.password" role="alert" class="text-sm text-danger">{{ form.errors.password }}</p>
            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-11 px-4 rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium disabled:opacity-50">Confirmer</button>
                <button type="button" @click="asking = false" class="min-h-11 px-4 rounded-md border border-theme-border text-sm text-theme-text-secondary">Annuler</button>
            </div>
        </form>

        <div v-else class="flex flex-wrap gap-2">
            <button type="button" @click="asking = true" class="min-h-11 px-4 rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium">
                {{ status.active ? 'Remplacer le jeton' : 'Créer le jeton' }}
            </button>
            <button v-if="status.active" type="button" @click="revoke" class="min-h-11 px-4 rounded-md border border-theme-border text-sm text-theme-text-secondary">Révoquer</button>
        </div>
        <p class="text-xs text-theme-text-muted">Guide de configuration du Raccourci : docs/raccourci-sms.md (dépôt morex_web).</p>
    </section>
</template>
