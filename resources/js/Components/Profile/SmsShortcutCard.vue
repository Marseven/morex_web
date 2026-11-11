<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
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
const issue = () => {
    if (props.status.active && !confirm("Créer un nouveau jeton ? L'ancien cessera de fonctionner : il faudra mettre à jour le Raccourci.")) return
    router.post('/profile/sms-token', {}, { preserveScroll: true })
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
                Un Raccourci iOS envoie vos SMS bancaires à Morex. Ce jeton ne sert qu'à cela : il ne donne accès à aucune autre donnée.
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

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="issue" class="min-h-11 px-4 rounded-md bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium">
                {{ status.active ? 'Remplacer le jeton' : 'Créer le jeton' }}
            </button>
            <button v-if="status.active" type="button" @click="revoke" class="min-h-11 px-4 rounded-md border border-theme-border text-sm text-theme-text-secondary">Révoquer</button>
        </div>
        <p class="text-xs text-theme-text-muted">Guide de configuration du Raccourci : docs/raccourci-sms.md (dépôt morex_web).</p>
    </section>
</template>
