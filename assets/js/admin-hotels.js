(function() {
	// Copy registration URL to clipboard.
	function copyRegUrl() {
		var regEl = document.getElementById('reg-url-text');
		if (!regEl || !window.navigator || !navigator.clipboard) {
			return;
		}

		var text = regEl.textContent || regEl.innerText || '';
		navigator.clipboard.writeText(text).then(function() {
			if (window.hotelChainAdmin && window.hotelChainAdmin.regCopiedText) {
				alert(window.hotelChainAdmin.regCopiedText);
			}
		});
	}

	// Copy landing URL to clipboard.
	function copyLandUrl() {
		var landEl = document.getElementById('land-url-text');
		if (!landEl || !window.navigator || !navigator.clipboard) {
			return;
		}

		var text = landEl.textContent || landEl.innerText || '';
		navigator.clipboard.writeText(text).then(function() {
			if (window.hotelChainAdmin && window.hotelChainAdmin.landCopiedText) {
				alert(window.hotelChainAdmin.landCopiedText);
			}
		});
	}

	// Attach click handlers for copy buttons if present.
	function bindCopyButtons() {
		var regButton = document.querySelector('[data-hotel-copy="reg"]');
		var landButton = document.querySelector('[data-hotel-copy="land"]');

		if (regButton) {
			regButton.addEventListener('click', function(event) {
				event.preventDefault();
				copyRegUrl();
			});
		}

		if (landButton) {
			landButton.addEventListener('click', function(event) {
				event.preventDefault();
				copyLandUrl();
			});
		}
	}

	// Client-side hotel search filtering.
	function bindSearchFiltering() {
		var input = document.getElementById('hotel-search-input');
		var form = document.getElementById('search-form');
		var desktopRows = document.querySelectorAll('[data-hotel-row]');
		var mobileCards = document.querySelectorAll('[data-hotel-card]');

		if (!input || !form) {
			return;
		}

		// Prevent form submit (no page reload).
		form.addEventListener('submit', function(event) {
			event.preventDefault();
		});

		function matches(element, value) {
			if (!element) {
				return false;
			}
			if (!value) {
				return true;
			}
			return element.textContent.toLowerCase().indexOf(value) !== -1;
		}

		function filterHotels(term) {
			var value = (term || '').toLowerCase().trim();

			desktopRows.forEach(function(row) {
				row.style.display = matches(row, value) ? '' : 'none';
			});

			mobileCards.forEach(function(card) {
				card.style.display = matches(card, value) ? '' : 'none';
			});
		}

		// Initial filter if input has a value from URL.
		if (input.value) {
			filterHotels(input.value);
		}

		input.addEventListener('input', function(event) {
			filterHotels(event.target.value);
		});
	}

	// Initialize once DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			bindCopyButtons();
			bindSearchFiltering();
		});
	} else {
		bindCopyButtons();
		bindSearchFiltering();
	}
})();
