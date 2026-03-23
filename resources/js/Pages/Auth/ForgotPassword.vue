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
        <!-- Atmospheric background -->
        <div class="atmosphere"></div>

        <div class="w-full max-w-sm relative z-[1] animate-fade-up">
            <div class="text-center mb-10">
                <img src="/images/logo.png" alt="Morex" class="h-12 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary text-sm">Mot de passe oublié ?</p>
            </div>

            <div v-if="page.props.flash?.success" class="mb-6 glass-card border-success/30 text-success px-4 py-3 text-sm">
                {{ page.props.flash.success }}
            </div>

            <div class="glass-card p-6">
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
                            class="w-full bg-theme-surface/50 border border-theme-border rounded-lg px-4 py-3 text-theme-text-primary text-sm placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
                            placeholder="votre@email.com"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-xs text-danger">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-theme-btn-primary-bg text-theme-btn-primary-text font-semibold py-3 px-4 rounded-lg hover:opacity-90 transition-all duration-200 disabled:opacity-50 text-sm hover:shadow-[0_0_20px_rgba(219,242,39,0.25)]"
                    >
                        <span v-if="form.processing">Envoi en cours...</span>
                        <span v-else>Envoyer le lien</span>
                    </button>

                    <div class="text-center">
                        <Link href="/login" class="text-sm text-theme-text-secondary hover:text-[var(--color-accent)] transition-colors">
                            Retour à la connexion
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
