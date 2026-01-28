<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/reset-password')
}
</script>

<template>
    <Head title="Réinitialiser le mot de passe" />

    <div class="min-h-screen bg-theme-bg flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-10">
                <img src="/images/logo.png" alt="Morex" class="h-10 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary text-sm">Nouveau mot de passe</p>
            </div>

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
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-4 py-3 text-theme-text-primary text-sm placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none transition-colors"
                        placeholder="votre@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-2 text-xs text-danger">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                        Nouveau mot de passe
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autofocus
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-4 py-3 text-theme-text-primary text-sm placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none transition-colors"
                        placeholder="••••••••"
                    />
                    <p v-if="form.errors.password" class="mt-2 text-xs text-danger">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                        Confirmer le mot de passe
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="w-full bg-theme-surface border border-theme-border rounded-md px-4 py-3 text-theme-text-primary text-sm placeholder-theme-text-muted focus:border-white focus:ring-0 outline-none transition-colors"
                        placeholder="••••••••"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium py-3 px-4 rounded-md hover:opacity-90 transition-colors disabled:opacity-50 text-sm"
                >
                    <span v-if="form.processing">Réinitialisation...</span>
                    <span v-else>Réinitialiser le mot de passe</span>
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
