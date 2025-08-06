/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./Modules/**/*.blade.php", // Quét cả các file trong Modules
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
