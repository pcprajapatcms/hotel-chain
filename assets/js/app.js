(function () {
	// Built file placeholder. Run `npm run build` to regenerate.
	document.addEventListener('DOMContentLoaded', function () {
		var navToggle = document.querySelector('[data-nav-toggle]');
		var navMenu = document.querySelector('[data-nav-menu]');

		if (!navToggle || !navMenu) {
			return;
		}

		navToggle.addEventListener('click', function () {
			var isOpen = navMenu.classList.toggle('hidden');
			navToggle.setAttribute('aria-expanded', String(!isOpen));
		});
	});
}());
