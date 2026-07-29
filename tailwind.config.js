import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Segoe UI"', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(0 0 0 / 0.3), 0 1px 3px 0 rgb(0 0 0 / 0.2)',
                'card-hover': '0 8px 24px -6px rgb(0 0 0 / 0.5)',
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0', transform: 'translateY(6px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in-right': {
                    '0%': { opacity: '0', transform: 'translateX(12px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                'slide-in-left': {
                    '0%': { opacity: '0', transform: 'translateX(-14px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                'slide-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'pop-in': {
                    '0%': { opacity: '0', transform: 'scale(.85)' },
                    '60%': { opacity: '1', transform: 'scale(1.04)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                'toast-in': {
                    '0%': { opacity: '0', transform: 'translateY(-28px) scale(.94)' },
                    '55%': { opacity: '1', transform: 'translateY(6px) scale(1.02)' },
                    '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
                'toast-out': {
                    '0%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                    '100%': { opacity: '0', transform: 'translateY(-24px) scale(.95)' },
                },
                shine: {
                    '0%': { transform: 'translateX(-120%)' },
                    '100%': { transform: 'translateX(220%)' },
                },
                progress: {
                    '0%': { transform: 'scaleX(1)' },
                    '100%': { transform: 'scaleX(0)' },
                },
                'draw-circle': {
                    '0%': { strokeDasharray: '0 160', opacity: '0' },
                    '100%': { strokeDasharray: '160 160', opacity: '1' },
                },
                'draw-check': {
                    '0%': { strokeDasharray: '0 60', strokeDashoffset: '0' },
                    '45%': { strokeDasharray: '0 60' },
                    '100%': { strokeDasharray: '60 60' },
                },
                'grow-y': {
                    '0%': { transform: 'translateY(-50%) scaleY(0)' },
                    '100%': { transform: 'translateY(-50%) scaleY(1)' },
                },
                'bounce-soft': {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-3px)' },
                },
                'pulse-ring': {
                    '0%': { boxShadow: '0 0 0 0 rgba(52,211,153,.55)' },
                    '70%': { boxShadow: '0 0 0 12px rgba(52,211,153,0)' },
                    '100%': { boxShadow: '0 0 0 0 rgba(52,211,153,0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-4px)' },
                },
                'count-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-in': 'fade-in 0.35s ease-out both',
                'slide-in-right': 'slide-in-right 0.3s ease-out both',
                'slide-in-left': 'slide-in-left 0.4s ease-out both',
                'slide-up': 'slide-up 0.45s cubic-bezier(.22,1,.36,1) both',
                'pop-in': 'pop-in 0.4s cubic-bezier(.22,1,.36,1) both',
                'toast-in': 'toast-in 0.55s cubic-bezier(.22,1.4,.36,1) both',
                'toast-out': 'toast-out 0.45s ease-in both',
                shine: 'shine 2.2s ease-in-out infinite',
                progress: 'progress 4.6s linear forwards',
                'draw-circle': 'draw-circle 0.6s ease-out both',
                'draw-check': 'draw-check 0.8s ease-out both',
                'grow-y': 'grow-y 0.3s ease-out both',
                'bounce-soft': 'bounce-soft 1.6s ease-in-out infinite',
                'pulse-ring': 'pulse-ring 2s ease-out infinite',
                float: 'float 3s ease-in-out infinite',
                'count-up': 'count-up 0.5s ease-out both',
            },
        },
    },
    plugins: [forms, typography],
};
