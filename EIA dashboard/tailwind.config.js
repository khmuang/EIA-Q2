/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./*.html",
    "./backup_EIA Dashboard_20260329/**/*.php",
    "./backup/**/*.php"
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Custom Dashboard theme colors
      },
    },
  },
  plugins: [],
}