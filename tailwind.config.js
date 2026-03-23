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
                    bg: '#0D0D0D',
                    surface: '#1A1A1A',
                    'surface-light': '#252525',
                    card: '#1E1E1E',
                    border: '#2A2A2A',
                    divider: '#222222',
                    'text-primary': '#FFFFFF',
                    'text-secondary': '#8E8E93',
                    'text-muted': '#52525B',
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
                'lg': '10px',
            },
        },
    },
    plugins: [forms],
};
