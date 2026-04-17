import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', 'Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // DST primary deep-blue (from logo)
                brand: {
                    50:  '#eff5ff',
                    100: '#dbe7ff',
                    200: '#bfd4ff',
                    300: '#93b8ff',
                    400: '#6093ff',
                    500: '#3b6eff',
                    600: '#2453f0',
                    700: '#1c40d0',
                    800: '#1e40af',
                    900: '#1e3a8a',
                    950: '#172554',
                },
                // DST warm orange accent (from logo)
                accent: {
                    50:  '#fff8eb',
                    100: '#ffeac6',
                    200: '#ffd488',
                    300: '#ffb74a',
                    400: '#ff9a20',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                // Indian saffron for festive highlights
                saffron: {
                    50:  '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#f97316',
                    600: '#ea580c',
                    700: '#c2410c',
                    800: '#9a3412',
                    900: '#7c2d12',
                },
                // Money/success
                money: {
                    50:  '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                },
            },
            boxShadow: {
                brand: '0 10px 25px -5px rgba(30, 64, 175, 0.25), 0 8px 10px -6px rgba(30, 64, 175, 0.15)',
                glow: '0 0 0 4px rgba(245, 158, 11, 0.18)',
                card: '0 1px 2px 0 rgba(0,0,0,0.04), 0 4px 20px -4px rgba(30, 64, 175, 0.08)',
            },
            backgroundImage: {
                'hero-mesh': 'radial-gradient(at 30% 20%, rgba(245, 158, 11, 0.18) 0, transparent 40%), radial-gradient(at 80% 0%, rgba(59, 110, 255, 0.28) 0, transparent 50%), radial-gradient(at 70% 80%, rgba(16, 185, 129, 0.16) 0, transparent 45%), radial-gradient(at 10% 90%, rgba(249, 115, 22, 0.15) 0, transparent 40%)',
                'grid-soft': 'linear-gradient(to right, rgba(30,58,138,0.07) 1px, transparent 1px), linear-gradient(to bottom, rgba(30,58,138,0.07) 1px, transparent 1px)',
            },
            backgroundSize: {
                'grid-soft': '44px 44px',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-8px)' },
                },
                shimmer: {
                    '0%, 100%': { opacity: '0.5' },
                    '50%': { opacity: '1' },
                },
            },
            animation: {
                float: 'float 6s ease-in-out infinite',
                shimmer: 'shimmer 2.5s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
