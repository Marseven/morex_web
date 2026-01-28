<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { UserIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    user: { type: Object, required: true },
    twoFactor: { type: Object, default: () => ({ enabled: false, qrCodeSvg: null, secret: null, recoveryCodes: null }) },
})

const page = usePage()

// Profile form
const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
    phone: props.user.phone || '',
})

const submitProfile = () => {
    profileForm.put('/profile')
}

// Password form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const submitPassword = () => {
    passwordForm.put('/profile/password', {
        onSuccess: () => {
            passwordForm.reset()
        }
    })
}

// Avatar
const avatarInput = ref(null)
const avatarPreview = ref(null)

const selectAvatar = () => {
    avatarInput.value.click()
}

const handleAvatarChange = (e) => {
    const file = e.target.files[0]
    if (file) {
        avatarPreview.value = URL.createObjectURL(file)

        const formData = new FormData()
        formData.append('avatar', file)

        router.post('/profile/avatar', formData, {
            forceFormData: true,
        })
    }
}

const deleteAvatar = () => {
    if (confirm('Supprimer la photo de profil ?')) {
        router.delete('/profile/avatar')
        avatarPreview.value = null
    }
}

// Theme
const themeForm = useForm({
    theme: props.user.theme || 'dark',
})

const submitTheme = () => {
    themeForm.put('/profile/theme')
}

const getAvatarUrl = () => {
    if (avatarPreview.value) return avatarPreview.value
    if (props.user.avatar) return `/storage/${props.user.avatar}`
    return null
}

