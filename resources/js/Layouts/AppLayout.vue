<script setup>
import { ref, onMounted, watch, computed, h } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import {
    HomeIcon,
    ArrowsRightLeftIcon,
    WalletIcon,
    ChartPieIcon,
    FlagIcon,
    CreditCardIcon,
    ChartBarIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import Toast from '@/Components/Toast.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const currentDate = computed(() => page.props.currentDate)
const currentBudgetPeriod = computed(() => page.props.currentBudgetPeriod)

const navigation = [
    { name: 'Dashboard', href: '/', icon: HomeIcon },
    { name: 'Transactions', href: '/transactions', icon: ArrowsRightLeftIcon },
    { name: 'Comptes', href: '/accounts', icon: WalletIcon },
    { name: 'Budgets', href: '/budgets', icon: ChartPieIcon },
    { name: 'Objectifs', href: '/goals', icon: FlagIcon },
    { name: 'Dettes', href: '/debts', icon: CreditCardIcon },
    { name: 'Analytics', href: '/analytics', icon: ChartBarIcon },
]

const sidebarOpen = ref(false)

// Theme management
const currentTheme = computed(() => user.value?.theme || 'dark')

onMounted(() => {
    applyTheme(currentTheme.value)
})

watch(currentTheme, (newTheme) => {
    applyTheme(newTheme)
})

const applyTheme = (theme) => {
    document.documentElement.classList.remove('light', 'dark')
    if (theme === 'light') {
        document.documentElement.classList.add('light')
    }
}

const logout = () => {
    router.post('/logout')
}

const isCurrentRoute = (href) => {
    if (href === '/') {
        return page.url === '/'
    }
    return page.url.startsWith(href)
}

const getInitials = (name) => {
    if (!name) return '?'
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}
</script>

<template>
    <div class="min-h-screen bg-theme-bg transition-colors duration-200">
        <!-- Mobile sidebar overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/80 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Mobile sidebar -->
        <div
            class="fixed inset-y-0 left-0 z-50 w-64 bg-theme-bg border-r border-theme-border transform transition-transform lg:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-full flex-col px-4 pb-4">
                <div class="flex h-14 items-center justify-between border-b border-theme-border">
                    <img src="/images/logo.png" alt="Morex" class="h-6 w-auto" />
                    <button @click="sidebarOpen = false" class="text-theme-text-secondary hover:text-theme-text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 py-4">
                    <ul class="space-y-1">
                        <li v-for="item in navigation" :key="item.name">
                            <Link
                                :href="item.href"
                                @click="sidebarOpen = false"
                                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md transition-colors"
                                :class="isCurrentRoute(item.href)
                                    ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium'
                                    : 'text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface'"
                            >
                                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div class="border-t border-theme-border pt-4">
                    <Link
                        href="/profile"
                        @click="sidebarOpen = false"
                        class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-theme-surface transition-colors"
                    >
                        <div class="w-8 h-8 rounded-full bg-theme-surface border border-theme-border flex items-center justify-center overflow-hidden">
                            <img v-if="user?.avatar" :src="`/storage/${user.avatar}`" class="w-full h-full object-cover" />
                            <span v-else class="text-xs font-medium text-theme-text-secondary">
                                {{ getInitials(user?.name) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-theme-text-primary truncate">{{ user?.name }}</p>
                            <p class="text-xs text-theme-text-muted truncate">{{ user?.email }}</p>
                        </div>
                    </Link>
                    <button
                        @click="logout"
                        class="w-full mt-2 px-3 py-2 flex items-center gap-3 text-left text-sm text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        Déconnexion
                    </button>
                </div>
            </div>
        </div>

        <!-- Desktop sidebar -->
        <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-56 lg:flex-col">
            <div class="flex grow flex-col border-r border-theme-border bg-theme-bg px-4 pb-4">
                <div class="flex h-14 items-center border-b border-theme-border">
                    <img src="/images/logo.png" alt="Morex" class="h-6 w-auto" />
                </div>

                <nav class="flex-1 py-4">
                    <ul class="space-y-1">
                        <li v-for="item in navigation" :key="item.name">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md transition-colors"
                                :class="isCurrentRoute(item.href)
                                    ? 'bg-theme-btn-primary-bg text-theme-btn-primary-text font-medium'
                                    : 'text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface'"
                            >
                                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div class="border-t border-theme-border pt-4">
                    <Link
                        href="/profile"
                        class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-theme-surface transition-colors"
                        :class="isCurrentRoute('/profile') ? 'bg-theme-surface' : ''"
                    >
                        <div class="w-8 h-8 rounded-full bg-theme-surface border border-theme-border flex items-center justify-center overflow-hidden">
                            <img v-if="user?.avatar" :src="`/storage/${user.avatar}`" class="w-full h-full object-cover" />
                            <span v-else class="text-xs font-medium text-theme-text-secondary">
                                {{ getInitials(user?.name) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-theme-text-primary truncate">{{ user?.name }}</p>
                            <p class="text-xs text-theme-text-muted truncate">{{ user?.email }}</p>
                        </div>
                    </Link>
                    <button
                        @click="logout"
                        class="w-full mt-2 px-3 py-2 flex items-center gap-3 text-left text-sm text-theme-text-secondary hover:text-theme-text-primary transition-colors"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        Déconnexion
                    </button>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="lg:pl-56">
            <!-- Top bar -->
            <div class="sticky top-0 z-40 flex h-14 items-center gap-4 border-b border-theme-border bg-theme-bg px-4 lg:px-6">
                <button
                    type="button"
                    class="lg:hidden text-theme-text-secondary hover:text-theme-text-primary"
                    @click="sidebarOpen = true"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Date et période budgétaire -->
                <div class="flex items-center gap-4 text-sm">
                    <div class="hidden sm:flex items-center gap-2 text-theme-text-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>{{ currentDate }}</span>
                    </div>
                    <div v-if="currentBudgetPeriod" class="flex items-center gap-2 px-2 py-1 bg-theme-surface rounded-md">
                        <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                        <span class="text-theme-text-primary font-medium">{{ currentBudgetPeriod }}</span>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-end gap-3">
                    <Link
                        href="/transactions/create"
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-md hover:opacity-90 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden sm:inline">Nouvelle transaction</span>
                    </Link>
                </div>
            </div>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mx-4 mt-4 lg:mx-6">
                <div class="bg-theme-surface border border-success/30 text-success rounded-md px-4 py-3 text-sm">
                    {{ $page.props.flash.success }}
                </div>
            </div>
            <div v-if="$page.props.flash?.error" class="mx-4 mt-4 lg:mx-6">
                <div class="bg-theme-surface border border-danger/30 text-danger rounded-md px-4 py-3 text-sm">
                    {{ $page.props.flash.error }}
                </div>
            </div>

            <!-- Page content -->
            <main class="p-4 lg:p-6">
                <slot />
            </main>
        </div>

        <!-- Toast notifications -->
        <Toast />
    </div>
</template>
