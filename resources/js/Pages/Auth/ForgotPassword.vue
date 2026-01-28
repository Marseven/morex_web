<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const form = useForm({
    email: '',
})

const submit = () => {
    form.post('/forgot-password')
}
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div class="min-h-screen bg-theme-bg flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-10">
                <img src="/images/logo.png" alt="Morex" class="h-10 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary text-sm">Mot de passe oublié ?</p>
            </div>

            <div v-if="page.props.flash?.success" class="mb-6 bg-theme-surface border border-green-500/30 text-green-400 rounded-md px-4 py-3 text-sm">
                {{ page.props.flash.success }}
            </div>

            <p class="text-theme-text-secondary text-sm mb-6">
                Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.
            </p>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label for="email" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                        Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-4 py-3 text-theme-text-primary text-sm placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none transition-colors"
                        placeholder="votre@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-2 text-xs text-danger">
                        {{ form.errors.email }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium py-3 px-4 rounded-md hover:opacity-90 transition-colors disabled:opacity-50 text-sm"
                >
                    <span v-if="form.processing">Envoi en cours...</span>
                    <span v-else>Envoyer le lien</span>
                </button>

                <div class="text-center">
                    <Link href="/login" class="text-sm text-theme-text-secondary hover:text-theme-text-primary transition-colors">
                        Retour à la connexion
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
