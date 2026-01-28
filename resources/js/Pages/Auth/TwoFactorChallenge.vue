<script setup>
import { ref } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const useRecoveryCode = ref(false)

const form = useForm({
    code: '',
    recovery_code: '',
})

const submit = () => {
    form.post('/two-factor-challenge')
}

const toggleRecoveryCode = () => {
    useRecoveryCode.value = !useRecoveryCode.value
    form.code = ''
    form.recovery_code = ''
    form.clearErrors()
}
</script>

<template>
    <Head title="Vérification en deux étapes" />

    <div class="min-h-screen bg-theme-bg flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-10">
                <img src="/images/logo.png" alt="Morex" class="h-10 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary text-sm">Vérification en deux étapes</p>
            </div>

            <p class="text-theme-text-secondary text-sm mb-6">
                <template v-if="!useRecoveryCode">
                    Entrez le code à 6 chiffres généré par votre application d'authentification.
                </template>
                <template v-else>
                    Entrez l'un de vos codes de récupération.
                </template>
            </p>

            <form @submit.prevent="submit" class="space-y-5">
                <div v-if="!useRecoveryCode">
                    <label for="code" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                        Code d'authentification
                    </label>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-4 py-3 text-theme-text-primary text-sm text-center tracking-widest font-mono placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none transition-colors"
                        placeholder="000000"
                    />
                    <p v-if="form.errors.code" class="mt-2 text-xs text-danger">
                        {{ form.errors.code }}
                    </p>
                </div>

                <div v-else>
                    <label for="recovery_code" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                        Code de récupération
                    </label>
                    <input
                        id="recovery_code"
                        v-model="form.recovery_code"
                        type="text"
                        required
                        autofocus
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-4 py-3 text-theme-text-primary text-sm text-center tracking-widest font-mono placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none transition-colors"
                        placeholder="XXXX-XXXX"
                    />
                    <p v-if="form.errors.recovery_code" class="mt-2 text-xs text-danger">
                        {{ form.errors.recovery_code }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium py-3 px-4 rounded-md hover:opacity-90 transition-colors disabled:opacity-50 text-sm"
                >
                    <span v-if="form.processing">Vérification...</span>
                    <span v-else>Vérifier</span>
                </button>

                <div class="text-center space-y-2">
                    <button
                        type="button"
                        @click="toggleRecoveryCode"
                        class="text-sm text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                    >
                        <template v-if="!useRecoveryCode">
                            Utiliser un code de récupération
                        </template>
                        <template v-else>
                            Utiliser le code d'authentification
                        </template>
                    </button>
                    <div>
                        <Link href="/login" class="text-sm text-theme-text-muted hover:text-theme-text-secondary transition-colors">
                            Annuler
                        </Link>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
