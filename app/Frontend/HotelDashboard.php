<?php
/**
 * Hotel Dashboard service provider.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Database\Schema;
use HotelChain\Support\AccountSettings;

/**
 * Hotel Dashboard menu and routing.
 */
class HotelDashboard implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'check_hotel_user' ) );
		add_action( 'wp_ajax_hotel_request_video', array( $this, 'handle_video_request' ) );
		add_action( 'wp_ajax_hotel_toggle_video_status', array( $this, 'handle_toggle_video_status' ) );
	}

	/**
	 * Handle AJAX video request from hotel.
	 *
	 * @return void
	 */
	public function handle_video_request(): void {
		check_ajax_referer( 'hotel_video_request', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		if ( ! $video_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid video.', 'hotel-chain' ) ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel            = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_send_json_error( array( 'message' => __( 'Hotel not found.', 'hotel-chain' ) ) );
		}

		$assignment_repo = new HotelVideoAssignmentRepository();

		// Check if already assigned or pending.
		$existing = $assignment_repo->get_assignment( $hotel->id, $video_id );
		if ( $existing ) {
			if ( 'active' === $existing->status ) {
				wp_send_json_error( array( 'message' => __( 'Video is already assigned.', 'hotel-chain' ) ) );
			}
			if ( 'pending' === $existing->status ) {
				wp_send_json_error( array( 'message' => __( 'Request already pending.', 'hotel-chain' ) ) );
			}
		}

		$result = $assignment_repo->request( $hotel->id, $video_id, $current_user->ID );

		if ( $result ) {
			// Auto-approve if setting is enabled.
			if ( AccountSettings::is_auto_approve_requests() ) {
				$assignment_repo->approve( $result, get_current_user_id() );
				wp_send_json_success( array( 'message' => __( 'Video access granted automatically.', 'hotel-chain' ) ) );
			} else {
				wp_send_json_success( array( 'message' => __( 'Request sent successfully.', 'hotel-chain' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send request.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Handle AJAX toggle video status by hotel.
	 *
	 * @return void
	 */
	public function handle_toggle_video_status(): void {
		check_ajax_referer( 'hotel_video_request', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$video_id   = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $video_id || ! in_array( $new_status, array( 'active', 'inactive' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel            = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_send_json_error( array( 'message' => __( 'Hotel not found.', 'hotel-chain' ) ) );
		}

		$assignment_repo = new HotelVideoAssignmentRepository();
		$result          = $assignment_repo->update_hotel_status( $hotel->id, $video_id, $new_status );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => 'active' === $new_status
						? __( 'Video activated.', 'hotel-chain' )
						: __( 'Video deactivated.', 'hotel-chain' ),
					'status'  => $new_status,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Check if current user is a hotel user and redirect if needed.
	 *
	 * @return void
	 */
	public function check_hotel_user(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			return;
		}

		// If hotel user tries to access admin dashboard, redirect to hotel dashboard.
		$screen = get_current_screen();
		if ( $screen && 'dashboard' === $screen->id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=hotel-dashboard' ) );
			exit;
		}
	}

	/**
	 * Register hotel dashboard menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Only show to hotel role users.
		if ( ! current_user_can( 'read' ) ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			return;
		}

		add_menu_page(
			__( 'Hotel Dashboard', 'hotel-chain' ),
			__( 'Dashboard', 'hotel-chain' ),
			'read',
			'hotel-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-building',
			2
		);

		// Guest Management menu.
		add_menu_page(
			__( 'Guest Management', 'hotel-chain' ),
			__( 'Guest Management', 'hotel-chain' ),
			'read',
			'hotel-guest-management',
			array( $this, 'render_guest_management' ),
			'dashicons-groups',
			4
		);

		// Note: Video Library menu is registered by VideoLibraryPage to avoid conflicts.
		// It delegates to HotelVideoLibraryPage for hotel users.
	}

	/**
	 * Render dashboard home page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hotel-chain' ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel            = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found.', 'hotel-chain' ) );
		}

		// Get logo URL from hotel logo_id.
		$logo_id  = isset( $hotel->logo_id ) ? absint( $hotel->logo_id ) : 0;
		$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

		// Get repositories.
		$guest_repo      = new GuestRepository();
		$assignment_repo = new HotelVideoAssignmentRepository();
		$video_repo      = new VideoRepository();

		// Calculate guest statistics.
		$total_guests   = $guest_repo->count_hotel_guests( $hotel->id );
		$active_guests  = $guest_repo->count_hotel_guests( $hotel->id, 'active' );
		$expired_guests = $guest_repo->count_hotel_guests( $hotel->id, 'expired' );

		// Get assigned videos.
		$assigned_videos       = $assignment_repo->get_hotel_videos( $hotel->id, array( 'status' => 'active' ) );
		$meditations_available = count( $assigned_videos );

		// Calculate total practice time (sum of view_duration from video_views table).
		global $wpdb;
		$video_views_table      = Schema::get_table_name( 'video_views' );
		$total_practice_seconds = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(view_duration) FROM {$video_views_table} WHERE hotel_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hotel->id
			)
		);
		$total_practice_hours   = $total_practice_seconds ? round( (int) $total_practice_seconds / 3600 ) : 0;

		// Get top meditations (videos with most views for this hotel in the last 7 days).
		$top_meditations_data = array();
		foreach ( $assigned_videos as $assignment ) {
			$video = $video_repo->get_by_video_id( (int) $assignment->video_id );
			if ( ! $video ) {
				continue;
			}

			// Get practice count (views) for this video in the last 7 days.
			$practice_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$video_views_table} WHERE video_id = %d AND hotel_id = %d AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$video->video_id,
					$hotel->id
				)
			);

			// Get average completion percentage (based on actual progress, not just completed views).
			$avg_completion_pct = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(AVG(completion_percentage), 0) FROM {$video_views_table} WHERE video_id = %d AND hotel_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$video->video_id,
					$hotel->id
				)
			);
			$completion_rate = round( $avg_completion_pct, 0 );

			$top_meditations_data[] = array(
				'video_id'   => $video->video_id,
				'title'      => $video->title,
				'practices'  => (int) $practice_count,
				'completion' => $completion_rate,
			);
		}

		// Sort by practice count (most practices first) and limit to 5.
		usort(
			$top_meditations_data,
			function ( $a, $b ) {
				return $b['practices'] - $a['practices'];
			}
		);
		$top_meditations_data = array_slice( $top_meditations_data, 0, 5 );

		// Get recent activity (guest registrations, video completions, and expiring guests).
		$recent_guests = $guest_repo->get_hotel_guests(
			$hotel->id,
			array(
				'status'  => 'active',
				'limit'   => 5,
				'orderby' => 'created_at',
				'order'   => 'DESC',
			)
		);

		$recent_activities = array();

		// Add guest registrations.
		foreach ( array_slice( $recent_guests, 0, 2 ) as $guest ) {
			$guest_name = trim( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) );
			if ( empty( $guest_name ) ) {
				$guest_name = __( 'Guest', 'hotel-chain' );
			}
			$recent_activities[] = array(
				'type'      => 'registration',
				/* translators: %s: Guest name */
				'message'   => sprintf( __( '%s registered for meditation series', 'hotel-chain' ), $guest_name ),
				'time'      => human_time_diff( strtotime( $guest->created_at ), time() ) . ' ago',
				'timestamp' => strtotime( $guest->created_at ),
			);
		}

		// Get recent video completions/starts.
		$video_metadata_table = Schema::get_table_name( 'video_metadata' );
		$guests_table         = Schema::get_table_name( 'guests' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$recent_views = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vv.*, v.title, u.display_name, g.first_name, g.last_name
				FROM {$video_views_table} vv
				INNER JOIN {$video_metadata_table} v ON vv.video_id = v.video_id
				LEFT JOIN {$wpdb->users} u ON vv.user_id = u.ID
				LEFT JOIN {$guests_table} g ON vv.user_id = g.user_id AND g.hotel_id = %d
				WHERE vv.hotel_id = %d
				ORDER BY vv.viewed_at DESC
				LIMIT 3",
				$hotel->id,
				$hotel->id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $recent_views as $view ) {
			$user_name = $view->display_name;
			if ( ! $user_name && $view->first_name ) {
				$user_name = trim( ( $view->first_name ?? '' ) . ' ' . ( $view->last_name ?? '' ) );
			}
			if ( ! $user_name ) {
				$user_name = __( 'Guest', 'hotel-chain' );
			}
			$recent_activities[] = array(
				'type'      => 'completion',
				/* translators: %s: User name */
				'message'   => sprintf( __( '%s began meditation series', 'hotel-chain' ), $user_name ),
				'time'      => human_time_diff( strtotime( $view->viewed_at ), time() ) . ' ago',
				'timestamp' => strtotime( $view->viewed_at ),
			);
		}

		// Check for guests expiring soon (within 3 days).
		$guests_table = Schema::get_table_name( 'guests' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$expiring_guests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$guests_table} 
				WHERE hotel_id = %d AND status = 'active' 
				AND access_end IS NOT NULL 
				AND access_end BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
				ORDER BY access_end ASC
				LIMIT 1",
				$hotel->id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $expiring_guests as $expiring_guest ) {
			$guest_name = trim( ( $expiring_guest->first_name ?? '' ) . ' ' . ( $expiring_guest->last_name ?? '' ) );
			if ( empty( $guest_name ) ) {
				$guest_name = __( 'Guest', 'hotel-chain' );
			}
			$days_left           = ceil( ( strtotime( $expiring_guest->access_end ) - time() ) / DAY_IN_SECONDS );
			$recent_activities[] = array(
				'type'      => 'expiring',
				/* translators: 1: Guest name, 2: Number of days, 3: Day/days text */
				'message'   => sprintf( __( 'Guest account expiring soon: %1$s (%2$d %3$s)', 'hotel-chain' ), $guest_name, $days_left, _n( 'day', 'days', $days_left, 'hotel-chain' ) ),
				'time'      => human_time_diff( strtotime( $expiring_guest->access_end ), time() ) . ' ago',
				'timestamp' => strtotime( $expiring_guest->access_end ),
			);
		}

		// Sort activities by timestamp (most recent first) and limit to 3.
		usort(
			$recent_activities,
			function ( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);
		$recent_activities = array_slice( $recent_activities, 0, 3 );

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
						<h1><?php esc_html_e( 'HOTEL – Dashboard', 'hotel-chain' ); ?></h1>
						<p class="text-slate-600"><?php esc_html_e( 'Overview metrics and quick links for hotel administrators', 'hotel-chain' ); ?></p>
					</div>
				</div>



				<div class="space-y-6">
					<!-- Statistics Cards -->
					<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-8 h-8" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
								</svg>
								<p>Total Guests</p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $total_guests ) ); ?></h2>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-8 h-8" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
									<path d="M20 2v4"></path>
									<path d="M22 4h-4"></path>
									<circle cx="4" cy="20" r="2"></circle>
								</svg>
								<p>Active Guests</p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $active_guests ) ); ?></h2>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-heart w-8 h-8" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M11 14h2a2 2 0 0 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"></path>
									<path d="m14.45 13.39 5.05-4.694C20.196 8 21 6.85 21 5.75a2.75 2.75 0 0 0-4.797-1.837.276.276 0 0 1-.406 0A2.75 2.75 0 0 0 11 5.75c0 1.2.802 2.248 1.5 2.946L16 11.95"></path>
									<path d="m2 15 6 6"></path>
									<path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a1 1 0 0 0-2.75-2.91"></path>
								</svg>
								<p>Expired Guests</p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $expired_guests ) ); ?></h2>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-8 h-8" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<p>Total Practice Time</p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $total_practice_hours ) ); ?> hrs</h2>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 w-8 h-8" aria-hidden="true" style="color: rgb(196, 196, 196);">
									<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
									<circle cx="12" cy="8" r="2"></circle>
									<path d="M12 10v12"></path>
									<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
									<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
								</svg>
								<p>Meditations Available</p>
							</div>
							<h2><?php echo esc_html( number_format_i18n( $meditations_available ) ); ?></h2>
						</div>
					</div>

					<!-- Quick Links -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400">QUICK LINKS</h3>
						<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-video-library' ) ); ?>" class="border border-solid border-gray-400 rounded p-6 text-center hover:bg-gray-50 cursor-pointer no-underline">
								<div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center bg-primary">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 w-8 h-8" aria-hidden="true" style="color: rgb(60, 56, 55);">
										<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
										<circle cx="12" cy="8" r="2"></circle>
										<path d="M12 10v12"></path>
										<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
										<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
									</svg>
								</div>
								<p class="mb-2 text-black">Meditation Library</p>
								<p class="text-gray-600">
								<?php
								/* translators: %d: Number of meditations */
								echo esc_html( sprintf( _n( '%d meditation', '%d meditations', $meditations_available, 'hotel-chain' ), $meditations_available ) );
								?>
								</p>
							</a>

							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-profile' ) ); ?>" class="border border-solid border-gray-400 rounded p-6 text-center hover:bg-gray-50 cursor-pointer no-underline">
								<div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center bg-primary">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-column w-8 h-8" aria-hidden="true" style="color: rgb(60, 56, 55);">
										<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
										<path d="M18 17V9"></path>
										<path d="M13 17V5"></path>
										<path d="M8 17v-3"></path>
									</svg>
								</div>
								<p class="mb-2 text-black">Analytics</p>
								<p class="text-gray-600">View reports</p>
							</a>

							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-profile' ) ); ?>" class="border border-solid border-gray-400 rounded p-6 text-center hover:bg-gray-50 cursor-pointer no-underline">
								<div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center bg-primary">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings w-8 h-8" aria-hidden="true" style="color: rgb(60, 56, 55);">
										<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"></path>
										<circle cx="12" cy="12" r="3"></circle>
									</svg>
								</div>
								<p class="mb-2 text-black">Hotel Profile</p>
								<p class="text-gray-600">Settings</p>
							</a>
						</div>
					</div>

					<!-- Top Meditations This Week -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400">TOP MEDITATIONS THIS WEEK</h3>
						<div class="space-y-3">
							<?php if ( ! empty( $top_meditations_data ) ) : ?>
								<?php foreach ( $top_meditations_data as $meditation ) : ?>
									<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border border-solid border-gray-400 rounded p-4">
										<div class="flex items-center gap-4 flex-1">
											<div class="w-16 h-16 rounded flex items-center justify-center flex-shrink-0 bg-primary">
												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 w-8 h-8" aria-hidden="true" style="color: rgb(60, 56, 55);">
													<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
													<circle cx="12" cy="8" r="2"></circle>
													<path d="M12 10v12"></path>
													<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
													<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
												</svg>
											</div>
											<div class="flex-1 min-w-0">
													<p class="mb-2 text-black"><?php echo esc_html( $meditation['title'] ); ?></p>
												<p class="text-gray-600">
												<?php
												/* translators: %d: Number of practices */
												echo esc_html( sprintf( _n( '%d practice', '%d practices', $meditation['practices'], 'hotel-chain' ), $meditation['practices'] ) );
												?>
												</p>
											</div>
										</div>
										<div class="text-left sm:text-right">
											<p class="mb-2 text-black"><?php echo esc_html( $meditation['completion'] ); ?>% completion</p>
										</div>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="text-center py-8 text-gray-500">
									<p><?php esc_html_e( 'No meditation data available yet.', 'hotel-chain' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Recent Activity -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400">RECENT ACTIVITY</h3>
						<div class="space-y-3">
							<?php if ( ! empty( $recent_activities ) ) : ?>
								<?php foreach ( $recent_activities as $activity ) : ?>
									<div class="p-4 rounded-l border-l-4 flex items-start gap-3 bg-primary">
										<?php if ( 'registration' === $activity['type'] ) : ?>
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-5 h-5 flex-shrink-0" aria-hidden="true" style="color: rgb(60, 56, 55); margin-top: 2px;">
												<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
											</svg>
										<?php elseif ( 'expiring' === $activity['type'] ) : ?>
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-5 h-5 flex-shrink-0" aria-hidden="true" style="color: rgb(60, 56, 55); margin-top: 2px;">
												<path d="M12 2v8"></path>
												<path d="m4.93 10.93 1.41 1.41"></path>
												<path d="M2 18h2"></path>
												<path d="M20 18h2"></path>
												<path d="m19.07 10.93-1.41 1.41"></path>
												<path d="M22 22H2"></path>
												<path d="m8 6 4-4 4 4"></path>
												<path d="M16 18a4 4 0 0 0-8 0"></path>
											</svg>
										<?php else : ?>
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-5 h-5 flex-shrink-0" aria-hidden="true" style="color: rgb(60, 56, 55); margin-top: 2px;">
												<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
												<path d="M20 2v4"></path>
												<path d="M22 4h-4"></path>
												<circle cx="4" cy="20" r="2"></circle>
											</svg>
										<?php endif; ?>
										<div class="flex-1">
											<p class="mb-2 text-black"><?php echo esc_html( $activity['message'] ); ?></p>
											<p class="text-gray-600"><?php echo esc_html( $activity['time'] ); ?></p>
										</div>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="text-center py-8 text-gray-500">
									<p><?php esc_html_e( 'No recent activity.', 'hotel-chain' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render video library page (delegates to HotelVideoLibraryPage).
	 *
	 * @return void
	 */
	public function render_video_library(): void {
		$library_page = new \HotelChain\Frontend\HotelVideoLibraryPage();
		$library_page->render_page();
	}

	/**
	 * Render guest management page.
	 *
	 * @return void
	 */
	public function render_guest_management(): void {
		$management_page = new \HotelChain\Frontend\HotelGuestManagementPage();
		$management_page->render_page();
	}
}
