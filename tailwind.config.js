/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
	'./*.php',
	'./app/**/*.php',
	'./template-parts/**/*.php',
	'./src/js/**/*.js'
	],
	theme: {
		extend: {
			colors: {
				brand: {
					50: '#f5fbff',
					100: '#e0f2ff',
					200: '#b9e0ff',
					300: '#82c5ff',
					400: '#4aa8ff',
					500: '#1f88ff',
					600: '#0f6fe5',
					700: '#0c58b3',
					800: '#0f4a8f',
					900: '#123f74'
				}
			}
		}
	},
	plugins: []
};
