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
                sans: ['Figtree', 'Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Apna Invoice primary — deep navy (from "Apna Invoice" wordmark)
                brand: {
                    50:  '#eef3fc',
                    100: '#dbe3f5',
                    200: '#b8c8e9',
                    300: '#90a8da',
                    400: '#5a7dc0',
                    500: '#3a5ba8',
                    600: '#274690',
                    700: '#1e3a8a',
                    800: '#162c6b',
                    900: '#0f1f4f',
                    950: '#091535',
                },
                // Apna Invoice accent — warm saffron-orange (from the document/check icon)
                accent: {
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
                    950: '#481e0d',
                },
                // Kept as saffron alias (same palette as accent) for festive/India-cue spots
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
                // Money/success (unchanged — semantic, not brand)
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
                brand: '0 10px 25px -5px rgba(30, 58, 138, 0.30), 0 8px 10px -6px rgba(30, 58, 138, 0.18)',
                glow: '0 0 0 4px rgba(249, 115, 22, 0.20)',
                card: '0 1px 2px 0 rgba(0,0,0,0.04), 0 4px 20px -4px rgba(30, 58, 138, 0.08)',
            },
            backgroundImage: {
                'hero-mesh': 'radial-gradient(at 30% 20%, rgba(249, 115, 22, 0.18) 0, transparent 40%), radial-gradient(at 80% 0%, rgba(30, 58, 138, 0.28) 0, transparent 50%), radial-gradient(at 70% 80%, rgba(16, 185, 129, 0.14) 0, transparent 45%), radial-gradient(at 10% 90%, rgba(249, 115, 22, 0.15) 0, transparent 40%)',
                'grid-soft': 'linear-gradient(to right, rgba(30,58,138,0.07) 1px, transparent 1px), linear-gradient(to bottom, rgba(30,58,138,0.07) 1px, transparent 1px)',
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
