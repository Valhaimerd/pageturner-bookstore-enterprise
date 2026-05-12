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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#f2fbfa',
                    100: '#d9f3ef',
                    200: '#b7e7df',
                    300: '#89d5ca',
                    400: '#57bcae',
                    500: '#339e91',
                    600: '#257f75',
                    700: '#20665e',
                    800: '#1e514c',
                    900: '#1b4440',
                },
                sage: {
                    50: '#f6f8f4',
                    100: '#e8eee4',
                    200: '#d3dfcf',
                    300: '#b6c9b1',
                    400: '#93ad8d',
                    500: '#728d6c',
                    600: '#5b7256',
                    700: '#495b46',
                    800: '#3c493b',
                    900: '#333d32',
                },
                ink: {
                    50: '#f6f8f8',
                    100: '#edf1f1',
                    200: '#d8e0df',
                    300: '#b7c4c3',
                    400: '#8b9a99',
                    500: '#6c7b7b',
                    600: '#556262',
                    700: '#434d4d',
                    800: '#2f3737',
                    900: '#1f2626',
                },
            },
            boxShadow: {
                soft: '0 10px 30px rgba(20, 45, 40, 0.08)',
                panel: '0 18px 45px rgba(24, 58, 53, 0.10)',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
        },
    },

    plugins: [forms],
};
