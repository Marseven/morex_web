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
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <img src="/images/logo.png" alt="Morex" class="h-10 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary">Créez votre compte</p>
            </div>

            <div class="bg-theme-card rounded-2xl p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-theme-text-secondary mb-2">
                            Nom
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            autofocus
                            class="w-full bg-theme-surface border border-theme-border rounded-xl px-4 py-3 text-theme-text-primary placeholder-theme-text-secondary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                            placeholder="Votre nom"
                        />
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-theme-text-secondary mb-2">
                            Email
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-xl px-4 py-3 text-theme-text-primary placeholder-theme-text-secondary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                            placeholder="votre@email.com"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-500">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-theme-text-secondary mb-2">
                            Mot de passe
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-xl px-4 py-3 text-theme-text-primary placeholder-theme-text-secondary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-500">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-theme-text-secondary mb-2">
                            Confirmer le mot de passe
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-xl px-4 py-3 text-theme-text-primary placeholder-theme-text-secondary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                            placeholder="••••••••"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-primary hover:bg-primary-dark text-theme-text-primary font-medium py-3 px-4 rounded-xl transition-colors disabled:opacity-50"
                    >
                        <span v-if="form.processing">Création...</span>
                        <span v-else>Créer mon compte</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-theme-text-secondary">
                        Déjà un compte ?
                        <Link href="/login" class="text-primary hover:underline">
                            Se connecter
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
