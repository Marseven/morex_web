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
    CalendarIcon,
    ArrowRightOnRectangleIcon,
    BanknotesIcon,
    BellIcon,
    SunIcon,
    MoonIcon,
    ComputerDesktopIcon,
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
    { name: 'Cycles', href: '/budget-cycles', icon: CalendarIcon },
    { name: 'Transferts', href: '/transfers', icon: BanknotesIcon },
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

const systemMediaQuery = window.matchMedia('(prefers-color-scheme: light)')

const applyTheme = (theme) => {
    document.documentElement.classList.remove('light', 'dark')
    if (theme === 'system') {
        if (systemMediaQuery.matches) {
            document.documentElement.classList.add('light')
        }
    } else if (theme === 'light') {
        document.documentElement.classList.add('light')
    }
}

systemMediaQuery.addEventListener('change', () => {
    if (currentTheme.value === 'system') {
        applyTheme('system')
    }
})

const toggleTheme = () => {
    const cycle = { dark: 'light', light: 'system', system: 'dark' }
    const next = cycle[currentTheme.value] || 'dark'
    router.put('/profile/theme', { theme: next }, { preserveState: true, preserveScroll: true })
}

const themeIcon = computed(() => {
    if (currentTheme.value === 'light') return SunIcon
    if (currentTheme.value === 'system') return ComputerDesktopIcon
    return MoonIcon
})

const themeLabel = computed(() => {
    const labels = { dark: 'Sombre', light: 'Clair', system: 'Système' }
    return labels[currentTheme.value] || 'Sombre'
})

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
        <!-- Atmospheric background -->
        <div class="atmosphere"></div>

        <!-- Mobile sidebar overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Mobile sidebar -->
        <div
            class="fixed inset-y-0 left-0 z-50 w-64 glass-sidebar border-r border-theme-border transform transition-transform lg:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-full flex-col px-4 pb-4">
                <div class="flex h-14 items-center justify-between border-b border-theme-divider">
                    <img :src="currentTheme === 'light' ? '/images/logo-dark.png' : '/images/logo.png'" alt="MR Money" class="h-8 w-auto" />
                    <button @click="sidebarOpen = false" class="text-theme-text-secondary hover:text-theme-text-primary transition-colors">
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
                                class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg transition-all duration-200"
                                :class="isCurrentRoute(item.href)
                                    ? 'bg-[var(--color-lime-glow)] text-[var(--color-accent)] font-medium nav-active'
                                    : 'text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface-hover'"
                            >
                                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div class="border-t border-theme-divider pt-4">
                    <Link
                        href="/profile"
                        @click="sidebarOpen = false"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-theme-surface-hover transition-colors"
                    >
                        <div class="w-8 h-8 rounded-full bg-[var(--color-teal-glow)] border border-[var(--color-brand)] flex items-center justify-center overflow-hidden">
                            <img v-if="user?.avatar" :src="`/storage/${user.avatar}`" class="w-full h-full object-cover" />
                            <span v-else class="text-xs font-semibold text-[var(--color-brand-light)]">
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
                        class="w-full mt-2 px-3 py-2 flex items-center gap-3 text-left text-sm text-theme-text-secondary hover:text-danger transition-colors rounded-lg"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        Déconnexion
                    </button>
                </div>
            </div>
        </div>

        <!-- Desktop sidebar -->
        <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-56 lg:flex-col">
            <div class="flex grow flex-col border-r border-theme-border glass-sidebar px-4 pb-4">
                <div class="flex h-14 items-center border-b border-theme-divider">
                    <img :src="currentTheme === 'light' ? '/images/logo-dark.png' : '/images/logo.png'" alt="MR Money" class="h-8 w-auto" />
                </div>

                <nav class="flex-1 py-4">
                    <ul class="space-y-1">
                        <li v-for="item in navigation" :key="item.name">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg transition-all duration-200"
                                :class="isCurrentRoute(item.href)
                                    ? 'bg-[var(--color-lime-glow)] text-[var(--color-accent)] font-medium nav-active'
                                    : 'text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface-hover'"
                            >
                                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div class="border-t border-theme-divider pt-4">
                    <Link
                        href="/profile"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-theme-surface-hover transition-colors"
                        :class="isCurrentRoute('/profile') ? 'bg-theme-surface-hover' : ''"
                    >
                        <div class="w-8 h-8 rounded-full bg-[var(--color-teal-glow)] border border-[var(--color-brand)] flex items-center justify-center overflow-hidden">
                            <img v-if="user?.avatar" :src="`/storage/${user.avatar}`" class="w-full h-full object-cover" />
                            <span v-else class="text-xs font-semibold text-[var(--color-brand-light)]">
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
                        class="w-full mt-2 px-3 py-2 flex items-center gap-3 text-left text-sm text-theme-text-secondary hover:text-danger transition-colors rounded-lg"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        Déconnexion
                    </button>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="lg:pl-56 relative z-[1]">
            <!-- Top bar (glassmorphism) -->
            <div class="sticky top-0 z-40 flex h-14 items-center gap-4 border-b border-theme-border glass-header px-4 lg:px-6">
                <button
                    type="button"
                    class="lg:hidden text-theme-text-secondary hover:text-theme-text-primary transition-colors"
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
                    <div v-if="currentBudgetPeriod" class="flex items-center gap-2 px-3 py-1 bg-[var(--color-lime-glow)] border border-[rgba(219,242,39,0.15)] rounded-full">
                        <span class="w-2 h-2 rounded-full bg-[var(--color-accent)] pulse-dot"></span>
                        <span class="text-xs font-medium text-[var(--color-accent)]">{{ currentBudgetPeriod }}</span>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-end gap-2">
                    <!-- Notification bell -->
                    <button
                        class="relative w-9 h-9 flex items-center justify-center rounded-lg text-theme-text-secondary hover:text-theme-text-primary hover:bg-theme-surface-hover transition-all duration-200"
                        title="Notifications"
                    >
                        <BellIcon class="w-5 h-5" />
                        <!-- Badge (uncomment when notifications exist) -->
                        <!-- <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-danger pulse-dot"></span> -->
                    </button>

                    <!-- Theme toggle -->
                    <button
                        @click="toggleTheme"
                        class="w-9 h-9 flex items-center justify-center rounded-lg text-theme-text-secondary hover:text-[var(--color-accent)] hover:bg-theme-surface-hover transition-all duration-200"
                        :title="`Thème : ${themeLabel}`"
                    >
                        <component :is="themeIcon" class="w-5 h-5" />
                    </button>

                    <!-- Separator -->
                    <div class="hidden sm:block w-px h-6 bg-theme-divider mx-1"></div>

                    <!-- New transaction -->
                    <Link
                        href="/transactions/create"
                        class="inline-flex items-center gap-2 px-4 py-1.5 bg-theme-btn-primary-bg text-theme-btn-primary-text text-sm font-medium rounded-lg hover:opacity-90 transition-all duration-200 hover:shadow-[0_0_12px_rgba(219,242,39,0.2)]"
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
                <div class="glass-card border-success/30 text-success px-4 py-3 text-sm">
                    {{ $page.props.flash.success }}
                </div>
            </div>
            <div v-if="$page.props.flash?.error" class="mx-4 mt-4 lg:mx-6">
                <div class="glass-card border-danger/30 text-danger px-4 py-3 text-sm">
                    {{ $page.props.flash.error }}
                </div>
            </div>

            <!-- Page content -->
            <main class="p-4 lg:p-6 animate-fade-up">
                <slot />
            </main>
        </div>

        <!-- Toast notifications -->
        <Toast />
    </div>
</template>
