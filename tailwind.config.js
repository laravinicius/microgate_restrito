/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./*.php", "./components/**/*.html", "./js/**/*.js"],
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
