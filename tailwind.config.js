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
            },
            colors: {
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
            },
            boxShadow: {
                brand: '0 10px 25px -5px rgba(30, 64, 175, 0.25), 0 8px 10px -6px rgba(30, 64, 175, 0.15)',
            },
        },
    },

    plugins: [forms],
};
