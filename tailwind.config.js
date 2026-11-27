import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                kopdes: {
                    50: '#FEF2F2',
                    100: '#FEE2E2',
                    200: '#FECACA',
                    300: '#FCA5A5',
                    400: '#F87171',
                    500: '#DC2626',
                    600: '#C81E1E',
                    700: '#B91C1C',
                    800: '#991B1B',
                    900: '#7F1D1D',
                    950: '#450A0A',
                },
                surface: {
                    canvas: '#F8FAFC',
                    card: '#FFFFFF',
                    subtle: '#F1F5F9',
                    border: '#E2E8F0',
                },
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                card: '0 1px 2px 0 rgba(15, 23, 42, 0.04), 0 1px 3px 0 rgba(15, 23, 42, 0.05)',
                'card-hover': '0 4px 12px -2px rgba(15, 23, 42, 0.07), 0 2px 6px -2px rgba(15, 23, 42, 0.04)',
                dropdown: '0 10px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05)',
                modal: '0 24px 48px -12px rgba(15, 23, 42, 0.22)',
            },
            keyframes: {
                'rfid-ping': {
                    '0%': { transform: 'scale(0.95)', opacity: '0.65' },
                    '70%': { transform: 'scale(1.38)', opacity: '0' },
                    '100%': { transform: 'scale(1.38)', opacity: '0' },
                },
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(6px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'rfid-ping': 'rfid-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite',
                'fade-in-up': 'fade-in-up 0.2s ease-out both',
            },
        },
    },

    plugins: [forms],
};
