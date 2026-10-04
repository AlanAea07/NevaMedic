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
            colors: {
                neva: {
                    ink: '#07101E', navy: '#0C2F62', blue: '#1765D8',
                    silver: '#BCC4CF', mist: '#F2F5F9', line: '#DCE2EA', muted: '#627085',
                },
            },
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Libre Caslon Display', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
