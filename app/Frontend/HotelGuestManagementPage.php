<?php
/**
 * Hotel Guest Management page.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;
use HotelChain\Database\Schema;
use HotelChain\Support\AccountSettings;

/**
 * Hotel Guest Management page for hotel users.
 */
class HotelGuestManagementPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_hotel_export_guests', array( $this, 'handle_export_guests' ) );
		add_action( 'wp_ajax_hotel_resend_guest_email', array( $this, 'ajax_resend_guest_email' ) );
		add_action( 'wp_ajax_hotel_extend_guest_access', array( $this, 'ajax_extend_guest_access' ) );
		add_action( 'wp_ajax_hotel_deactivate_guest', array( $this, 'ajax_deactivate_guest' ) );
		add_action( 'wp_ajax_hotel_reactivate_guest', array( $this, 'ajax_reactivate_guest' ) );
	}
	/**
	 * Render the guest management page.
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

		$guest_repository = new GuestRepository();
		$video_repository = new VideoRepository();
		$assignment_repo = new HotelVideoAssignmentRepository();

		// Get filter/search parameters.
		$search_query = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_guest_id = isset( $_GET['guest_id'] ) ? absint( $_GET['guest_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		// Debug: Log guest_id if present.
		if ( $selected_guest_id && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Hotel Guest Management: Selected guest_id = ' . $selected_guest_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Get all guests for this hotel.
		$all_guests = $guest_repository->get_hotel_guests( $hotel->id, array( 'limit' => -1 ) );

		// Calculate statistics.
		$total_guests = count( $all_guests );
		$active_guests = 0;
		$expiring_soon = 0;
		$expired_guests = 0;
		$locked_guests = 0;

		$expiry_warning_days = AccountSettings::get_expiry_warning_period();
		$now = current_time( 'mysql' );
		$warning_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_warning_days} days" ) );

		foreach ( $all_guests as $guest ) {
			if ( 'active' === $guest->status ) {
				++$active_guests;
				if ( $guest->access_end && $guest->access_end > $now && $guest->access_end <= $warning_date ) {
					++$expiring_soon;
				}
			} elseif ( 'expired' === $guest->status || ( $guest->access_end && $guest->access_end <= $now ) ) {
				++$expired_guests;
			} elseif ( 'revoked' === $guest->status || 'locked' === $guest->status ) {
				++$locked_guests;
			}
		}

		// Filter guests based on search and status.
		$filtered_guests = array();
		foreach ( $all_guests as $guest ) {
			// Apply search filter.
			if ( ! empty( $search_query ) ) {
				$search_in = strtolower( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) . ' ' . ( $guest->email ?? '' ) );
				if ( stripos( $search_in, strtolower( $search_query ) ) === false ) {
					continue;
				}
			}

			// Apply status filter.
			if ( ! empty( $status_filter ) ) {
				$guest_status = $guest->status;
				if ( 'expiring_soon' === $status_filter ) {
					if ( 'active' !== $guest_status || ! $guest->access_end || $guest->access_end <= $now || $guest->access_end > $warning_date ) {
						continue;
					}
				} elseif ( $status_filter !== $guest_status ) {
					continue;
				}
			}

			$filtered_guests[] = $guest;
		}

		// Get video views data for guests.
		global $wpdb;
		$video_views_table = Schema::get_table_name( 'video_views' );
		$video_metadata_table = Schema::get_table_name( 'video_metadata' );

		// Get last active times and video counts for each guest.
		$guest_stats = array();
		foreach ( $filtered_guests as $guest ) {
			if ( ! $guest->user_id ) {
				$guest_stats[ $guest->id ] = array(
					'last_active' => null,
					'video_count' => 0,
					'practice_time' => 0,
				);
				continue;
			}

			// Get last active time.
			$last_active = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(viewed_at) FROM {$video_views_table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel->id,
					$guest->user_id
				)
			);

			// Get video count (distinct videos watched).
			$video_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT video_id) FROM {$video_views_table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel->id,
					$guest->user_id
				)
			);

			// Get total practice time.
			$practice_time = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(view_duration), 0) FROM {$video_views_table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel->id,
					$guest->user_id
				)
			);

			$guest_stats[ $guest->id ] = array(
				'last_active' => $last_active,
				'video_count' => $video_count,
				'practice_time' => $practice_time,
			);
		}

		// Get selected guest details if provided.
		$selected_guest = null;
		$selected_guest_videos = array();
		if ( $selected_guest_id ) {
			// Search in all_guests, not filtered_guests, to ensure we find the guest even if filters are applied.
			foreach ( $all_guests as $guest ) {
				if ( (int) $guest->id === (int) $selected_guest_id ) {
					$selected_guest = $guest;
					break;
				}
			}
			
			// If not found in all_guests, try to get directly from repository.
			if ( ! $selected_guest ) {
				$selected_guest = $guest_repository->get_by_id( $selected_guest_id );
				// Verify the guest belongs to this hotel.
				if ( $selected_guest && (int) $selected_guest->hotel_id !== (int) $hotel->id ) {
					$selected_guest = null;
				}
			}

			if ( $selected_guest && $selected_guest->user_id ) {
				// Get assigned videos for this hotel.
				$assigned_videos = $assignment_repo->get_hotel_videos( $hotel->id, array( 'status' => 'active' ) );
				$assigned_video_ids = array();
				foreach ( $assigned_videos as $assignment ) {
					$assigned_video_ids[] = (int) $assignment->video_id;
				}

				// Get video progress for this guest.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$selected_guest_videos = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT 
							vm.video_id,
							vm.title,
							MAX(vv.viewed_at) as last_viewed,
							MAX(vv.completion_percentage) as completion_percentage,
							MAX(vv.completed) as completed,
							SUM(vv.view_duration) as total_duration
						FROM {$video_metadata_table} vm
						INNER JOIN {$video_views_table} vv ON vm.video_id = vv.video_id
						WHERE vv.hotel_id = %d AND vv.user_id = %d AND vm.video_id IN (" . implode( ',', array_map( 'intval', $assigned_video_ids ) ) . ")
						GROUP BY vm.video_id
						ORDER BY last_viewed DESC",
						$hotel->id,
						$selected_guest->user_id
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$this->render_html( $hotel, $filtered_guests, $guest_stats, $total_guests, $active_guests, $expiring_soon, $expired_guests, $locked_guests, $search_query, $status_filter, $selected_guest, $selected_guest_videos );
	}

	

	/**
	 * Render the HTML for the guest management page.
	 *
	 * @param object $hotel Hotel object.
	 * @param array  $guests Filtered guests array.
	 * @param array  $guest_stats Guest statistics array.
	 * @param int    $total_guests Total guests count.
	 * @param int    $active_guests Active guests count.
	 * @param int    $expiring_soon Expiring soon count.
	 * @param int    $expired_guests Expired guests count.
	 * @param int    $locked_guests Locked guests count.
	 * @param string $search_query Search query.
	 * @param string $status_filter Status filter.
	 * @param object|null $selected_guest Selected guest object.
	 * @param array  $selected_guest_videos Selected guest video progress.
	 * @return void
	 */
	private function render_html( $hotel, $guests, $guest_stats, $total_guests, $active_guests, $expiring_soon, $expired_guests, $locked_guests, $search_query, $status_filter, $selected_guest, $selected_guest_videos ): void {
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce = wp_create_nonce( 'hotel_guest_management' );
		$export_url = admin_url( 'admin-post.php?action=hotel_export_guests&hotel_id=' . $hotel->id . '&_wpnonce=' . wp_create_nonce( 'hotel_export_guests_' . $hotel->id ) );
		
		// Get logo URL from hotel.
		$logo_id  = isset( $hotel->logo_id ) ? absint( $hotel->logo_id ) : 0;
		$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
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
						<h1><?php esc_html_e( 'HOTEL – Guest Management', 'hotel-chain' ); ?></h1>
						<p class="text-slate-600"><?php esc_html_e( 'Manage guest accounts and meditation access', 'hotel-chain' ); ?></p>
					</div>
				</div>

				<div class="space-y-6">
					<!-- Search and Filter Bar -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<form method="get" action="" class="flex items-center justify-between gap-4 flex-wrap">
							<input type="hidden" name="page" value="hotel-guest-management">
							<?php if ( $selected_guest_id ) : ?>
								<input type="hidden" name="guest_id" value="<?php echo esc_attr( $selected_guest_id ); ?>">
							<?php endif; ?>
							<div class="flex-1 flex items-center gap-3 border border-solid border-gray-400 rounded px-4 py-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search w-5 h-5" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="m21 21-4.34-4.34"></path>
									<circle cx="11" cy="11" r="8"></circle>
								</svg>
								<input type="text" name="search" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search guests by name or email...', 'hotel-chain' ); ?>" class="flex-1 border-none outline-none bg-transparent text-gray-600">
								<?php if ( ! empty( $search_query ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-guest-management' . ( $selected_guest_id ? '&guest_id=' . $selected_guest_id : '' ) . ( ! empty( $status_filter ) ? '&status=' . urlencode( $status_filter ) : '' ) ) ); ?>" class="text-gray-400 hover:text-gray-600" title="<?php esc_attr_e( 'Clear search', 'hotel-chain' ); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x">
											<path d="M18 6L6 18"></path>
											<path d="M6 6l12 12"></path>
										</svg>
									</a>
								<?php endif; ?>
								<button type="submit" class="px-3 py-1 rounded transition-all hover:opacity-90 bg-secondary text-white border-none cursor-pointer" title="<?php esc_attr_e( 'Search', 'hotel-chain' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search">
										<path d="m21 21-4.34-4.34"></path>
										<circle cx="11" cy="11" r="8"></circle>
									</svg>
								</button>
							</div>
							<div class="flex items-center gap-2 border border-solid border-gray-400 rounded px-4 py-2">
								<span class="text-gray-600"><?php esc_html_e( 'Status:', 'hotel-chain' ); ?></span>
								<select name="status" onchange="this.form.submit()" class="border-none outline-none bg-transparent cursor-pointer text-gray-600">
									<option value=""><?php esc_html_e( 'All', 'hotel-chain' ); ?></option>
									<option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'hotel-chain' ); ?></option>
									<option value="expiring_soon" <?php selected( $status_filter, 'expiring_soon' ); ?>><?php esc_html_e( 'Expiring Soon', 'hotel-chain' ); ?></option>
									<option value="expired" <?php selected( $status_filter, 'expired' ); ?>><?php esc_html_e( 'Expired', 'hotel-chain' ); ?></option>
									<option value="revoked" <?php selected( $status_filter, 'revoked' ); ?>><?php esc_html_e( 'Locked', 'hotel-chain' ); ?></option>
								</select>
							</div>
							<?php
							// Build export URL with current filters.
							$export_params = array(
								'action' => 'hotel_export_guests',
								'hotel_id' => $hotel->id,
								'_wpnonce' => wp_create_nonce( 'hotel_export_guests_' . $hotel->id ),
							);
							if ( ! empty( $search_query ) ) {
								$export_params['search'] = $search_query;
							}
							if ( ! empty( $status_filter ) ) {
								$export_params['status'] = $status_filter;
							}
							$export_url = admin_url( 'admin-post.php?' . http_build_query( $export_params ) );
							
							// Build clear filters URL.
							$clear_url = admin_url( 'admin.php?page=hotel-guest-management' );
							if ( $selected_guest_id ) {
								$clear_url = add_query_arg( 'guest_id', $selected_guest_id, $clear_url );
							}
							?>
							<?php if ( ! empty( $search_query ) || ! empty( $status_filter ) ) : ?>
								<a href="<?php echo esc_url( $clear_url ); ?>" class="px-4 py-2 rounded transition-all hover:opacity-90 bg-white border border-solid border-gray-400 text-gray-600 text-decoration-none">
									<?php esc_html_e( 'Clear Filters', 'hotel-chain' ); ?>
								</a>
							<?php endif; ?>
							<a href="<?php echo esc_url( $export_url ); ?>" class="px-4 py-2 rounded transition-all hover:opacity-90 bg-secondary text-white border-none text-decoration-none"><?php esc_html_e( 'Export CSV', 'hotel-chain' ); ?></a>
						</form>
					</div>

					<!-- Statistics Cards -->
					<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-6 h-6" aria-hidden="true">
									<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
								</svg>
								<p class="text-black"><?php esc_html_e( 'Total Guests', 'hotel-chain' ); ?></p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $total_guests ) ); ?></h2>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-6 h-6" aria-hidden="true">
									<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
									<path d="M20 2v4"></path>
									<path d="M22 4h-4"></path>
									<circle cx="4" cy="20" r="2"></circle>
								</svg>
								<p class="text-black"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $active_guests ) ); ?></h2>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-6 h-6" aria-hidden="true">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<p class="text-black"><?php esc_html_e( 'Expiring Soon', 'hotel-chain' ); ?></p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $expiring_soon ) ); ?></h2>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-6 h-6" aria-hidden="true">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<p class="text-black"><?php esc_html_e( 'Expired', 'hotel-chain' ); ?></p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $expired_guests ) ); ?></h2>
						</div>
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-6 h-6" aria-hidden="true">
									<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
								</svg>
								<p class="text-black"><?php esc_html_e( 'Locked', 'hotel-chain' ); ?></p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $locked_guests ) ); ?></h2>
						</div>
					</div>

					<!-- Guests Table -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<div class="border border-solid border-gray-400 rounded overflow-x-auto">
							<div class="min-w-[800px]">
								<!-- Table Header -->
								<div class="bg-gray-200 border-b border-solid border-gray-400 grid grid-cols-7 gap-4 p-3">
									<div class="col-span-2 font-semibold"><?php esc_html_e( 'Guest', 'hotel-chain' ); ?></div>
									<div class="text-black font-semibold"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></div>
									<div class="text-black font-semibold"><?php esc_html_e( 'Series Access', 'hotel-chain' ); ?></div>
									<div class="text-black font-semibold"><?php esc_html_e( 'Last Active', 'hotel-chain' ); ?></div>
									<div class="text-black font-semibold"><?php esc_html_e( 'Access Expires', 'hotel-chain' ); ?></div>
									<div class="text-black font-semibold"><?php esc_html_e( 'Actions', 'hotel-chain' ); ?></div>
								</div>

								<!-- Table Rows -->
								<?php if ( empty( $guests ) ) : ?>
									<div class="p-8 text-center text-gray-600">
										<?php esc_html_e( 'No guests found.', 'hotel-chain' ); ?>
									</div>
								<?php else : ?>
									<?php foreach ( $guests as $guest ) : ?>
										<?php
										$guest_name = trim( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) );
										if ( empty( $guest_name ) ) {
											$guest_name = __( 'Guest', 'hotel-chain' );
										}
										$initials = strtoupper( substr( $guest->first_name ?? 'G', 0, 1 ) . substr( $guest->last_name ?? '', 0, 1 ) );
										if ( strlen( $initials ) < 2 ) {
											$initials = strtoupper( substr( $guest->email ?? 'G', 0, 1 ) );
										}

										$stats = $guest_stats[ $guest->id ] ?? array();
										$last_active = $stats['last_active'] ?? null;
										$video_count = $stats['video_count'] ?? 0;
										$practice_time = $stats['practice_time'] ?? 0;

										// Determine status display.
										$status = $guest->status;
										$expiry_warning_days = AccountSettings::get_expiry_warning_period();
										$now = current_time( 'mysql' );
										$warning_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_warning_days} days" ) );

										if ( 'active' === $status && $guest->access_end && $guest->access_end > $now && $guest->access_end <= $warning_date ) {
											$status_display = 'expiring_soon';
										} else {
											$status_display = $status;
										}

										$status_classes = array(
											'active' => 'bg-green-200 border border-green-600 text-green-900',
											'expiring_soon' => 'bg-orange-200 border border-orange-600 text-orange-900',
											'expired' => 'bg-red-200 border border-red-600 text-red-900',
											'revoked' => 'bg-yellow-200 border border-yellow-600 text-yellow-900',
											'locked' => 'bg-purple-200 border border-purple-600 text-purple-900',
										);

										$status_labels = array(
											'active' => __( 'Active', 'hotel-chain' ),
											'expiring_soon' => __( 'Expiring Soon', 'hotel-chain' ),
											'expired' => __( 'Expired', 'hotel-chain' ),
											'revoked' => __( 'Locked', 'hotel-chain' ),
											'locked' => __( 'Locked', 'hotel-chain' ),
										);

										$status_style = $status_classes[ $status_display ] ?? $status_classes['active'];
										$status_label = $status_labels[ $status_display ] ?? ucfirst( $status_display );

										// Format last active.
										$last_active_display = __( 'Never', 'hotel-chain' );
										if ( $last_active ) {
											$last_active_ts = strtotime( $last_active );
											$last_active_display = human_time_diff( $last_active_ts, time() ) . ' ' . __( 'ago', 'hotel-chain' );
										}

										// Format access start and expires.
										$access_start_display = __( 'Never', 'hotel-chain' );
										if ( $guest->access_start ) {
											$access_start_display = date_i18n( 'M j, Y', strtotime( $guest->access_start ) );
										}
										$access_expires_display = __( 'Never', 'hotel-chain' );
										if ( $guest->access_end ) {
											$access_expires_display = date_i18n( 'M j, Y', strtotime( $guest->access_end ) );
										}
										?>
										<div class="grid grid-cols-7 gap-4 p-3 border-b border-solid border-gray-400 last:border-b-0 hover:bg-gray-50 cursor-pointer guest-row" data-guest-id="<?php echo esc_attr( $guest->id ); ?>">
											<div class="col-span-2 flex items-center gap-3">
												<div class="w-10 h-10 rounded-full flex items-center justify-center bg-primary border border-gray-400">
													<span class="text-primary font-semibold"><?php echo esc_html( $initials ); ?></span>
												</div>
												<div>
													<div class="text-black"><?php echo esc_html( $guest_name ); ?></div>
													<div class="flex items-center gap-1 text-gray-600 text-sm">
														<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail w-3 h-3" aria-hidden="true">
															<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
															<rect x="2" y="4" width="20" height="16" rx="2"></rect>
														</svg>
														<?php echo esc_html( $guest->email ?? '' ); ?>
													</div>
												</div>
											</div>
											<div class="flex items-center">
												<span class="px-3 py-1 rounded <?php echo esc_attr( $status_style ); ?> text-sm font-semibold"><?php echo esc_html( $status_label ); ?></span>
											</div>
											<div class="flex items-center">
												<span class="px-3 py-1 rounded flex items-center gap-2 bg-primary border border-gray-400 text-primary text-sm font-semibold">
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-4 h-4" aria-hidden="true" style="color: rgb(122, 122, 122);">
														<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
														<circle cx="12" cy="8" r="2"></circle>
														<path d="M12 10v12"></path>
														<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
														<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
													</svg>
													<?php
													/* translators: %d: number of meditations */
													echo esc_html( sprintf( _n( '%d meditation', '%d meditations', $video_count, 'hotel-chain' ), $video_count ) );
													?>
												</span>
											</div>
											<div class="flex items-center text-gray-600">
												<?php echo esc_html( $last_active_display ); ?>
											</div>
											<div class="flex flex-col items-start">
												<div class="text-gray-800 text-sm mb-2">
													<?php echo esc_html( $access_start_display ); ?>
												</div>
												<div class="text-gray-800 text-sm">
													<?php echo esc_html( $access_expires_display ); ?>
												</div>
											</div>
											<div class="flex items-center gap-2">
												<button class="p-1 border border-solid border-gray-400 rounded hover:bg-gray-100 guest-view-analytics" data-guest-id="<?php echo esc_attr( $guest->id ); ?>" title="<?php esc_attr_e( 'View Analytics', 'hotel-chain' ); ?>">
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-4 h-4" aria-hidden="true" style="color: rgb(122, 122, 122);">
														<path d="M12 2v8"></path>
														<path d="m4.93 10.93 1.41 1.41"></path>
														<path d="M2 18h2"></path>
														<path d="M20 18h2"></path>
														<path d="m19.07 10.93-1.41 1.41"></path>
														<path d="M22 22H2"></path>
														<path d="m8 6 4-4 4 4"></path>
														<path d="M16 18a4 4 0 0 0-8 0"></path>
													</svg>
												</button>
												<button class="p-1 border border-solid border-gray-400 rounded hover:bg-gray-100 guest-resend-email" data-guest-id="<?php echo esc_attr( $guest->id ); ?>" title="<?php esc_attr_e( 'Resend Email', 'hotel-chain' ); ?>">
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail w-4 h-4" aria-hidden="true" style="color: rgb(122, 122, 122);">
														<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
														<rect x="2" y="4" width="20" height="16" rx="2"></rect>
													</svg>
												</button>
												<?php if ( 'revoked' === $guest->status || 'locked' === $guest->status ) : ?>
													<button class="p-1 border border-solid border-gray-400 rounded hover:bg-gray-100 guest-reactivate-row" data-guest-id="<?php echo esc_attr( $guest->id ); ?>" title="<?php esc_attr_e( 'Reactivate Account', 'hotel-chain' ); ?>">
														<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-unlock w-4 h-4" aria-hidden="true" style="color: rgb(122, 122, 122);">
															<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
															<path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
														</svg>
													</button>
												<?php else : ?>
													<button class="p-1 border border-solid border-gray-400 rounded hover:bg-gray-100 guest-deactivate-row" data-guest-id="<?php echo esc_attr( $guest->id ); ?>" title="<?php esc_attr_e( 'Deactivate Account', 'hotel-chain' ); ?>">
														<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock w-4 h-4" aria-hidden="true" style="color: rgb(122, 122, 122);">
															<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
															<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
														</svg>
													</button>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Guest Detail Panel -->
					<?php if ( $selected_guest ) : ?>
						<div id="guest-detail-panel">
							<?php $this->render_guest_detail_panel( $selected_guest, $selected_guest_videos, $guest_stats[ $selected_guest->id ] ?? array() ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<script>
		(function() {
			const ajaxUrl = '<?php echo esc_url( $ajax_url ); ?>';
			const nonce = '<?php echo esc_js( $nonce ); ?>';

			// Search input - submit on Enter key.
			const searchInput = document.querySelector('input[name="search"]');
			if (searchInput) {
				searchInput.addEventListener('keypress', function(e) {
					if (e.key === 'Enter') {
						e.preventDefault();
						this.form.submit();
					}
				});
			}

			// Scroll to detail panel if it exists.
			<?php if ( $selected_guest ) : ?>
			window.addEventListener('load', function() {
				const detailPanel = document.getElementById('guest-detail-panel');
				if (detailPanel) {
					// Make sure panel is visible.
					detailPanel.style.display = 'block';
					setTimeout(function() {
						detailPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}, 100);
				} else {
					console.error('Guest detail panel not found in DOM');
				}
			});
			<?php else : ?>
			// Debug: Check if guest_id is in URL but guest not found.
			<?php if ( $selected_guest_id ) : ?>
			console.warn('Guest ID <?php echo esc_js( (string) $selected_guest_id ); ?> was requested but guest not found');
			<?php endif; ?>
			<?php endif; ?>

			// Helper function to build URL with query parameters.
			function buildGuestUrl(guestId) {
				let url = '<?php echo esc_url( admin_url( 'admin.php?page=hotel-guest-management' ) ); ?>';
				url += '&guest_id=' + encodeURIComponent(guestId);
				// Preserve existing search and status filters.
				<?php if ( ! empty( $search_query ) ) : ?>
				url += '&search=' + encodeURIComponent('<?php echo esc_js( $search_query ); ?>');
				<?php endif; ?>
				<?php if ( ! empty( $status_filter ) ) : ?>
				url += '&status=' + encodeURIComponent('<?php echo esc_js( $status_filter ); ?>');
				<?php endif; ?>
				return url;
			}

			// Handle guest row click to show detail panel.
			document.querySelectorAll('.guest-row').forEach(function(row) {
				row.addEventListener('click', function(e) {
					// Don't trigger if clicking on action buttons.
					if (e.target.closest('button')) {
						return;
					}
					const guestId = this.getAttribute('data-guest-id');
					if (guestId) {
						console.log('Opening guest detail panel for guest_id:', guestId);
						const url = buildGuestUrl(guestId);
						console.log('Navigating to:', url);
						window.location.href = url;
					} else {
						console.error('No guest_id found on row');
					}
				});
			});

			// Handle view analytics button.
			document.querySelectorAll('.guest-view-analytics').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const guestId = this.getAttribute('data-guest-id');
					if (guestId) {
						window.location.href = buildGuestUrl(guestId);
					}
				});
			});

			// Handle resend email button.
			document.querySelectorAll('.guest-resend-email, .guest-resend-access-email').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const guestId = this.getAttribute('data-guest-id');
					if (!guestId) return;

					const originalText = this.textContent || this.innerHTML;
					this.disabled = true;
					if (this.textContent) {
						this.textContent = '<?php echo esc_js( __( 'Sending...', 'hotel-chain' ) ); ?>';
					}

					const formData = new FormData();
					formData.append('action', 'hotel_resend_guest_email');
					formData.append('guest_id', guestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Email sent successfully!', 'hotel-chain' ) ); ?>');
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Failed to send email.', 'hotel-chain' ) ); ?>');
						}
						this.disabled = false;
						if (this.textContent) {
							this.textContent = originalText;
						} else {
							this.innerHTML = originalText;
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						if (this.textContent) {
							this.textContent = originalText;
						} else {
							this.innerHTML = originalText;
						}
					});
				});
			});

			// Handle extend access button - show form.
			document.querySelectorAll('.guest-extend-access-btn').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const container = this.closest('.extend-access-container');
					if (!container) return;
					
					const form = container.querySelector('.extend-access-form');
					const message = container.querySelector('.extend-access-message');
					if (form) {
						form.classList.remove('hidden');
						if (message) {
							message.textContent = '';
							message.className = 'extend-access-message mt-2 text-sm';
						}
						// Focus on input
						const input = container.querySelector('.extend-access-days');
						if (input) {
							setTimeout(function() {
								input.focus();
								input.select();
							}, 100);
						}
					}
				});
			});

			// Handle extend access submit.
			document.querySelectorAll('.extend-access-submit').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const container = this.closest('.extend-access-container');
					if (!container) return;
					
					const guestId = container.getAttribute('data-guest-id');
					const input = container.querySelector('.extend-access-days');
					const message = container.querySelector('.extend-access-message');
					const submitBtn = this;
					const cancelBtn = container.querySelector('.extend-access-cancel');
					const form = container.querySelector('.extend-access-form');
					
					if (!guestId || !input) return;
					
					const days = parseInt(input.value);
					if (!days || isNaN(days) || days <= 0) {
						if (message) {
							message.textContent = '<?php echo esc_js( __( 'Please enter a valid number of days.', 'hotel-chain' ) ); ?>';
							message.style.color = 'rgb(220, 38, 38)';
						}
						return;
					}

					submitBtn.disabled = true;
					if (cancelBtn) cancelBtn.disabled = true;
					if (input) input.disabled = true;
					const originalText = submitBtn.textContent || submitBtn.innerHTML;
					if (submitBtn.textContent) {
						submitBtn.textContent = '<?php echo esc_js( __( 'Extending...', 'hotel-chain' ) ); ?>';
					}
					
					if (message) {
						message.textContent = '';
						message.className = 'extend-access-message mt-2 text-sm';
					}

					const formData = new FormData();
					formData.append('action', 'hotel_extend_guest_access');
					formData.append('guest_id', guestId);
					formData.append('days', days);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							if (message) {
								message.textContent = data.data.message || '<?php echo esc_js( __( 'Access extended successfully!', 'hotel-chain' ) ); ?>';
								message.style.color = 'rgb(34, 197, 94)';
							}
							// Hide form after success
							if (form) {
								setTimeout(function() {
									form.classList.add('hidden');
									if (input) input.value = '30';
								}, 2000);
							}
							// Reload page to update display after a short delay
							setTimeout(function() {
								window.location.reload();
							}, 2000);
						} else {
							if (message) {
								message.textContent = data.data?.message || '<?php echo esc_js( __( 'Failed to extend access.', 'hotel-chain' ) ); ?>';
								message.style.color = 'rgb(220, 38, 38)';
							}
							submitBtn.disabled = false;
							if (cancelBtn) cancelBtn.disabled = false;
							if (input) input.disabled = false;
							if (submitBtn.textContent) {
								submitBtn.textContent = originalText;
							} else {
								submitBtn.innerHTML = originalText;
							}
						}
					})
					.catch(error => {
						console.error('Error:', error);
						if (message) {
							message.textContent = '<?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?>';
							message.style.color = 'rgb(220, 38, 38)';
						}
						submitBtn.disabled = false;
						if (cancelBtn) cancelBtn.disabled = false;
						if (input) input.disabled = false;
						if (submitBtn.textContent) {
							submitBtn.textContent = originalText;
						} else {
							submitBtn.innerHTML = originalText;
						}
					});
				});
			});

			// Handle extend access cancel.
			document.querySelectorAll('.extend-access-cancel').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const container = this.closest('.extend-access-container');
					if (!container) return;
					
					const form = container.querySelector('.extend-access-form');
					const message = container.querySelector('.extend-access-message');
					const input = container.querySelector('.extend-access-days');
					
					if (form) {
						form.classList.add('hidden');
					}
					if (message) {
						message.textContent = '';
						message.className = 'extend-access-message mt-2 text-sm';
					}
					if (input) {
						input.value = '30';
					}
				});
			});

			// Handle Enter key in extend access input.
			document.querySelectorAll('.extend-access-days').forEach(function(input) {
				input.addEventListener('keypress', function(e) {
					if (e.key === 'Enter') {
						e.preventDefault();
						const container = this.closest('.extend-access-container');
						if (!container) return;
						const submitBtn = container.querySelector('.extend-access-submit');
						if (submitBtn && !submitBtn.disabled) {
							submitBtn.click();
						}
					}
				});
			});

			// Handle deactivate button.
			document.querySelectorAll('.guest-deactivate').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const guestId = this.getAttribute('data-guest-id');
					if (!guestId) return;

					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to deactivate this guest account?', 'hotel-chain' ) ); ?>')) {
						return;
					}

					this.disabled = true;
					const originalText = this.textContent || this.innerHTML;
					if (this.textContent) {
						this.textContent = '<?php echo esc_js( __( 'Deactivating...', 'hotel-chain' ) ); ?>';
					}

					const formData = new FormData();
					formData.append('action', 'hotel_deactivate_guest');
					formData.append('guest_id', guestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Guest account deactivated successfully.', 'hotel-chain' ) ); ?>');
							// Reload page to update display.
							window.location.reload();
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Failed to deactivate account.', 'hotel-chain' ) ); ?>');
							this.disabled = false;
							if (this.textContent) {
								this.textContent = originalText;
							} else {
								this.innerHTML = originalText;
							}
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						if (this.textContent) {
							this.textContent = originalText;
						} else {
							this.innerHTML = originalText;
						}
					});
				});
			});

			// Handle reactivate button.
			document.querySelectorAll('.guest-reactivate').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const guestId = this.getAttribute('data-guest-id');
					if (!guestId) return;

					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to reactivate this guest account?', 'hotel-chain' ) ); ?>')) {
						return;
					}

					this.disabled = true;
					const originalText = this.textContent || this.innerHTML;
					if (this.textContent) {
						this.textContent = '<?php echo esc_js( __( 'Reactivating...', 'hotel-chain' ) ); ?>';
					}

					const formData = new FormData();
					formData.append('action', 'hotel_reactivate_guest');
					formData.append('guest_id', guestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Guest account reactivated successfully.', 'hotel-chain' ) ); ?>');
							// Reload page to update display.
							window.location.reload();
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Failed to reactivate account.', 'hotel-chain' ) ); ?>');
							this.disabled = false;
							if (this.textContent) {
								this.textContent = originalText;
							} else {
								this.innerHTML = originalText;
							}
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						if (this.textContent) {
							this.textContent = originalText;
						} else {
							this.innerHTML = originalText;
						}
					});
				});
			});

			// Handle deactivate button in table row.
			document.querySelectorAll('.guest-deactivate-row').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const guestId = this.getAttribute('data-guest-id');
					if (!guestId) return;

					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to deactivate this guest account?', 'hotel-chain' ) ); ?>')) {
						return;
					}

					this.disabled = true;
					const originalTitle = this.getAttribute('title');
					this.setAttribute('title', '<?php echo esc_js( __( 'Deactivating...', 'hotel-chain' ) ); ?>');

					const formData = new FormData();
					formData.append('action', 'hotel_deactivate_guest');
					formData.append('guest_id', guestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Guest account deactivated successfully.', 'hotel-chain' ) ); ?>');
							window.location.reload();
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Failed to deactivate account.', 'hotel-chain' ) ); ?>');
							this.disabled = false;
							this.setAttribute('title', originalTitle);
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						this.setAttribute('title', originalTitle);
					});
				});
			});

			// Handle reactivate button in table row.
			document.querySelectorAll('.guest-reactivate-row').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					const guestId = this.getAttribute('data-guest-id');
					if (!guestId) return;

					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to reactivate this guest account?', 'hotel-chain' ) ); ?>')) {
						return;
					}

					this.disabled = true;
					const originalTitle = this.getAttribute('title');
					this.setAttribute('title', '<?php echo esc_js( __( 'Reactivating...', 'hotel-chain' ) ); ?>');

					const formData = new FormData();
					formData.append('action', 'hotel_reactivate_guest');
					formData.append('guest_id', guestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Guest account reactivated successfully.', 'hotel-chain' ) ); ?>');
							window.location.reload();
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Failed to reactivate account.', 'hotel-chain' ) ); ?>');
							this.disabled = false;
							this.setAttribute('title', originalTitle);
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						this.setAttribute('title', originalTitle);
					});
				});
			});

			// Handle more actions button.
			document.querySelectorAll('.guest-more-actions').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					// More actions can be added here (e.g., dropdown menu).
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render guest detail panel.
	 *
	 * @param object $guest Guest object.
	 * @param array  $videos Video progress array.
	 * @param array  $stats Guest statistics.
	 * @return void
	 */
	private function render_guest_detail_panel( $guest, $videos, $stats ): void {
		$guest_name = trim( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) );
		if ( empty( $guest_name ) ) {
			$guest_name = __( 'Guest', 'hotel-chain' );
		}
		$initials = strtoupper( substr( $guest->first_name ?? 'G', 0, 1 ) . substr( $guest->last_name ?? '', 0, 1 ) );
		if ( strlen( $initials ) < 2 ) {
			$initials = strtoupper( substr( $guest->email ?? 'G', 0, 1 ) );
		}

		$last_active = $stats['last_active'] ?? null;
		$practice_time = $stats['practice_time'] ?? 0;
		$practice_hours = round( $practice_time / 3600, 1 );

		$last_active_display = __( 'Never', 'hotel-chain' );
		if ( $last_active ) {
			$last_active_ts = strtotime( $last_active );
			$last_active_display = human_time_diff( $last_active_ts, time() ) . ' ' . __( 'ago', 'hotel-chain' );
		}

		$access_expires_display = __( 'Never', 'hotel-chain' );
		if ( $guest->access_end ) {
			$access_expires_display = date_i18n( 'M j, Y', strtotime( $guest->access_end ) );
		}

		// Calculate completion stats.
		$completed_count = 0;
		$total_completion = 0;
		$total_sessions = 0;
		foreach ( $videos as $video ) {
			if ( $video->completed ) {
				++$completed_count;
			}
			$total_completion += (int) $video->completion_percentage;
			++$total_sessions;
		}
		$avg_completion = $total_sessions > 0 ? round( $total_completion / $total_sessions ) : 0;

		// Format duration helper.
		$format_duration = function( $seconds ) {
			if ( ! $seconds ) {
				return '0:00';
			}
			$mins = floor( $seconds / 60 );
			$secs = $seconds % 60;
			return sprintf( '%d:%02d', $mins, $secs );
		};
		?>
		<div class="bg-white rounded p-4 border border-solid border-gray-400">
			<div class="mb-4 pb-3 border-b border-solid border-gray-400">
				<h3 class="text-gray-800"><?php esc_html_e( 'Guest Detail Panel', 'hotel-chain' ); ?></h3>
			</div>
			<div class="bg-white border border-solid border-gray-400 rounded p-6">
				<div class="grid grid-cols-3 gap-6">
					<div class="col-span-1">
						<div class="flex items-center gap-3 mb-4">
							<div class="w-16 h-16 rounded-full flex items-center justify-center bg-primary border border-gray-400">
								<span class="text-primary text-xl font-semibold"><?php echo esc_html( $initials ); ?></span>
							</div>
							<div>
								<div class="mb-1 text-black font-semibold"><?php echo esc_html( $guest_name ); ?></div>
								<div class="text-gray-600 text-sm"><?php echo esc_html( $guest->email ?? '' ); ?></div>
							</div>
						</div>
						<div class="space-y-3 mb-4">
							<div class="flex justify-between">
								<span class="text-gray-600 text-sm"><?php esc_html_e( 'Status:', 'hotel-chain' ); ?></span>
								<span class="px-2 py-1 rounded bg-primary text-primary text-sm font-semibold"><?php echo esc_html( ucfirst( $guest->status ) ); ?></span>
							</div>
							<div class="flex justify-between">
								<span class="text-gray-600 text-sm"><?php esc_html_e( 'Last Active:', 'hotel-chain' ); ?></span>
								<span class="text-gray-800 text-sm"><?php echo esc_html( $last_active_display ); ?></span>
							</div>
							<div class="flex justify-between">
								<span class="text-gray-600 text-sm"><?php esc_html_e( 'Access Expires:', 'hotel-chain' ); ?></span>
								<span class="text-gray-800 text-sm"><?php echo esc_html( $access_expires_display ); ?></span>
							</div>
							<div class="flex justify-between">
								<span class="text-gray-600 text-sm"><?php esc_html_e( 'Practice Time:', 'hotel-chain' ); ?></span>
								<span class="text-gray-800 text-sm"><?php echo esc_html( $practice_hours ); ?> <?php esc_html_e( 'hours', 'hotel-chain' ); ?></span>
							</div>
						</div>
						<div class="space-y-2">
							<button class="w-full px-4 py-2 rounded flex items-center justify-center gap-2 transition-all hover:opacity-90 bg-primary text-primary border border-gray-400 guest-resend-access-email" data-guest-id="<?php echo esc_attr( $guest->id ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail w-4 h-4" aria-hidden="true">
									<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
									<rect x="2" y="4" width="20" height="16" rx="2"></rect>
								</svg>
								<?php esc_html_e( 'Resend Access Email', 'hotel-chain' ); ?>
							</button>
							<div class="w-full extend-access-container" data-guest-id="<?php echo esc_attr( $guest->id ); ?>">
								<button class="w-full px-4 py-2 rounded flex items-center justify-center gap-2 transition-all hover:opacity-80 bg-white border border-gray-400 text-gray-800 guest-extend-access-btn">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-heart w-4 h-4" aria-hidden="true">
										<path d="M11 14h2a2 2 0 0 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"></path>
										<path d="m14.45 13.39 5.05-4.694C20.196 8 21 6.85 21 5.75a2.75 2.75 0 0 0-4.797-1.837.276.276 0 0 1-.406 0A2.75 2.75 0 0 0 11 5.75c0 1.2.802 2.248 1.5 2.946L16 11.95"></path>
										<path d="m2 15 6 6"></path>
										<path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a1 1 0 0 0-2.75-2.91"></path>
									</svg>
									<?php esc_html_e( 'Extend Access', 'hotel-chain' ); ?>
								</button>
								<div class="extend-access-form hidden mt-2">
									<div class="flex gap-2">
										<input type="number" min="1" value="30" class="extend-access-days flex-1 px-3 py-2 border border-gray-400 rounded" placeholder="<?php esc_attr_e( 'Days', 'hotel-chain' ); ?>">
										<button class="extend-access-submit px-4 py-2 rounded transition-all hover:opacity-90 bg-primary text-primary border border-gray-400">
											<?php esc_html_e( 'Extend', 'hotel-chain' ); ?>
										</button>
										<button class="extend-access-cancel px-4 py-2 rounded transition-all hover:opacity-90 bg-white border border-gray-400 text-gray-600">
											<?php esc_html_e( 'Cancel', 'hotel-chain' ); ?>
										</button>
									</div>
									<div class="extend-access-message mt-2 text-sm text-gray-600"></div>
								</div>
							</div>
							<?php if ( 'revoked' === $guest->status || 'locked' === $guest->status ) : ?>
									<button class="w-full px-4 py-2 rounded flex items-center justify-center gap-2 transition-all hover:opacity-80 bg-primary text-primary border border-gray-400 guest-reactivate" data-guest-id="<?php echo esc_attr( $guest->id ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-unlock w-4 h-4" aria-hidden="true">
										<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
										<path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
									</svg>
									<?php esc_html_e( 'Reactivate Account', 'hotel-chain' ); ?>
								</button>
							<?php else : ?>
								<button class="w-full px-4 py-2 rounded flex items-center justify-center gap-2 transition-all hover:opacity-80 bg-white border border-gray-400 text-gray-600 guest-deactivate" data-guest-id="<?php echo esc_attr( $guest->id ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-4 h-4" aria-hidden="true">
										<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
									</svg>
									<?php esc_html_e( 'Deactivate Account', 'hotel-chain' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
					<div class="col-span-2">
						<div class="mb-4">
							<h3 class="mb-3 pb-2 border-b border-solid border-gray-400 text-gray-800 text-lg font-semibold">
								<?php
								/* translators: %d: number of meditations */
								echo esc_html( sprintf( __( 'Meditation Progress (%d meditations)', 'hotel-chain' ), count( $videos ) ) );
								?>
							</h3>
							<div class="space-y-2">
								<?php if ( empty( $videos ) ) : ?>
									<div class="p-4 text-center text-gray-600">
										<?php esc_html_e( 'No meditation progress yet.', 'hotel-chain' ); ?>
									</div>
								<?php else : ?>
									<?php foreach ( $videos as $video ) : ?>
										<?php
										$completion = (int) $video->completion_percentage;
										$is_completed = (bool) $video->completed;
										$status_text = $is_completed ? __( 'Completed', 'hotel-chain' ) : ( $completion > 0 ? __( 'In Progress', 'hotel-chain' ) : __( 'Not Started', 'hotel-chain' ) );
										$duration_display = $format_duration( (int) $video->total_duration );
										?>
										<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
											<div class="flex items-center gap-3">
												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-5 h-5" aria-hidden="true">
													<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
													<circle cx="12" cy="8" r="2"></circle>
													<path d="M12 10v12"></path>
													<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
													<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
												</svg>
												<div>
														<div class="text-black"><?php echo esc_html( $video->title ?? __( 'Unknown', 'hotel-chain' ) ); ?></div>
													<div class="text-gray-600 text-sm">
														<?php
														/* translators: %s: practice time */
														echo esc_html( sprintf( __( 'Practice time: %s', 'hotel-chain' ), $duration_display ) );
														?>
													</div>
												</div>
											</div>
											<div class="text-right">
												<div class="mb-1 text-black"><?php echo esc_html( $status_text ); ?></div>
												<div class="text-gray-600 text-sm"><?php echo esc_html( $completion ); ?>%</div>
											</div>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
						<div class="mt-4 p-4 rounded bg-primary border border-solid border-gray-400">
							<h4 class="mb-3 text-black font-semibold"><?php esc_html_e( 'Practice Summary', 'hotel-chain' ); ?></h4>
							<div class="grid grid-cols-3 gap-4">
								<div>
									<div class="text-gray-600 text-sm"><?php esc_html_e( 'Completed', 'hotel-chain' ); ?></div>
									<div class="text-black text-xl font-semibold"><?php echo esc_html( $completed_count ); ?> / <?php echo esc_html( count( $videos ) ); ?></div>
								</div>
								<div>
									<div class="text-gray-600 text-sm"><?php esc_html_e( 'Avg. Completion', 'hotel-chain' ); ?></div>
									<div class="text-black text-xl font-semibold"><?php echo esc_html( $avg_completion ); ?>%</div>
								</div>
								<div>
									<div class="text-gray-600 text-sm"><?php esc_html_e( 'Total Sessions', 'hotel-chain' ); ?></div>
									<div class
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle CSV export of guests.
	 *
	 * @return void
	 */
	public function handle_export_guests(): void {
		// Ensure user is logged in.
		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			wp_die( esc_html__( 'Unable to verify user.', 'hotel-chain' ) );
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user instanceof \WP_User ) {
			wp_die( esc_html__( 'Unable to verify user.', 'hotel-chain' ) );
		}

		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'Unauthorized access. Hotel role required. User roles: ' . implode( ', ', $current_user->roles ), 'hotel-chain' ) );
		}

		$hotel_id = isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $hotel_id ) {
			wp_die( esc_html__( 'Invalid hotel ID.', 'hotel-chain' ) );
		}

		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_user_id( $current_user_id );
		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found for this user. User ID: ' . $current_user_id, 'hotel-chain' ) );
		}
		$logo_id  = isset( $hotel->logo_id ) ? absint( $hotel->logo_id ) : 0;
		$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found for this user. User ID: ' . $current_user_id, 'hotel-chain' ) );
		}

		if ( (int) $hotel->id !== (int) $hotel_id ) {
			wp_die( esc_html__( 'Unauthorized access. Hotel ID mismatch. Expected: ' . $hotel->id . ', Got: ' . $hotel_id, 'hotel-chain' ) );
		}

		// Verify nonce after we have the hotel_id.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $nonce ) {
			wp_die( esc_html__( 'Security nonce missing.', 'hotel-chain' ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'hotel_export_guests_' . $hotel_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'hotel-chain' ) );
		}

		// Get filter parameters.
		$search_query = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$guest_repo = new GuestRepository();
		$all_guests = $guest_repo->get_hotel_guests( $hotel_id, array( 'limit' => -1 ) );

		// Apply the same filters as the page.
		$expiry_warning_days = AccountSettings::get_expiry_warning_period();
		$now = current_time( 'mysql' );
		$warning_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_warning_days} days" ) );

		// Get video views data for statistics.
		global $wpdb;
		$video_views_table = Schema::get_table_name( 'video_views' );

		$filtered_guests = array();
		foreach ( $all_guests as $guest ) {
			// Apply search filter.
			if ( ! empty( $search_query ) ) {
				$search_in = strtolower( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) . ' ' . ( $guest->email ?? '' ) );
				if ( stripos( $search_in, strtolower( $search_query ) ) === false ) {
					continue;
				}
			}

			// Apply status filter.
			if ( ! empty( $status_filter ) ) {
				$guest_status = $guest->status;
				if ( 'expiring_soon' === $status_filter ) {
					if ( 'active' !== $guest_status || ! $guest->access_end || $guest->access_end <= $now || $guest->access_end > $warning_date ) {
						continue;
					}
				} elseif ( $status_filter !== $guest_status ) {
					continue;
				}
			}

			$filtered_guests[] = $guest;
		}

		// Set headers for CSV download.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		$filename = 'guests-' . sanitize_file_name( $hotel->hotel_name ) . '-' . date( 'Y-m-d' );
		if ( ! empty( $search_query ) || ! empty( $status_filter ) ) {
			$filename .= '-filtered';
		}
		header( 'Content-Disposition: attachment; filename=' . $filename . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output BOM for UTF-8.
		echo "\xEF\xBB\xBF";

		$output = fopen( 'php://output', 'w' );

		// CSV headers.
		fputcsv(
			$output,
			array(
				__( 'Guest Code', 'hotel-chain' ),
				__( 'First Name', 'hotel-chain' ),
				__( 'Last Name', 'hotel-chain' ),
				__( 'Email', 'hotel-chain' ),
				__( 'Phone', 'hotel-chain' ),
				__( 'Status', 'hotel-chain' ),
				__( 'Access Start', 'hotel-chain' ),
				__( 'Access End', 'hotel-chain' ),
				__( 'Last Active', 'hotel-chain' ),
				__( 'Videos Watched', 'hotel-chain' ),
				__( 'Practice Time (Hours)', 'hotel-chain' ),
				__( 'Created At', 'hotel-chain' ),
			)
		);

		// CSV rows.
		foreach ( $filtered_guests as $guest ) {
			// Get last active time.
			$last_active = null;
			$video_count = 0;
			$practice_time = 0;

			if ( $guest->user_id ) {
				$last_active = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT MAX(viewed_at) FROM {$video_views_table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$hotel_id,
						$guest->user_id
					)
				);

				$video_count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT video_id) FROM {$video_views_table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$hotel_id,
						$guest->user_id
					)
				);

				$practice_time_seconds = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(view_duration), 0) FROM {$video_views_table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$hotel_id,
						$guest->user_id
					)
				);
				$practice_time = round( $practice_time_seconds / 3600, 2 );
			}

			// Format dates.
			$access_start_formatted = $guest->access_start ? date_i18n( 'Y-m-d H:i:s', strtotime( $guest->access_start ) ) : '';
			$access_end_formatted = $guest->access_end ? date_i18n( 'Y-m-d H:i:s', strtotime( $guest->access_end ) ) : '';
			$last_active_formatted = $last_active ? date_i18n( 'Y-m-d H:i:s', strtotime( $last_active ) ) : '';
			$created_at_formatted = $guest->created_at ? date_i18n( 'Y-m-d H:i:s', strtotime( $guest->created_at ) ) : '';

			fputcsv(
				$output,
				array(
					$guest->guest_code ?? '',
					$guest->first_name ?? '',
					$guest->last_name ?? '',
					$guest->email ?? '',
					$guest->phone ?? '',
					ucfirst( $guest->status ?? '' ),
					$access_start_formatted,
					$access_end_formatted,
					$last_active_formatted,
					$video_count,
					$practice_time,
					$created_at_formatted,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX handler to resend guest access email.
	 *
	 * @return void
	 */
	public function ajax_resend_guest_email(): void {
		check_ajax_referer( 'hotel_guest_management', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$guest_id = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		if ( ! $guest_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid guest ID.', 'hotel-chain' ) ) );
		}

		$guest_repo = new GuestRepository();
		$guest      = $guest_repo->get_by_id( $guest_id );

		if ( ! $guest ) {
			wp_send_json_error( array( 'message' => __( 'Guest not found.', 'hotel-chain' ) ) );
		}

		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );

		// Verify the hotel belongs to the current user.
		$user_hotel = $hotel_repo->get_by_user_id( $current_user->ID );
		if ( ! $hotel || ! $user_hotel || $hotel->id !== $user_hotel->id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		// Generate new verification token if needed.
		if ( ! $guest->verification_token ) {
			$new_token = wp_generate_password( 32, false );
			$guest_repo->update( $guest_id, array( 'verification_token' => $new_token ) );
		} else {
			$new_token = $guest->verification_token;
		}

		// Send email.
		$sent = $this->send_access_email( $guest, $hotel, $new_token );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Access email sent successfully!', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send email. Please try again.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * AJAX handler to extend guest access.
	 *
	 * @return void
	 */
	public function ajax_extend_guest_access(): void {
		check_ajax_referer( 'hotel_guest_management', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$guest_id = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$days     = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;

		if ( ! $guest_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid guest ID.', 'hotel-chain' ) ) );
		}

		$guest_repo = new GuestRepository();
		$guest      = $guest_repo->get_by_id( $guest_id );

		if ( ! $guest ) {
			wp_send_json_error( array( 'message' => __( 'Guest not found.', 'hotel-chain' ) ) );
		}

		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );

		// Verify the hotel belongs to the current user.
		$user_hotel = $hotel_repo->get_by_user_id( $current_user->ID );
		if ( ! $hotel || ! $user_hotel || $hotel->id !== $user_hotel->id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		// Calculate new access_end date.
		$current_end = $guest->access_end ? strtotime( $guest->access_end ) : time();
		$new_end     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$days} days", $current_end ) );

		$updated = $guest_repo->update( $guest_id, array( 'access_end' => $new_end ) );

		if ( $updated ) {
			wp_send_json_success(
				array(
					'message'     => __( 'Access extended successfully!', 'hotel-chain' ),
					'new_end'    => date_i18n( 'M j, Y', strtotime( $new_end ) ),
					'new_end_raw' => $new_end,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to extend access. Please try again.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * AJAX handler to deactivate guest account.
	 *
	 * @return void
	 */
	public function ajax_deactivate_guest(): void {
		check_ajax_referer( 'hotel_guest_management', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$guest_id = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		if ( ! $guest_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid guest ID.', 'hotel-chain' ) ) );
		}

		$guest_repo = new GuestRepository();
		$guest      = $guest_repo->get_by_id( $guest_id );

		if ( ! $guest ) {
			wp_send_json_error( array( 'message' => __( 'Guest not found.', 'hotel-chain' ) ) );
		}

		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );

		// Verify the hotel belongs to the current user.
		$user_hotel = $hotel_repo->get_by_user_id( $current_user->ID );
		if ( ! $hotel || ! $user_hotel || $hotel->id !== $user_hotel->id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$updated = $guest_repo->update( $guest_id, array( 'status' => 'revoked' ) );

		if ( $updated ) {
			wp_send_json_success( array( 'message' => __( 'Guest account deactivated successfully.', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to deactivate account. Please try again.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * AJAX handler to reactivate guest account.
	 *
	 * @return void
	 */
	public function ajax_reactivate_guest(): void {
		check_ajax_referer( 'hotel_guest_management', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$guest_id = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		if ( ! $guest_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid guest ID.', 'hotel-chain' ) ) );
		}

		$guest_repo = new GuestRepository();
		$guest      = $guest_repo->get_by_id( $guest_id );

		if ( ! $guest ) {
			wp_send_json_error( array( 'message' => __( 'Guest not found.', 'hotel-chain' ) ) );
		}

		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );

		// Verify the hotel belongs to the current user.
		$user_hotel = $hotel_repo->get_by_user_id( $current_user->ID );
		if ( ! $hotel || ! $user_hotel || $hotel->id !== $user_hotel->id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		// Check if access_end is still valid, otherwise set status based on that.
		$now = current_time( 'mysql' );
		$new_status = 'active';
		
		// If access_end has passed, set to expired instead of active.
		if ( $guest->access_end && $guest->access_end <= $now ) {
			$new_status = 'expired';
		}

		$updated = $guest_repo->update( $guest_id, array( 'status' => $new_status ) );

		if ( $updated ) {
			$message = 'expired' === $new_status 
				? __( 'Guest account reactivated, but access has expired. Please extend access.', 'hotel-chain' )
				: __( 'Guest account reactivated successfully.', 'hotel-chain' );
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reactivate account. Please try again.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Send access email to guest.
	 *
	 * @param object $guest Guest object.
	 * @param object $hotel Hotel object.
	 * @param string $token Verification token.
	 * @return bool
	 */
	private function send_access_email( $guest, $hotel, string $token ): bool {
		$verify_url = add_query_arg( 'token', $token, home_url( '/verify-email' ) );

		/* translators: %s: Hotel name */
		$subject = sprintf( __( 'Your meditation access - %s', 'hotel-chain' ), $hotel->hotel_name );

		$message = sprintf(
			__( "Hello %1\$s,\n\nWelcome to %2\$s!\n\nPlease click the link below to verify your email address and activate your meditation access:\n\n%3\$s\n\nThis link will expire in 24 hours.\n\nIf you didn't request this access, you can safely ignore this email.\n\nThank you!", 'hotel-chain' ),
			trim( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) ) ?: __( 'Guest', 'hotel-chain' ),
			$hotel->hotel_name,
			$verify_url
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $guest->email, $subject, $message, $headers );
	}
}

