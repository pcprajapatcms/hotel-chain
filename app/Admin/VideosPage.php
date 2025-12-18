<?php
/**
 * Admin Videos upload page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\VideoRepository;

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
	}

		/**
		 * Register admin menu entry.
		 *
		 * @return void
		 */
		public function register_menu(): void {
			// Attach under the main Hotel Accounts menu for consistency.
			$parent_slug = 'hotel-chain-accounts';

			add_submenu_page(
				$parent_slug,
				__( 'Upload Videos', 'hotel-chain' ),
				__( 'Upload Videos', 'hotel-chain' ),
				'manage_options',
				'hotel-video-upload',
				array( $this, 'render_page' )
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

		if ( empty( $title ) || empty( $_FILES['video_file']['name'] ?? '' ) ) {
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

		// Handle main video file upload.
		$video_attachment_id = 0;
		if ( ! empty( $_FILES['video_file']['name'] ?? '' ) ) {
			// Upload as an unattached media item (no CPT parent).
			$video_attachment_id = media_handle_upload( 'video_file', 0 );
		}

		// Optional thumbnail upload (do this now so we can persist it together with metadata).
		$thumb_attachment_id = 0;
		$thumb_url           = '';
		if ( ! empty( $_FILES['video_thumbnail']['name'] ?? '' ) ) {
			// Upload thumbnail as unattached media and track its ID/URL only.
			$thumb_attachment_id = media_handle_upload( 'video_thumbnail', 0 );
			if ( ! is_wp_error( $thumb_attachment_id ) && $thumb_attachment_id ) {
				$thumb_url = wp_get_attachment_url( $thumb_attachment_id ) ?: '';
			} else {
				$thumb_attachment_id = 0;
			}
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
			$video_id = $video_repository->create_or_update(
				null,
				array(
					'title'              => $title,
					'description'        => $description,
					'practice_tip'       => $practice_tip,
					'category'           => $category_id ? (string) $category_id : '',
					'tags'               => $tags_raw,
					'thumbnail_id'       => $thumb_attachment_id,
					'thumbnail_url'      => $thumb_url,
					'video_file_id'      => $video_attachment_id,
					'duration_seconds'   => $duration_seconds,
					'duration_label'     => $duration_label,
					'file_size'          => $file_size,
					'file_format'        => $file_format,
					'resolution_width'   => $resolution_width,
					'resolution_height'  => $resolution_height,
					'default_language'   => $language,
					'total_views'        => 0,
					'total_completions'  => 0,
					'avg_completion_rate'=> 0.00,
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
		$raw_categories   = get_option( 'hotel_chain_video_categories', array() );
		$tags_suggestions = get_option( 'hotel_chain_video_tags', array() );

		// Normalise categories so a line like \"Onboarding, Safety\" becomes two options.
		$categories = array();
		if ( is_array( $raw_categories ) ) {
			foreach ( $raw_categories as $line ) {
				$parts = explode( ',', (string) $line );
				foreach ( $parts as $part ) {
					$part = trim( $part );
					if ( '' !== $part ) {
						$categories[] = $part;
					}
				}
			}
		}
		$categories = array_values( array_unique( $categories ) );

		if ( ! is_array( $tags_suggestions ) ) {
			$tags_suggestions = array();
		}
		?>
		<div class="wrap w-8/12 mx-auto">
			<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Upload New Video', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-400 pb-3"><?php esc_html_e( 'Upload training and onboarding videos and organize them by category and tags.', 'hotel-chain' ); ?></p>

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

			<form id="hotel-video-upload-form" class="bg-white rounded p-4 border border-solid border-gray-400 bg-blue-50 mb-6" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'hotel_chain_upload_video' ); ?>
				<input type="hidden" name="action" value="hotel_chain_upload_video" />

				<div class="mb-4 pb-3 border-b border-gray-300 flex items-center justify-between">
					<h3 class="text-lg font-semibold"><?php esc_html_e( 'Upload New Video', 'hotel-chain' ); ?></h3>
					<button type="submit" class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 flex items-center gap-2">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4" aria-hidden="true">
							<path d="M5 12h14"></path>
							<path d="M12 5v14"></path>
						</svg>
						<?php esc_html_e( 'Start Upload', 'hotel-chain' ); ?>
					</button>
				</div>

				<div class="bg-white border border-solid border-gray-400 rounded p-6">
					<div class="mb-6">
						<div class="mb-2 text-gray-700"><?php esc_html_e( 'Video File', 'hotel-chain' ); ?></div>
						<label class="border border-solid border-gray-400 border-dashed rounded-lg p-12 text-center bg-gray-50 hover:bg-gray-100 cursor-pointer block">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload w-16 h-16 text-gray-400 mx-auto mb-4" aria-hidden="true">
								<path d="M12 3v12"></path>
								<path d="m17 8-5-5-5 5"></path>
								<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
							</svg>
							<div class="mb-2"><?php esc_html_e( 'Drag and drop video file here', 'hotel-chain' ); ?></div>
							<div class="text-gray-600 mb-4"><?php esc_html_e( 'or click to browse', 'hotel-chain' ); ?></div>
							<span class="px-6 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 inline-block"><?php esc_html_e( 'Select Video File', 'hotel-chain' ); ?></span>
							<input type="file" name="video_file" class="hidden" accept="video/mp4,video/quicktime,video/x-msvideo" required />
							<div class="text-gray-600 mt-3 text-sm">
								<?php esc_html_e( 'Supported formats: MP4, MOV, AVI • Max size depends on server limits', 'hotel-chain' ); ?>
							</div>
						</label>
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
						</div>
						<div>
							<div class="mb-3">
								<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_description"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
								<textarea id="video_description" name="video_description" rows="4" placeholder="<?php esc_attr_e( '[Text area for video description]', 'hotel-chain' ); ?>" class="w-full border border-solid border-slate-300 rounded p-3 bg-white text-gray-700 min-h-32 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
							</div>
							<div class="mb-3">
								<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_practice_tip"><?php esc_html_e( 'Practice Tip', 'hotel-chain' ); ?></label>
								<textarea id="video_practice_tip" name="video_practice_tip" rows="3" placeholder="<?php esc_attr_e( 'Enter a helpful practice tip for viewers...', 'hotel-chain' ); ?>" class="w-full border border-solid border-slate-300 rounded p-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
							</div>
							<div class="mb-4">
								<label class="mb-1 block text-gray-700 text-sm font-semibold" for="video_language"><?php esc_html_e( 'Default Language', 'hotel-chain' ); ?></label>
								<input type="text" id="video_language" name="video_language" placeholder="<?php esc_attr_e( 'English', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
							</div>
						</div>
					</div>

					<div class="mb-6 pt-6 border-t border-solid border-gray-300">
						<div class="mb-3 text-gray-700 font-semibold text-sm"><?php esc_html_e( 'Video Thumbnail (Optional)', 'hotel-chain' ); ?></div>
						<div class="grid grid-cols-4 gap-4">
							<label class="border border-solid border-gray-400 border-dashed rounded p-4 text-center bg-gray-50 hover:bg-gray-100 cursor-pointer">
								<div class="w-full aspect-video bg-gray-200 border-2 border-gray-300 rounded mb-2 flex items-center justify-center">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload w-8 h-8 text-gray-400" aria-hidden="true">
										<path d="M12 3v12"></path>
										<path d="m17 8-5-5-5 5"></path>
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									</svg>
								</div>
								<div class="text-gray-600 text-sm"><?php esc_html_e( 'Upload thumbnail', 'hotel-chain' ); ?></div>
								<input type="file" name="video_thumbnail" class="hidden" accept="image/*" />
							</label>
						</div>
					</div>

					<div class="flex gap-3">
						<button type="submit" class="flex-1 px-6 py-3 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center justify-center gap-2 hover:bg-green-300">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload w-4 h-4" aria-hidden="true">
								<path d="M12 3v12"></path>
								<path d="m17 8-5-5-5 5"></path>
								<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
							</svg>
							<?php esc_html_e( 'Upload Video', 'hotel-chain' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=video' ) ); ?>" class="px-6 py-3 bg-gray-200 border-2 border-gray-400 rounded text-gray-900 flex items-center justify-center hover:bg-gray-300">
							<?php esc_html_e( 'Cancel', 'hotel-chain' ); ?>
						</a>
					</div>
				</div>
			</form>

			<div id="hotel-video-upload-progress" class="mt-6 hidden">
				<h3 class="text-lg font-semibold mb-3 border-b border-gray-300 pb-2"><?php esc_html_e( 'Upload Progress', 'hotel-chain' ); ?></h3>
				<div class="border border-solid border-gray-300 rounded p-4 bg-white">
					<div class="flex justify-between mb-2 text-sm text-gray-700">
						<span id="hotel-video-upload-filename"></span>
						<span id="hotel-video-upload-percent">0%</span>
					</div>
					<div class="w-full bg-gray-100 rounded h-3 overflow-hidden border border-solid border-gray-300">
						<div id="hotel-video-upload-bar" class="h-3 bg-blue-400" style="width:0%;"></div>
					</div>
					<p id="hotel-video-upload-status" class="mt-2 text-xs text-gray-600"><?php esc_html_e( 'Preparing upload...', 'hotel-chain' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
