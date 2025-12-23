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

	// Upload progress for video upload form.
	function bindVideoUploadForm() {
		var form = document.getElementById('hotel-video-upload-form');
		if (!form) {
			return;
		}

		var progressWrapper = document.getElementById('hotel-video-upload-progress');
		var bar = document.getElementById('hotel-video-upload-bar');
		var percentLabel = document.getElementById('hotel-video-upload-percent');
		var statusLabel = document.getElementById('hotel-video-upload-status');
		var fileLabel = document.getElementById('hotel-video-upload-filename');

		// Elements for local video preview (created in VideosPage::render_page()).
		var previewWrapper = document.getElementById('hotel-video-upload-preview-wrapper');
		var previewPlayer = document.getElementById('hotel-video-upload-player');
		var placeholder = document.getElementById('hotel-video-upload-placeholder');
		var removeButton = document.getElementById('hotel-video-upload-remove');
		var fileInput = document.getElementById('hotel-video-file-input');
		var currentObjectUrl = null;

		function resetProgress() {
			if (!progressWrapper) {
				return;
			}
			progressWrapper.classList.add('hidden');
			if (bar) bar.style.width = '0%';
			if (percentLabel) percentLabel.textContent = '0%';
			if (statusLabel) statusLabel.textContent = (window.hotelChainAdmin && window.hotelChainAdmin.uploadPreparingText) || 'Preparing upload...';
			if (fileLabel) fileLabel.textContent = '';
		}

		function showProgress() {
			if (!progressWrapper) {
				return;
			}
			progressWrapper.classList.remove('hidden');
		}

		// Clean up any existing preview URL and show placeholder.
		function resetPreview() {
			if (currentObjectUrl && window.URL && URL.revokeObjectURL) {
				try {
					URL.revokeObjectURL(currentObjectUrl);
				} catch (e) {
					// Ignore revoke errors.
				}
				currentObjectUrl = null;
			}
			if (previewPlayer) {
				previewPlayer.removeAttribute('src');
				try {
					previewPlayer.load();
				} catch (e2) {
					// Ignore.
				}
			}
			if (previewWrapper) {
				previewWrapper.classList.add('hidden');
			}
			if (placeholder) {
				placeholder.classList.remove('hidden');
			}
		}

		// When a file is selected, show a local preview without uploading yet.
		if (fileInput && window.URL && URL.createObjectURL) {
			fileInput.addEventListener('change', function () {
				if (!fileInput.files || !fileInput.files.length) {
					resetPreview();
					return;
				}

				var file = fileInput.files[0];

				// Ignore non-video files just in case.
				if (!file.type || file.type.indexOf('video/') !== 0) {
					resetPreview();
					return;
				}

				resetPreview();

				if (!previewPlayer || !previewWrapper) {
					return;
				}

				currentObjectUrl = URL.createObjectURL(file);
				previewPlayer.src = currentObjectUrl;
				previewWrapper.classList.remove('hidden');
				if (placeholder) {
					placeholder.classList.add('hidden');
				}
			});
		}

		// Remove selected video (just clears the file input and preview, no server call).
		if (removeButton && fileInput) {
			removeButton.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				fileInput.value = '';
				resetPreview();
				// Also clear any progress UI text to avoid confusion.
				if (fileLabel) {
					fileLabel.textContent = '';
				}
			});
		}

		// Prevent label from triggering file input when clicking on video player controls
		if (previewPlayer) {
			previewPlayer.addEventListener('click', function (e) {
				e.stopPropagation();
			});
		}

		// Thumbnail preview handling
		var thumbPreviewWrapper = document.getElementById('hotel-thumbnail-upload-preview-wrapper');
		var thumbPreviewImg = document.getElementById('hotel-thumbnail-upload-preview');
		var thumbPlaceholder = document.getElementById('hotel-thumbnail-upload-placeholder');
		var thumbRemoveButton = document.getElementById('hotel-thumbnail-upload-remove');
		var thumbFileInput = document.getElementById('hotel-thumbnail-file-input');
		var thumbCurrentObjectUrl = null;

		// Clean up thumbnail preview and show placeholder.
		function resetThumbnailPreview() {
			if (thumbCurrentObjectUrl && window.URL && URL.revokeObjectURL) {
				try {
					URL.revokeObjectURL(thumbCurrentObjectUrl);
				} catch (e) {
					// Ignore revoke errors.
				}
				thumbCurrentObjectUrl = null;
			}
			if (thumbPreviewImg) {
				thumbPreviewImg.removeAttribute('src');
			}
			if (thumbPreviewWrapper) {
				thumbPreviewWrapper.classList.add('hidden');
			}
			if (thumbPlaceholder) {
				thumbPlaceholder.classList.remove('hidden');
			}
		}

		// When a thumbnail file is selected, show a local preview without uploading yet.
		if (thumbFileInput && window.URL && URL.createObjectURL) {
			thumbFileInput.addEventListener('change', function () {
				if (!thumbFileInput.files || !thumbFileInput.files.length) {
					resetThumbnailPreview();
					return;
				}

				var file = thumbFileInput.files[0];

				// Ignore non-image files just in case.
				if (!file.type || file.type.indexOf('image/') !== 0) {
					resetThumbnailPreview();
					return;
				}

				resetThumbnailPreview();

				if (!thumbPreviewImg || !thumbPreviewWrapper) {
					return;
				}

				thumbCurrentObjectUrl = URL.createObjectURL(file);
				thumbPreviewImg.src = thumbCurrentObjectUrl;
				thumbPreviewWrapper.classList.remove('hidden');
				if (thumbPlaceholder) {
					thumbPlaceholder.classList.add('hidden');
				}
			});
		}

		// Remove selected thumbnail (just clears the file input and preview, no server call).
		if (thumbRemoveButton && thumbFileInput) {
			thumbRemoveButton.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				thumbFileInput.value = '';
				resetThumbnailPreview();
			});
		}

		// Prevent label from triggering file input when clicking on thumbnail image
		if (thumbPreviewImg) {
			thumbPreviewImg.addEventListener('click', function (e) {
				e.stopPropagation();
			});
		}

		form.addEventListener('submit', function(event) {
			if (!fileInput || !fileInput.files || !fileInput.files.length) {
				// Let normal submit happen; server-side will show error.
				return;
			}

			showProgress();

			if (statusLabel) {
				statusLabel.textContent = (window.hotelChainAdmin && window.hotelChainAdmin.uploadPreparingText) || 'Preparing upload...';
			}

			var file = fileInput.files[0];
			if (fileLabel) {
				fileLabel.textContent = file.name + ' (' + Math.round(file.size / (1024 * 1024)) + ' MB)';
			}

			// Let the normal browser form submission continue (no preventDefault),
			// so WordPress handles creating the post and media. We only show a
			// visual progress indicator without tracking exact bytes.
		});
	}

	// Autocomplete for video tags field on the video upload page.
	function bindVideoTagsAutocomplete() {
		var input = document.getElementById('video_tags');
		if (!input || !window.hotelChainAdmin || !Array.isArray(window.hotelChainAdmin.videoTags)) {
			return;
		}

		var allTags = window.hotelChainAdmin.videoTags.slice();
		var container = document.createElement('div');
		container.id = 'video-tags-suggestions';
		container.className = 'mt-1 border border-solid border-gray-300 rounded bg-white shadow-sm text-sm text-gray-700 hidden';

		input.parentNode.appendChild(container);

		function closeSuggestions() {
			container.classList.add('hidden');
			container.innerHTML = '';
		}

		function getCurrentTags() {
			var value = input.value || '';
			return value
				.split(',')
				.map(function(part) { return part.trim(); })
				.filter(function(part) { return part.length > 0; });
		}

		function renderSuggestions(query) {
			var existing = getCurrentTags();
			var lowerExisting = existing.map(function(t) { return t.toLowerCase(); });
			var q = query.toLowerCase();

			var matches = allTags.filter(function(tag) {
				var lower = String(tag).toLowerCase();
				return lower.indexOf(q) !== -1 && lowerExisting.indexOf(lower) === -1;
			}).slice(0, 8);

			if (!matches.length) {
				closeSuggestions();
				return;
			}

			container.innerHTML = '';
			matches.forEach(function(tag) {
				var item = document.createElement('button');
				item.type = 'button';
				item.textContent = tag;
				item.className = 'block w-full text-left px-3 py-1 hover:bg-blue-50';
				item.addEventListener('click', function() {
					var current = getCurrentTags();
					// Replace the last partial tag with the selected suggestion.
					if (current.length) {
						current[current.length - 1] = tag;
					} else {
						current.push(tag);
					}
					// Ensure unique tags.
					var seen = {};
					var unique = current.filter(function(t) {
						var key = t.toLowerCase();
						if (seen[key]) return false;
						seen[key] = true;
						return true;
					});
					// Always join with comma + space, but do not add a trailing comma.
					input.value = unique.join(', ');
					closeSuggestions();
					input.focus();
				});
				container.appendChild(item);
			});

			container.classList.remove('hidden');
		}

		input.addEventListener('input', function(e) {
			var value = e.target.value || '';
			var parts = value.split(',');
			var last = parts[parts.length - 1].trim();

			if (last.length < 2) {
				closeSuggestions();
				return;
			}

			renderSuggestions(last);
		});

		input.addEventListener('blur', function() {
			setTimeout(closeSuggestions, 150);
		});
	}

	// Replace video file from the video detail panel.
	function bindVideoReplace() {
		var buttons = document.querySelectorAll('[data-hotel-replace-button]');
		if (!buttons.length) {
			return;
		}

		buttons.forEach(function(button) {
			button.addEventListener('click', function() {
				var form = button.closest('form');
				if (!form) {
					return;
				}

				var input = form.querySelector('input[type="file"][name="replace_video_file"][data-hotel-replace-input]');
				var label = form.querySelector('[data-hotel-replace-label]');
				if (!input) {
					return;
				}

				// When a file is chosen, show a highlighted message; actual upload happens on Save Changes submit.
				function handleChange() {
					input.removeEventListener('change', handleChange);
					if (!input.files || !input.files.length) {
						return;
					}

					var file = input.files[0];
					var sizeMb = Math.round(file.size / (1024 * 1024));
					if (label) {
						label.textContent = 'New video selected: ' + file.name + ' (' + sizeMb + ' MB). It will replace the current file when you click "Save Changes".';
						label.classList.remove('hidden');
					}
				}

				input.addEventListener('change', handleChange);
				input.click();
			});
		});
	}

	// Inline video preview player in the Video Library detail panel.
	function bindInlineVideoPreview() {
		var buttons = document.querySelectorAll('[data-hotel-video-play]');
		if (!buttons.length) {
			return;
		}

		buttons.forEach(function(button) {
			button.addEventListener('click', function() {
				var previewId = button.getAttribute('data-video-preview-id');
				var playLabel = button.getAttribute('data-video-play-label') || 'Play Preview';
				var stopLabel = button.getAttribute('data-video-stop-label') || 'Stop Preview';
				if (!previewId) {
					return;
				}

				var video = document.getElementById(previewId);
				if (!video) {
					return;
				}

				var wrapper = video.parentElement || null;
				var poster = wrapper ? wrapper.querySelector('[data-hotel-video-poster]') : null;

				var isPlaying = !video.paused && !video.ended && video.currentTime > 0;

				if (!isPlaying) {
					// Show video, hide poster.
					if (poster) {
						poster.classList.add('hidden');
					}
					video.classList.remove('hidden');
					video.controls = true;
					try {
						video.currentTime = 0;
					} catch (e) {
						// Ignore if not seekable yet.
					}
					video.play();
					button.textContent = stopLabel;
				} else {
					// Stop preview and restore poster.
					video.pause();
					try {
						video.currentTime = 0;
					} catch (e2) {
						// Ignore.
					}
					video.classList.add('hidden');
					if (poster) {
						poster.classList.remove('hidden');
					}
					button.textContent = playLabel;
				}
			});
		});
	}

	// Initialize once DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			bindCopyButtons();
			bindSearchFiltering();
			bindVideoUploadForm();
			bindVideoTagsAutocomplete();
			bindVideoReplace();
			bindInlineVideoPreview();
		});
	} else {
		bindCopyButtons();
		bindSearchFiltering();
		bindVideoUploadForm();
		bindVideoTagsAutocomplete();
		bindVideoReplace();
		bindInlineVideoPreview();
	}
})();
