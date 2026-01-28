import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from './Components/Toast.vue';
import { useToast } from './Composables/useToast';

const appName = import.meta.env.VITE_APP_NAME || 'Morex';

// Handle global Inertia errors (419 CSRF, 500, etc.)
router.on('invalid', (event) => {
    const { warning } = useToast();
    const status = event.detail.response?.status;

    if (status === 419) {
        event.preventDefault();
        warning('Votre session a expiré. La page va se rafraîchir.');
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
});

// Handle network errors
router.on('error', (event) => {
    const { error } = useToast();
    error('Une erreur réseau est survenue. Veuillez réessayer.');
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // Register Toast globally
        app.component('Toast', Toast);

        return app.mount(el);
    },
    progress: {
        color: '#0666EB',
    },
});
