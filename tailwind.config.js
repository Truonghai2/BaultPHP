/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        bault: {
          50: "#f5f7ff",
          100: "#ebf0ff",
          200: "#ced9fe",
          300: "#a6b9fe",
          400: "#7b93fc",
          500: "#4f6cf9",
          600: "#3d4ff0",
          700: "#3642dc",
          800: "#2f37b3",
          900: "#2d338d",
        },
      },
      animation: {
        "fade-in": "fadeIn 0.5s ease-in",
        "slide-up": "slideUp 0.5s ease-out",
      },
      keyframes: {
        fadeIn: {
          "0%": { opacity: "0" },
          "100%": { opacity: "1" },
        },
        slideUp: {
          "0%": { transform: "translateY(20px)", opacity: "0" },
          "100%": { transform: "translateY(0)", opacity: "1" },
        },
      },
    },
  },
  plugins: [],
};
