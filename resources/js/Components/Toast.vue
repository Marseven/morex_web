<script setup>
import { useToast } from '@/Composables/useToast'

const { toasts, removeToast } = useToast()

const getIcon = (type) => {
    switch (type) {
        case 'success':
            return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`
        case 'error':
            return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`
        case 'warning':
            return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`
        default:
            return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`
    }
}

const getClasses = (type) => {
    switch (type) {
        case 'success':
            return 'bg-success/10 border-success/30 text-success'
        case 'error':
            return 'bg-danger/10 border-danger/30 text-danger'
        case 'warning':
            return 'bg-warning/10 border-warning/30 text-warning'
        default:
            return 'bg-theme-surface border-theme-border text-theme-text-primary'
    }
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-lg border shadow-lg backdrop-blur-sm',
                        getClasses(toast.type)
                    ]"
                >
                    <span v-html="getIcon(toast.type)"></span>
                    <p class="text-sm font-medium flex-1">{{ toast.message }}</p>
                    <button
                        @click="removeToast(toast.id)"
                        class="opacity-60 hover:opacity-100 transition-opacity"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active {
    transition: all 0.3s ease-out;
}

.toast-leave-active {
    transition: all 0.2s ease-in;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-move {
    transition: transform 0.3s ease;
}
</style>
