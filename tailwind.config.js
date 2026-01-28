import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Amazon Ember', 'Arial', 'Helvetica', 'sans-serif'],
            },
            colors: {
                // Theme colors using CSS variables
                theme: {
                    bg: 'var(--color-bg)',
                    surface: 'var(--color-surface)',
                    'surface-light': 'var(--color-surface-light)',
                    card: 'var(--color-card)',
                    border: 'var(--color-border)',
                    divider: 'var(--color-divider)',
                    'text-primary': 'var(--color-text-primary)',
                    'text-secondary': 'var(--color-text-secondary)',
                    'text-muted': 'var(--color-text-muted)',
                    'btn-primary-bg': 'var(--color-btn-primary-bg)',
                    'btn-primary-text': 'var(--color-btn-primary-text)',
                },
                // Legacy dark colors (for backward compatibility)
                dark: {
                    bg: '#000000',
                    surface: '#0A0A0A',
                    'surface-light': '#141414',
                    card: '#111111',
                    border: '#1F1F1F',
                    divider: '#1A1A1A',
                    'text-primary': '#FFFFFF',
                    'text-secondary': '#71717A',
                    'text-muted': '#52525B',
                },
                // Accent
                accent: {
                    DEFAULT: '#FFFFFF',
                    muted: '#A1A1AA',
                    subtle: '#3F3F46',
                },
                // Status colors
                success: '#10B981',
                warning: '#F59E0B',
                danger: '#EF4444',
            },
            borderRadius: {
                'sm': '4px',
                'DEFAULT': '6px',
                'md': '8px',
                'lg': '10px',
            },
        },
    },
    plugins: [forms],
};
