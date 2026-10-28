import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans Variable"', '"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#EFF5FF', 100: '#DCE8FD', 200: '#BFD5FB', 300: '#93B8F7', 400: '#5F94F0',
                    500: '#3B73E6', 600: '#2559CC', 700: '#1D46A5', 800: '#1B3A82', 900: '#1A3068', 950: '#111F45',
                },
                ink: {
                    50: '#F4F7FB', 100: '#EAF0F7', 200: '#D9E2EE', 300: '#BFCBDC', 400: '#8E9DB5',
                    500: '#66758F', 600: '#4B5A73', 700: '#36445A', 800: '#232F42', 900: '#152033', 950: '#0C1524',
                },
            },
            boxShadow: {
                soft: '0 10px 30px -12px rgba(26, 48, 104, 0.18), 0 2px 6px rgba(26, 48, 104, 0.06)',
                lift: '0 22px 40px -18px rgba(26, 48, 104, 0.30), 0 4px 10px rgba(26, 48, 104, 0.06)',
            },
            keyframes: {
                float: { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } },
                blob: { '0%,100%': { transform: 'translate(0,0) scale(1)' }, '50%': { transform: 'translate(20px,-16px) scale(1.08)' } },
                rise: { from: { opacity: '0', transform: 'translateY(14px)' }, to: { opacity: '1', transform: 'none' } },
                pop: { '0%': { transform: 'scale(.6)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } },
                shake: { '0%,100%': { transform: 'translateX(0)' }, '20%,60%': { transform: 'translateX(-6px)' }, '40%,80%': { transform: 'translateX(6px)' } },
            },
            animation: {
                float: 'float 6s ease-in-out infinite',
                'float-slow': 'float 8s ease-in-out 1s infinite',
                blob: 'blob 14s ease-in-out infinite',
                rise: 'rise .45s cubic-bezier(.22,1,.36,1) both',
                pop: 'pop .35s cubic-bezier(.34,1.56,.64,1) both',
                shake: 'shake .4s ease-in-out',
            },
        },
    },
    plugins: [],
};
