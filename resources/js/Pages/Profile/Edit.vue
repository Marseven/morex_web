<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { UserIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    user: { type: Object, required: true },
})

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
