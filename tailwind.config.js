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
                // Diselaraskan dengan logo PT Geosys Energi Prima (teal-biru pada ikon)
                brand: {
                    50: '#ecf9fe',
                    100: '#d5f2fc',
                    200: '#ace3f6',
                    300: '#70cceb',
                    400: '#29afe0',
                    500: '#0f8cbd',
                    600: '#0a729e',
                    700: '#0e597c',
                    800: '#104660',
                    900: '#10374c',
                },
                // Aksen utama (menggantikan biru default) — teal-petrol senada logo
                blue: {
                    50: '#ecf9fe',
                    100: '#d5f2fc',
                    200: '#ace3f6',
                    300: '#70cceb',
                    400: '#29afe0',
                    500: '#0f8cbd',
                    600: '#0a729e',
                    700: '#0e597c',
                    800: '#104660',
                    900: '#10374c',
                },
                // Latar netral gelap dengan sedikit rona teal — elegan & senada logo
                slate: {
                    50: '#f8fbfc',
                    100: '#f1f6f9',
                    200: '#e1ebef',
                    300: '#cbdae1',
                    400: '#94acb8',
                    500: '#607d8a',
                    600: '#445d6a',
                    700: '#304855',
                    800: '#1a2d37',
                    900: '#0d1c26',
                    950: '#030b11',
                },
                // Palet khusus modul "Manajemen Tugas" (gaya Notion) — netral hangat + biru Notion
                notion: {
                    blue: '#0075de',
                    'blue-active': '#005bab',
                    'blue-badge-bg': '#f2f9ff',
                    'blue-badge-text': '#097fe8',
                    navy: '#213183',
                    ink: 'rgba(0,0,0,0.95)',
                },
                warm: {
                    white: '#f6f5f4',
                    50: '#f6f5f4',
                    100: '#efeeec',
                    300: '#a39e98',
                    500: '#615d59',
                    800: '#31302e',
                },
            },
            boxShadow: {
                notion: '0 4px 18px 0 rgb(0 0 0 / 0.04), 0 2.025px 7.85px 0 rgb(0 0 0 / 0.027), 0 0.8px 2.93px 0 rgb(0 0 0 / 0.02), 0 0.175px 1.04px 0 rgb(0 0 0 / 0.01)',
                'notion-deep': '0 1px 3px 0 rgb(0 0 0 / 0.01), 0 3px 7px 0 rgb(0 0 0 / 0.02), 0 7px 15px 0 rgb(0 0 0 / 0.02), 0 14px 28px 0 rgb(0 0 0 / 0.04), 0 23px 52px 0 rgb(0 0 0 / 0.05)',
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