const getInitials = () => {
    return props.user.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

// Two-Factor Authentication
const showRecoveryCodes = ref(false)

const twoFactorEnableForm = useForm({})
const twoFactorConfirmForm = useForm({
    code: '',
})
const twoFactorDisableForm = useForm({
    password: '',
})
const regenerateCodesForm = useForm({
    password: '',
})

const enableTwoFactor = () => {
    twoFactorEnableForm.post('/two-factor/enable', {
        preserveScroll: true,
    })
}

const confirmTwoFactor = () => {
    twoFactorConfirmForm.post('/two-factor/confirm', {
        preserveScroll: true,
        onSuccess: () => {
            twoFactorConfirmForm.reset()
            showRecoveryCodes.value = true
        },
    })
}

const disableTwoFactor = () => {
    if (!confirm('Voulez-vous vraiment désactiver l\'authentification à deux facteurs ?')) return

    twoFactorDisableForm.delete('/two-factor/disable', {
        preserveScroll: true,
        onSuccess: () => {
            twoFactorDisableForm.reset()
            showRecoveryCodes.value = false
        },
    })
}

const regenerateRecoveryCodes = () => {
    regenerateCodesForm.post('/two-factor/recovery-codes', {
        preserveScroll: true,
        onSuccess: () => {
            regenerateCodesForm.reset()
        },
    })
}

const isSettingUp2FA = computed(() => {
    return props.twoFactor.qrCodeSvg && !props.twoFactor.enabled
})
</script>

<template>
    <Head title="Profil" />

    <AppLayout>
        <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center gap-2">
                <UserIcon class="w-5 h-5 text-theme-text-secondary" />
                <h1 class="text-lg font-semibold text-theme-text-primary">Mon profil</h1>
            </div>

            <!-- Avatar Section -->
            <div class="bg-theme-card border border-theme-border rounded-lg p-6">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-4">Photo de profil</h2>

                <div class="flex items-center gap-6">
                    <div class="relative">
                        <div
                            v-if="getAvatarUrl()"
                            class="w-20 h-20 rounded-full bg-cover bg-center border-2 border-theme-border"
                            :style="{ backgroundImage: `url(${getAvatarUrl()})` }"
                        ></div>
                        <div
                            v-else
                            class="w-20 h-20 rounded-full bg-theme-surface border-2 border-theme-border flex items-center justify-center"
                        >
                            <span class="text-2xl font-semibold text-theme-text-secondary">{{ getInitials() }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleAvatarChange"
                        />
                        <button
                            type="button"
                            @click="selectAvatar"
                            class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                        >
                            Changer la photo
                        </button>
                        <button
                            v-if="user.avatar"
                            type="button"
                            @click="deleteAvatar"
                            class="px-4 py-2 text-sm text-theme-text-secondary hover:text-danger border border-theme-border rounded-md transition-colors"
                        >
                            Supprimer
                        </button>
                    </div>
                </div>
                <p class="text-xs text-theme-text-muted mt-3">JPG, PNG ou GIF. Max 2MB.</p>
            </div>

            <!-- Profile Information -->
            <form @submit.prevent="submitProfile" class="bg-theme-card border border-theme-border rounded-lg p-6">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-4">Informations personnelles</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nom</label>
                        <input
                            v-model="profileForm.name"
                            type="text"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                        />
                        <p v-if="profileForm.errors.name" class="text-xs text-danger mt-1">{{ profileForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Email</label>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                        />
                        <p v-if="profileForm.errors.email" class="text-xs text-danger mt-1">{{ profileForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Téléphone</label>
                        <input
                            v-model="profileForm.phone"
                            type="tel"
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary placeholder-theme-text-muted focus:border-theme-text-primary focus:ring-0 outline-none"
                            placeholder="+241 77 12 34 56"
                        />
                        <p v-if="profileForm.errors.phone" class="text-xs text-danger mt-1">{{ profileForm.errors.phone }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                    >
                        Enregistrer
                    </button>
                </div>
            </form>

            <!-- Password -->
            <form @submit.prevent="submitPassword" class="bg-theme-card border border-theme-border rounded-lg p-6">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-4">Mot de passe</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Mot de passe actuel</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                        />
                        <p v-if="passwordForm.errors.current_password" class="text-xs text-danger mt-1">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Nouveau mot de passe</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                        />
                        <p v-if="passwordForm.errors.password" class="text-xs text-danger mt-1">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">Confirmer le mot de passe</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                    >
                        Changer le mot de passe
                    </button>
                </div>
            </form>

            <!-- Two-Factor Authentication -->
            <div class="bg-theme-card border border-theme-border rounded-lg p-6">
                <div class="flex items-center gap-2 mb-4">
                    <ShieldCheckIcon class="w-5 h-5 text-theme-text-secondary" />
                    <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider">Authentification à deux facteurs</h2>
                </div>

                <!-- Success message -->
                <div v-if="page.props.flash?.success" class="mb-4 bg-theme-surface border border-green-500/30 text-green-400 rounded-md px-4 py-3 text-sm">
                    {{ page.props.flash.success }}
                </div>

                <!-- 2FA Not Enabled -->
                <div v-if="!twoFactor.enabled && !isSettingUp2FA">
                    <p class="text-sm text-theme-text-secondary mb-4">
                        Ajoutez une couche de sécurité supplémentaire à votre compte en activant l'authentification à deux facteurs.
                    </p>
                    <button
                        type="button"
                        @click="enableTwoFactor"
                        :disabled="twoFactorEnableForm.processing"
                        class="px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                    >
                        Activer
                    </button>
                </div>

                <!-- 2FA Setup in Progress -->
                <div v-else-if="isSettingUp2FA">
                    <p class="text-sm text-theme-text-secondary mb-4">
                        Scannez le QR code ci-dessous avec votre application d'authentification (Google Authenticator, Authy, etc.)
                    </p>

                    <div class="flex flex-col items-center gap-4 mb-6">
                        <div class="bg-white p-4 rounded-lg" v-html="twoFactor.qrCodeSvg"></div>
                        <div class="text-center">
                            <p class="text-xs text-theme-text-muted mb-1">Ou entrez ce code manuellement :</p>
                            <code class="text-sm text-theme-text-primary bg-theme-surface px-3 py-1 rounded font-mono">{{ twoFactor.secret }}</code>
                        </div>
                    </div>

                    <form @submit.prevent="confirmTwoFactor" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-2">
                                Code de vérification
                            </label>
                            <input
                                v-model="twoFactorConfirmForm.code"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                required
                                class="w-full bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary text-center tracking-widest font-mono focus:border-theme-text-primary focus:ring-0 outline-none"
                                placeholder="000000"
                            />
                            <p v-if="twoFactorConfirmForm.errors.code" class="text-xs text-danger mt-1">{{ twoFactorConfirmForm.errors.code }}</p>
                        </div>
                        <button
                            type="submit"
                            :disabled="twoFactorConfirmForm.processing"
                            class="w-full px-4 py-2 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors disabled:opacity-50"
                        >
                            Confirmer
                        </button>
                    </form>
                </div>

                <!-- 2FA Enabled -->
                <div v-else-if="twoFactor.enabled">
                    <div class="flex items-center gap-2 text-green-400 mb-4">
                        <ShieldCheckIcon class="w-5 h-5" />
                        <span class="text-sm font-medium">Authentification à deux facteurs activée</span>
                    </div>

                    <!-- Recovery Codes -->
                    <div v-if="twoFactor.recoveryCodes && (showRecoveryCodes || page.props.flash?.success?.includes('régénérés'))" class="mb-6">
                        <p class="text-sm text-theme-text-secondary mb-3">
                            Conservez ces codes de récupération dans un endroit sûr. Ils vous permettront de vous connecter si vous perdez l'accès à votre appareil.
                        </p>
                        <div class="bg-theme-surface border border-theme-border rounded-md p-4 grid grid-cols-2 gap-2">
                            <code v-for="code in twoFactor.recoveryCodes" :key="code" class="text-sm text-theme-text-primary font-mono">
                                {{ code }}
                            </code>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Regenerate Recovery Codes -->
                        <div>
                            <p class="text-sm text-theme-text-secondary mb-2">Régénérer les codes de récupération</p>
                            <div class="flex gap-2">
                                <input
                                    v-model="regenerateCodesForm.password"
                                    type="password"
                                    placeholder="Mot de passe actuel"
                                    class="flex-1 bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                                />
                                <button
                                    type="button"
                                    @click="regenerateRecoveryCodes"
                                    :disabled="regenerateCodesForm.processing || !regenerateCodesForm.password"
                                    class="px-4 py-2 text-sm text-theme-text-secondary hover:text-theme-text-primary border border-theme-border rounded-md transition-colors disabled:opacity-50"
                                >
                                    Régénérer
                                </button>
                            </div>
                            <p v-if="regenerateCodesForm.errors.password" class="text-xs text-danger mt-1">{{ regenerateCodesForm.errors.password }}</p>
                        </div>

                        <!-- Disable 2FA -->
                        <div>
                            <p class="text-sm text-theme-text-secondary mb-2">Désactiver l'authentification à deux facteurs</p>
                            <div class="flex gap-2">
                                <input
                                    v-model="twoFactorDisableForm.password"
                                    type="password"
                                    placeholder="Mot de passe actuel"
                                    class="flex-1 bg-theme-surface border border-theme-border rounded-md px-3 py-2 text-sm text-theme-text-primary focus:border-theme-text-primary focus:ring-0 outline-none"
                                />
                                <button
                                    type="button"
                                    @click="disableTwoFactor"
                                    :disabled="twoFactorDisableForm.processing || !twoFactorDisableForm.password"
                                    class="px-4 py-2 text-sm text-danger hover:bg-danger/10 border border-danger/30 rounded-md transition-colors disabled:opacity-50"
                                >
                                    Désactiver
                                </button>
                            </div>
                            <p v-if="twoFactorDisableForm.errors.password" class="text-xs text-danger mt-1">{{ twoFactorDisableForm.errors.password }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Theme -->
            <div class="bg-theme-card border border-theme-border rounded-lg p-6">
                <h2 class="text-xs font-medium text-theme-text-secondary uppercase tracking-wider mb-4">Apparence</h2>

                <div class="flex gap-4">
                    <button
                        type="button"
                        @click="themeForm.theme = 'dark'; submitTheme()"
                        class="flex-1 p-4 rounded-lg border-2 transition-all"
                        :class="themeForm.theme === 'dark' ? 'border-theme-btn-primary-bg' : 'border-theme-border hover:border-theme-text-secondary'"
                    >
                        <div class="w-full h-20 rounded bg-black border border-zinc-800 mb-3 flex items-center justify-center">
                            <div class="w-8 h-1 bg-white rounded"></div>
                        </div>
                        <p class="text-sm font-medium text-theme-text-primary">Sombre</p>
                        <p class="text-xs text-theme-text-muted">Interface sombre</p>
                    </button>

                    <button
                        type="button"
                        @click="themeForm.theme = 'light'; submitTheme()"
                        class="flex-1 p-4 rounded-lg border-2 transition-all"
                        :class="themeForm.theme === 'light' ? 'border-theme-btn-primary-bg' : 'border-theme-border hover:border-theme-text-secondary'"
                    >
                        <div class="w-full h-20 rounded bg-white border border-gray-200 mb-3 flex items-center justify-center">
                            <div class="w-8 h-1 bg-black rounded"></div>
                        </div>
                        <p class="text-sm font-medium text-theme-text-primary">Clair</p>
                        <p class="text-xs text-theme-text-muted">Interface claire</p>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
