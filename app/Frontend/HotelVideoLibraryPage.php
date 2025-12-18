<?php
/**
 * Hotel Video Library page.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;

/**
 * Hotel Video Library page for hotel users.
 */
class HotelVideoLibraryPage {
	/**
	 * Render the video library page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hotel-chain' ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found.', 'hotel-chain' ) );
		}

		$video_repository = new VideoRepository();
		$assignment_repo = new HotelVideoAssignmentRepository();

		// Get filter/search parameters.
		$search_query = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category_filter = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_mode = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'grid'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Get ALL videos from the system (not just assigned to this hotel).
		$all_videos = $video_repository->get_all( array( 'limit' => -1 ) );

		// Apply filters and calculate statistics.
		$videos = array();
		$total_duration_seconds = 0;
		$total_completions = 0;
		$total_views = 0;

		foreach ( $all_videos as $video_meta ) {
			// Apply search filter.
			if ( ! empty( $search_query ) && stripos( $video_meta->title, $search_query ) === false && stripos( $video_meta->description ?? '', $search_query ) === false ) {
				continue;
			}

			// Apply category filter.
			if ( ! empty( $category_filter ) && $video_meta->category !== $category_filter ) {
				continue;
			}

			// For now, all videos are "active" (we can add status later if needed).
			if ( ! empty( $status_filter ) && $status_filter !== 'active' ) {
				continue;
			}

			$videos[] = $video_meta;
			$total_duration_seconds += (int) $video_meta->duration_seconds;
			$total_completions += (int) $video_meta->total_completions;
			$total_views += (int) $video_meta->total_views;
		}

		// Calculate statistics.
		$total_videos = count( $videos );
		$active_videos = $total_videos; // All assigned videos are active.
		$total_duration_hours = round( $total_duration_seconds / 3600, 1 );
		$avg_completion = $total_videos > 0 && $total_views > 0 ? round( ( $total_completions / $total_views ) * 100, 0 ) : 0;

		// Get distinct categories from all videos.
		$categories = array();
		foreach ( $all_videos as $video ) {
			if ( ! empty( $video->category ) && ! in_array( $video->category, $categories, true ) ) {
				$categories[] = $video->category;
			}
		}
		sort( $categories );

		// Format duration helper.
		$format_duration = function( $seconds ) {
			if ( ! $seconds ) {
				return '0:00';
			}
			$mins = floor( $seconds / 60 );
			$secs = $seconds % 60;
			return sprintf( '%d:%02d', $mins, $secs );
		};

		// Prepare video data for JavaScript (for dynamic panel display).
		$videos_data = array();
		foreach ( $all_videos as $vid ) {
			$thumb_url = '';
			if ( $vid->thumbnail_id ) {
				$thumb_url = wp_get_attachment_image_url( $vid->thumbnail_id, 'medium' );
			} elseif ( $vid->thumbnail_url ) {
				$thumb_url = $vid->thumbnail_url;
			}
			$completion = $vid->total_views > 0 ? round( ( $vid->total_completions / $vid->total_views ) * 100, 0 ) : 0;

			// Get assignment status for this hotel.
			$assignment = $assignment_repo->get_assignment( $hotel->id, $vid->video_id );
			$assignment_status = $assignment ? $assignment->status : 'none';
			
			$status_by_hotel = $assignment && isset( $assignment->status_by_hotel ) ? $assignment->status_by_hotel : 'active';

			$videos_data[ $vid->video_id ] = array(
				'video_id' => $vid->video_id,
				'title' => $vid->title,
				'description' => $vid->description ?: __( 'No description available.', 'hotel-chain' ),
				'category' => $vid->category ?: __( 'Uncategorized', 'hotel-chain' ),
				'duration' => $format_duration( $vid->duration_seconds ),
				'thumbnail_url' => $thumb_url,
				'total_completions' => number_format_i18n( $vid->total_completions ),
				'avg_completion' => $completion . '%',
				'assignment_status' => $assignment_status,
				'status_by_hotel' => $status_by_hotel,
			);
		}

		?>
		<div class="flex-1 overflow-auto p-4 lg:p-8 wrap w-8/12 mx-auto">
			<div class="max-w-7xl mx-auto">
				<div class="space-y-6">
					<!-- Header -->
					<div class="mb-6 border-b border-solid border-gray-300 pb-3">
						<h1 class="mb-1" style="color: rgb(60, 56, 55); font-family: var(--font-serif);"><?php esc_html_e( 'HOTEL – Video Library', 'hotel-chain' ); ?></h1>
						<p style="color: rgb(122, 122, 122); font-family: var(--font-sans);"><?php esc_html_e( 'Browse all videos available in the system', 'hotel-chain' ); ?></p>
					</div>

					<!-- Filters -->
					<div class="bg-white rounded p-4 border border-solid border-gray-300">
						<div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
							<!-- Search -->
							<div class="w-full lg:flex-1 flex items-center gap-3 border border-solid border-gray-300 rounded px-4 py-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search w-5 h-5" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="m21 21-4.34-4.34"></path>
									<circle cx="11" cy="11" r="8"></circle>
								</svg>
								<input 
									type="text" 
									id="hotel-video-search" 
									placeholder="<?php esc_attr_e( 'Search videos...', 'hotel-chain' ); ?>" 
									value="<?php echo esc_attr( $search_query ); ?>"
									class="flex-1 border-none outline-none bg-transparent"
									style="color: rgb(122, 122, 122); font-family: var(--font-sans);"
								/>
							</div>

							<!-- Filter Button -->
							<div class="flex items-center gap-2 border border-solid border-gray-300 rounded px-4 py-2 cursor-pointer" id="hotel-filter-toggle">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel w-5 h-5" aria-hidden="true" style="color: rgb(122, 122, 122);">
									<path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
								</svg>
								<span style="font-family: var(--font-sans); color: rgb(61, 61, 68);"><?php esc_html_e( 'Filter', 'hotel-chain' ); ?></span>
							</div>

							<!-- Category Dropdown -->
							<div class="flex items-center gap-2 border border-solid border-gray-300 rounded px-4 py-2">
								<span style="color: rgb(122, 122, 122); font-family: var(--font-sans);"><?php esc_html_e( 'Category:', 'hotel-chain' ); ?></span>
								<select id="hotel-category-filter" class="border-none outline-none bg-transparent cursor-pointer" style="font-family: var(--font-sans); color: rgb(61, 61, 68);">
									<option value=""><?php esc_html_e( 'All', 'hotel-chain' ); ?></option>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $category_filter, $cat ); ?>>
											<?php echo esc_html( $cat ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<!-- Status Dropdown -->
							<div class="flex items-center gap-2 border border-solid border-gray-300 rounded px-4 py-2">
								<span style="color: rgb(122, 122, 122); font-family: var(--font-sans);"><?php esc_html_e( 'Status:', 'hotel-chain' ); ?></span>
								<select id="hotel-status-filter" class="border-none outline-none bg-transparent cursor-pointer" style="font-family: var(--font-sans); color: rgb(61, 61, 68);">
									<option value=""><?php esc_html_e( 'All', 'hotel-chain' ); ?></option>
									<option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'hotel-chain' ); ?></option>
									<option value="inactive" <?php selected( $status_filter, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'hotel-chain' ); ?></option>
								</select>
							</div>

							<!-- View Toggle -->
							<div class="flex gap-2">
								<button 
									type="button" 
									class="p-2 border border-solid border-gray-300 rounded hotel-view-btn <?php echo 'grid' === $view_mode ? 'active' : ''; ?>" 
									data-view="grid"
									style="<?php echo 'grid' === $view_mode ? 'background-color: rgb(240, 231, 215);' : ''; ?>"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-grid3x3 lucide-grid-3x3 w-5 h-5" aria-hidden="true" style="color: rgb(61, 61, 68);">
										<rect width="18" height="18" x="3" y="3" rx="2"></rect>
										<path d="M3 9h18"></path>
										<path d="M3 15h18"></path>
										<path d="M9 3v18"></path>
										<path d="M15 3v18"></path>
									</svg>
								</button>
								<button 
									type="button" 
									class="p-2 border rounded hotel-view-btn <?php echo 'list' === $view_mode ? 'active' : ''; ?>" 
									data-view="list"
									style="<?php echo 'list' === $view_mode ? 'border-color: rgb(61, 61, 68); background-color: rgb(240, 231, 215);' : 'border-color: rgb(196, 196, 196);'; ?>"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list w-5 h-5" aria-hidden="true" style="color: rgb(122, 122, 122);">
										<path d="M3 5h.01"></path>
										<path d="M3 12h.01"></path>
										<path d="M3 19h.01"></path>
										<path d="M8 5h13"></path>
										<path d="M8 12h13"></path>
										<path d="M8 19h13"></path>
									</svg>
								</button>
							</div>
						</div>
					</div>

					<!-- Stats Cards -->
					<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
						<div class="bg-white rounded p-4 border border-solid border-gray-300">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-6 h-6" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
									<circle cx="12" cy="8" r="2"></circle>
									<path d="M12 10v12"></path>
									<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
									<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
								</svg>
								<div style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Total Videos', 'hotel-chain' ); ?></div>
							</div>
							<div style="color: rgb(61, 61, 68); font-family: var(--font-serif); font-size: 2rem;"><?php echo esc_html( $total_videos ); ?></div>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-300">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check w-6 h-6" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<circle cx="12" cy="12" r="10"></circle>
									<path d="m9 12 2 2 4-4"></path>
								</svg>
								<div style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></div>
							</div>
							<div style="color: rgb(61, 61, 68); font-family: var(--font-serif); font-size: 2rem;"><?php echo esc_html( $active_videos ); ?></div>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-300">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-6 h-6" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<div style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Total Duration', 'hotel-chain' ); ?></div>
							</div>
							<div style="color: rgb(61, 61, 68); font-family: var(--font-serif); font-size: 2rem;"><?php echo esc_html( $total_duration_hours ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></div>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-300">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-6 h-6" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
									<path d="M20 2v4"></path>
									<path d="M22 4h-4"></path>
									<circle cx="4" cy="20" r="2"></circle>
								</svg>
								<div style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Avg. Completion', 'hotel-chain' ); ?></div>
							</div>
							<div style="color: rgb(61, 61, 68); font-family: var(--font-serif); font-size: 2rem;"><?php echo esc_html( $avg_completion ); ?>%</div>
						</div>
					</div>

					<?php if ( 'list' === $view_mode ) : ?>
						<!-- List View -->
						<div class="bg-white rounded p-4 border border-solid border-gray-300">
							<div class="mb-4 pb-3 border-b-2" style="border-color: rgb(196, 196, 196);">
								<h3 style="font-family: var(--font-sans); color: rgb(61, 61, 68);"><?php esc_html_e( 'List View (Alternative)', 'hotel-chain' ); ?></h3>
							</div>
							<div class="bg-white border border-solid border-gray-300 rounded overflow-hidden">
								<div class="grid grid-cols-6 gap-4 p-3 border-b-2" style="background-color: rgb(240, 231, 215); border-color: rgb(196, 196, 196);">
									<div class="col-span-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Video Title', 'hotel-chain' ); ?></div>
									<div style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Category', 'hotel-chain' ); ?></div>
									<div style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Duration', 'hotel-chain' ); ?></div>
									<div style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></div>
									<div style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Actions', 'hotel-chain' ); ?></div>
								</div>
								<?php if ( empty( $videos ) ) : ?>
									<div class="p-8 text-center" style="font-family: var(--font-sans); color: rgb(122, 122, 122);">
										<?php esc_html_e( 'No videos found.', 'hotel-chain' ); ?>
									</div>
								<?php else : ?>
								<?php foreach ( $videos as $video ) : ?>
									<?php
									$video_id_key = (string) $video->video_id;
									$video_json_data = isset( $videos_data[ $video_id_key ] ) ? $videos_data[ $video_id_key ] : array();
									?>
									<div class="grid grid-cols-6 gap-4 p-3 border-b-2 last:border-b-0 hotel-video-row" 
										data-video-id="<?php echo esc_attr( $video->video_id ); ?>" 
										data-video-data="<?php echo esc_attr( wp_json_encode( $video_json_data ) ); ?>"
										style="border-color: rgb(196, 196, 196); cursor: pointer;">
											<div class="col-span-2 flex items-center gap-3">
												<?php
												$thumbnail_url = '';
												if ( $video->thumbnail_id ) {
													$thumbnail_url = wp_get_attachment_image_url( $video->thumbnail_id, 'thumbnail' );
												} elseif ( $video->thumbnail_url ) {
													$thumbnail_url = $video->thumbnail_url;
												}
												?>
												<div class="w-16 h-12 border-2 rounded flex items-center justify-center" style="background-color: rgb(240, 231, 215); border-color: rgb(196, 196, 196);">
													<?php if ( $thumbnail_url ) : ?>
														<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" class="w-full h-full object-cover rounded" />
													<?php else : ?>
														<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-6 h-6" aria-hidden="true" style="color: rgb(61, 61, 68);">
															<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
															<circle cx="12" cy="8" r="2"></circle>
															<path d="M12 10v12"></path>
															<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
															<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
														</svg>
													<?php endif; ?>
												</div>
												<div style="font-family: var(--font-sans); color: rgb(61, 61, 68);"><?php echo esc_html( $video->title ); ?></div>
											</div>
											<div class="flex items-center" style="font-family: var(--font-sans); color: rgb(122, 122, 122);"><?php echo esc_html( $video->category ?: __( 'Uncategorized', 'hotel-chain' ) ); ?></div>
											<div class="flex items-center" style="font-family: var(--font-sans); color: rgb(61, 61, 68);"><?php echo esc_html( $format_duration( $video->duration_seconds ) ); ?></div>
											<div class="flex items-center">
												<span class="px-3 py-1 rounded" style="background-color: rgb(240, 231, 215); border: 2px solid rgb(61, 61, 68); font-family: var(--font-sans); font-size: 0.75rem; color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></span>
											</div>
											<div class="flex items-center">
												<button class="px-3 py-1 rounded transition-all hover:opacity-80 hotel-edit-btn" data-video-id="<?php echo esc_attr( $video->video_id ); ?>" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); border: none;"><?php esc_html_e( 'Edit', 'hotel-chain' ); ?></button>
											</div>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php else : ?>
						<!-- Grid View -->
						<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
							<?php if ( empty( $videos ) ) : ?>
								<div class="col-span-full p-8 text-center" style="font-family: var(--font-sans); color: rgb(122, 122, 122);">
									<?php esc_html_e( 'No videos found.', 'hotel-chain' ); ?>
								</div>
							<?php else : ?>
								<?php foreach ( $videos as $video ) : ?>
									<?php
									$video_id_key = (string) $video->video_id;
									$video_json_data = isset( $videos_data[ $video_id_key ] ) ? $videos_data[ $video_id_key ] : array();
									?>
									<div class="bg-white border border-solid border-gray-300 rounded overflow-hidden cursor-pointer transition-all hover:shadow-lg hotel-video-card" 
										data-video-id="<?php echo esc_attr( $video->video_id ); ?>"
										data-video-data="<?php echo esc_attr( wp_json_encode( $video_json_data ) ); ?>">
										<div class="border-b border-solid border-gray-300 h-32 flex items-center justify-center relative" style="background-color: rgb(240, 231, 215);">
											<?php
											$thumbnail_url = '';
											if ( $video->thumbnail_id ) {
												$thumbnail_url = wp_get_attachment_image_url( $video->thumbnail_id, 'medium' );
											} elseif ( $video->thumbnail_url ) {
												$thumbnail_url = $video->thumbnail_url;
											}
											if ( $thumbnail_url ) :
												?>
												<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $video->title ); ?>" class="w-full h-full object-cover" />
											<?php else : ?>
												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-12 h-12" aria-hidden="true" style="color: rgb(61, 61, 68);">
													<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
													<circle cx="12" cy="8" r="2"></circle>
													<path d="M12 10v12"></path>
													<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
													<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
												</svg>
											<?php endif; ?>
											<div class="absolute top-2 right-2 px-2 py-1 rounded" style="background-color: rgb(255, 255, 255); border: 1px solid rgb(196, 196, 196); font-family: var(--font-sans); font-size: 0.75rem; color: rgb(61, 61, 68);"><?php echo esc_html( $format_duration( $video->duration_seconds ) ); ?></div>
											<div class="absolute top-2 left-2 px-2 py-1 rounded" style="background-color: rgb(240, 231, 215); border: 2px solid rgb(61, 61, 68); font-family: var(--font-sans); font-size: 0.75rem; color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></div>
										</div>
										<div class="p-3">
											<div class="mb-1" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php echo esc_html( $video->title ); ?></div>
											<div style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem;"><?php echo esc_html( $video->category ?: __( 'Uncategorized', 'hotel-chain' ) ); ?></div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- Detail Panel (hidden by default, shown via JavaScript) -->
					<div id="hotel-video-detail-panel" class="bg-white rounded p-4 border border-solid border-gray-300" style="display: none; margin-top: 1.5rem;">
						<div class="mb-4 pb-3 border-b border-solid border-gray-300">
							<h3 style="font-family: var(--font-sans); color: rgb(61, 61, 68);"><?php esc_html_e( 'Video Detail Panel (on click)', 'hotel-chain' ); ?></h3>
						</div>
						<div class="bg-white border border-solid border-gray-300 rounded p-6">
							<div class="grid grid-cols-2 gap-6">
								<div>
									<div class="border border-solid border-gray-300 rounded h-80 flex items-center justify-center mb-4" style="background-color: rgb(240, 231, 215);">
										<img id="detail-thumbnail" src="" alt="" class="w-full h-full object-cover rounded hidden" />
										<svg id="detail-placeholder" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-16 h-16" aria-hidden="true" style="color: rgb(61, 61, 68);">
											<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
											<circle cx="12" cy="8" r="2"></circle>
											<path d="M12 10v12"></path>
											<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
											<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
										</svg>
									</div>
									<div class="space-y-2">
										<div class="flex justify-between">
											<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Duration:', 'hotel-chain' ); ?></span>
											<span id="detail-duration" style="font-family: var(--font-sans); color: rgb(61, 61, 68);"></span>
										</div>
										<div class="flex justify-between">
											<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Category:', 'hotel-chain' ); ?></span>
											<span id="detail-category" style="font-family: var(--font-sans); color: rgb(61, 61, 68);"></span>
										</div>
										<div class="flex justify-between">
											<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Total Completions:', 'hotel-chain' ); ?></span>
											<span id="detail-completions" style="font-family: var(--font-sans); color: rgb(61, 61, 68);"></span>
										</div>
										<div class="flex justify-between">
											<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Avg. Completion:', 'hotel-chain' ); ?></span>
											<span id="detail-avg-completion" style="font-family: var(--font-sans); color: rgb(61, 61, 68);"></span>
										</div>
									</div>
								</div>
								<div>
									<h3 id="detail-title" class="mb-3" style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 1.5rem;"></h3>
									<div id="detail-description" class="mb-4" style="font-family: var(--font-sans); color: rgb(122, 122, 122); line-height: 1.6;"></div>
								<div class="mb-4">
									<div class="mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Assignment Status:', 'hotel-chain' ); ?></div>
									<div id="detail-status-badge"></div>
								</div>
								<div class="mb-4" id="detail-hotel-status-section" style="display: none;">
									<div class="mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'My Video Status:', 'hotel-chain' ); ?></div>
									<div class="flex gap-2">
										<button 
											id="detail-set-active-btn"
											class="px-4 py-2 rounded transition-all hover:opacity-80" 
											style="background-color: rgb(240, 231, 215); border: 2px solid rgb(61, 61, 68); font-family: var(--font-sans); color: rgb(61, 61, 68);"
											data-video-id=""
										><?php esc_html_e( 'Active', 'hotel-chain' ); ?></button>
										<button 
											id="detail-set-inactive-btn"
											class="px-4 py-2 rounded transition-all hover:opacity-80" 
											style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); font-family: var(--font-sans); color: rgb(122, 122, 122);"
											data-video-id=""
										><?php esc_html_e( 'Set Inactive', 'hotel-chain' ); ?></button>
									</div>
									<div id="detail-hotel-status-message" class="mt-2 hidden"></div>
								</div>
								<div class="flex gap-2">
									<button 
										id="detail-request-btn"
										class="flex-1 px-4 py-2 rounded border border-solid border-gray-300 transition-all hover:opacity-90" 
										style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); border: none;"
										data-video-id=""
									><?php esc_html_e( 'Send Request to Admin', 'hotel-chain' ); ?></button>
									<button class="flex-1 px-4 py-2 rounded border border-solid border-gray-300 transition-all hover:opacity-80" style="background-color: rgb(255, 255, 255); font-family: var(--font-sans); color: rgb(61, 61, 68);"><?php esc_html_e( 'View Analytics', 'hotel-chain' ); ?></button>
								</div>
								<div id="detail-request-message" class="mt-3 hidden"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function() {
			const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			const requestNonce = '<?php echo esc_js( wp_create_nonce( 'hotel_video_request' ) ); ?>';

			// Search functionality
			const searchInput = document.getElementById('hotel-video-search');
			if (searchInput) {
				searchInput.addEventListener('keypress', function(e) {
					if (e.key === 'Enter') {
						const search = this.value;
						const url = new URL(window.location.href);
						if (search) {
							url.searchParams.set('search', search);
						} else {
							url.searchParams.delete('search');
						}
						window.location.href = url.toString();
					}
				});
			}

			// Category filter
			const categoryFilter = document.getElementById('hotel-category-filter');
			if (categoryFilter) {
				categoryFilter.addEventListener('change', function() {
					const url = new URL(window.location.href);
					if (this.value) {
						url.searchParams.set('category', this.value);
					} else {
						url.searchParams.delete('category');
					}
					window.location.href = url.toString();
				});
			}

			// Status filter
			const statusFilter = document.getElementById('hotel-status-filter');
			if (statusFilter) {
				statusFilter.addEventListener('change', function() {
					const url = new URL(window.location.href);
					if (this.value) {
						url.searchParams.set('status', this.value);
					} else {
						url.searchParams.delete('status');
					}
					window.location.href = url.toString();
				});
			}

			// View toggle
			const viewButtons = document.querySelectorAll('.hotel-view-btn');
			viewButtons.forEach(function(btn) {
				btn.addEventListener('click', function() {
					const view = this.dataset.view;
					const url = new URL(window.location.href);
					url.searchParams.set('view', view);
					window.location.href = url.toString();
				});
			});

			// Function to update hotel status buttons UI
			function updateHotelStatusUI(statusByHotel, videoId) {
				const activeBtn = document.getElementById('detail-set-active-btn');
				const inactiveBtn = document.getElementById('detail-set-inactive-btn');
				const messageEl = document.getElementById('detail-hotel-status-message');

				if (messageEl) {
					messageEl.classList.add('hidden');
				}

				if (activeBtn) activeBtn.dataset.videoId = videoId;
				if (inactiveBtn) inactiveBtn.dataset.videoId = videoId;

				if (statusByHotel === 'active') {
					// Active state
					if (activeBtn) {
						activeBtn.style.backgroundColor = 'rgb(240, 231, 215)';
						activeBtn.style.border = '2px solid rgb(61, 61, 68)';
						activeBtn.style.color = 'rgb(61, 61, 68)';
					}
					if (inactiveBtn) {
						inactiveBtn.style.backgroundColor = 'rgb(255, 255, 255)';
						inactiveBtn.style.border = '2px solid rgb(196, 196, 196)';
						inactiveBtn.style.color = 'rgb(122, 122, 122)';
					}
				} else {
					// Inactive state
					if (activeBtn) {
						activeBtn.style.backgroundColor = 'rgb(255, 255, 255)';
						activeBtn.style.border = '2px solid rgb(196, 196, 196)';
						activeBtn.style.color = 'rgb(122, 122, 122)';
					}
					if (inactiveBtn) {
						inactiveBtn.style.backgroundColor = 'rgb(240, 231, 215)';
						inactiveBtn.style.border = '2px solid rgb(61, 61, 68)';
						inactiveBtn.style.color = 'rgb(61, 61, 68)';
					}
				}
			}

			// Function to update status badge and button
			function updateStatusUI(status, videoId, statusByHotel) {
				const statusBadge = document.getElementById('detail-status-badge');
				const requestBtn = document.getElementById('detail-request-btn');
				const messageEl = document.getElementById('detail-request-message');
				const hotelStatusSection = document.getElementById('detail-hotel-status-section');

				if (messageEl) {
					messageEl.classList.add('hidden');
					messageEl.textContent = '';
				}

				// Show/hide hotel status section (only for assigned videos)
				if (hotelStatusSection) {
					if (status === 'active') {
						hotelStatusSection.style.display = 'block';
						updateHotelStatusUI(statusByHotel || 'active', videoId);
					} else {
						hotelStatusSection.style.display = 'none';
					}
				}

				if (statusBadge) {
					if (status === 'active') {
						statusBadge.innerHTML = '<span class="px-3 py-1 rounded" style="background-color: rgb(187, 247, 208); border: 2px solid rgb(34, 197, 94); font-family: var(--font-sans); font-size: 0.875rem; color: rgb(22, 101, 52); font-weight: 600;"><?php echo esc_js( __( 'Assigned', 'hotel-chain' ) ); ?></span>';
					} else if (status === 'pending') {
						statusBadge.innerHTML = '<span class="px-3 py-1 rounded" style="background-color: rgb(254, 249, 195); border: 2px solid rgb(234, 179, 8); font-family: var(--font-sans); font-size: 0.875rem; color: rgb(133, 77, 14); font-weight: 600;"><?php echo esc_js( __( 'Pending Approval', 'hotel-chain' ) ); ?></span>';
					} else {
						statusBadge.innerHTML = '<span class="px-3 py-1 rounded" style="background-color: rgb(226, 232, 240); border: 2px solid rgb(148, 163, 184); font-family: var(--font-sans); font-size: 0.875rem; color: rgb(71, 85, 105); font-weight: 600;"><?php echo esc_js( __( 'Not Assigned', 'hotel-chain' ) ); ?></span>';
					}
				}

				if (requestBtn) {
					requestBtn.dataset.videoId = videoId;
					if (status === 'active') {
						requestBtn.textContent = '<?php echo esc_js( __( 'Already Assigned', 'hotel-chain' ) ); ?>';
						requestBtn.disabled = true;
						requestBtn.style.opacity = '0.5';
						requestBtn.style.cursor = 'not-allowed';
					} else if (status === 'pending') {
						requestBtn.textContent = '<?php echo esc_js( __( 'Request Pending', 'hotel-chain' ) ); ?>';
						requestBtn.disabled = true;
						requestBtn.style.opacity = '0.5';
						requestBtn.style.cursor = 'not-allowed';
					} else {
						requestBtn.textContent = '<?php echo esc_js( __( 'Send Request to Admin', 'hotel-chain' ) ); ?>';
						requestBtn.disabled = false;
						requestBtn.style.opacity = '1';
						requestBtn.style.cursor = 'pointer';
					}
				}
			}

			// Function to show video detail panel
			function showVideoDetail(videoData) {
				const panel = document.getElementById('hotel-video-detail-panel');
				if (!panel || !videoData) {
					return;
				}

				// Populate panel with video data
				const titleEl = document.getElementById('detail-title');
				const descEl = document.getElementById('detail-description');
				const durationEl = document.getElementById('detail-duration');
				const categoryEl = document.getElementById('detail-category');
				const completionsEl = document.getElementById('detail-completions');
				const avgCompletionEl = document.getElementById('detail-avg-completion');

				if (titleEl) titleEl.textContent = videoData.title || '';
				if (descEl) descEl.textContent = videoData.description || '';
				if (durationEl) durationEl.textContent = videoData.duration || '0:00';
				if (categoryEl) categoryEl.textContent = videoData.category || '';
				if (completionsEl) completionsEl.textContent = videoData.total_completions || '0';
				if (avgCompletionEl) avgCompletionEl.textContent = videoData.avg_completion || '0%';

				// Update status UI
				updateStatusUI(videoData.assignment_status || 'none', videoData.video_id, videoData.status_by_hotel || 'active');

				// Handle thumbnail
				const thumbnailImg = document.getElementById('detail-thumbnail');
				const placeholderSvg = document.getElementById('detail-placeholder');
				if (videoData.thumbnail_url) {
					thumbnailImg.src = videoData.thumbnail_url;
					thumbnailImg.alt = videoData.title || '';
					thumbnailImg.classList.remove('hidden');
					placeholderSvg.classList.add('hidden');
				} else {
					thumbnailImg.classList.add('hidden');
					placeholderSvg.classList.remove('hidden');
				}

				// Show panel and scroll to it
				panel.style.display = 'block';
				setTimeout(function() {
					panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 50);
			}

			// Handle request button click
			const requestBtn = document.getElementById('detail-request-btn');
			if (requestBtn) {
				requestBtn.addEventListener('click', function() {
					if (this.disabled) return;

					const videoId = this.dataset.videoId;
					if (!videoId) return;

					const messageEl = document.getElementById('detail-request-message');
					this.disabled = true;
					this.textContent = '<?php echo esc_js( __( 'Sending...', 'hotel-chain' ) ); ?>';

					const formData = new FormData();
					formData.append('action', 'hotel_request_video');
					formData.append('video_id', videoId);
					formData.append('nonce', requestNonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							updateStatusUI('pending', videoId);
							if (messageEl) {
								messageEl.className = 'mt-3 p-3 rounded text-sm';
								messageEl.style.backgroundColor = 'rgb(187, 247, 208)';
								messageEl.style.border = '1px solid rgb(34, 197, 94)';
								messageEl.style.color = 'rgb(22, 101, 52)';
								messageEl.textContent = data.data.message;
								messageEl.classList.remove('hidden');
							}
						} else {
							this.disabled = false;
							this.textContent = '<?php echo esc_js( __( 'Send Request to Admin', 'hotel-chain' ) ); ?>';
							if (messageEl) {
								messageEl.className = 'mt-3 p-3 rounded text-sm';
								messageEl.style.backgroundColor = 'rgb(254, 202, 202)';
								messageEl.style.border = '1px solid rgb(239, 68, 68)';
								messageEl.style.color = 'rgb(153, 27, 27)';
								messageEl.textContent = data.data.message;
								messageEl.classList.remove('hidden');
							}
						}
					})
					.catch(error => {
						this.disabled = false;
						this.textContent = '<?php echo esc_js( __( 'Send Request to Admin', 'hotel-chain' ) ); ?>';
						console.error('Error:', error);
					});
				});
			}

			// Handle hotel status toggle (Active/Inactive buttons)
			function handleHotelStatusToggle(newStatus) {
				const activeBtn = document.getElementById('detail-set-active-btn');
				const videoId = activeBtn ? activeBtn.dataset.videoId : null;
				if (!videoId) return;

				const messageEl = document.getElementById('detail-hotel-status-message');

				const formData = new FormData();
				formData.append('action', 'hotel_toggle_video_status');
				formData.append('video_id', videoId);
				formData.append('status', newStatus);
				formData.append('nonce', requestNonce);

				fetch(ajaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						updateHotelStatusUI(newStatus, videoId);
						if (messageEl) {
							messageEl.className = 'mt-2 p-2 rounded text-sm';
							messageEl.style.backgroundColor = 'rgb(187, 247, 208)';
							messageEl.style.border = '1px solid rgb(34, 197, 94)';
							messageEl.style.color = 'rgb(22, 101, 52)';
							messageEl.textContent = data.data.message;
							messageEl.classList.remove('hidden');
							setTimeout(() => messageEl.classList.add('hidden'), 3000);
						}
					} else {
						if (messageEl) {
							messageEl.className = 'mt-2 p-2 rounded text-sm';
							messageEl.style.backgroundColor = 'rgb(254, 202, 202)';
							messageEl.style.border = '1px solid rgb(239, 68, 68)';
							messageEl.style.color = 'rgb(153, 27, 27)';
							messageEl.textContent = data.data.message;
							messageEl.classList.remove('hidden');
						}
					}
				})
				.catch(error => {
					console.error('Error:', error);
				});
			}

			const setActiveBtn = document.getElementById('detail-set-active-btn');
			const setInactiveBtn = document.getElementById('detail-set-inactive-btn');

			if (setActiveBtn) {
				setActiveBtn.addEventListener('click', function() {
					handleHotelStatusToggle('active');
				});
			}

			if (setInactiveBtn) {
				setInactiveBtn.addEventListener('click', function() {
					handleHotelStatusToggle('inactive');
				});
			}

			// Video card/list row click
			const videoCards = document.querySelectorAll('.hotel-video-card, .hotel-video-row');
			videoCards.forEach(function(card) {
				card.addEventListener('click', function(e) {
					if (e.target.closest('.hotel-edit-btn')) {
						return; // Let edit button handle its own click
					}
					const videoDataStr = this.dataset.videoData;
					if (videoDataStr) {
						try {
							const videoData = JSON.parse(videoDataStr);
							showVideoDetail(videoData);
						} catch (err) {
							console.error('Error parsing video data:', err);
						}
					}
				});
			});

			// Edit button click (same as card click)
			const editButtons = document.querySelectorAll('.hotel-edit-btn');
			editButtons.forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const card = this.closest('.hotel-video-card, .hotel-video-row');
					if (card) {
						const videoDataStr = card.dataset.videoData;
						if (videoDataStr) {
							try {
								const videoData = JSON.parse(videoDataStr);
								showVideoDetail(videoData);
							} catch (err) {
								console.error('Error parsing video data:', err);
							}
						}
					}
				});
			});

			// Show panel on page load if video_id is in URL (for initial page load)
			const urlParams = new URLSearchParams(window.location.search);
			const videoIdFromUrl = urlParams.get('video_id');
			if (videoIdFromUrl) {
				// Find video data from cards
				const cardWithVideo = document.querySelector('[data-video-id="' + videoIdFromUrl + '"]');
				if (cardWithVideo && cardWithVideo.dataset.videoData) {
					try {
						const videoData = JSON.parse(cardWithVideo.dataset.videoData);
						showVideoDetail(videoData);
					} catch (err) {
						console.error('Error parsing video data:', err);
					}
				}
			}
		})();
		</script>
		<?php
	}
}
