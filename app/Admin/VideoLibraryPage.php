<?php
/**
 * Admin Video Library page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Database\Schema;

/**
 * Render Video library admin page.
 */
class VideoLibraryPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_export_videos', array( $this, 'handle_export' ) );
		add_action( 'admin_post_hotel_chain_update_video', array( $this, 'handle_update' ) );
		add_action( 'admin_post_hotel_chain_delete_video', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_hotel_chain_unassign_video', array( $this, 'handle_unassign' ) );
		add_action( 'admin_post_hotel_chain_assign_video', array( $this, 'handle_assign' ) );
		add_action( 'wp_ajax_hotel_chain_get_video_detail', array( $this, 'ajax_get_video_detail' ) );
		add_action( 'wp_ajax_hotel_chain_ajax_assign_video', array( $this, 'ajax_assign_video' ) );
		add_action( 'wp_ajax_hotel_chain_ajax_unassign_video', array( $this, 'ajax_unassign_video' ) );
	}

	/**
	 * Register submenu under Hotel Accounts.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'hotel-chain-accounts',
			__( 'System Video Library', 'hotel-chain' ),
			__( 'System Library', 'hotel-chain' ),
			'manage_options',
			'hotel-video-library',
			array( $this, 'render_page' )
		);
	}

	/**
	 * AJAX handler to get video detail panel HTML.
	 *
	 * @return void
	 */
	public function ajax_get_video_detail(): void {
		check_ajax_referer( 'hotel_chain_video_library', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		$selected_cat = isset( $_POST['video_cat'] ) ? sanitize_text_field( wp_unslash( $_POST['video_cat'] ) ) : '';

		if ( ! $video_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid video ID.', 'hotel-chain' ) ) );
		}

		$video_repository = new VideoRepository();
		$assignment_repository = new HotelVideoAssignmentRepository();
		$hotel_repository = new HotelRepository();

		$video = $video_repository->get_by_video_id( $video_id );

		if ( ! $video ) {
			wp_send_json_error( array( 'message' => __( 'Video not found.', 'hotel-chain' ) ) );
		}

		// Build data for the panel.
		$title = $video->title;
		$description = $video->description;
		$category_label = $video->category ?: __( 'Uncategorized', 'hotel-chain' );

		$tag_names = array();
		if ( ! empty( $video->tags ) ) {
			$tag_names = array_filter( array_map( 'trim', explode( ',', $video->tags ) ) );
		}

		$video_attachment_id = (int) $video->video_file_id;

		$thumbnail_url = '';
		if ( $video->thumbnail_id ) {
			$thumbnail_url = wp_get_attachment_image_url( $video->thumbnail_id, 'medium' );
		} elseif ( $video->thumbnail_url ) {
			$thumbnail_url = $video->thumbnail_url;
		}

		$duration_label = $video->duration_label ?: '';

		$file_size_label = '';
		if ( $video->file_size ) {
			$file_size_label = round( $video->file_size / ( 1024 * 1024 ) ) . ' MB';
		}

		$format_label = $video->file_format ?: '';

		$resolution_label = '';
		if ( $video->resolution_width && $video->resolution_height ) {
			$resolution_label = $video->resolution_width . 'x' . $video->resolution_height;
		}

		$uploaded_label = $video->created_at ? date_i18n( 'M j, Y', strtotime( $video->created_at ) ) : '';

		$hotel_count = $assignment_repository->get_video_assignment_count( $video->video_id );
		$total_views = (int) $video->total_views;
		$avg_completion = $video->avg_completion_rate ? number_format( $video->avg_completion_rate, 1 ) . '%' : '0%';

		// Categories and tags for form.
		$categories = get_option( 'hotel_chain_video_categories', array() );
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		$normalised_categories = array();
		foreach ( $categories as $line ) {
			$parts = explode( ',', (string) $line );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$normalised_categories[] = $part;
				}
			}
		}
		$normalised_categories = array_values( array_unique( $normalised_categories ) );

		$tags_suggestions = get_option( 'hotel_chain_video_tags', array() );
		if ( ! is_array( $tags_suggestions ) ) {
			$tags_suggestions = array();
		}

		// Hotels for assignment table.
		$all_hotels = $hotel_repository->get_all( array( 'status' => 'active', 'limit' => -1 ) );
		$assigned_hotels = $assignment_repository->get_video_hotels( $video->video_id, array( 'status' => '' ) );
		$assigned_map = array();
		foreach ( $assigned_hotels as $assignment ) {
			$assigned_map[ $assignment->hotel_id ] = $assignment;
		}

		ob_start();
		$this->render_video_detail_panel(
			$video,
			$selected_cat,
			$thumbnail_url,
			$duration_label,
			$file_size_label,
			$format_label,
			$resolution_label,
			$uploaded_label,
			$hotel_count,
			$total_views,
			$avg_completion,
			$normalised_categories,
			$tags_suggestions,
			$tag_names,
			$video_attachment_id,
			$all_hotels,
			$assigned_map
		);
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX handler to assign video to hotel.
	 *
	 * @return void
	 */
	public function ajax_assign_video(): void {
		check_ajax_referer( 'hotel_chain_video_library', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;
		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;

		if ( ! $hotel_id || ! $video_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$assignment_repository = new HotelVideoAssignmentRepository();
		$result = $assignment_repository->assign( $hotel_id, $video_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Video assigned successfully.', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to assign video.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * AJAX handler to unassign video from hotel.
	 *
	 * @return void
	 */
	public function ajax_unassign_video(): void {
		check_ajax_referer( 'hotel_chain_video_library', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;

		if ( ! $assignment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$assignment_repository = new HotelVideoAssignmentRepository();
		$result = $assignment_repository->delete( $assignment_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Video unassigned successfully.', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to unassign video.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Render video detail panel (used by AJAX and direct render).
	 *
	 * @param object $video                Video object.
	 * @param string $selected_cat         Selected category filter.
	 * @param string $thumbnail_url        Thumbnail URL.
	 * @param string $duration_label       Duration label.
	 * @param string $file_size_label      File size label.
	 * @param string $format_label         Format label.
	 * @param string $resolution_label     Resolution label.
	 * @param string $uploaded_label       Uploaded date label.
	 * @param int    $hotel_count          Number of assigned hotels.
	 * @param int    $total_views          Total views.
	 * @param string $avg_completion       Average completion rate.
	 * @param array  $normalised_categories Categories list.
	 * @param array  $tags_suggestions     Tags suggestions.
	 * @param array  $tag_names            Current video tags.
	 * @param int    $video_attachment_id  Video attachment ID.
	 * @param array  $all_hotels           All hotels.
	 * @param array  $assigned_map         Map of assigned hotels.
	 * @return void
	 */
	private function render_video_detail_panel(
		$video,
		$selected_cat,
		$thumbnail_url,
		$duration_label,
		$file_size_label,
		$format_label,
		$resolution_label,
		$uploaded_label,
		$hotel_count,
		$total_views,
		$avg_completion,
		$normalised_categories,
		$tags_suggestions,
		$tag_names,
		$video_attachment_id,
		$all_hotels,
		$assigned_map
	): void {
		$title = $video->title;
		$description = $video->description;
		?>
		<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
			<div class="mb-4 pb-3 border-b border-solid border-gray-300">
				<h3 class="text-lg font-semibold"><?php esc_html_e( 'Video Detail & Edit', 'hotel-chain' ); ?></h3>
			</div>
			<div class="bg-white border border-solid border-gray-300 rounded p-6">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-4" enctype="multipart/form-data">
					<?php wp_nonce_field( 'hotel_chain_update_video' ); ?>
					<input type="hidden" name="action" value="hotel_chain_update_video" />
					<input type="hidden" name="video_id" value="<?php echo esc_attr( $video->video_id ); ?>" />
					<input type="hidden" name="video_cat" value="<?php echo esc_attr( $selected_cat ); ?>" />
					<input type="file" name="replace_video_file" accept="video/*" class="hidden" data-hotel-replace-input="1" />
					<div class="grid grid-cols-3 gap-6">
						<div class="col-span-1">
							<div class="bg-gray-200 border border-solid border-gray-300 rounded h-48 flex items-center justify-center mb-4 overflow-hidden relative">
								<?php if ( $video_attachment_id ) : ?>
									<?php if ( $thumbnail_url ) : ?>
										<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" class="w-full h-full object-cover" data-hotel-video-poster="<?php echo esc_attr( $video->video_id ); ?>" />
									<?php else : ?>
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-16 h-16 text-gray-400" aria-hidden="true" data-hotel-video-poster="<?php echo esc_attr( $video->video_id ); ?>">
											<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
											<rect x="2" y="6" width="14" height="12" rx="2"></rect>
										</svg>
									<?php endif; ?>
									<video id="hotel-video-preview-<?php echo esc_attr( $video->video_id ); ?>" class="w-full h-full object-cover rounded hidden" preload="metadata" playsinline>
										<source src="<?php echo esc_url( wp_get_attachment_url( $video_attachment_id ) ); ?>" type="video/mp4" />
									</video>
								<?php else : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-16 h-16 text-gray-400" aria-hidden="true">
										<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
										<rect x="2" y="6" width="14" height="12" rx="2"></rect>
									</svg>
								<?php endif; ?>
							</div>
							<div class="space-y-2 mb-4 text-sm">
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Duration:', 'hotel-chain' ); ?></span>
									<span><?php echo esc_html( $duration_label ?: '-' ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'File Size:', 'hotel-chain' ); ?></span>
									<span><?php echo esc_html( $file_size_label ?: '-' ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Format:', 'hotel-chain' ); ?></span>
									<span><?php echo esc_html( $format_label ?: '-' ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Resolution:', 'hotel-chain' ); ?></span>
									<span><?php echo esc_html( $resolution_label ?: '-' ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Uploaded:', 'hotel-chain' ); ?></span>
									<span><?php echo esc_html( $uploaded_label ); ?></span>
								</div>
							</div>
							<?php if ( $video_attachment_id ) : ?>
								<button type="button" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-200 border border-solid border-blue-400 rounded text-blue-900" data-hotel-video-play="1" data-video-preview-id="hotel-video-preview-<?php echo esc_attr( $video->video_id ); ?>" data-video-play-label="<?php esc_attr_e( 'Play Preview', 'hotel-chain' ); ?>" data-video-stop-label="<?php esc_attr_e( 'Stop Preview', 'hotel-chain' ); ?>">
									<?php esc_html_e( 'Play Preview', 'hotel-chain' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="w-full px-4 py-2 bg-blue-100 border border-solid border-blue-200 rounded text-blue-400 cursor-not-allowed">
									<?php esc_html_e( 'No Video File', 'hotel-chain' ); ?>
								</button>
							<?php endif; ?>
							<div class="mt-3 text-xs text-blue-900 bg-blue-50 border border-solid border-blue-200 rounded px-3 py-2 hidden" data-hotel-replace-label="1"></div>
						</div>
						<div class="col-span-2">
							<div class="mb-4 mt-0">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_title_inline"><?php esc_html_e( 'Video Title', 'hotel-chain' ); ?></label>
								<input type="text" id="video_title_inline" name="video_title" value="<?php echo esc_attr( $title ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
							</div>
							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_category_inline"><?php esc_html_e( 'Category', 'hotel-chain' ); ?></label>
								<select id="video_category_inline" name="video_category" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500">
									<option value=""><?php esc_html_e( 'Uncategorized', 'hotel-chain' ); ?></option>
									<?php foreach ( $normalised_categories as $cat_name ) : ?>
										<option value="<?php echo esc_attr( $cat_name ); ?>" <?php selected( $video->category, $cat_name ); ?>>
											<?php echo esc_html( $cat_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_description_inline"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
								<textarea id="video_description_inline" name="video_description" rows="4" class="w-full border border-solid border-slate-300 rounded p-3 bg-white min-h-20 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-500"><?php echo esc_textarea( $description ); ?></textarea>
							</div>
							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_practice_tip_inline"><?php esc_html_e( 'Practice Tip', 'hotel-chain' ); ?></label>
								<textarea id="video_practice_tip_inline" name="video_practice_tip" rows="3" class="w-full border border-solid border-slate-300 rounded p-3 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-500"><?php echo esc_textarea( $video->practice_tip ?? '' ); ?></textarea>
							</div>
							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_language_inline"><?php esc_html_e( 'Default Language', 'hotel-chain' ); ?></label>
								<input type="text" id="video_language_inline" name="video_language" value="<?php echo esc_attr( $video->default_language ?: '' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
							</div>
							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block"><?php esc_html_e( 'Tags', 'hotel-chain' ); ?></label>
								<?php if ( ! empty( $tags_suggestions ) ) : ?>
									<div class="flex flex-wrap gap-2">
										<?php foreach ( $tags_suggestions as $tag_name ) : ?>
											<label class="inline-flex items-center gap-1 text-sm text-slate-800 border border-slate-300 rounded px-2 py-1 bg-white">
												<input type="checkbox" name="video_tags[]" value="<?php echo esc_attr( $tag_name ); ?>" <?php checked( in_array( $tag_name, $tag_names, true ) ); ?> />
												<span><?php echo esc_html( $tag_name ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<p class="text-xs text-gray-600"><?php esc_html_e( 'No tags defined yet. Add tags in the Video Taxonomy page.', 'hotel-chain' ); ?></p>
								<?php endif; ?>
							</div>

							<div class="grid grid-cols-2 gap-3 pt-2">
								<button type="submit" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900">
									<?php esc_html_e( 'Save Changes', 'hotel-chain' ); ?>
								</button>
								<a href="#hotel-assignments-section" class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 text-center">
									<?php esc_html_e( 'View Assignments', 'hotel-chain' ); ?>
								</a>
								<button type="button" class="px-4 py-2 bg-purple-200 border-2 border-purple-400 rounded text-purple-900" data-hotel-replace-button="1">
									<?php esc_html_e( 'Replace Video', 'hotel-chain' ); ?>
								</button>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hotel_chain_delete_video', 'video_id' => $video->video_id, 'video_cat' => $selected_cat ), admin_url( 'admin-post.php' ) ), 'hotel_chain_delete_video' ) ); ?>" class="px-4 py-2 bg-red-200 border-2 border-red-400 rounded text-red-900 text-center" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this video?', 'hotel-chain' ) ); ?>');">
									<?php esc_html_e( 'Delete Video', 'hotel-chain' ); ?>
								</a>
							</div>
							<div class="mb-6 p-4 bg-gray-50 border-2 border-gray-300 rounded mt-4">
								<div class="mb-2 text-sm font-semibold text-gray-800"><?php esc_html_e( 'Assignment Statistics:', 'hotel-chain' ); ?></div>
								<div class="grid grid-cols-3 gap-4 text-sm">
									<div class="text-center">
										<div class="text-gray-600"><?php esc_html_e( 'Assigned To', 'hotel-chain' ); ?></div>
										<div class="text-gray-900">
											<?php
											printf(
												/* translators: %d: number of hotels. */
												esc_html__( '%d hotels', 'hotel-chain' ),
												$hotel_count
											);
											?>
										</div>
									</div>
									<div class="text-center">
										<div class="text-gray-600"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
										<div class="text-gray-900"><?php echo esc_html( number_format_i18n( $total_views ) ); ?></div>
									</div>
									<div class="text-center">
										<div class="text-gray-600"><?php esc_html_e( 'Avg. Completion', 'hotel-chain' ); ?></div>
										<div class="text-gray-900"><?php echo esc_html( $avg_completion ); ?></div>
									</div>
								</div>
							</div>
							
						</div>
					</div>
				</form>
			</div>
		</div>

		<div id="hotel-assignments-section" class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
			<div class="mb-4 pb-3 border-b border-solid border-gray-300">
				<h3 class="text-lg font-semibold"><?php esc_html_e( 'Hotel Assignments', 'hotel-chain' ); ?></h3>
			</div>
			<?php if ( empty( $all_hotels ) ) : ?>
				<p class="text-gray-600 py-4 text-center"><?php esc_html_e( 'No hotels found.', 'hotel-chain' ); ?></p>
			<?php else : ?>
			<table class="w-full">
				<thead>
					<tr class="border-b border-solid border-gray-200">
						<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Hotel', 'hotel-chain' ); ?></th>
						<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></th>
						<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Hotel Status', 'hotel-chain' ); ?></th>
						<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Assigned', 'hotel-chain' ); ?></th>
						<th class="text-right py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Actions', 'hotel-chain' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_hotels as $hotel ) : ?>
						<?php
						$assignment = isset( $assigned_map[ $hotel->id ] ) ? $assigned_map[ $hotel->id ] : null;
						$is_assigned = $assignment && in_array( $assignment->status, array( 'active', 'pending' ), true );
						?>
						<tr class="border-b border-solid border-gray-100 hover:bg-gray-50">
							<td class="py-3 px-2">
								<div class="font-medium text-gray-900"><?php echo esc_html( $hotel->hotel_name ); ?></div>
								<div class="text-xs text-gray-500"><?php echo esc_html( $hotel->hotel_code ); ?></div>
							</td>
							<td class="py-3 px-2">
								<?php if ( $assignment ) : ?>
									<?php if ( 'active' === $assignment->status ) : ?>
										<span class="inline-block px-2 py-1 bg-green-100 text-green-800 text-xs rounded"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></span>
									<?php elseif ( 'pending' === $assignment->status ) : ?>
										<span class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded"><?php esc_html_e( 'Pending', 'hotel-chain' ); ?></span>
									<?php else : ?>
										<span class="inline-block px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded"><?php esc_html_e( 'Inactive', 'hotel-chain' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="inline-block px-2 py-1 bg-gray-100 text-gray-500 text-xs rounded"><?php esc_html_e( 'Not Assigned', 'hotel-chain' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="py-3 px-2">
								<?php if ( $assignment ) : ?>
									<?php
									$status_by_hotel = isset( $assignment->status_by_hotel ) ? $assignment->status_by_hotel : 'active';
									if ( 'active' === $status_by_hotel ) :
									?>
										<span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></span>
									<?php else : ?>
										<span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded"><?php esc_html_e( 'Inactive', 'hotel-chain' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="text-gray-400">—</span>
								<?php endif; ?>
							</td>
							<td class="py-3 px-2 text-gray-600 text-sm">
								<?php if ( $assignment ) : ?>
									<?php echo esc_html( date_i18n( 'M j, Y', strtotime( $assignment->assigned_at ) ) ); ?>
								<?php else : ?>
									<span class="text-gray-400">—</span>
								<?php endif; ?>
							</td>
							<td class="py-3 px-2 text-right">
								<?php if ( $is_assigned ) : ?>
									<button type="button" class="ajax-unassign-btn inline-block px-3 py-1 bg-red-200 border border-solid border-red-400 rounded text-red-900 text-sm hover:bg-red-300" data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>" data-video-id="<?php echo esc_attr( $video->video_id ); ?>">
										<?php esc_html_e( 'Unassign', 'hotel-chain' ); ?>
									</button>
								<?php else : ?>
									<button type="button" class="ajax-assign-btn inline-block px-3 py-1 bg-green-200 border border-solid border-green-400 rounded text-green-900 text-sm hover:bg-green-300" data-hotel-id="<?php echo esc_attr( $hotel->id ); ?>" data-video-id="<?php echo esc_attr( $video->video_id ); ?>">
										<?php esc_html_e( 'Assign', 'hotel-chain' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Export videos list as CSV.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_export_videos' );

		$selected_cat = isset( $_GET['video_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['video_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$video_repository = new VideoRepository();
		$videos = $video_repository->get_all(
			array(
				'category' => $selected_cat,
				'limit'    => -1,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=video-library-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Could not open output stream.', 'hotel-chain' ) );
		}

		fputcsv(
			$output,
			array(
				'ID',
				'Title',
				'Category',
				'Tags',
				'Duration',
				'Default Language',
				'Video URL',
				'Uploaded',
			)
		);

		foreach ( $videos as $video ) {
			$video_url = home_url( '/videos/' . $video->slug . '/' );
			$uploaded_date = $video->created_at ? date( 'Y-m-d', strtotime( $video->created_at ) ) : '';

			fputcsv(
				$output,
				array(
					$video->video_id,
					$video->title,
					$video->category ?: '',
					$video->tags ?: '',
					$video->duration_label ?: '',
					$video->default_language ?: '',
					$video_url,
					$uploaded_date,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle assign video to hotel.
	 *
	 * @return void
	 */
	public function handle_assign(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_assign_video' );

		$hotel_id = isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0;
		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0;
		$selected_cat = isset( $_GET['video_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['video_cat'] ) ) : '';

		if ( $hotel_id && $video_id ) {
			$assignment_repo = new HotelVideoAssignmentRepository();
			$assignment_repo->assign( $hotel_id, $video_id, get_current_user_id() );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'hotel-video-library',
					'video_id'  => $video_id,
					'video_cat' => $selected_cat,
					'assigned'  => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle unassign video from hotel.
	 *
	 * @return void
	 */
	public function handle_unassign(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_unassign_video' );

		$assignment_id = isset( $_GET['assignment_id'] ) ? absint( $_GET['assignment_id'] ) : 0;
		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0;
		$selected_cat = isset( $_GET['video_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['video_cat'] ) ) : '';

		if ( $assignment_id ) {
			$assignment_repo = new HotelVideoAssignmentRepository();
			$assignment_repo->delete( $assignment_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'hotel-video-library',
					'video_id'   => $video_id,
					'video_cat'  => $selected_cat,
					'unassigned' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle video deletion.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_delete_video' );

		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_cat = isset( $_GET['video_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['video_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $video_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'hotel-video-library',
						'video_cat' => $selected_cat,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$video_repository = new VideoRepository();
		$video = $video_repository->get_by_video_id( $video_id );

		if ( ! $video ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'hotel-video-library',
						'video_cat' => $selected_cat,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		global $wpdb;
		$table = Schema::get_table_name( 'video_metadata' );

		// Delete from custom table.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $table, array( 'video_id' => $video_id ), array( '%d' ) );

		// Also delete assignments.
		$assignment_repo = new HotelVideoAssignmentRepository();
		$assignments = $assignment_repo->get_video_hotels( $video_id );
		foreach ( $assignments as $assignment ) {
			$assignment_repo->delete( $assignment->id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'hotel-video-library',
					'video_cat' => $selected_cat,
					'deleted'   => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle inline video updates from the library detail panel.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_update_video' );

		$video_id     = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_cat = isset( $_POST['video_cat'] ) ? sanitize_text_field( wp_unslash( $_POST['video_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $video_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'hotel-video-library',
						'video_cat' => $selected_cat,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$video_repository = new VideoRepository();
		$video = $video_repository->get_by_video_id( $video_id );

		if ( ! $video ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'hotel-video-library',
						'video_cat' => $selected_cat,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$title        = isset( $_POST['video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['video_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$description  = isset( $_POST['video_description'] ) ? wp_kses_post( wp_unslash( $_POST['video_description'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$practice_tip = isset( $_POST['video_practice_tip'] ) ? wp_kses_post( wp_unslash( $_POST['video_practice_tip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$category     = isset( $_POST['video_category'] ) ? sanitize_text_field( wp_unslash( $_POST['video_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

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

		$update_data = array(
			'title'            => $title,
			'description'      => $description,
			'practice_tip'     => $practice_tip,
			'category'         => $category,
			'tags'             => $tags_raw,
			'default_language' => $language,
		);

		$video_replaced = 0;

		// Optional: replace main video file if a new one was uploaded.
		if ( ! empty( $_FILES['replace_video_file']['name'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslashed
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$video_attachment_id = media_handle_upload( 'replace_video_file', 0 );

			if ( ! is_wp_error( $video_attachment_id ) && $video_attachment_id ) {
				$update_data['video_file_id'] = $video_attachment_id;
				$video_replaced = 1;

				// Recalculate duration label from attachment metadata.
				$duration_label = '';
				$metadata       = wp_get_attachment_metadata( $video_attachment_id );
				if ( ! empty( $metadata['length_formatted'] ) ) {
					$duration_label = $metadata['length_formatted'];
				} else {
					$file = get_attached_file( $video_attachment_id );
					if ( $file && function_exists( 'wp_read_video_metadata' ) ) {
						$video_meta = wp_read_video_metadata( $file );
						if ( ! empty( $video_meta['length_formatted'] ) ) {
							$duration_label = $video_meta['length_formatted'];
						}
					}
				}

				if ( $duration_label ) {
					$update_data['duration_label'] = $duration_label;
				}

				// Also update technical metadata.
				if ( isset( $metadata['length'] ) ) {
					$update_data['duration_seconds'] = (int) $metadata['length'];
				}
				if ( isset( $metadata['filesize'] ) ) {
					$update_data['file_size'] = (int) $metadata['filesize'];
				}
				if ( isset( $metadata['width'] ) ) {
					$update_data['resolution_width'] = (int) $metadata['width'];
				}
				if ( isset( $metadata['height'] ) ) {
					$update_data['resolution_height'] = (int) $metadata['height'];
				}
				$file = get_attached_file( $video_attachment_id );
				if ( $file ) {
					$ext = pathinfo( $file, PATHINFO_EXTENSION );
					if ( $ext ) {
						$update_data['file_format'] = strtoupper( $ext );
					}
				}
			}
		}

		$video_repository->update( $video_id, $update_data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'hotel-video-library',
					'video_id'       => $video_id,
					'video_cat'      => $selected_cat,
					'video_updated'  => 1,
					'video_replaced' => $video_replaced,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render library page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$selected_cat      = isset( $_GET['video_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['video_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$video_updated     = isset( $_GET['video_updated'] ) ? absint( $_GET['video_updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$video_replaced    = isset( $_GET['video_replaced'] ) ? absint( $_GET['video_replaced'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$deleted           = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$unassigned        = isset( $_GET['unassigned'] ) ? absint( $_GET['unassigned'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$assigned          = isset( $_GET['assigned'] ) ? absint( $_GET['assigned'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$video_repository = new VideoRepository();
		$assignment_repository = new HotelVideoAssignmentRepository();

		// Fetch videos from custom table.
		$videos = $video_repository->get_all(
			array(
				'category' => $selected_cat,
				'limit'    => 24,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$total_videos = $video_repository->get_count( $selected_cat );

		// Get categories from options (Video Taxonomy page).
		$categories = get_option( 'hotel_chain_video_categories', array() );
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		// Normalise categories (split comma-separated).
		$normalised_categories = array();
		foreach ( $categories as $line ) {
			$parts = explode( ',', (string) $line );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$normalised_categories[] = $part;
				}
			}
		}
		$normalised_categories = array_values( array_unique( $normalised_categories ) );
		?>
		<div class="wrap w-8/12 mx-auto">
			<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'System Video Library', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-300 pb-3"><?php esc_html_e( 'Browse all videos available across the hotel chain.', 'hotel-chain' ); ?></p>

			<?php if ( $video_updated ) : ?>
				<div class="bg-green-50 border border-solid border-green-300 rounded p-3 mb-2 text-sm text-green-900">
					<?php esc_html_e( 'Video details updated successfully.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $video_replaced ) : ?>
				<div class="bg-blue-50 border border-solid border-blue-300 rounded p-3 mb-4 text-sm text-blue-900">
					<?php esc_html_e( 'Video file has been replaced successfully.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $deleted ) : ?>
				<div class="bg-red-50 border border-solid border-red-300 rounded p-3 mb-4 text-sm text-red-900">
					<?php esc_html_e( 'Video has been deleted successfully.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $unassigned ) : ?>
				<div class="bg-orange-50 border border-solid border-orange-300 rounded p-3 mb-4 text-sm text-orange-900">
					<?php esc_html_e( 'Video has been unassigned from the hotel.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $assigned ) : ?>
				<div class="bg-green-50 border border-solid border-green-300 rounded p-3 mb-4 text-sm text-green-900">
					<?php esc_html_e( 'Video has been assigned to the hotel.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
				<div class="mb-4 pb-3 border-b border-solid border-gray-300 flex items-center justify-between">
					<h3 class="text-lg font-semibold">
						<?php
						printf(
							/* translators: %d: number of videos. */
							esc_html__( 'System Video Library (%d videos)', 'hotel-chain' ),
							$total_videos
						);
						?>
					</h3>
					<div class="flex gap-3">
						<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="border border-solid border-gray-300 rounded px-4 py-2 flex items-center gap-2 bg-white">
							<input type="hidden" name="page" value="hotel-video-library" />
							<span class="text-gray-600 text-sm"><?php esc_html_e( 'Filter:', 'hotel-chain' ); ?></span>
							<select name="video_cat" class="border-none bg-transparent text-sm text-gray-800 focus:outline-none focus:ring-0" onchange="this.form.submit()">
								<option value=""><?php esc_html_e( 'All Categories', 'hotel-chain' ); ?></option>
								<?php foreach ( $normalised_categories as $cat_name ) : ?>
									<option value="<?php echo esc_attr( $cat_name ); ?>" <?php selected( $selected_cat, $cat_name ); ?>>
										<?php echo esc_html( $cat_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</form>
						<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hotel_chain_export_videos', 'video_cat' => $selected_cat ), admin_url( 'admin-post.php' ) ), 'hotel_chain_export_videos' ) ); ?>" class="px-4 py-2 bg-blue-200 border border-solid border-blue-400 rounded text-blue-900 inline-flex items-center justify-center">
							<?php esc_html_e( 'Export List', 'hotel-chain' ); ?>
						</a>
					</div>
				</div>

				<?php if ( empty( $videos ) ) : ?>
					<p class="text-gray-600"><?php esc_html_e( 'No videos found. Upload your first video to see it here.', 'hotel-chain' ); ?></p>
				<?php else : ?>
					<div class="grid grid-cols-1 md:grid-cols-4 gap-4" id="video-library-grid">
						<?php foreach ( $videos as $video ) : ?>
							<?php
							$category_label = $video->category ?: esc_html__( 'Uncategorized', 'hotel-chain' );
							$duration_label = $video->duration_label ?: '';

							$thumbnail_url = '';
							if ( $video->thumbnail_id ) {
								$thumbnail_url = wp_get_attachment_image_url( $video->thumbnail_id, 'medium' );
							} elseif ( $video->thumbnail_url ) {
								$thumbnail_url = $video->thumbnail_url;
							}

							$hotel_count = $assignment_repository->get_video_assignment_count( $video->video_id );
							?>
							<div class="video-card border border-solid border-gray-300 rounded overflow-hidden hover:border-blue-400 cursor-pointer bg-white" data-video-id="<?php echo esc_attr( $video->video_id ); ?>">
								<div class="bg-gray-200 border-b border-solid border-gray-300 h-32 flex items-center justify-center relative overflow-hidden">
									<?php if ( $thumbnail_url ) : ?>
										<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" class="w-full h-full object-cover" />
									<?php else : ?>
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-12 h-12 text-gray-400" aria-hidden="true">
											<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
											<rect x="2" y="6" width="14" height="12" rx="2"></rect>
										</svg>
									<?php endif; ?>
									<?php if ( $duration_label ) : ?>
										<div class="absolute bottom-2 right-2 px-2 py-1 rounded text-xs bg-white border border-gray-300">
											<?php echo esc_html( $duration_label ); ?>
										</div>
									<?php endif; ?>
								</div>
								<div class="p-3">
									<div class="mb-1 text-sm font-medium text-gray-900"><?php echo esc_html( $video->title ); ?></div>
									<div class="text-gray-600 mb-2 text-xs"><?php echo esc_html( $category_label ); ?></div>
									<div class="text-gray-700 text-xs">
										<?php esc_html_e( 'Assigned to hotels: ', 'hotel-chain' ); ?>
										<?php echo esc_html( $hotel_count ); ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- AJAX loaded detail panel container -->
			<div id="video-detail-container"></div>

			<script>
			(function() {
				const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				const nonce = '<?php echo esc_js( wp_create_nonce( 'hotel_chain_video_library' ) ); ?>';
				const selectedCat = '<?php echo esc_js( $selected_cat ); ?>';
				const detailContainer = document.getElementById('video-detail-container');
				let currentVideoId = null;

				// Click handler for video cards
				document.querySelectorAll('.video-card').forEach(function(card) {
					card.addEventListener('click', function() {
						const videoId = this.dataset.videoId;
						if (!videoId) return;

						// Highlight selected card
						document.querySelectorAll('.video-card').forEach(c => c.classList.remove('ring-2', 'ring-blue-500'));
						this.classList.add('ring-2', 'ring-blue-500');

						// Show loading state
						detailContainer.innerHTML = '<div class="bg-white rounded p-8 mb-6 border border-solid border-gray-300 text-center"><div class="text-gray-500"><?php echo esc_js( __( 'Loading video details...', 'hotel-chain' ) ); ?></div></div>';

						// Fetch video details via AJAX
						const formData = new FormData();
						formData.append('action', 'hotel_chain_get_video_detail');
						formData.append('video_id', videoId);
						formData.append('video_cat', selectedCat);
						formData.append('nonce', nonce);

						fetch(ajaxUrl, {
							method: 'POST',
							body: formData
						})
						.then(response => response.json())
						.then(data => {
							if (data.success && data.data.html) {
								detailContainer.innerHTML = data.data.html;
								currentVideoId = videoId;

								// Scroll to detail panel
								detailContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

								// Re-init any JS functionality for the panel
								initDetailPanelJS();
							} else {
								detailContainer.innerHTML = '<div class="bg-red-50 rounded p-4 mb-6 border border-solid border-red-300 text-red-900">' + (data.data.message || '<?php echo esc_js( __( 'Error loading video details.', 'hotel-chain' ) ); ?>') + '</div>';
							}
						})
						.catch(error => {
							console.error('Error:', error);
							detailContainer.innerHTML = '<div class="bg-red-50 rounded p-4 mb-6 border border-solid border-red-300 text-red-900"><?php echo esc_js( __( 'Error loading video details.', 'hotel-chain' ) ); ?></div>';
						});
					});
				});

				// Re-initialize JS for detail panel (play button, replace video, etc.)
				function initDetailPanelJS() {
					// Play/Stop preview button
					const playBtn = detailContainer.querySelector('[data-hotel-video-play]');
					if (playBtn) {
						playBtn.addEventListener('click', function() {
							const videoId = this.dataset.videoPreviewId;
							const video = document.getElementById(videoId);
							const poster = detailContainer.querySelector('[data-hotel-video-poster]');
							
							if (!video) return;

							if (video.paused) {
								if (poster) poster.classList.add('hidden');
								video.classList.remove('hidden');
								video.play();
								this.textContent = this.dataset.videoStopLabel;
							} else {
								video.pause();
								video.currentTime = 0;
								video.classList.add('hidden');
								if (poster) poster.classList.remove('hidden');
								this.textContent = this.dataset.videoPlayLabel;
							}
						});
					}

					// Replace video button
					const replaceBtn = detailContainer.querySelector('[data-hotel-replace-button]');
					const replaceInput = detailContainer.querySelector('[data-hotel-replace-input]');
					const replaceLabel = detailContainer.querySelector('[data-hotel-replace-label]');
					
					if (replaceBtn && replaceInput) {
						replaceBtn.addEventListener('click', function() {
							replaceInput.click();
						});

						replaceInput.addEventListener('change', function() {
							if (this.files && this.files[0]) {
								if (replaceLabel) {
									replaceLabel.textContent = '<?php echo esc_js( __( 'Selected:', 'hotel-chain' ) ); ?> ' + this.files[0].name;
									replaceLabel.classList.remove('hidden');
								}
							}
						});
					}

					// AJAX Assign buttons
					detailContainer.querySelectorAll('.ajax-assign-btn').forEach(function(btn) {
						btn.addEventListener('click', function() {
							const hotelId = this.dataset.hotelId;
							const videoId = this.dataset.videoId;
							const row = this.closest('tr');
							
							this.disabled = true;
							this.textContent = '<?php echo esc_js( __( 'Assigning...', 'hotel-chain' ) ); ?>';

							const formData = new FormData();
							formData.append('action', 'hotel_chain_ajax_assign_video');
							formData.append('hotel_id', hotelId);
							formData.append('video_id', videoId);
							formData.append('nonce', nonce);

							fetch(ajaxUrl, { method: 'POST', body: formData })
								.then(r => r.json())
								.then(data => {
									if (data.success) {
										// Reload the detail panel to reflect changes
										const card = document.querySelector('.video-card[data-video-id="' + videoId + '"]');
										if (card) card.click();
									} else {
										alert(data.data.message || '<?php echo esc_js( __( 'Error assigning video.', 'hotel-chain' ) ); ?>');
										this.disabled = false;
										this.textContent = '<?php echo esc_js( __( 'Assign', 'hotel-chain' ) ); ?>';
									}
								})
								.catch(() => {
									alert('<?php echo esc_js( __( 'Error assigning video.', 'hotel-chain' ) ); ?>');
									this.disabled = false;
									this.textContent = '<?php echo esc_js( __( 'Assign', 'hotel-chain' ) ); ?>';
								});
						});
					});

					// AJAX Unassign buttons
					detailContainer.querySelectorAll('.ajax-unassign-btn').forEach(function(btn) {
						btn.addEventListener('click', function() {
							if (!confirm('<?php echo esc_js( __( 'Are you sure you want to unassign this video from this hotel?', 'hotel-chain' ) ); ?>')) {
								return;
							}

							const assignmentId = this.dataset.assignmentId;
							const videoId = this.dataset.videoId;
							
							this.disabled = true;
							this.textContent = '<?php echo esc_js( __( 'Removing...', 'hotel-chain' ) ); ?>';

							const formData = new FormData();
							formData.append('action', 'hotel_chain_ajax_unassign_video');
							formData.append('assignment_id', assignmentId);
							formData.append('nonce', nonce);

							fetch(ajaxUrl, { method: 'POST', body: formData })
								.then(r => r.json())
								.then(data => {
									if (data.success) {
										// Reload the detail panel to reflect changes
										const card = document.querySelector('.video-card[data-video-id="' + videoId + '"]');
										if (card) card.click();
									} else {
										alert(data.data.message || '<?php echo esc_js( __( 'Error unassigning video.', 'hotel-chain' ) ); ?>');
										this.disabled = false;
										this.textContent = '<?php echo esc_js( __( 'Unassign', 'hotel-chain' ) ); ?>';
									}
								})
								.catch(() => {
									alert('<?php echo esc_js( __( 'Error unassigning video.', 'hotel-chain' ) ); ?>');
									this.disabled = false;
									this.textContent = '<?php echo esc_js( __( 'Unassign', 'hotel-chain' ) ); ?>';
								});
						});
					});
				}

				// Load video if ID is in URL (for page reload after form submit)
				<?php if ( $selected_video_id ) : ?>
				(function() {
					const card = document.querySelector('.video-card[data-video-id="<?php echo esc_js( $selected_video_id ); ?>"]');
					if (card) {
						card.click();
					}
				})();
				<?php endif; ?>
			})();
			</script>

		</div>
		<?php
	}
}
