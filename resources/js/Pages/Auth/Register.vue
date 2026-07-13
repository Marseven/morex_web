<script setup>
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/register')
}
</script>

<template>
    <div class="min-h-screen bg-theme-bg flex items-center justify-center p-4">
        <!-- Atmospheric background -->
        <div class="atmosphere"></div>

        <div class="w-full max-w-md relative z-[1] animate-fade-up">
            <div class="text-center mb-8">
                <img src="/images/logo.png" alt="Morex" class="h-12 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary">Créez votre compte</p>
            </div>

            <div class="glass-card p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="name" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                            Nom
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            autofocus
                            class="w-full bg-theme-surface border border-theme-border rounded-lg px-4 py-3 text-theme-text-primary placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
                            placeholder="Votre nom"
                        />
                        <p v-if="form.errors.name" class="mt-2 text-xs text-danger">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                            Email
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-lg px-4 py-3 text-theme-text-primary placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
                            placeholder="votre@email.com"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-xs text-danger">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                            Mot de passe
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-lg px-4 py-3 text-theme-text-primary placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
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
                            class="w-full bg-theme-surface border border-theme-border rounded-lg px-4 py-3 text-theme-text-primary placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
                            placeholder="••••••••"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-theme-btn-primary-bg text-theme-btn-primary-text font-semibold py-3 px-4 rounded-lg hover:opacity-90 transition-all duration-200 disabled:opacity-50 text-sm hover:shadow-[0_0_20px_rgba(219,242,39,0.25)]"
                    >
                        <span v-if="form.processing">Création...</span>
                        <span v-else>Créer mon compte</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-theme-text-secondary text-sm">
                        Déjà un compte ?
                        <Link href="/login" class="text-[var(--color-accent)] hover:underline font-medium">
                            Se connecter
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
