/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./*.php", "./components/**/*.html", "./js/**/*.js"],
  safelist: [
    // Classes geradas dinamicamente em JS (template literals não são detectados pelo scanner)
    "text-green-400",
    "text-blue-400",
    "text-orange-400",
    "text-red-400",
    "text-gray-400",
    "text-gray-500",
    "text-gray-600",
    "text-white",
    "font-medium",
    "font-semibold",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          dark: '#1f1f1f',
          light: '#F5F5F7'
        }
      },
      backgroundImage: {
        'metallic-chrome': 'linear-gradient(145deg, #fcfcfc 5%, #ffffff 15%, #9ca3af 50%, #4b5563 100%)'
      }
    }
  },
  plugins: []
};