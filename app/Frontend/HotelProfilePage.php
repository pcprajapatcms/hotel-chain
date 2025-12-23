<?php
/**
 * Hotel Profile page.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Repositories\HotelRepository;

/**
 * Hotel Profile page for hotel users.
 */
class HotelProfilePage {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_hotel_profile_upload_media', array( $this, 'handle_media_upload' ) );
		add_action( 'wp_ajax_hotel_profile_delete_media', array( $this, 'handle_media_delete' ) );
		add_action( 'wp_ajax_hotel_profile_save', array( $this, 'handle_save' ) );
		add_action( 'init', array( $this, 'add_cors_headers' ) );
	}

	/**
	 * Add CORS headers for local development.
	 *
	 * @return void
	 */
	public function add_cors_headers(): void {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
			if ( strpos( $origin, 'hotel-chain.local' ) !== false ) {
				header( 'Access-Control-Allow-Origin: ' . $origin );
				header( 'Access-Control-Allow-Credentials: true' );
				header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type, X-Requested-With' );
			}
		}
	}

	/**
	 * Handle media upload via AJAX.
	 *
	 * @return void
	 */
	public function handle_media_upload(): void {
		check_ajax_referer( 'hotel_profile_upload', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded', 'hotel-chain' ) );
		}

		$file = $_FILES['file'];
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		// Validate file type.
		$allowed_video = array( 'video/mp4', 'video/webm', 'video/quicktime' );
		$allowed_image = array( 'image/jpeg', 'image/png', 'image/jpg' );

		if ( 'video' === $type && ! in_array( $file['type'], $allowed_video, true ) ) {
			wp_send_json_error( __( 'Invalid video format. Please upload MP4, WebM or MOV.', 'hotel-chain' ) );
		}

		if ( 'image' === $type && ! in_array( $file['type'], $allowed_image, true ) ) {
			wp_send_json_error( __( 'Invalid image format. Please upload PNG or JPG.', 'hotel-chain' ) );
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
		check_ajax_referer( 'hotel_profile_upload', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Invalid attachment ID', 'hotel-chain' ) );
		}

		// Delete from WordPress (and AWS if using offload plugin).
		$deleted = wp_delete_attachment( $attachment_id, true );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'File deleted', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( __( 'Failed to delete file', 'hotel-chain' ) );
		}
	}

	/**
	 * Handle profile save via AJAX.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_ajax_referer( 'hotel_profile_upload', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel            = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_send_json_error( __( 'Hotel not found', 'hotel-chain' ) );
		}

		// Decode existing welcome section so we can preserve values when nothing is changed.
		$existing_welcome = array();
		if ( ! empty( $hotel->welcome_section ) ) {
			$decoded = json_decode( $hotel->welcome_section, true );
			if ( is_array( $decoded ) ) {
				$existing_welcome = $decoded;
			}
		}

		// Helper to fetch a value from POST or fall back to existing welcome_section.
		$get_welcome_field = static function ( string $key, $default = '' ) use ( $existing_welcome ) {
			if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
				return wp_unslash( $_POST[ $key ] );
			}

			if ( isset( $existing_welcome[ $key ] ) ) {
				return $existing_welcome[ $key ];
			}

			return $default;
		};

		// Build welcome section JSON, preserving existing values when POST is empty.
		$welcome_section = array(
			'welcome_video_id'     => absint( $get_welcome_field( 'welcome_video_id', 0 ) ),
			'welcome_thumbnail_id' => absint( $get_welcome_field( 'welcome_thumbnail_id', 0 ) ),
			'welcome_heading'      => sanitize_text_field( (string) $get_welcome_field( 'welcome_heading', '' ) ),
			'welcome_subheading'   => sanitize_text_field( (string) $get_welcome_field( 'welcome_subheading', '' ) ),
			'welcome_description'  => sanitize_textarea_field( (string) $get_welcome_field( 'welcome_description', '' ) ),
			'steps'                => array(),
		);

		// Process steps.
		if ( isset( $_POST['steps'] ) && is_array( $_POST['steps'] ) ) {
			foreach ( $_POST['steps'] as $step ) {
				$welcome_section['steps'][] = array(
					'heading'     => isset( $step['heading'] ) ? sanitize_text_field( wp_unslash( $step['heading'] ) ) : '',
					'description' => isset( $step['description'] ) ? sanitize_textarea_field( wp_unslash( $step['description'] ) ) : '',
				);
			}
		}

		// Update hotel data.
		$update_data = array(
			'hotel_name'      => isset( $_POST['hotel_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hotel_name'] ) ) : $hotel->hotel_name,
			'contact_phone'   => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
			'address'         => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'city'            => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'country'         => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
			'website'         => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
			'welcome_section' => wp_json_encode( $welcome_section ),
		);

		$result = $hotel_repository->update( $hotel->id, $update_data );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Profile saved successfully', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( __( 'Failed to save profile', 'hotel-chain' ) );
		}
	}

	/**
	 * Add the Hotel Profile menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			return;
		}

		add_menu_page(
			__( 'Hotel Profile', 'hotel-chain' ),
			__( 'Hotel Profile', 'hotel-chain' ),
			'read',
			'hotel-profile',
			array( $this, 'render_page' ),
			'dashicons-building',
			25
		);
	}

	/**
	 * Render the profile page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hotel-chain' ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel            = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found.', 'hotel-chain' ) );
		}

		// Decode existing welcome section data for initial form state.
		$welcome_section = array();
		if ( ! empty( $hotel->welcome_section ) ) {
			$decoded = json_decode( $hotel->welcome_section, true );
			if ( is_array( $decoded ) ) {
				$welcome_section = $decoded;
			}
		}

		$welcome_video_id     = isset( $welcome_section['welcome_video_id'] ) ? absint( $welcome_section['welcome_video_id'] ) : 0;
		$welcome_thumbnail_id = isset( $welcome_section['welcome_thumbnail_id'] ) ? absint( $welcome_section['welcome_thumbnail_id'] ) : 0;
		$welcome_video_url    = $welcome_video_id ? wp_get_attachment_url( $welcome_video_id ) : '';
		$welcome_thumb_url    = $welcome_thumbnail_id ? wp_get_attachment_url( $welcome_thumbnail_id ) : '';

		// Enqueue media uploader.
		wp_enqueue_media();
		?>
		<div class="wrap max-w-full">
			<div class="flex-1 overflow-auto p-4 lg:p-8 border border-solid border-gray-400">
				<div class="max-w-7xl mx-auto">
					<div class="space-y-6">
					<div class="mb-6 pb-4 border-b border-solid border-gray-400">
						<h1 class="mb-1"><?php esc_html_e( 'HOTEL – Hotel Profile', 'hotel-chain' ); ?></h1>
						<p><?php esc_html_e( "Customize your hotel's branding and settings", 'hotel-chain' ); ?></p>
					</div>

					<!-- Hotel Information -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Hotel Information', 'hotel-chain' ); ?></h3>
						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Hotel Name', 'hotel-chain' ); ?></label>
									<input type="text" name="hotel_name" value="<?php echo esc_attr( $hotel->hotel_name ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Hotel Code', 'hotel-chain' ); ?></label>
									<input type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" value="<?php echo esc_html( $hotel->hotel_code ); ?>" readonly />
								</div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Contact Email', 'hotel-chain' ); ?></label>
									<input type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" value="<?php echo esc_html( $hotel->contact_email ?: '-' ); ?>" readonly />
								</div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Support Phone', 'hotel-chain' ); ?></label>
									<input type="text" name="contact_phone" value="<?php echo esc_attr( $hotel->contact_phone ?: '' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="<?php esc_attr_e( '+1 (555) 123-4567', 'hotel-chain' ); ?>" />
								</div>
							</div>
							<div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Address', 'hotel-chain' ); ?></label>
									<input type="text" name="address" value="<?php echo esc_attr( $hotel->address ?: '' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="<?php esc_attr_e( '123 Main Street', 'hotel-chain' ); ?>" />
								</div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'City', 'hotel-chain' ); ?></label>
									<input type="text" name="city" value="<?php echo esc_attr( $hotel->city ?: '' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="<?php esc_attr_e( 'New York', 'hotel-chain' ); ?>" />
								</div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Country', 'hotel-chain' ); ?></label>
									<input type="text" name="country" value="<?php echo esc_attr( $hotel->country ?: '' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="<?php esc_attr_e( 'United States', 'hotel-chain' ); ?>" />
								</div>
								<div class="mb-4">
									<label class="block mb-1"><?php esc_html_e( 'Website', 'hotel-chain' ); ?></label>
									<input type="url" name="website" value="<?php echo esc_attr( $hotel->website ?: '' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="https://example.com" />
								</div>
							</div>
						</div>
					</div>

					<!-- Welcome Section Customization -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400"><?php esc_html_e( 'Welcome Section', 'hotel-chain' ); ?></h3>
						
						<!-- Welcome Video -->
						<div class="mb-6">
							<div class="font-medium mb-4"><?php esc_html_e( 'Welcome Video', 'hotel-chain' ); ?></div>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
								<!-- Video Upload -->
								<div>
									<label class="block mb-2 text-gray-700"><?php esc_html_e( 'Video File', 'hotel-chain' ); ?></label>
									<div id="video-drop-zone" class="border border-solid border-gray-400 border-dashed rounded p-6 text-center bg-gray-50 transition-colors cursor-pointer hover:border-blue-400 hover:bg-blue-50">
										<input type="hidden" name="welcome_video_id" id="welcome_video_id" value="<?php echo esc_attr( $welcome_video_id ); ?>" />
										<input type="file" id="welcome-video-file" accept="video/mp4,video/webm,video/quicktime" class="hidden" />
										<div id="welcome-video-preview" class="<?php echo $welcome_video_id ? '' : 'hidden '; ?>mb-4">
											<video id="welcome-video-player" class="w-full rounded" controls style="max-height: 200px;" <?php echo $welcome_video_url ? 'src="' . esc_url( $welcome_video_url ) . '"' : ''; ?>></video>
											<button type="button" id="remove-video-btn" class="mt-2 px-3 py-1 bg-red-200 border-2 border-red-400 rounded text-red-900 text-sm">
												<?php esc_html_e( 'Remove Video', 'hotel-chain' ); ?>
											</button>
										</div>
										<div id="welcome-video-uploading" class="hidden mb-4">
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
										<div id="welcome-video-placeholder" class="<?php echo $welcome_video_id ? 'hidden ' : ''; ?>mb-4">
											<div class="w-16 h-16 mx-auto bg-gray-200 border border-solid border-gray-400 rounded-full flex items-center justify-center mb-3">
												<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polygon points="5 3 19 12 5 21 5 3"></polygon>
												</svg>
											</div>
											<p class="text-gray-700 text-sm font-medium"><?php esc_html_e( 'Drop video here or click to browse', 'hotel-chain' ); ?></p>
											<p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'MP4, WebM or MOV. Max 100MB', 'hotel-chain' ); ?></p>
										</div>
									</div>
								</div>
								<!-- Thumbnail Upload -->
								<div>
									<label class="block mb-2 text-gray-700"><?php esc_html_e( 'Video Thumbnail', 'hotel-chain' ); ?></label>
									<div id="thumbnail-drop-zone" class="border border-solid border-gray-400 border-dashed rounded p-6 text-center bg-gray-50 transition-colors cursor-pointer hover:border-blue-400 hover:bg-blue-50">
										<input type="hidden" name="welcome_thumbnail_id" id="welcome_thumbnail_id" value="<?php echo esc_attr( $welcome_thumbnail_id ); ?>" />
										<input type="file" id="welcome-thumbnail-file" accept="image/png,image/jpeg,image/jpg" class="hidden" />
										<div id="welcome-thumbnail-preview" class="<?php echo $welcome_thumbnail_id ? '' : 'hidden '; ?>mb-4">
											<img id="welcome-thumbnail-img" src="<?php echo esc_url( $welcome_thumb_url ); ?>" alt="Thumbnail" class="w-full rounded object-cover mx-auto" style="max-height: 200px;" />
											<button type="button" id="remove-thumbnail-btn" class="mt-2 px-3 py-1 bg-red-200 border-2 border-red-400 rounded text-red-900 text-sm">
												<?php esc_html_e( 'Remove Thumbnail', 'hotel-chain' ); ?>
											</button>
										</div>
										<div id="welcome-thumbnail-uploading" class="hidden mb-4">
											<div class="w-16 h-16 mx-auto mb-3">
												<svg class="animate-spin w-full h-full text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
													<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
													<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
												</svg>
											</div>
											<p class="text-blue-600 text-sm font-medium"><?php esc_html_e( 'Uploading image...', 'hotel-chain' ); ?></p>
											<div class="w-full bg-gray-200 rounded-full h-2 mt-2">
												<div id="thumbnail-progress-bar" class="bg-blue-500 h-2 rounded-full transition-all" style="width: 0%"></div>
											</div>
											<p id="thumbnail-progress-text" class="text-gray-500 text-xs mt-1">0%</p>
										</div>
										<div id="welcome-thumbnail-placeholder" class="<?php echo $welcome_thumbnail_id ? 'hidden ' : ''; ?>mb-4">
											<div class="w-16 h-16 mx-auto bg-gray-200 border border-solid border-gray-400 rounded flex items-center justify-center mb-3">
												<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
													<circle cx="9" cy="9" r="2"></circle>
													<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
												</svg>
											</div>
											<p class="text-gray-700 text-sm font-medium"><?php esc_html_e( 'Drop image here or click to browse', 'hotel-chain' ); ?></p>
											<p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'PNG or JPG. Recommended 1920x1080', 'hotel-chain' ); ?></p>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Welcome Message -->
						<div class="border-t border-gray-400 pt-6 mb-6">
							<div class="mb-4 font-medium"><?php esc_html_e( 'Welcome Message', 'hotel-chain' ); ?></div>
							<div class="space-y-4">
								<div>
									<label class="block mb-1 text-gray-700"><?php esc_html_e( 'Heading', 'hotel-chain' ); ?></label>
									<input type="text" name="welcome_heading" value="WELCOME TO YOUR SANCTUARY" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
								<div>
									<label class="block mb-1 text-gray-700"><?php esc_html_e( 'Subheading', 'hotel-chain' ); ?></label>
									<input type="text" name="welcome_subheading" value="The Inner Peace Series" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
								<div>
									<label class="block mb-1 text-gray-700"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
									<textarea name="welcome_description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500"><?php esc_html_e( 'Watch this brief introduction to learn how to get the most from your meditation practice', 'hotel-chain' ); ?></textarea>
								</div>
							</div>
						</div>

						<!-- Steps Repeater -->
						<div class="border-t border-gray-400 pt-6">
							<div class="flex items-center justify-between mb-4">
								<div class="font-medium"><?php esc_html_e( 'Steps', 'hotel-chain' ); ?></div>
								<button type="button" id="add-step-btn" class="px-3 py-1 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 text-sm flex items-center gap-1">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M12 5v14"></path>
										<path d="M5 12h14"></path>
									</svg>
									<?php esc_html_e( 'Add Step', 'hotel-chain' ); ?>
								</button>
							</div>
							<div id="steps-container" class="space-y-4">
								<!-- Step 1 -->
								<div class="step-item p-4 border border-solid border-gray-400 rounded bg-gray-50" data-step="1">
									<div class="flex items-center justify-between mb-3">
										<div class="flex items-center gap-2">
											<span class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold bg-gray-600">1</span>
											<span class="font-medium text-gray-700"><?php esc_html_e( 'Step 1', 'hotel-chain' ); ?></span>
										</div>
										<button type="button" class="remove-step-btn text-red-600 hover:text-red-800">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
												<path d="M3 6h18"></path>
												<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
												<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
											</svg>
										</button>
									</div>
									<div class="space-y-3">
										<div>
											<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Heading', 'hotel-chain' ); ?></label>
											<input type="text" name="steps[0][heading]" value="Practice in Order" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
										</div>
										<div>
											<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
											<textarea name="steps[0][description]" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500">Each meditation builds on the previous one for maximum benefit</textarea>
										</div>
									</div>
								</div>
								<!-- Step 2 -->
								<div class="step-item p-4 border border-solid border-gray-400 rounded bg-gray-50" data-step="2">
									<div class="flex items-center justify-between mb-3">
										<div class="flex items-center gap-2">
											<span class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold bg-gray-600">2</span>
											<span class="font-medium text-gray-700"><?php esc_html_e( 'Step 2', 'hotel-chain' ); ?></span>
										</div>
										<button type="button" class="remove-step-btn text-red-600 hover:text-red-800">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
												<path d="M3 6h18"></path>
												<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
												<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
											</svg>
										</button>
									</div>
									<div class="space-y-3">
										<div>
											<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Heading', 'hotel-chain' ); ?></label>
											<input type="text" name="steps[1][heading]" value="Find Your Space" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
										</div>
										<div>
											<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
											<textarea name="steps[1][description]" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500">Choose a quiet place where you won't be disturbed</textarea>
										</div>
									</div>
								</div>
								<!-- Step 3 -->
								<div class="step-item p-4 border border-solid border-gray-400 rounded bg-gray-50" data-step="3">
									<div class="flex items-center justify-between mb-3">
										<div class="flex items-center gap-2">
											<span class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold bg-gray-600">3</span>
											<span class="font-medium text-gray-700"><?php esc_html_e( 'Step 3', 'hotel-chain' ); ?></span>
										</div>
										<button type="button" class="remove-step-btn text-red-600 hover:text-red-800">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
												<path d="M3 6h18"></path>
												<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
												<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
											</svg>
										</button>
									</div>
									<div class="space-y-3">
										<div>
											<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Heading', 'hotel-chain' ); ?></label>
											<input type="text" name="steps[2][heading]" value="No Pressure" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
										</div>
										<div>
											<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
											<textarea name="steps[2][description]" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500">There's no wrong way. Simply show up and be present</textarea>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<script>
					document.addEventListener('DOMContentLoaded', function() {
						// Use relative URL to avoid CORS issues with different ports
						const ajaxUrl = '/wp-admin/admin-ajax.php';
						const uploadNonce = '<?php echo esc_js( wp_create_nonce( 'hotel_profile_upload' ) ); ?>';

						// Video upload elements
						const videoDropZone = document.getElementById('video-drop-zone');
						const videoFileInput = document.getElementById('welcome-video-file');
						const videoInput = document.getElementById('welcome_video_id');
						const videoPreview = document.getElementById('welcome-video-preview');
						const videoPlaceholder = document.getElementById('welcome-video-placeholder');
						const videoUploading = document.getElementById('welcome-video-uploading');
						const videoPlayer = document.getElementById('welcome-video-player');
						const videoProgressBar = document.getElementById('video-progress-bar');
						const videoProgressText = document.getElementById('video-progress-text');
						const removeVideoBtn = document.getElementById('remove-video-btn');

						// Thumbnail upload elements
						const thumbDropZone = document.getElementById('thumbnail-drop-zone');
						const thumbFileInput = document.getElementById('welcome-thumbnail-file');
						const thumbInput = document.getElementById('welcome_thumbnail_id');
						const thumbPreview = document.getElementById('welcome-thumbnail-preview');
						const thumbPlaceholder = document.getElementById('welcome-thumbnail-placeholder');
						const thumbUploading = document.getElementById('welcome-thumbnail-uploading');
						const thumbImg = document.getElementById('welcome-thumbnail-img');
						const thumbProgressBar = document.getElementById('thumbnail-progress-bar');
						const thumbProgressText = document.getElementById('thumbnail-progress-text');
						const removeThumbBtn = document.getElementById('remove-thumbnail-btn');

						// Upload file via AJAX to WordPress media library
						function uploadFile(file, type, progressBar, progressText, onSuccess, onError) {
							const formData = new FormData();
							formData.append('action', 'hotel_profile_upload_media');
							formData.append('nonce', uploadNonce);
							formData.append('file', file);
							formData.append('type', type);

							const xhr = new XMLHttpRequest();
							xhr.open('POST', ajaxUrl, true);

							xhr.upload.onprogress = function(e) {
								if (e.lengthComputable) {
									const percent = Math.round((e.loaded / e.total) * 100);
									progressBar.style.width = percent + '%';
									progressText.textContent = percent + '%';
								}
							};

							xhr.onload = function() {
								if (xhr.status === 200) {
									try {
										const response = JSON.parse(xhr.responseText);
										if (response.success) {
											onSuccess(response.data);
										} else {
											onError(response.data || '<?php esc_html_e( 'Upload failed', 'hotel-chain' ); ?>');
										}
									} catch (e) {
										onError('<?php esc_html_e( 'Invalid response', 'hotel-chain' ); ?>');
									}
								} else {
									onError('<?php esc_html_e( 'Upload failed', 'hotel-chain' ); ?>');
								}
							};

							xhr.onerror = function() {
								onError('<?php esc_html_e( 'Network error', 'hotel-chain' ); ?>');
							};

							xhr.send(formData);
						}

						// Handle video upload
						function handleVideoUpload(file) {
							if (!file || !file.type.startsWith('video/')) {
								alert('<?php esc_html_e( 'Please select a valid video file', 'hotel-chain' ); ?>');
								return;
							}
							if (file.size > 100 * 1024 * 1024) {
								alert('<?php esc_html_e( 'Video file must be less than 100MB', 'hotel-chain' ); ?>');
								return;
							}

							videoPlaceholder.classList.add('hidden');
							videoPreview.classList.add('hidden');
							videoUploading.classList.remove('hidden');
							videoProgressBar.style.width = '0%';

							uploadFile(file, 'video', videoProgressBar, videoProgressText, 
								function(data) {
									videoInput.value = data.attachment_id;
									videoPlayer.src = data.url;
									videoUploading.classList.add('hidden');
									videoPreview.classList.remove('hidden');
								},
								function(error) {
									alert(error);
									videoUploading.classList.add('hidden');
									videoPlaceholder.classList.remove('hidden');
								}
							);
						}

						// Handle thumbnail upload
						function handleThumbnailUpload(file) {
							if (!file || !file.type.startsWith('image/')) {
								alert('<?php esc_html_e( 'Please select a valid image file', 'hotel-chain' ); ?>');
								return;
							}
							if (file.size > 5 * 1024 * 1024) {
								alert('<?php esc_html_e( 'Image file must be less than 5MB', 'hotel-chain' ); ?>');
								return;
							}

							thumbPlaceholder.classList.add('hidden');
							thumbPreview.classList.add('hidden');
							thumbUploading.classList.remove('hidden');
							thumbProgressBar.style.width = '0%';

							uploadFile(file, 'image', thumbProgressBar, thumbProgressText,
								function(data) {
									thumbInput.value = data.attachment_id;
									thumbImg.src = data.url;
									thumbUploading.classList.add('hidden');
									thumbPreview.classList.remove('hidden');
								},
								function(error) {
									alert(error);
									thumbUploading.classList.add('hidden');
									thumbPlaceholder.classList.remove('hidden');
								}
							);
						}

						// Video drop zone events
						videoDropZone.addEventListener('click', function(e) {
							if (!e.target.closest('button')) {
								videoFileInput.click();
							}
						});
						videoFileInput.addEventListener('change', function() {
							if (this.files[0]) handleVideoUpload(this.files[0]);
						});
						videoDropZone.addEventListener('dragover', function(e) {
							e.preventDefault();
							this.classList.add('border-blue-500', 'bg-blue-50');
						});
						videoDropZone.addEventListener('dragleave', function(e) {
							e.preventDefault();
							this.classList.remove('border-blue-500', 'bg-blue-50');
						});
						videoDropZone.addEventListener('drop', function(e) {
							e.preventDefault();
							this.classList.remove('border-blue-500', 'bg-blue-50');
							if (e.dataTransfer.files[0]) handleVideoUpload(e.dataTransfer.files[0]);
						});

						// Thumbnail drop zone events
						thumbDropZone.addEventListener('click', function(e) {
							if (!e.target.closest('button')) {
								thumbFileInput.click();
							}
						});
						thumbFileInput.addEventListener('change', function() {
							if (this.files[0]) handleThumbnailUpload(this.files[0]);
						});
						thumbDropZone.addEventListener('dragover', function(e) {
							e.preventDefault();
							this.classList.add('border-blue-500', 'bg-blue-50');
						});
						thumbDropZone.addEventListener('dragleave', function(e) {
							e.preventDefault();
							this.classList.remove('border-blue-500', 'bg-blue-50');
						});
						thumbDropZone.addEventListener('drop', function(e) {
							e.preventDefault();
							this.classList.remove('border-blue-500', 'bg-blue-50');
							if (e.dataTransfer.files[0]) handleThumbnailUpload(e.dataTransfer.files[0]);
						});

						// Delete attachment from server
						function deleteAttachment(attachmentId, onSuccess) {
							if (!attachmentId) {
								onSuccess();
								return;
							}
							const formData = new FormData();
							formData.append('action', 'hotel_profile_delete_media');
							formData.append('nonce', uploadNonce);
							formData.append('attachment_id', attachmentId);

							fetch(ajaxUrl, { method: 'POST', body: formData })
								.then(response => response.json())
								.then(data => { onSuccess(); })
								.catch(err => { onSuccess(); }); // Still clear UI even if delete fails
						}

						// Remove buttons
						removeVideoBtn.addEventListener('click', function(e) {
							e.stopPropagation();
							const attachmentId = videoInput.value;
							deleteAttachment(attachmentId, function() {
								videoInput.value = '';
								videoPlayer.src = '';
								videoPreview.classList.add('hidden');
								videoPlaceholder.classList.remove('hidden');
							});
						});
						removeThumbBtn.addEventListener('click', function(e) {
							e.stopPropagation();
							const attachmentId = thumbInput.value;
							deleteAttachment(attachmentId, function() {
								thumbInput.value = '';
								thumbImg.src = '';
								thumbPreview.classList.add('hidden');
								thumbPlaceholder.classList.remove('hidden');
							});
						});

						// Steps repeater
						const container = document.getElementById('steps-container');
						const addBtn = document.getElementById('add-step-btn');
						
						function updateStepNumbers() {
							const steps = container.querySelectorAll('.step-item');
							steps.forEach((step, index) => {
								step.dataset.step = index + 1;
								step.querySelector('.w-8').textContent = index + 1;
								step.querySelector('.font-medium').textContent = '<?php esc_html_e( 'Step', 'hotel-chain' ); ?> ' + (index + 1);
								step.querySelectorAll('input, textarea').forEach(input => {
									input.name = input.name.replace(/\[\d+\]/, '[' + index + ']');
								});
							});
						}
						
						addBtn.addEventListener('click', function() {
							const stepCount = container.querySelectorAll('.step-item').length;
							const newStep = document.createElement('div');
							newStep.className = 'step-item p-4 border border-solid border-gray-400 rounded bg-gray-50';
							newStep.dataset.step = stepCount + 1;
							newStep.innerHTML = `
								<div class="flex items-center justify-between mb-3">
									<div class="flex items-center gap-2">
										<span class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold" style="background-color: rgb(61, 61, 68);">${stepCount + 1}</span>
										<span class="font-medium text-gray-700"><?php esc_html_e( 'Step', 'hotel-chain' ); ?> ${stepCount + 1}</span>
									</div>
									<button type="button" class="remove-step-btn text-red-600 hover:text-red-800">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M3 6h18"></path>
											<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
											<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
										</svg>
									</button>
								</div>
								<div class="space-y-3">
									<div>
										<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Heading', 'hotel-chain' ); ?></label>
										<input type="text" name="steps[${stepCount}][heading]" value="" class="w-full rounded p-2 bg-white" style="border: 2px solid rgb(196, 196, 196);" />
									</div>
									<div>
										<label class="block mb-1 text-gray-600 text-sm"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
										<textarea name="steps[${stepCount}][description]" rows="2" class="w-full rounded p-2 bg-white" style="border: 2px solid rgb(196, 196, 196);"></textarea>
									</div>
								</div>
							`;
							container.appendChild(newStep);
						});
						
						container.addEventListener('click', function(e) {
							if (e.target.closest('.remove-step-btn')) {
								const stepItem = e.target.closest('.step-item');
								if (container.querySelectorAll('.step-item').length > 1) {
									stepItem.remove();
									updateStepNumbers();
								}
							}
						});

						// Save Changes button
						const saveBtn = document.getElementById('save-profile-btn');
						const cancelBtn = document.getElementById('cancel-profile-btn');
						
						if (saveBtn) {
							saveBtn.addEventListener('click', function(e) {
								e.preventDefault();
								saveBtn.disabled = true;
								saveBtn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><?php esc_html_e( 'Saving...', 'hotel-chain' ); ?>';

								const formData = new FormData();
								formData.append('action', 'hotel_profile_save');
								formData.append('nonce', uploadNonce);
								
								// Hotel info
								formData.append('hotel_name', document.querySelector('input[name="hotel_name"]')?.value || '');
								formData.append('contact_phone', document.querySelector('input[name="contact_phone"]')?.value || '');
								formData.append('address', document.querySelector('input[name="address"]')?.value || '');
								formData.append('city', document.querySelector('input[name="city"]')?.value || '');
								formData.append('country', document.querySelector('input[name="country"]')?.value || '');
								formData.append('website', document.querySelector('input[name="website"]')?.value || '');
								
								// Welcome section
								formData.append('welcome_video_id', videoInput.value || '');
								formData.append('welcome_thumbnail_id', thumbInput.value || '');
								formData.append('welcome_heading', document.querySelector('input[name="welcome_heading"]')?.value || '');
								formData.append('welcome_subheading', document.querySelector('input[name="welcome_subheading"]')?.value || '');
								formData.append('welcome_description', document.querySelector('textarea[name="welcome_description"]')?.value || '');
								
								// Steps
								const stepItems = document.querySelectorAll('.step-item');
								stepItems.forEach((step, index) => {
									const heading = step.querySelector('input[name^="steps"]')?.value || '';
									const description = step.querySelector('textarea[name^="steps"]')?.value || '';
									formData.append('steps[' + index + '][heading]', heading);
									formData.append('steps[' + index + '][description]', description);
								});

								fetch(ajaxUrl, { method: 'POST', body: formData })
									.then(response => response.json())
									.then(data => {
										saveBtn.disabled = false;
										saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path><path d="M7 3v4a1 1 0 0 0 1 1h7"></path></svg><?php esc_html_e( 'Save Changes', 'hotel-chain' ); ?>';
										if (data.success) {
											alert('<?php esc_html_e( 'Profile saved successfully!', 'hotel-chain' ); ?>');
										} else {
											alert(data.data || '<?php esc_html_e( 'Failed to save profile', 'hotel-chain' ); ?>');
										}
									})
									.catch(err => {
										saveBtn.disabled = false;
										saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path><path d="M7 3v4a1 1 0 0 0 1 1h7"></path></svg><?php esc_html_e( 'Save Changes', 'hotel-chain' ); ?>';
										alert('<?php esc_html_e( 'Network error. Please try again.', 'hotel-chain' ); ?>');
									});
							});
						}

						if (cancelBtn) {
							cancelBtn.addEventListener('click', function(e) {
								e.preventDefault();
								window.location.reload();
							});
						}
					});
					</script>

					<!-- Guest Portal Settings -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400"><?php esc_html_e( 'Guest Portal Settings', 'hotel-chain' ); ?></h3>
						<div class="space-y-4">
							<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
								<div>
									<div class="mb-1"><?php esc_html_e( 'Default Access Duration', 'hotel-chain' ); ?></div>
									<div class="text-gray-600"><?php esc_html_e( 'How long guests have access after approval', 'hotel-chain' ); ?></div>
								</div>
								<div class="border border-solid border-gray-400 rounded px-4 py-2 bg-white"><?php esc_html_e( '30 days', 'hotel-chain' ); ?></div>
							</div>
							<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
								<div>
									<div class="mb-1"><?php esc_html_e( 'Email Notifications', 'hotel-chain' ); ?></div>
									<div class="text-gray-600"><?php esc_html_e( 'Receive alerts for new access requests', 'hotel-chain' ); ?></div>
								</div>
								<div class="w-12 h-6 bg-green-500 border-2 border-green-600 rounded-full relative">
									<div class="w-5 h-5 bg-white border-2 border-green-600 rounded-full absolute top-0 right-0"></div>
								</div>
							</div>
							<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
								<div>
									<div class="mb-1"><?php esc_html_e( 'Guest Analytics Tracking', 'hotel-chain' ); ?></div>
									<div class="text-gray-600"><?php esc_html_e( 'Track detailed viewing analytics for guests', 'hotel-chain' ); ?></div>
								</div>
								<div class="w-12 h-6 bg-green-500 border-2 border-green-600 rounded-full relative">
									<div class="w-5 h-5 bg-white border-2 border-green-600 rounded-full absolute top-0 right-0"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Notifications & Alerts -->
					<div class="bg-white rounded p-4 hidden border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Notifications & Alerts', 'hotel-chain' ); ?></h3>
						<div class="space-y-3">
							<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
								<div>
									<div class="mb-1"><?php esc_html_e( 'Expiry Alerts', 'hotel-chain' ); ?></div>
									<div class="text-gray-600"><?php esc_html_e( 'Get notified before guest access expires', 'hotel-chain' ); ?></div>
								</div>
								<div class="w-12 h-6 bg-green-500 border-2 border-green-600 rounded-full relative">
									<div class="w-5 h-5 bg-white border-2 border-green-600 rounded-full absolute top-0 right-0"></div>
								</div>
							</div>
							<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
								<div>
									<div class="mb-1"><?php esc_html_e( 'Alert Timing', 'hotel-chain' ); ?></div>
									<div class="text-gray-600"><?php esc_html_e( 'When to send expiry notifications', 'hotel-chain' ); ?></div>
								</div>
								<div class="border border-solid border-gray-400 rounded px-4 py-2 bg-white"><?php esc_html_e( '3 days before', 'hotel-chain' ); ?></div>
							</div>
						</div>
					</div>

					<!-- Action Buttons -->
					<div class="rounded p-4 bg-gray-50">
						<div class="flex items-center justify-between">
							<div class="flex gap-3">
								<button id="save-profile-btn" type="button" class="flex-1 px-6 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center gap-2">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
										<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
										<path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
										<path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
									</svg>
									<?php esc_html_e( 'Save Changes', 'hotel-chain' ); ?>
								</button>
								<button id="cancel-profile-btn" type="button" class="px-6 py-2 bg-gray-200 border-2 border-gray-400 rounded text-gray-900"><?php esc_html_e( 'Cancel', 'hotel-chain' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
