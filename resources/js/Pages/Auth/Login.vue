<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import Toast from '@/Components/Toast.vue'
import { useToast } from '@/Composables/useToast'

const { warning } = useToast()

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/login', {
        onError: (errors) => {
            // Handle 419 CSRF token mismatch
            if (Object.keys(errors).length === 0) {
                warning('Votre session a expiré. La page va se rafraîchir.')
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            }
        },
    })
}
</script>

<template>
    <div class="min-h-screen bg-theme-bg flex items-center justify-center p-4">
        <!-- Atmospheric background -->
        <div class="atmosphere"></div>

        <Toast />
        <div class="w-full max-w-sm relative z-[1] animate-fade-up">
            <div class="text-center mb-10">
                <img src="/images/logo.png" alt="Morex" class="h-10 w-auto mx-auto mb-4" />
                <p class="text-theme-text-secondary text-sm">Pilotez votre avenir financier</p>
            </div>

            <form @submit.prevent="submit" class="glass-card p-6 space-y-5">
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

                <div>
                    <label for="password" class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                        Mot de passe
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full bg-theme-surface/50 border border-theme-border rounded-lg px-4 py-3 text-theme-text-primary text-sm placeholder-theme-text-muted input-glow focus:ring-0 outline-none transition-all duration-200"
                        placeholder="••••••••"
                    />
                    <p v-if="form.errors.password" class="mt-2 text-xs text-danger">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 bg-theme-surface/50 border-theme-border rounded text-[var(--color-brand)] focus:ring-[var(--color-brand)] focus:ring-offset-0"
                        />
                        <label for="remember" class="ml-2 text-sm text-theme-text-secondary">
                            Se souvenir de moi
                        </label>
                    </div>
                    <Link href="/forgot-password" class="text-sm text-theme-text-secondary hover:text-[var(--color-accent)] transition-colors">
                        Mot de passe oublié ?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-theme-btn-primary-bg text-theme-btn-primary-text font-semibold py-3 px-4 rounded-lg hover:opacity-90 transition-all duration-200 disabled:opacity-50 text-sm hover:shadow-[0_0_20px_rgba(219,242,39,0.25)]"
                >
                    <span v-if="form.processing">Connexion...</span>
                    <span v-else>Se connecter</span>
                </button>
            </form>
        </div>
    </div>
</template>
