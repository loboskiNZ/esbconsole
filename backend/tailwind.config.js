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
        },
    },

    plugins: [forms],

    safelist: [
        'vx32-routing-source-card--stagebox-a',
        'vx32-routing-source-card--stagebox-b',
        'vx32-routing-source-card--ableton',
        'vx32-routing-source-card--learned',
        'vx32-routing-source-card--suggested',
        'vx32-routing-source-card--not-learned',
        'vx32-routing-source-card__badge--learned',
        'vx32-routing-source-card__badge--suggested',
        'vx32-routing-source-card__badge--not-learned',
        'vx32-routing-console-card--learned',
        'vx32-routing-console-card--suggested',
        'vx32-routing-console-card--not-learned',
        'vx32-routing-console-card__summary--learned',
        'vx32-routing-console-card__summary--suggested',
        'vx32-routing-console-card__summary--not-learned',
        'vx32-routing-dest-card--foh',
        'vx32-routing-dest-card--iems',
        'vx32-routing-dest-card--learned',
        'vx32-routing-dest-card--partial',
        'vx32-routing-dest-card--suggested',
        'vx32-routing-dest-card--not-learned',
    ],
};
