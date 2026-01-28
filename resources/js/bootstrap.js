import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Handle 419 CSRF token expiration globally
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 419) {
            // Session expired, reload the page after a short delay
            // The toast will be shown by the Inertia handler
            console.warn('Session expired (419). Page will reload.');
        }
        return Promise.reject(error);
    }
);
