import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from './Components/Toast.vue';
import { useToast } from './Composables/useToast';

const appName = import.meta.env.VITE_APP_NAME || 'Morex';

// Handle global Inertia errors (4XX, 5XX)
router.on('invalid', (event) => {
    const { error, warning } = useToast();
    const status = event.detail.response?.status;

    switch (status) {
        case 401:
            event.preventDefault();
            warning('Session expirée. Veuillez vous reconnecter.');
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
            break;
        case 403:
            event.preventDefault();
            error('Accès refusé. Vous n\'avez pas les permissions nécessaires.');
            break;
        case 404:
            event.preventDefault();
            error('Ressource introuvable.');
            break;
        case 419:
            event.preventDefault();
            warning('Votre session a expiré. La page va se rafraîchir.');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            break;
        case 422:
            // Validation errors are usually handled by components
            break;
        case 429:
            event.preventDefault();
            warning('Trop de requêtes. Veuillez patienter un moment.');
            break;
        case 500:
            event.preventDefault();
            error('Erreur serveur. Veuillez réessayer plus tard.');
            break;
        case 502:
        case 503:
        case 504:
            event.preventDefault();
            error('Service temporairement indisponible. Veuillez réessayer.');
            break;
        default:
            if (status >= 400) {
                event.preventDefault();
                error(`Erreur ${status}. Veuillez réessayer.`);
            }
    }
});

// Handle network errors
router.on('error', (event) => {
    const { error } = useToast();
    error('Erreur de connexion. Vérifiez votre connexion internet.');
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
