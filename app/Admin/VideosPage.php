<?php
/**
 * Admin Videos upload page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Support\StyleSettings;

/**
 * Render Video upload admin page.
 */
class VideosPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_upload_video', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_video_upload_media', array( $this, 'handle_media_upload' ) );
		add_action( 'wp_ajax_video_delete_media', array( $this, 'handle_media_delete' ) );
	}

		/**
		 * Register admin menu entry.
		 *
		 * @return void
		 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Upload Videos', 'hotel-chain' ),
			__( 'Upload Videos', 'hotel-chain' ),
			'manage_options',
			'hotel-video-upload',
			array( $this, 'render_page' ),
			'dashicons-upload',
			6
		);
	}

	/**
	 * Handle video upload form submit.
	 *
	 * @return void
	 */
	public function handle_upload(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_upload_video' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$title        = isset( $_POST['video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['video_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$description  = isset( $_POST['video_description'] ) ? wp_kses_post( wp_unslash( $_POST['video_description'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$practice_tip = isset( $_POST['video_practice_tip'] ) ? wp_kses_post( wp_unslash( $_POST['video_practice_tip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$category_id  = isset( $_POST['video_category'] ) ? sanitize_text_field( wp_unslash( $_POST['video_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Tags are selected one-by-one (checkboxes); normalise to comma-separated string for storage.
		$tags_raw = '';
		if ( isset( $_POST['video_tags'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw_tags = (array) wp_unslash( $_POST['video_tags'] );
			$clean    = array();
			foreach ( $raw_tags as $tag ) {
				$tag = sanitize_text_field( $tag );
				if ( '' !== $tag ) {
					$clean[] = $tag;
				}
			}
			$tags_raw = implode( ',', array_unique( $clean ) );
		}

		$language = isset( $_POST['video_language'] ) ? sanitize_text_field( wp_unslash( $_POST['video_language'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Get video and thumbnail attachment IDs from hidden inputs (uploaded via AJAX).
		$video_attachment_id = isset( $_POST['video_file_id'] ) ? absint( $_POST['video_file_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$thumb_attachment_id = isset( $_POST['video_thumbnail_id'] ) ? absint( $_POST['video_thumbnail_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $title ) || ! $video_attachment_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'hotel-video-upload',
						'video_err' => 'missing_required',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Get thumbnail URL if thumbnail was uploaded.
		$thumb_url = '';
		if ( $thumb_attachment_id ) {
			$attachment_url = wp_get_attachment_url( $thumb_attachment_id );
			$thumb_url      = $attachment_url ? $attachment_url : '';
		}

		if ( ! is_wp_error( $video_attachment_id ) && $video_attachment_id ) {

			// Derive duration and technical metadata from attachment metadata.
			$duration_label    = '';
			$duration_seconds  = null;
			$file_size         = null;
			$resolution_width  = null;
			$resolution_height = null;
			$file_format       = '';

			$metadata = wp_get_attachment_metadata( $video_attachment_id );

			if ( ! empty( $metadata['length_formatted'] ) ) {
				$duration_label = $metadata['length_formatted'];
			}

			if ( isset( $metadata['length'] ) ) {
				$duration_seconds = (int) $metadata['length'];
			}

			if ( isset( $metadata['filesize'] ) ) {
				$file_size = (int) $metadata['filesize'];
			}

			if ( isset( $metadata['width'] ) ) {
				$resolution_width = (int) $metadata['width'];
			}

			if ( isset( $metadata['height'] ) ) {
				$resolution_height = (int) $metadata['height'];
			}

			$file = get_attached_file( $video_attachment_id );
			if ( $file ) {
				$ext = pathinfo( $file, PATHINFO_EXTENSION );
				if ( $ext ) {
					$file_format = strtoupper( $ext );
				}

				// Fallback to wp_read_video_metadata if duration label is still empty.
				if ( '' === $duration_label && function_exists( 'wp_read_video_metadata' ) ) {
					$video_meta = wp_read_video_metadata( $file );
					if ( ! empty( $video_meta['length_formatted'] ) ) {
						$duration_label = $video_meta['length_formatted'];
					}
					if ( isset( $video_meta['length'] ) && null === $duration_seconds ) {
						$duration_seconds = (int) $video_meta['length'];
					}
				}
			}

			// Persist metadata into custom video_metadata table.
			$video_repository = new VideoRepository();
			$video_id         = $video_repository->create_or_update(
				null,
				array(
					'title'               => $title,
					'description'         => $description,
					'practice_tip'        => $practice_tip,
					'category'            => $category_id ? (string) $category_id : '',
					'tags'                => $tags_raw,
					'thumbnail_id'        => $thumb_attachment_id,
					'thumbnail_url'       => $thumb_url,
					'video_file_id'       => $video_attachment_id,
					'duration_seconds'    => $duration_seconds,
					'duration_label'      => $duration_label,
					'file_size'           => $file_size,
					'file_format'         => $file_format,
					'resolution_width'    => $resolution_width,
					'resolution_height'   => $resolution_height,
					'default_language'    => $language,
					'total_views'         => 0,
					'total_completions'   => 0,
					'avg_completion_rate' => 0.00,
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'hotel-video-upload',
					'video_ok' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render upload page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$video_ok  = isset( $_GET['video_ok'] ) ? absint( $_GET['video_ok'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$video_err = isset( $_GET['video_err'] ) ? sanitize_text_field( wp_unslash( $_GET['video_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Categories & suggested tags come from the dedicated Video Taxonomy settings page.
		$taxonomy_repository = new \HotelChain\Repositories\VideoTaxonomyRepository();
		$categories          = $taxonomy_repository->get_category_names();
		$tags_suggestions    = $taxonomy_repository->get_tag_names();
		$logo_url            = StyleSettings::get_logo_url();
		?>
		<div class="flex-1 overflow-auto p-4 lg:p-8 lg:px-0">
			<div class="w-12/12 md:w-10/12 mx-auto p-0">
				<div class="flex items-center gap-4 mb-6 pb-3 border-b border-solid border-gray-400">
					<?php if ( ! empty( $logo_url ) ) : ?>
						<div class="flex-shrink-0">
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'hotel-chain' ); ?>" class="h-12 md:h-16 w-auto object-contain" />
						</div>
					<?php endif; ?>
					<div class="flex-1">
						<h1><?php esc_html_e( 'Upload New Video', 'hotel-chain' ); ?></h1>
						<p class="text-slate-600"><?php esc_html_e( 'Upload training and onboarding videos and organize them by category and tags.', 'hotel-chain' ); ?></p>
					</div>
				</div>

				<?php if ( $video_ok ) : ?>
					<div class="bg-green-50 border-2 border-green-400 rounded p-4 mb-4">
						<p class="text-green-900 font-medium mb-1"><?php esc_html_e( 'Video uploaded successfully.', 'hotel-chain' ); ?></p>
						<p class="text-green-800 text-sm"><?php esc_html_e( 'You can assign this video to hotels or guests from the Videos list.', 'hotel-chain' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $video_err ) : ?>
					<div class="bg-red-50 border-2 border-red-400 rounded p-4 mb-4">
						<p class="text-red-900 font-medium">
							<?php
							switch ( $video_err ) {
								case 'missing_required':
									esc_html_e( 'Please provide a video title and select a video file.', 'hotel-chain' );
									break;
								case 'create_failed':
									esc_html_e( 'Failed to create video entry. Please try again.', 'hotel-chain' );
									break;
								default:
									esc_html_e( 'An error occurred while uploading the video.', 'hotel-chain' );
							}
							?>
						</p>
					</div>
				<?php endif; ?>

				<form id="hotel-video-upload-form" class="bg-white rounded p-4 border border-solid border-gray-400 mb-6" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( 'hotel_chain_upload_video' ); ?>
					<input type="hidden" name="action" value="hotel_chain_upload_video" />

					<div class="mb-4 pb-3 border-b border-gray-300 flex items-center justify-between">
						<h3 class="text-lg font-semibold"><?php esc_html_e( 'Upload New Video', 'hotel-chain' ); ?></h3>
					</div>

					<div class="bg-white border border-solid border-gray-400 rounded p-6">
						<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
							<!-- Video File Uploader (Left Side) -->
							<div>
								<label class="block mb-2 text-gray-700"><?php esc_html_e( 'Video File', 'hotel-chain' ); ?></label>
								<div id="video-drop-zone" class="border border-solid border-gray-400 border-dashed rounded p-6 text-center bg-gray-50 transition-colors cursor-pointer hover:border-blue-400 hover:bg-blue-50">
									<input type="hidden" name="video_file_id" id="video_file_id" value="" />
									<input type="hidden" name="video_file_url" id="video_file_url" value="" />
									<input type="file" id="video-file" accept="video/mp4,video/quicktime,video/x-msvideo" class="hidden" />
									<div id="video-preview" class="hidden mb-4">
										<video id="video-player" src="" class="w-full rounded bg-black mx-auto" style="max-height: 200px;" controls></video>
										<button type="button" id="remove-video-btn" class="mt-2 px-3 py-1 bg-red-200 border-2 border-red-400 rounded text-red-900 text-sm">
											<?php esc_html_e( 'Remove Video', 'hotel-chain' ); ?>
										</button>
									</div>
									<div id="video-uploading" class="hidden mb-4">
										<div class="w-16 h-16 mx-auto mb-3">
											<svg class="animate-spin w-full h-full text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
												<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
												<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
											</svg>
										</div>
										<p class="text-blue-600 text-sm font-medium"><?php esc_html_e( 'Uploading video...', 'hotel-chain' ); ?></p>
										<div class="w-full bg-gray-200 rounded-full h-2 mt-2">
											<div id="video-progress-bar" class="bg-blue-500 h-2 rounded-full transition-all" style="width: 0%"></div>
										</div>
										<p id="video-progress-text" class="text-gray-500 text-xs mt-1">0%</p>
									</div>
									<div id="video-placeholder" class="mb-4">
										<div class="w-16 h-16 mx-auto bg-gray-200 border border-solid border-gray-400 rounded flex items-center justify-center mb-3">
											<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
												<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
												<circle cx="12" cy="13" r="3"></circle>
											</svg>
										</div>
										<p class="text-gray-700 text-sm font-medium"><?php esc_html_e( 'Drop video here or click to browse', 'hotel-chain' ); ?></p>
										<p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'MP4, MOV or AVI. Max size depends on server limits', 'hotel-chain' ); ?></p>
									</div>
								</div>
							</div>

							<!-- Video Thumbnail Uploader (Right Side) -->
							<div>
								<label class="block mb-2 text-gray-700"><?php esc_html_e( 'Video Thumbnail (Optional)', 'hotel-chain' ); ?></label>
								<div id="thumbnail-drop-zone" class="border border-solid border-gray-400 border-dashed rounded p-6 text-center bg-gray-50 transition-colors cursor-pointer hover:border-blue-400 hover:bg-blue-50">
									<input type="hidden" name="video_thumbnail_id" id="video_thumbnail_id" value="" />
									<input type="hidden" name="video_thumbnail_url" id="video_thumbnail_url" value="" />
									<input type="file" id="thumbnail-file" accept="image/png,image/jpeg,image/jpg,image/svg+xml" class="hidden" />
									<div id="thumbnail-preview" class="hidden mb-4">
										<img id="thumbnail-img" src="" alt="Thumbnail" class="w-full aspect-video rounded object-cover mx-auto" />
										<button type="button" id="remove-thumbnail-btn" class="mt-2 px-3 py-1 bg-red-200 border-2 border-red-400 rounded text-red-900 text-sm">
											<?php esc_html_e( 'Remove Thumbnail', 'hotel-chain' ); ?>
										</button>
									</div>
									<div id="thumbnail-uploading" class="hidden mb-4">
										<div class="w-16 h-16 mx-auto mb-3">
											<svg class="animate-spin w-full h-full text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
												<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
												<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
											</svg>
										</div>
										<p class="text-blue-600 text-sm font-medium"><?php esc_html_e( 'Uploading thumbnail...', 'hotel-chain' ); ?></p>
										<div class="w-full bg-gray-200 rounded-full h-2 mt-2">
											<div id="thumbnail-progress-bar" class="bg-blue-500 h-2 rounded-full transition-all" style="width: 0%"></div>
										</div>
										<p id="thumbnail-progress-text" class="text-gray-500 text-xs mt-1">0%</p>
									</div>
									<div id="thumbnail-placeholder" class="mb-4">
										<div class="w-16 h-16 mx-auto bg-gray-200 border border-solid border-gray-400 rounded flex items-center justify-center mb-3">
											<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
												<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
												<circle cx="9" cy="9" r="2"></circle>
												<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
											</svg>
										</div>
										<p class="text-gray-700 text-sm font-medium"><?php esc_html_e( 'Drop thumbnail here or click to browse', 'hotel-chain' ); ?></p>
										<p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'PNG, JPG or SVG. Recommended 16:9 aspect ratio', 'hotel-chain' ); ?></p>
									</div>
								</div>
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<div class="mb-4">
									<label class="mb-1 block font-semibold text-sm text-slate-800" for="video_title"><?php esc_html_e( 'Video Title', 'hotel-chain' ); ?></label>
									<input type="text" id="video_title" name="video_title" required placeholder="<?php esc_attr_e( 'e.g., Welcome & Hotel Tour', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
								<div class="mb-4">
									<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_category"><?php esc_html_e( 'Category', 'hotel-chain' ); ?></label>
									<select id="video_category" name="video_category" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500 max-w-full">
										<option value=""><?php esc_html_e( 'Select category...', 'hotel-chain' ); ?></option>
										<?php foreach ( $categories as $category_name ) : ?>
											<option value="<?php echo esc_attr( $category_name ); ?>"><?php echo esc_html( $category_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="mb-4">
									<label class="mb-1 block text-gray-700 text-sm font-semibold"><?php esc_html_e( 'Tags', 'hotel-chain' ); ?></label>
									<?php if ( ! empty( $tags_suggestions ) ) : ?>
										<div class="flex flex-wrap gap-2">
											<?php foreach ( $tags_suggestions as $tag_name ) : ?>
												<label class="inline-flex items-center gap-1 text-sm text-slate-800 border border-slate-300 rounded px-2 py-1 bg-white">
													<input type="checkbox" name="video_tags[]" value="<?php echo esc_attr( $tag_name ); ?>" />
													<span><?php echo esc_html( $tag_name ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<p class="text-xs text-gray-600"><?php esc_html_e( 'No tags defined yet. Add tags in the Video Taxonomy page.', 'hotel-chain' ); ?></p>
									<?php endif; ?>
								</div>
								<div class="mb-4">
									<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_language"><?php esc_html_e( 'Default Language', 'hotel-chain' ); ?></label>
									<input type="text" id="video_language" name="video_language" placeholder="<?php esc_attr_e( 'English', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
							</div>
							<div>
								<div class="mb-3">
									<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_description"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
									<?php
									wp_editor(
										'',
										'video_description',
										array(
											'textarea_name' => 'video_description',
											'textarea_rows' => 10,
											'media_buttons' => false,
											'teeny'     => false,
											'quicktags' => true,
										)
									);
									?>
								</div>
								<div class="mb-3">
									<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_practice_tip"><?php esc_html_e( 'Practice Tip', 'hotel-chain' ); ?></label>
									<textarea id="video_practice_tip" name="video_practice_tip" rows="3" placeholder="<?php esc_attr_e( 'Enter a helpful practice tip for viewers...', 'hotel-chain' ); ?>" class="w-full border border-solid border-slate-300 rounded p-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
								</div>
							</div>
						</div>


						<div class="flex flex-col sm:flex-row gap-3">
							<button type="submit" class="w-full px-6 py-3 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center justify-center gap-2 hover:bg-green-300">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload w-4 h-4" aria-hidden="true">
									<path d="M12 3v12"></path>
									<path d="m17 8-5-5-5 5"></path>
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
								</svg>
								<?php esc_html_e( 'Upload Video', 'hotel-chain' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-video-upload' ) ); ?>" class="w-full sm:w-auto px-6 py-3 bg-gray-200 border-2 border-gray-400 rounded text-gray-900 flex items-center justify-center hover:bg-gray-300">
								<?php esc_html_e( 'Cancel', 'hotel-chain' ); ?>
							</a>
						</div>
					</div>
				</form>
			</div>
		</div>

		<script>
		(function() {
			const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			const uploadNonce = '<?php echo esc_js( wp_create_nonce( 'video_upload_media' ) ); ?>';

			// Video uploader with drag & drop and progress bar
			const videoDropZone = document.getElementById('video-drop-zone');
			const videoFileInput = document.getElementById('video-file');
			const videoId = document.getElementById('video_file_id');
			const videoUrl = document.getElementById('video_file_url');
			const videoPreview = document.getElementById('video-preview');
			const videoPlaceholder = document.getElementById('video-placeholder');
			const videoUploading = document.getElementById('video-uploading');
			const videoPlayer = document.getElementById('video-player');
			const videoProgressBar = document.getElementById('video-progress-bar');
			const videoProgressText = document.getElementById('video-progress-text');
			const removeVideoBtn = document.getElementById('remove-video-btn');

			// Upload file via AJAX
			function uploadVideoFile(file) {
				if (!file || !file.type.startsWith('video/')) {
					alert('<?php echo esc_js( __( 'Please select a valid video file', 'hotel-chain' ) ); ?>');
					return;
				}

				videoPlaceholder.classList.add('hidden');
				videoPreview.classList.add('hidden');
				videoUploading.classList.remove('hidden');
				videoProgressBar.style.width = '0%';
				videoProgressText.textContent = '0%';

				const formData = new FormData();
				formData.append('action', 'video_upload_media');
				formData.append('nonce', uploadNonce);
				formData.append('file', file);
				formData.append('type', 'video');

				const xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxUrl, true);

				xhr.upload.onprogress = function(e) {
					if (e.lengthComputable) {
						const percent = Math.round((e.loaded / e.total) * 100);
						videoProgressBar.style.width = percent + '%';
						videoProgressText.textContent = percent + '%';
					}
				};

				xhr.onload = function() {
					if (xhr.status === 200) {
						try {
							const response = JSON.parse(xhr.responseText);
							if (response.success) {
								if (videoId) videoId.value = response.data.attachment_id;
								if (videoUrl) videoUrl.value = response.data.url;
								if (videoPlayer) videoPlayer.src = response.data.url;
								videoUploading.classList.add('hidden');
								videoPreview.classList.remove('hidden');
							} else {
								alert(response.data || '<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
								videoUploading.classList.add('hidden');
								videoPlaceholder.classList.remove('hidden');
							}
						} catch (e) {
							alert('<?php echo esc_js( __( 'Invalid response', 'hotel-chain' ) ); ?>');
							videoUploading.classList.add('hidden');
							videoPlaceholder.classList.remove('hidden');
						}
					} else {
						alert('<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
						videoUploading.classList.add('hidden');
						videoPlaceholder.classList.remove('hidden');
					}
				};

				xhr.onerror = function() {
					alert('<?php echo esc_js( __( 'Network error', 'hotel-chain' ) ); ?>');
					videoUploading.classList.add('hidden');
					videoPlaceholder.classList.remove('hidden');
				};

				xhr.send(formData);
			}

			// Click to browse
			if (videoDropZone) {
				videoDropZone.addEventListener('click', function() {
					videoFileInput.click();
				});
			}

			// File input change
			if (videoFileInput) {
				videoFileInput.addEventListener('change', function(e) {
					if (e.target.files && e.target.files[0]) {
						uploadVideoFile(e.target.files[0]);
					}
				});
			}

			// Drag and drop
			if (videoDropZone) {
				videoDropZone.addEventListener('dragover', function(e) {
					e.preventDefault();
					e.stopPropagation();
					videoDropZone.style.borderColor = 'rgb(59, 130, 246)';
					videoDropZone.style.backgroundColor = 'rgb(239, 246, 255)';
				});

				videoDropZone.addEventListener('dragleave', function(e) {
					e.preventDefault();
					e.stopPropagation();
					videoDropZone.style.borderColor = 'rgb(196, 196, 196)';
					videoDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
				});

				videoDropZone.addEventListener('drop', function(e) {
					e.preventDefault();
					e.stopPropagation();
					videoDropZone.style.borderColor = 'rgb(196, 196, 196)';
					videoDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
					if (e.dataTransfer.files && e.dataTransfer.files[0]) {
						uploadVideoFile(e.dataTransfer.files[0]);
					}
				});
			}

			// Remove video
			if (removeVideoBtn) {
				removeVideoBtn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					if (videoId && videoId.value) {
						const formData = new FormData();
						formData.append('action', 'video_delete_media');
						formData.append('nonce', uploadNonce);
						formData.append('attachment_id', videoId.value);
						fetch(ajaxUrl, { method: 'POST', body: formData })
							.then(response => response.json())
							.then(data => {
								if (!data.success) {
									console.error('Delete failed:', data.data);
								}
							})
							.catch(error => console.error('Error:', error));
					}
					if (videoId) videoId.value = '';
					if (videoUrl) videoUrl.value = '';
					if (videoPlayer) videoPlayer.removeAttribute('src');
					videoPreview.classList.add('hidden');
					videoPlaceholder.classList.remove('hidden');
				});
			}

			// Thumbnail uploader with drag & drop and progress bar
			const thumbnailDropZone = document.getElementById('thumbnail-drop-zone');
			const thumbnailFileInput = document.getElementById('thumbnail-file');
			const thumbnailId = document.getElementById('video_thumbnail_id');
			const thumbnailUrl = document.getElementById('video_thumbnail_url');
			const thumbnailPreview = document.getElementById('thumbnail-preview');
			const thumbnailPlaceholder = document.getElementById('thumbnail-placeholder');
			const thumbnailUploading = document.getElementById('thumbnail-uploading');
			const thumbnailImg = document.getElementById('thumbnail-img');
			const thumbnailProgressBar = document.getElementById('thumbnail-progress-bar');
			const thumbnailProgressText = document.getElementById('thumbnail-progress-text');
			const removeThumbnailBtn = document.getElementById('remove-thumbnail-btn');

			// Upload file via AJAX
			function uploadThumbnailFile(file) {
				if (!file || !file.type.startsWith('image/')) {
					alert('<?php echo esc_js( __( 'Please select a valid image file', 'hotel-chain' ) ); ?>');
					return;
				}
				if (file.size > 5 * 1024 * 1024) {
					alert('<?php echo esc_js( __( 'Thumbnail file must be less than 5MB', 'hotel-chain' ) ); ?>');
					return;
				}

				thumbnailPlaceholder.classList.add('hidden');
				thumbnailPreview.classList.add('hidden');
				thumbnailUploading.classList.remove('hidden');
				thumbnailProgressBar.style.width = '0%';
				thumbnailProgressText.textContent = '0%';

				const formData = new FormData();
				formData.append('action', 'video_upload_media');
				formData.append('nonce', uploadNonce);
				formData.append('file', file);
				formData.append('type', 'image');

				const xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxUrl, true);

				xhr.upload.onprogress = function(e) {
					if (e.lengthComputable) {
						const percent = Math.round((e.loaded / e.total) * 100);
						thumbnailProgressBar.style.width = percent + '%';
						thumbnailProgressText.textContent = percent + '%';
					}
				};

				xhr.onload = function() {
					if (xhr.status === 200) {
						try {
							const response = JSON.parse(xhr.responseText);
							if (response.success) {
								if (thumbnailId) thumbnailId.value = response.data.attachment_id;
								if (thumbnailUrl) thumbnailUrl.value = response.data.url;
								if (thumbnailImg) thumbnailImg.src = response.data.url;
								thumbnailUploading.classList.add('hidden');
								thumbnailPreview.classList.remove('hidden');
							} else {
								alert(response.data || '<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
								thumbnailUploading.classList.add('hidden');
								thumbnailPlaceholder.classList.remove('hidden');
							}
						} catch (e) {
							alert('<?php echo esc_js( __( 'Invalid response', 'hotel-chain' ) ); ?>');
							thumbnailUploading.classList.add('hidden');
							thumbnailPlaceholder.classList.remove('hidden');
						}
					} else {
						alert('<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
						thumbnailUploading.classList.add('hidden');
						thumbnailPlaceholder.classList.remove('hidden');
					}
				};

				xhr.onerror = function() {
					alert('<?php echo esc_js( __( 'Network error', 'hotel-chain' ) ); ?>');
					thumbnailUploading.classList.add('hidden');
					thumbnailPlaceholder.classList.remove('hidden');
				};

				xhr.send(formData);
			}

			// Click to browse
			if (thumbnailDropZone) {
				thumbnailDropZone.addEventListener('click', function() {
					thumbnailFileInput.click();
				});
			}

			// File input change
			if (thumbnailFileInput) {
				thumbnailFileInput.addEventListener('change', function(e) {
					if (e.target.files && e.target.files[0]) {
						uploadThumbnailFile(e.target.files[0]);
					}
				});
			}

			// Drag and drop
			if (thumbnailDropZone) {
				thumbnailDropZone.addEventListener('dragover', function(e) {
					e.preventDefault();
					e.stopPropagation();
					thumbnailDropZone.style.borderColor = 'rgb(59, 130, 246)';
					thumbnailDropZone.style.backgroundColor = 'rgb(239, 246, 255)';
				});

				thumbnailDropZone.addEventListener('dragleave', function(e) {
					e.preventDefault();
					e.stopPropagation();
					thumbnailDropZone.style.borderColor = 'rgb(196, 196, 196)';
					thumbnailDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
				});

				thumbnailDropZone.addEventListener('drop', function(e) {
					e.preventDefault();
					e.stopPropagation();
					thumbnailDropZone.style.borderColor = 'rgb(196, 196, 196)';
					thumbnailDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
					if (e.dataTransfer.files && e.dataTransfer.files[0]) {
						uploadThumbnailFile(e.dataTransfer.files[0]);
					}
				});
			}

			// Remove thumbnail
			if (removeThumbnailBtn) {
				removeThumbnailBtn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					if (thumbnailId && thumbnailId.value) {
						const formData = new FormData();
						formData.append('action', 'video_delete_media');
						formData.append('nonce', uploadNonce);
						formData.append('attachment_id', thumbnailId.value);
						fetch(ajaxUrl, { method: 'POST', body: formData })
							.then(response => response.json())
							.then(data => {
								if (!data.success) {
									console.error('Delete failed:', data.data);
								}
							})
							.catch(error => console.error('Error:', error));
					}
					if (thumbnailId) thumbnailId.value = '';
					if (thumbnailUrl) thumbnailUrl.value = '';
					if (thumbnailImg) thumbnailImg.removeAttribute('src');
					thumbnailPreview.classList.add('hidden');
					thumbnailPlaceholder.classList.remove('hidden');
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Handle media upload via AJAX.
	 *
	 * @return void
	 */
	public function handle_media_upload(): void {
		check_ajax_referer( 'video_upload_media', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded', 'hotel-chain' ) );
		}

		$file = $_FILES['file'];
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		// Validate file type.
		$allowed_video = array( 'video/mp4', 'video/quicktime', 'video/x-msvideo' );
		$allowed_image = array( 'image/jpeg', 'image/png', 'image/jpg', 'image/svg+xml' );

		if ( 'video' === $type && ! in_array( $file['type'], $allowed_video, true ) ) {
			wp_send_json_error( __( 'Invalid video format. Please upload MP4, MOV or AVI.', 'hotel-chain' ) );
		}

		if ( 'image' === $type && ! in_array( $file['type'], $allowed_image, true ) ) {
			wp_send_json_error( __( 'Invalid image format. Please upload PNG, JPG or SVG.', 'hotel-chain' ) );
		}

		// Use WordPress media handling.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( $upload['error'] );
		}

		// Create attachment.
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		// Generate metadata.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => $upload['url'],
			)
		);
	}

	/**
	 * Handle media delete via AJAX.
	 *
	 * @return void
	 */
	public function handle_media_delete(): void {
		check_ajax_referer( 'video_upload_media', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Invalid attachment ID', 'hotel-chain' ) );
		}

		// Delete from WordPress.
		$deleted = wp_delete_attachment( $attachment_id, true );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'File deleted successfully', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( __( 'Failed to delete file', 'hotel-chain' ) );
		}
	}
}
