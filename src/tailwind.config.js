 /** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',           // ✅ this is required
  content: [
    './resources/views/**/*.blade.php',
    './resources/ts/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
        },
      },
      boxShadow: {
        soft: '0 18px 45px rgba(15, 23, 42, 0.16)',
      },
      keyframes: {
        'card-pop': {
          '0%': { opacity: '0', transform: 'translateY(10px) scale(0.97)' },
          '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
        },
      },
      animation: {
        'card-pop': 'card-pop 0.35s ease-out',
      },
    },
  },
  plugins: [],
}
