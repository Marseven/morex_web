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
                sans: ['Golos Text', 'Arial', 'Helvetica', 'sans-serif'],
            },
            colors: {
                // Theme colors using CSS variables
                theme: {
                    bg: 'var(--color-bg)',
                    surface: 'var(--color-surface)',
                    'surface-light': 'var(--color-surface-light)',
                    'surface-hover': 'var(--color-surface-hover)',
                    card: 'var(--color-card)',
                    border: 'var(--color-border)',
                    divider: 'var(--color-divider)',
                    'text-primary': 'var(--color-text-primary)',
                    'text-secondary': 'var(--color-text-secondary)',
                    'text-muted': 'var(--color-text-muted)',
                    'btn-primary-bg': 'var(--color-btn-primary-bg)',
                    'btn-primary-text': 'var(--color-btn-primary-text)',
                },
                // Brand colors
                brand: {
                    DEFAULT: 'var(--color-brand)',
                    light: 'var(--color-brand-light)',
                },
                // Legacy dark colors (for backward compatibility)
                dark: {
                    bg: '#060B0A',
                    surface: '#0F1816',
                    'surface-light': '#142220',
                    card: '#0F1816',
                    border: '#1C332F',
                    divider: '#152825',
                    'text-primary': '#EDF5F3',
                    'text-secondary': '#7FA69E',
                    'text-muted': '#3E635B',
                },
                // Accent
                accent: {
                    DEFAULT: 'var(--color-accent)',
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
                'lg': '12px',
                'xl': '16px',
            },
            backdropBlur: {
                'xs': '2px',
            },
        },
    },
    plugins: [forms],
};
