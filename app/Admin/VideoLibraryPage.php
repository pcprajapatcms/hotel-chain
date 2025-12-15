<?php
/**
 * Admin Video Library page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;

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
	}

	/**
	 * Register submenu under Videos post type.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$parent_slug = 'edit.php?post_type=video';

		add_submenu_page(
			$parent_slug,
			__( 'System Video Library', 'hotel-chain' ),
			__( 'System Library', 'hotel-chain' ),
			'manage_options',
			'hotel-video-library',
			array( $this, 'render_page' )
		);
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

		$selected_cat = isset( $_GET['video_cat'] ) ? absint( $_GET['video_cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $selected_cat ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'video_category',
					'field'    => 'term_id',
					'terms'    => $selected_cat,
				),
			);
		}

		$query = new \WP_Query( $query_args );

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
				'Categories',
				'Tags',
				'Duration',
				'Default Language',
				'Video URL',
				'Uploaded',
				'Author',
			)
		);

		while ( $query->have_posts() ) {
			$query->the_post();

			$post_id = get_the_ID();

			$category_names = array();
			$cats           = get_the_terms( $post_id, 'video_category' );
			if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
				$category_names = wp_list_pluck( $cats, 'name' );
			}
			$category_label = ! empty( $category_names ) ? implode( '; ', $category_names ) : '';

			$tag_names = array();
			$tags      = get_the_terms( $post_id, 'video_tag' );
			if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
				$tag_names = wp_list_pluck( $tags, 'name' );
			}
			$tag_label = ! empty( $tag_names ) ? implode( '; ', $tag_names ) : '';

			$duration_label = get_post_meta( $post_id, 'video_duration_label', true );
			if ( ! $duration_label ) {
				$video_attachment_id = (int) get_post_meta( $post_id, 'video_file_id', true );
				if ( $video_attachment_id ) {
					$metadata = wp_get_attachment_metadata( $video_attachment_id );
					if ( ! empty( $metadata['length_formatted'] ) ) {
						$duration_label = $metadata['length_formatted'];
					}
				}
			}

			$language = get_post_meta( $post_id, 'video_default_language', true );

			$author      = get_userdata( get_post_field( 'post_author', $post_id ) );
			$author_name = $author ? $author->display_name : '';

			fputcsv(
				$output,
				array(
					$post_id,
					get_the_title(),
					$category_label,
					$tag_label,
					$duration_label,
					$language,
					get_permalink( $post_id ),
					get_the_date( 'Y-m-d', $post_id ),
					$author_name,
				)
			);
		}

		wp_reset_postdata();
		fclose( $output );
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
		$selected_cat = isset( $_POST['video_cat'] ) ? absint( $_POST['video_cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $video_id || 'video' !== get_post_type( $video_id ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'post_type' => 'video',
						'page'      => 'hotel-video-library',
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		$title       = isset( $_POST['video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['video_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$description = isset( $_POST['video_description'] ) ? wp_kses_post( wp_unslash( $_POST['video_description'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$category_id = isset( $_POST['video_category'] ) ? absint( $_POST['video_category'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tags_raw    = isset( $_POST['video_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['video_tags'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$language    = isset( $_POST['video_language'] ) ? sanitize_text_field( wp_unslash( $_POST['video_language'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Update basic post fields.
		$update_args = array(
			'ID'           => $video_id,
			'post_title'   => $title,
			'post_content' => $description,
		);

		wp_update_post( $update_args );

		// Update category.
		if ( $category_id ) {
			wp_set_object_terms( $video_id, array( $category_id ), 'video_category', false );
		} else {
			wp_set_object_terms( $video_id, array(), 'video_category', false );
		}

		// Update tags (comma separated).
		if ( ! empty( $tags_raw ) ) {
			$tags = array_filter(
				array_map(
					'trim',
					explode( ',', $tags_raw )
				)
			);
			wp_set_object_terms( $video_id, $tags, 'video_tag', false );
		} else {
			wp_set_object_terms( $video_id, array(), 'video_tag', false );
		}

		// Update language meta.
		update_post_meta( $video_id, 'video_default_language', $language );

		$video_replaced = 0;

		// Optional: replace main video file if a new one was uploaded.
		if ( ! empty( $_FILES['replace_video_file']['name'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslashed
			// Load media library functions if not already loaded.
			if ( ! function_exists( 'media_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$video_attachment_id = media_handle_upload( 'replace_video_file', $video_id );

			if ( ! is_wp_error( $video_attachment_id ) && $video_attachment_id ) {
				update_post_meta( $video_id, 'video_file_id', $video_attachment_id );
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
					update_post_meta( $video_id, 'video_duration_label', $duration_label );
				}
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'       => 'video',
					'page'            => 'hotel-video-library',
					'video_id'        => $video_id,
					'video_cat'       => $selected_cat,
					'video_updated'   => 1,
					'video_replaced'  => $video_replaced,
				),
				admin_url( 'edit.php' )
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

		$selected_cat      = isset( $_GET['video_cat'] ) ? absint( $_GET['video_cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$video_updated     = isset( $_GET['video_updated'] ) ? absint( $_GET['video_updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$video_replaced    = isset( $_GET['video_replaced'] ) ? absint( $_GET['video_replaced'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Fetch videos, optionally filtered by category.
		$query_args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $selected_cat ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'video_category',
					'field'    => 'term_id',
					'terms'    => $selected_cat,
				),
			);
		}

		$query = new \WP_Query( $query_args );

		$total_videos = (int) $query->found_posts;

		// All video categories for the filter dropdown.
		$categories = get_terms(
			array(
				'taxonomy'   => 'video_category',
				'hide_empty' => false,
			)
		);
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
						<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="border border-solid border-gray-300 rounded px-4 py-2 flex items-center gap-2 bg-white">
							<input type="hidden" name="post_type" value="video" />
							<input type="hidden" name="page" value="hotel-video-library" />
							<span class="text-gray-600 text-sm"><?php esc_html_e( 'Filter:', 'hotel-chain' ); ?></span>
							<select name="video_cat" class="border-none bg-transparent text-sm text-gray-800 focus:outline-none focus:ring-0" onchange="this.form.submit()">
								<option value=""><?php esc_html_e( 'All Categories', 'hotel-chain' ); ?></option>
								<?php foreach ( $categories as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected_cat, $term->term_id ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</form>
						<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hotel_chain_export_videos', 'video_cat' => $selected_cat ), admin_url( 'admin-post.php' ) ), 'hotel_chain_export_videos' ) ); ?>" class="px-4 py-2 bg-blue-200 border border-solid border-blue-400 rounded text-blue-900 inline-flex items-center justify-center">
							<?php esc_html_e( 'Export List', 'hotel-chain' ); ?>
						</a>
					</div>
				</div>

				<?php if ( ! $query->have_posts() ) : ?>
					<p class="text-gray-600"><?php esc_html_e( 'No videos found. Upload your first video to see it here.', 'hotel-chain' ); ?></p>
				<?php else : ?>
					<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();

							$post_id   = get_the_ID();
							$title     = get_the_title();
							$detail_url = add_query_arg(
								array(
									'post_type' => 'video',
									'page'      => 'hotel-video-library',
									'video_id'  => $post_id,
									'video_cat' => $selected_cat,
								),
								admin_url( 'edit.php' )
							);

							$category_names = array();
							$cats           = get_the_terms( $post_id, 'video_category' );
							if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
								$category_names = wp_list_pluck( $cats, 'name' );
							}
							$category_label = ! empty( $category_names ) ? implode( ', ', $category_names ) : esc_html__( 'Uncategorized', 'hotel-chain' );

							$duration_label = get_post_meta( $post_id, 'video_duration_label', true );
							if ( ! $duration_label ) {
								$video_attachment_id = (int) get_post_meta( $post_id, 'video_file_id', true );
								if ( $video_attachment_id ) {
									$metadata = wp_get_attachment_metadata( $video_attachment_id );
									if ( ! empty( $metadata['length_formatted'] ) ) {
										$duration_label = $metadata['length_formatted'];
									}
								}
							}

							$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'medium' );
							?>
							<a href="<?php echo esc_url( $detail_url ); ?>" class="border border-solid border-gray-300 rounded overflow-hidden hover:border-blue-400 cursor-pointer bg-white block">
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
									<div class="mb-1 text-sm font-medium text-gray-900"><?php echo esc_html( $title ); ?></div>
									<div class="text-gray-600 mb-2 text-xs"><?php echo esc_html( $category_label ); ?></div>
									<div class="text-gray-700 text-xs">
										<?php esc_html_e( 'Assigned to hotels: ', 'hotel-chain' ); ?>
										<?php echo esc_html( get_post_meta( $post_id, 'video_hotel_count', true ) ?: '0' ); ?>
									</div>
								</div>
							</a>
						<?php endwhile; ?>
						<?php wp_reset_postdata(); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php
			// Detail panel for selected video.
			if ( $selected_video_id ) :
				$video = get_post( $selected_video_id );
				if ( $video && 'video' === $video->post_type ) :
					$title       = get_the_title( $video );
					$description = $video->post_content;

					$category_names = array();
					$cats           = get_the_terms( $video->ID, 'video_category' );
					if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
						$category_names = wp_list_pluck( $cats, 'name' );
					}
					$category_label = ! empty( $category_names ) ? implode( ', ', $category_names ) : esc_html__( 'Uncategorized', 'hotel-chain' );

					$tag_names = array();
					$tags      = get_the_terms( $video->ID, 'video_tag' );
					if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
						$tag_names = wp_list_pluck( $tags, 'name' );
					}

					$video_attachment_id = (int) get_post_meta( $video->ID, 'video_file_id', true );
					$metadata            = $video_attachment_id ? wp_get_attachment_metadata( $video_attachment_id ) : array();
					$preview_thumb_url   = get_the_post_thumbnail_url( $video->ID, 'medium' );

					$duration_label = get_post_meta( $video->ID, 'video_duration_label', true );
					if ( ! $duration_label && ! empty( $metadata['length_formatted'] ) ) {
						$duration_label = $metadata['length_formatted'];
					}

					// File size in MB, if available.
					$file_size_label = '';
					if ( ! empty( $metadata['filesize'] ) ) {
						$file_size_label = round( (int) $metadata['filesize'] / ( 1024 * 1024 ) ) . ' MB';
					} elseif ( $video_attachment_id ) {
						$file = get_attached_file( $video_attachment_id );
						if ( $file && file_exists( $file ) ) {
							$file_size_label = round( filesize( $file ) / ( 1024 * 1024 ) ) . ' MB';
						}
					}

					// Format from file extension.
					$format_label = '';
					if ( $video_attachment_id ) {
						$file = get_attached_file( $video_attachment_id );
						if ( $file ) {
							$ext          = strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) );
							$format_label = $ext;
						}
					}

					// Resolution.
					$resolution_label = '';
					if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
						$resolution_label = $metadata['width'] . 'x' . $metadata['height'];
					}

					$uploaded_label = get_the_date( 'M j, Y', $video );

					$edit_link = get_edit_post_link( $video );

					$hotel_count    = (int) get_post_meta( $video->ID, 'video_hotel_count', true );
					$total_views    = (int) get_post_meta( $video->ID, 'video_total_views', true );
					$avg_completion = get_post_meta( $video->ID, 'video_avg_completion', true );
					$avg_completion = $avg_completion ? $avg_completion . '%' : '0%';
					?>
					<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
						<div class="mb-4 pb-3 border-b border-solid border-gray-300">
							<h3 class="text-lg font-semibold"><?php esc_html_e( 'Video Detail & Edit', 'hotel-chain' ); ?></h3>
						</div>
						<div class="bg-white border border-solid border-gray-300 rounded p-6">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-4" enctype="multipart/form-data">
								<?php wp_nonce_field( 'hotel_chain_update_video' ); ?>
								<input type="hidden" name="action" value="hotel_chain_update_video" />
								<input type="hidden" name="video_id" value="<?php echo esc_attr( $video->ID ); ?>" />
								<input type="hidden" name="video_cat" value="<?php echo esc_attr( $selected_cat ); ?>" />
								<input type="file" name="replace_video_file" accept="video/*" class="hidden" data-hotel-replace-input="1" />
								<div class="grid grid-cols-3 gap-6">
									<div class="col-span-1">
										<div class="bg-gray-200 border border-solid border-gray-300 rounded h-48 flex items-center justify-center mb-4 overflow-hidden relative">
											<?php if ( $video_attachment_id ) : ?>
												<?php if ( $preview_thumb_url ) : ?>
													<img
														src="<?php echo esc_url( $preview_thumb_url ); ?>"
														alt=""
														class="w-full h-full object-cover"
														data-hotel-video-poster="<?php echo esc_attr( $video->ID ); ?>"
													/>
												<?php else : ?>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-16 h-16 text-gray-400" aria-hidden="true" data-hotel-video-poster="<?php echo esc_attr( $video->ID ); ?>">
														<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
														<rect x="2" y="6" width="14" height="12" rx="2"></rect>
													</svg>
												<?php endif; ?>
												<video
													id="hotel-video-preview-<?php echo esc_attr( $video->ID ); ?>"
													class="w-full h-full object-cover rounded hidden"
													preload="metadata"
													playsinline
												>
													<source src="<?php echo esc_url( wp_get_attachment_url( $video_attachment_id ) ); ?>" type="video/mp4" />
													<?php esc_html_e( 'Your browser does not support the video tag.', 'hotel-chain' ); ?>
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
											<button
												type="button"
												class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-200 border border-solid border-blue-400 rounded text-blue-900"
												data-hotel-video-play="1"
												data-video-preview-id="hotel-video-preview-<?php echo esc_attr( $video->ID ); ?>"
												data-video-play-label="<?php esc_attr_e( 'Play Preview', 'hotel-chain' ); ?>"
												data-video-stop-label="<?php esc_attr_e( 'Stop Preview', 'hotel-chain' ); ?>"
											>
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
												<?php foreach ( $categories as $term ) : ?>
													<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php echo in_array( $term->name, $category_names, true ) ? 'selected' : ''; ?>>
														<?php echo esc_html( $term->name ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="mb-4">
											<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_description_inline"><?php esc_html_e( 'Description', 'hotel-chain' ); ?></label>
											<textarea id="video_description_inline" name="video_description" rows="4" class="w-full border border-solid border-slate-300 rounded p-3 bg-white min-h-20 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-500"><?php echo esc_textarea( $description ); ?></textarea>
										</div>
										<div class="mb-4">
											<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_language_inline"><?php esc_html_e( 'Default Language', 'hotel-chain' ); ?></label>
											<input type="text" id="video_language_inline" name="video_language" value="<?php echo esc_attr( get_post_meta( $video->ID, 'video_default_language', true ) ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
										</div>
										<div class="mb-4">
											<label class="mb-1 text-sm font-semibold text-slate-800 block" for="video_tags_inline"><?php esc_html_e( 'Tags (comma separated)', 'hotel-chain' ); ?></label>
											<input type="text" id="video_tags_inline" name="video_tags" value="<?php echo esc_attr( implode( ', ', $tag_names ) ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
										</div>

										<div class="grid grid-cols-2 gap-3 pt-2">
											<button type="submit" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900">
												<?php esc_html_e( 'Save Changes', 'hotel-chain' ); ?>
											</button>
											<a href="#" class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 text-center">
												<?php esc_html_e( 'View Assignments', 'hotel-chain' ); ?>
											</a>
											<button type="button" class="px-4 py-2 bg-purple-200 border-2 border-purple-400 rounded text-purple-900" data-hotel-replace-button="1">
												<?php esc_html_e( 'Replace Video', 'hotel-chain' ); ?>
											</button>
											<a href="<?php echo esc_url( get_delete_post_link( $video->ID, '', true ) ); ?>" class="px-4 py-2 bg-red-200 border-2 border-red-400 rounded text-red-900 text-center" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this video?', 'hotel-chain' ) ); ?>');">
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
					<?php
				endif;
			endif;
			?>
		</div>
		<?php
	}
}
