import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './config/invoice_templates.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Inter for every interface surface — drawn for screen UI at
                // small sizes, with tabular numerals so money columns align.
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Plus Jakarta Sans stays on headings so the brand keeps its voice.
                display: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Apna Invoice primary - deep teal. Same trust as navy, but
                brand: {
                    50:   '#f0fdfa',
                    100:  '#ccfbf1',
                    200:  '#99f6e4',
                    300:  '#5eead4',
                    400:  '#2dd4bf',
                    500:  '#14b8a6',
                    600:  '#0d9488',
                    700:  '#0f766e',
                    800:  '#115e59',
                    900:  '#134e4a',
                    950:  '#042f2e',
                },
                // Warm sand/amber accent - carries the India cue the saffron did.
                accent: {
                    50:   '#fffbeb',
                    100:  '#fef3c7',
                    200:  '#fde68a',
                    300:  '#fcd34d',
                    400:  '#fbbf24',
                    500:  '#f59e0b',
                    600:  '#d97706',
                    700:  '#b45309',
                    800:  '#92400e',
                    900:  '#78350f',
                    950:  '#451a03',
                },
                // Money / success. A truer green than emerald so "paid" never
                money: {
                    50:   '#f0fdf4',
                    100:  '#dcfce7',
                    200:  '#bbf7d0',
                    300:  '#86efac',
                    400:  '#4ade80',
                    500:  '#22c55e',
                    600:  '#16a34a',
                    700:  '#15803d',
                    800:  '#166534',
                    900:  '#14532d',
                },
                // Danger / overdue / destructive. A state colour, never decorative -
                // if something is this colour it means money is late or an action
                // cannot be undone.
                danger: {
                    50:  '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
            },
            boxShadow: {
                brand: '0 10px 25px -5px rgba(15, 118, 110, 0.30), 0 8px 10px -6px rgba(15, 118, 110, 0.18)',
                glow: '0 0 0 4px rgba(180, 83, 9, 0.22)',
                card: '0 1px 2px 0 rgba(0,0,0,0.04), 0 4px 20px -4px rgba(19, 78, 74, 0.10)',
            },
            backgroundImage: {
                'hero-mesh': 'radial-gradient(at 30% 20%, rgba(245, 158, 11, 0.18) 0, transparent 40%), radial-gradient(at 80% 0%, rgba(15, 118, 110, 0.28) 0, transparent 50%), radial-gradient(at 70% 80%, rgba(34, 197, 94, 0.14) 0, transparent 45%), radial-gradient(at 10% 90%, rgba(245, 158, 11, 0.15) 0, transparent 40%)',
                'grid-soft': 'linear-gradient(to right, rgba(19,78,74,0.07) 1px, transparent 1px), linear-gradient(to bottom, rgba(19,78,74,0.07) 1px, transparent 1px)',
            },
            backgroundSize: {
                'grid-soft': '44px 44px',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-16px)' },
                },
                shimmer: {
                    '0%, 100%': { opacity: '0.5' },
                    '50%': { opacity: '1' },
                },
                'fade-up': {
                    '0%':   { opacity: '0', transform: 'translateY(14px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                float: 'float 6s ease-in-out infinite',
                'float-fast': 'float 3s ease-in-out infinite',
                shimmer: 'shimmer 2.5s ease-in-out infinite',
                'fade-up': 'fade-up 0.7s cubic-bezier(.16,1,.3,1) both',
            },
        },
    },

    plugins: [forms],
};
