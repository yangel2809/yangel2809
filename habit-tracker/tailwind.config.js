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
                // Fuentes del sistema: cero peticiones extra en el móvil.
                sans: ['system-ui', '-apple-system', 'Segoe UI', 'Roboto', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
