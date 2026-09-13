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
                sans: ['Poppins', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                maroon: {
                    50: '#f9f3f5',
                    100: '#f2e4e8',
                    200: '#e5c9d1',
                    300: '#d19eab',
                    400: '#b96c82',
                    500: '#9e4862',
                    600: '#842f4a',
                    700: '#70233a',
                    800: '#621f34',
                    900: '#54182b',
                    950: '#310b17',
                },
                hotpoker: {
                    DEFAULT: '#b28a4b',
                    50: '#fbf8f1',
                    100: '#f4ecd9',
                    200: '#e9d7b0',
                    300: '#d9ba7d',
                    400: '#c69c58',
                    500: '#b28a4b',
                    600: '#96703d',
                    700: '#785832',
                    800: '#62472d',
                    900: '#533d29',
                },
                berbere: {
                    DEFAULT: '#28656b',
                    50: '#f0f8f8',
                    100: '#d9eeee',
                    200: '#b7dfe0',
                    300: '#86c7ca',
                    400: '#50a6ab',
                    500: '#378990',
                    600: '#28656b',
                    700: '#28565b',
                    800: '#26474b',
                    900: '#233d40',
                },
                gold: {
                    DEFAULT: '#b28a4b',
                    50: '#fbf8f1',
                    100: '#f4ecd9',
                    200: '#e9d7b0',
                    300: '#d9ba7d',
                    400: '#c69c58',
                    500: '#b28a4b',
                    600: '#96703d',
                    700: '#785832',
                    800: '#62472d',
                    900: '#533d29',
                },
            },
        },
    },

    plugins: [forms],
};
