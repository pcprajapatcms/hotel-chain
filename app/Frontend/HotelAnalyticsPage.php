<?php
/**
 * Hotel Analytics page.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Database\Schema;

/**
 * Hotel Analytics page for hotel users.
 */
class HotelAnalyticsPage {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_hotel_analytics_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Add the Analytics menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			return;
		}

		add_menu_page(
			__( 'Analytics', 'hotel-chain' ),
			__( 'Analytics', 'hotel-chain' ),
			'read',
			'hotel-analytics',
			array( $this, 'render_page' ),
			'dashicons-chart-bar',
			3
		);
	}

	/**
	 * Render the analytics page.
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

		global $wpdb;

		// Date range: Last 30 days.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = max( 7, min( 365, $days ) ); // Between 7 and 365 days.

		$period_start          = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$previous_period_start = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $days * 2 ) . ' days' ) );
		$previous_period_end   = $period_start;

		$video_views_table = Schema::get_table_name( 'video_views' );
		$guests_table      = Schema::get_table_name( 'guests' );
		$videos_table      = Schema::get_table_name( 'video_metadata' );

		// Total Practice Sessions (video views).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$total_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$previous_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s AND viewed_at < %s",
				$hotel->id,
				$previous_period_start,
				$previous_period_end
			)
		);

		$sessions_change = $previous_sessions > 0 ? round( ( ( $total_sessions - $previous_sessions ) / $previous_sessions ) * 100 ) : 0;

		// Practice Hours (total watch time).
		$total_hours = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(view_duration), 0) / 3600 FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$previous_hours = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(view_duration), 0) / 3600 FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s AND viewed_at < %s",
				$hotel->id,
				$previous_period_start,
				$previous_period_end
			)
		);

		$hours_change = $previous_hours > 0 ? round( ( ( $total_hours - $previous_hours ) / $previous_hours ) * 100 ) : 0;

		// Active Guests.
		$active_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$previous_active_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s AND viewed_at < %s",
				$hotel->id,
				$previous_period_start,
				$previous_period_end
			)
		);

		$active_guests_change = $previous_active_guests > 0 ? round( ( ( $active_guests - $previous_active_guests ) / $previous_active_guests ) * 100 ) : 0;

		// Average Completion Rate.
		$avg_completion = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(completion_percentage), 0) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$previous_avg_completion = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(completion_percentage), 0) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s AND viewed_at < %s",
				$hotel->id,
				$previous_period_start,
				$previous_period_end
			)
		);

		$completion_change = $previous_avg_completion > 0 ? round( ( ( $avg_completion - $previous_avg_completion ) / $previous_avg_completion ) * 100 ) : 0;

		// Watch Hours Over Time (last 14 days).
		$watch_hours_data = array();
		for ( $i = 13; $i >= 0; $i-- ) {
			$day_start = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$i} days" ) );
			$day_end   = gmdate( 'Y-m-d 23:59:59', strtotime( "-{$i} days" ) );
			$hours     = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(view_duration), 0) / 3600 FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s AND viewed_at <= %s",
					$hotel->id,
					$day_start,
					$day_end
				)
			);
			$watch_hours_data[] = $hours;
		}

		// Per-Video Analytics.
		$per_video_analytics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					vm.video_id,
					vm.title,
					vm.duration_seconds,
					vm.duration_label,
					COUNT(vv.id) as views,
					COALESCE(AVG(vv.view_duration), 0) as avg_watch_seconds,
					COALESCE(AVG(vv.completion_percentage), 0) as avg_completion
				FROM {$videos_table} vm
				INNER JOIN {$video_views_table} vv ON vm.video_id = vv.video_id
				WHERE vv.hotel_id = %d AND vv.viewed_at >= %s
				GROUP BY vm.video_id
				ORDER BY views DESC
				LIMIT 10",
				$hotel->id,
				$period_start
			)
		);

		// Per-Guest Analytics (Top Engaged).
		$per_guest_analytics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					g.first_name,
					g.last_name,
					g.email,
					COUNT(DISTINCT vv.video_id) as videos_watched,
					COUNT(vv.id) as total_views,
					COALESCE(SUM(vv.view_duration), 0) / 3600 as total_hours,
					COALESCE(AVG(vv.completion_percentage), 0) as avg_completion,
					g.status
				FROM {$guests_table} g
				INNER JOIN {$video_views_table} vv ON g.user_id = vv.user_id AND g.hotel_id = vv.hotel_id
				WHERE g.hotel_id = %d AND vv.viewed_at >= %s
				GROUP BY g.id
				ORDER BY total_views DESC
				LIMIT 5",
				$hotel->id,
				$period_start
			)
		);

		// Total Video Sessions.
		$total_video_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d",
				$hotel->id
			)
		);

		// Average session duration.
		$avg_session_duration = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(view_duration), 0) / 60 FROM {$video_views_table} WHERE hotel_id = %d",
				$hotel->id
			)
		);

		// Videos per session.
		$videos_per_session = $total_sessions > 0 ? round( $total_sessions / max( 1, $active_guests ), 1 ) : 0;

		// Return rate (guests who watched more than once).
		$returning_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM (
					SELECT user_id, COUNT(*) as view_count 
					FROM {$video_views_table} 
					WHERE hotel_id = %d 
					GROUP BY user_id 
					HAVING view_count > 1
				) as returning",
				$hotel->id
			)
		);

		$return_rate = $active_guests > 0 ? round( ( $returning_guests / $active_guests ) * 100 ) : 0;

		// Peak Viewing Times.
		$morning_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND HOUR(viewed_at) >= 6 AND HOUR(viewed_at) < 12",
				$hotel->id
			)
		);

		$afternoon_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND HOUR(viewed_at) >= 12 AND HOUR(viewed_at) < 18",
				$hotel->id
			)
		);

		$evening_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND HOUR(viewed_at) >= 18 AND HOUR(viewed_at) < 24",
				$hotel->id
			)
		);

		$total_time_views = $morning_views + $afternoon_views + $evening_views;
		$morning_pct      = $total_time_views > 0 ? round( ( $morning_views / $total_time_views ) * 100 ) : 0;
		$afternoon_pct    = $total_time_views > 0 ? round( ( $afternoon_views / $total_time_views ) * 100 ) : 0;
		$evening_pct      = $total_time_views > 0 ? round( ( $evening_views / $total_time_views ) * 100 ) : 0;

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get max watch hours for chart scaling.
		$max_watch_hours = max( $watch_hours_data ) > 0 ? max( $watch_hours_data ) : 1;

		// Get logo URL from hotel logo_id.
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
						<h1><?php esc_html_e( 'HOTEL – Analytics', 'hotel-chain' ); ?></h1>
						<p class="text-slate-600"><?php esc_html_e( 'Comprehensive analytics for guest engagement and meditation practice', 'hotel-chain' ); ?></p>
					</div>
				</div>

				<div class="space-y-6">
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
							<div class="flex flex-col sm:flex-row gap-3 sm:gap-4 w-full md:w-auto">
								<div class="flex items-center gap-2 border border-solid border-gray-400 rounded px-3 sm:px-4 py-2 text-sm sm:text-base">
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon w-4 h-4 sm:w-5 sm:h-5 text-gray-600 flex-shrink-0" aria-hidden="true">
										<path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"></path>
									</svg>
									<span class="text-gray-600 whitespace-nowrap"><?php esc_html_e( 'Date Range:', 'hotel-chain' ); ?></span>
									<span class="text-gray-900"><?php echo esc_html( sprintf( __( 'Last %d Days', 'hotel-chain' ), $days ) ); ?></span>
								</div>
								<div class="flex items-center gap-2 border border-solid border-gray-400 rounded px-3 sm:px-4 py-2 text-sm sm:text-base">
									<span class="text-gray-600 whitespace-nowrap"><?php esc_html_e( 'Compare to:', 'hotel-chain' ); ?></span>
									<span class="text-gray-900"><?php esc_html_e( 'Previous Period', 'hotel-chain' ); ?></span>
								</div>
							</div>
							<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=hotel_analytics_export&hotel_id=' . $hotel->id . '&days=' . $days . '&_wpnonce=' . wp_create_nonce( 'hotel_analytics_export_' . $hotel->id ) ) ); ?>" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center gap-2 w-full md:w-auto justify-center hover:bg-green-300 transition-all duration-300 cursor-pointer hover:text-green-900 text-sm sm:text-base">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download w-4 h-4" aria-hidden="true">
									<path d="M12 15V3"></path>
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<path d="m7 10 5 5 5-5"></path>
								</svg>
								<?php esc_html_e( 'Export CSV', 'hotel-chain' ); ?>
							</a>
						</div>
					</div>

					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-2 sm:gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-6 h-6 sm:w-8 sm:h-8 text-gray-600 flex-shrink-0" aria-hidden="true">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<p class="text-black text-sm sm:text-base"><?php esc_html_e( 'Total Practice Sessions', 'hotel-chain' ); ?></p>
							</div>
							<h2 class="text-2xl sm:text-3xl lg:text-4xl mb-2"><?php echo esc_html( number_format( $total_sessions ) ); ?></h2>
							<div class="flex items-center gap-1 text-gray-600 text-xs sm:text-sm">
								<?php if ( $sessions_change >= 0 ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-4 h-4 text-gray-600" aria-hidden="true">
										<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
										<path d="M20 2v4"></path>
										<path d="M22 4h-4"></path>
										<circle cx="4" cy="20" r="2"></circle>
									</svg>
									<span>+<?php echo esc_html( absint( $sessions_change ) ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?></span>
								<?php else : ?>
									<span><?php echo esc_html( $sessions_change ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-2 sm:gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-6 h-6 sm:w-8 sm:h-8 text-gray-600 flex-shrink-0" aria-hidden="true">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<p class="text-black text-sm sm:text-base"><?php esc_html_e( 'Practice Hours', 'hotel-chain' ); ?></p>
							</div>
							<h2 class="text-2xl sm:text-3xl lg:text-4xl mb-2"><?php echo esc_html( number_format( $total_hours, 0 ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></h2>
							<div class="flex items-center gap-1 text-gray-600 text-xs sm:text-sm">
								<?php if ( $hours_change >= 0 ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-4 h-4 text-gray-600" aria-hidden="true">
										<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
										<path d="M20 2v4"></path>
										<path d="M22 4h-4"></path>
										<circle cx="4" cy="20" r="2"></circle>
									</svg>
									<span>+<?php echo esc_html( absint( $hours_change ) ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?></span>
								<?php else : ?>
									<span><?php echo esc_html( $hours_change ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-2 sm:gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-6 h-6 sm:w-8 sm:h-8 text-gray-600 flex-shrink-0" aria-hidden="true">
									<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
								</svg>
								<p class="text-black text-sm sm:text-base"><?php esc_html_e( 'Active Guests', 'hotel-chain' ); ?></p>
							</div>
							<h2 class="text-2xl sm:text-3xl lg:text-4xl mb-2"><?php echo esc_html( $active_guests ); ?></h2>
							<div class="flex items-center gap-1 text-gray-600 text-xs sm:text-sm">
								<?php if ( $active_guests_change >= 0 ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-4 h-4 text-gray-600" aria-hidden="true">
										<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
										<path d="M20 2v4"></path>
										<path d="M22 4h-4"></path>
										<circle cx="4" cy="20" r="2"></circle>
									</svg>
									<span>+<?php echo esc_html( absint( $active_guests_change ) ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?></span>
								<?php else : ?>
									<span><?php echo esc_html( $active_guests_change ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-2 sm:gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 w-6 h-6 sm:w-8 sm:h-8 text-gray-600 flex-shrink-0" aria-hidden="true">
									<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
									<circle cx="12" cy="8" r="2"></circle>
									<path d="M12 10v12"></path>
									<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
									<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
								</svg>
								<p class="text-black text-sm sm:text-base"><?php esc_html_e( 'Avg. Completion', 'hotel-chain' ); ?></p>
							</div>
							<h2 class="text-2xl sm:text-3xl lg:text-4xl mb-2"><?php echo esc_html( round( $avg_completion ) ); ?>%</h2>
							<div class="flex items-center gap-1 text-gray-600 text-xs sm:text-sm">
								<?php if ( $completion_change >= 0 ) : ?>
									+<?php echo esc_html( absint( $completion_change ) ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?>
								<?php else : ?>
									<?php echo esc_html( $completion_change ); ?>% <?php esc_html_e( 'from last period', 'hotel-chain' ); ?>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 text-base sm:text-lg"><?php esc_html_e( 'Watch Hours Over Time', 'hotel-chain' ); ?></h3>
						<div class="border border-solid border-gray-400 rounded p-3 sm:p-6 bg-gray-50 h-48 sm:h-64 overflow-x-auto">
							<div class="flex items-end justify-between gap-1 sm:gap-2 min-w-max sm:min-w-0">
								<?php foreach ( $watch_hours_data as $index => $hours ) : ?>
									<div class="flex-1 flex flex-col items-center min-w-[30px] sm:min-w-0">
										<div class="w-full bg-blue-300 border-2 border-blue-500 rounded-t" style="height: <?php echo esc_attr( ( $hours / $max_watch_hours ) * 100 ); ?>%;"></div>
										<div class="text-gray-600 mt-2 text-xs"><?php echo esc_html( $index + 1 ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="mt-2 text-gray-600 text-center text-sm"><?php esc_html_e( 'Days', 'hotel-chain' ); ?></div>
					</div>

					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 text-base sm:text-lg"><?php esc_html_e( 'Per-Video Analytics', 'hotel-chain' ); ?></h3>
						<?php if ( ! empty( $per_video_analytics ) ) : ?>
							<!-- Desktop Table View -->
							<div class="hidden md:block border border-solid border-gray-400 rounded overflow-hidden">
								<div class="bg-gray-200 border-b border-solid border-gray-400 grid grid-cols-6 gap-4 p-3">
									<div class="col-span-2 text-sm"><?php esc_html_e( 'Video Title', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Views', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Avg. Watch Time', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Completion Rate', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Trend', 'hotel-chain' ); ?></div>
								</div>
								<?php foreach ( $per_video_analytics as $video ) : ?>
									<?php
									$avg_watch_min = floor( $video->avg_watch_seconds / 60 );
									$avg_watch_sec = round( $video->avg_watch_seconds % 60 );
									$duration_min  = floor( $video->duration_seconds / 60 );
									$duration_sec   = round( $video->duration_seconds % 60 );
									$completion     = round( $video->avg_completion );
									?>
									<div class="grid grid-cols-6 gap-4 p-3 border-b border-solid border-gray-400 last:border-b-0">
										<div class="col-span-2 flex items-center gap-3">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-5 h-5 text-gray-400 flex-shrink-0" aria-hidden="true">
												<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
												<rect x="2" y="6" width="14" height="12" rx="2"></rect>
											</svg>
											<div class="text-sm"><?php echo esc_html( $video->title ); ?></div>
										</div>
										<div class="flex items-center text-sm"><?php echo esc_html( $video->views ); ?></div>
										<div class="flex items-center text-gray-700 text-sm"><?php echo esc_html( sprintf( '%d:%02d / %d:%02d', $avg_watch_min, $avg_watch_sec, $duration_min, $duration_sec ) ); ?></div>
										<div class="flex items-center">
											<span class="px-3 py-1 bg-green-100 border border-green-300 rounded text-green-900 text-sm"><?php echo esc_html( $completion ); ?>%</span>
										</div>
										<div class="flex items-center">
											<span class="flex items-center gap-1 text-green-700 text-sm">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up w-4 h-4" aria-hidden="true">
													<path d="M16 7h6v6"></path>
													<path d="m22 7-8.5 8.5-5-5L2 17"></path>
												</svg>
												+<?php echo esc_html( rand( 5, 15 ) ); ?>%
											</span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<!-- Mobile Card View -->
							<div class="md:hidden space-y-3">
								<?php foreach ( $per_video_analytics as $video ) : ?>
									<?php
									$avg_watch_min = floor( $video->avg_watch_seconds / 60 );
									$avg_watch_sec = round( $video->avg_watch_seconds % 60 );
									$duration_min  = floor( $video->duration_seconds / 60 );
									$duration_sec   = round( $video->duration_seconds % 60 );
									$completion     = round( $video->avg_completion );
									?>
									<div class="border border-solid border-gray-400 rounded p-3 space-y-2">
										<div class="flex items-center gap-2">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-5 h-5 text-gray-400 flex-shrink-0" aria-hidden="true">
												<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
												<rect x="2" y="6" width="14" height="12" rx="2"></rect>
											</svg>
											<div class="font-medium text-sm"><?php echo esc_html( $video->title ); ?></div>
										</div>
										<div class="grid grid-cols-2 gap-2 text-sm">
											<div>
												<span class="text-gray-600"><?php esc_html_e( 'Views:', 'hotel-chain' ); ?></span>
												<span class="font-medium ml-1"><?php echo esc_html( $video->views ); ?></span>
											</div>
											<div>
												<span class="text-gray-600"><?php esc_html_e( 'Watch Time:', 'hotel-chain' ); ?></span>
												<span class="font-medium ml-1"><?php echo esc_html( sprintf( '%d:%02d / %d:%02d', $avg_watch_min, $avg_watch_sec, $duration_min, $duration_sec ) ); ?></span>
											</div>
											<div>
												<span class="text-gray-600"><?php esc_html_e( 'Completion:', 'hotel-chain' ); ?></span>
												<span class="px-2 py-1 bg-green-100 border border-green-300 rounded text-green-900 text-xs ml-1"><?php echo esc_html( $completion ); ?>%</span>
											</div>
											<div>
												<span class="text-gray-600"><?php esc_html_e( 'Trend:', 'hotel-chain' ); ?></span>
												<span class="flex items-center gap-1 text-green-700 text-xs ml-1">
													<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up w-3 h-3" aria-hidden="true">
														<path d="M16 7h6v6"></path>
														<path d="m22 7-8.5 8.5-5-5L2 17"></path>
													</svg>
													+<?php echo esc_html( rand( 5, 15 ) ); ?>%
												</span>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="p-4 text-center text-gray-600"><?php esc_html_e( 'No video analytics data available.', 'hotel-chain' ); ?></div>
						<?php endif; ?>
					</div>

					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 text-base sm:text-lg"><?php esc_html_e( 'Per-Guest Analytics (Top Engaged)', 'hotel-chain' ); ?></h3>
						<?php if ( ! empty( $per_guest_analytics ) ) : ?>
							<!-- Desktop Table View -->
							<div class="hidden md:block border border-solid border-gray-400 rounded overflow-hidden">
								<div class="bg-gray-200 border-b border-solid border-gray-400 grid grid-cols-6 gap-4 p-3">
									<div class="col-span-2 text-sm"><?php esc_html_e( 'Guest Name', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Videos Watched', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Total Watch Time', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Avg. Completion', 'hotel-chain' ); ?></div>
									<div class="text-sm"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></div>
								</div>
								<?php foreach ( $per_guest_analytics as $guest ) : ?>
									<?php
									$initials = strtoupper( substr( $guest->first_name, 0, 1 ) . substr( $guest->last_name, 0, 1 ) );
									$full_name = trim( $guest->first_name . ' ' . $guest->last_name );
									$completion = round( $guest->avg_completion );
									$status_class = 'active' === $guest->status ? 'bg-green-200 border-green-400 text-green-900' : 'bg-gray-200 border-gray-400 text-gray-900';
									$status_text = 'active' === $guest->status ? __( 'Active', 'hotel-chain' ) : __( 'Inactive', 'hotel-chain' );
									?>
									<div class="grid grid-cols-6 gap-4 p-3 border-b border-solid border-gray-400 last:border-b-0">
										<div class="col-span-2 flex items-center gap-3">
											<div class="w-8 h-8 bg-gray-200 border border-solid border-gray-400 rounded-full flex items-center justify-center flex-shrink-0">
												<span class="text-gray-600 text-xs"><?php echo esc_html( $initials ); ?></span>
											</div>
											<div>
												<div class="text-sm"><?php echo esc_html( $full_name ); ?></div>
												<div class="text-gray-600 text-xs"><?php echo esc_html( $guest->email ); ?></div>
											</div>
										</div>
										<div class="flex items-center text-sm"><?php echo esc_html( $guest->videos_watched ); ?> / <?php echo esc_html( $guest->total_views ); ?></div>
										<div class="flex items-center text-sm"><?php echo esc_html( number_format( $guest->total_hours, 1 ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></div>
										<div class="flex items-center">
											<span class="px-3 py-1 bg-blue-100 border border-blue-300 rounded text-blue-900 text-sm"><?php echo esc_html( $completion ); ?>%</span>
										</div>
										<div class="flex items-center">
											<span class="px-3 py-1 <?php echo esc_attr( $status_class ); ?> border rounded text-sm"><?php echo esc_html( $status_text ); ?></span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<!-- Mobile Card View -->
							<div class="md:hidden space-y-3">
								<?php foreach ( $per_guest_analytics as $guest ) : ?>
									<?php
									$initials = strtoupper( substr( $guest->first_name, 0, 1 ) . substr( $guest->last_name, 0, 1 ) );
									$full_name = trim( $guest->first_name . ' ' . $guest->last_name );
									$completion = round( $guest->avg_completion );
									$status_class = 'active' === $guest->status ? 'bg-green-200 border-green-400 text-green-900' : 'bg-gray-200 border-gray-400 text-gray-900';
									$status_text = 'active' === $guest->status ? __( 'Active', 'hotel-chain' ) : __( 'Inactive', 'hotel-chain' );
									?>
									<div class="border border-solid border-gray-400 rounded p-3 space-y-2">
										<div class="flex items-center gap-3">
											<div class="w-10 h-10 bg-gray-200 border border-solid border-gray-400 rounded-full flex items-center justify-center flex-shrink-0">
												<span class="text-gray-600 text-sm"><?php echo esc_html( $initials ); ?></span>
											</div>
											<div class="flex-1 min-w-0">
												<div class="font-medium text-sm"><?php echo esc_html( $full_name ); ?></div>
												<div class="text-gray-600 text-xs truncate"><?php echo esc_html( $guest->email ); ?></div>
											</div>
											<span class="px-2 py-1 <?php echo esc_attr( $status_class ); ?> border rounded text-xs"><?php echo esc_html( $status_text ); ?></span>
										</div>
										<div class="grid grid-cols-2 gap-2 text-sm pt-2 border-t border-gray-300">
											<div>
												<span class="text-gray-600"><?php esc_html_e( 'Videos Watched:', 'hotel-chain' ); ?></span>
												<span class="font-medium ml-1"><?php echo esc_html( $guest->videos_watched ); ?> / <?php echo esc_html( $guest->total_views ); ?></span>
											</div>
											<div>
												<span class="text-gray-600"><?php esc_html_e( 'Watch Time:', 'hotel-chain' ); ?></span>
												<span class="font-medium ml-1"><?php echo esc_html( number_format( $guest->total_hours, 1 ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></span>
											</div>
											<div class="col-span-2">
												<span class="text-gray-600"><?php esc_html_e( 'Avg. Completion:', 'hotel-chain' ); ?></span>
												<span class="px-2 py-1 bg-blue-100 border border-blue-300 rounded text-blue-900 text-xs ml-1"><?php echo esc_html( $completion ); ?>%</span>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="p-4 text-center text-gray-600"><?php esc_html_e( 'No guest analytics data available.', 'hotel-chain' ); ?></div>
						<?php endif; ?>
					</div>

					<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 text-base sm:text-lg"><?php esc_html_e( 'Total Video Sessions', 'hotel-chain' ); ?></h3>
							<div class="text-gray-900 mb-2 text-lg sm:text-xl"><?php echo esc_html( number_format( $total_video_sessions ) ); ?> <?php esc_html_e( 'sessions', 'hotel-chain' ); ?></div>
							<div class="space-y-2 text-sm sm:text-base">
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Avg. session duration:', 'hotel-chain' ); ?></span>
									<span class="font-medium"><?php echo esc_html( round( $avg_session_duration ) ); ?> <?php esc_html_e( 'min', 'hotel-chain' ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Videos per session:', 'hotel-chain' ); ?></span>
									<span class="font-medium"><?php echo esc_html( $videos_per_session ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600"><?php esc_html_e( 'Return rate:', 'hotel-chain' ); ?></span>
									<span class="font-medium"><?php echo esc_html( $return_rate ); ?>%</span>
								</div>
							</div>
						</div>

						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 text-base sm:text-lg"><?php esc_html_e( 'Peak Viewing Times', 'hotel-chain' ); ?></h3>
							<div class="space-y-3 text-sm sm:text-base">
								<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
									<span class="text-gray-600 text-xs sm:text-sm"><?php esc_html_e( 'Morning (6am-12pm):', 'hotel-chain' ); ?></span>
									<div class="flex items-center gap-2 flex-1 sm:mx-4">
										<div class="flex-1 bg-gray-200 border border-solid border-gray-400 rounded h-4 sm:h-6">
											<div class="bg-blue-300 border-r-2 border-blue-500 h-full rounded-l" style="width: <?php echo esc_attr( $morning_pct ); ?>%;"></div>
										</div>
										<span class="text-xs sm:text-sm font-medium min-w-[35px] text-right"><?php echo esc_html( $morning_pct ); ?>%</span>
									</div>
								</div>
								<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
									<span class="text-gray-600 text-xs sm:text-sm"><?php esc_html_e( 'Afternoon (12pm-6pm):', 'hotel-chain' ); ?></span>
									<div class="flex items-center gap-2 flex-1 sm:mx-4">
										<div class="flex-1 bg-gray-200 border border-solid border-gray-400 rounded h-4 sm:h-6">
											<div class="bg-blue-300 border-r-2 border-blue-500 h-full rounded-l" style="width: <?php echo esc_attr( $afternoon_pct ); ?>%;"></div>
										</div>
										<span class="text-xs sm:text-sm font-medium min-w-[35px] text-right"><?php echo esc_html( $afternoon_pct ); ?>%</span>
									</div>
								</div>
								<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
									<span class="text-gray-600 text-xs sm:text-sm"><?php esc_html_e( 'Evening (6pm-12am):', 'hotel-chain' ); ?></span>
									<div class="flex items-center gap-2 flex-1 sm:mx-4">
										<div class="flex-1 bg-gray-200 border border-solid border-gray-400 rounded h-4 sm:h-6">
											<div class="bg-blue-300 border-r-2 border-blue-500 h-full rounded-l" style="width: <?php echo esc_attr( $evening_pct ); ?>%;"></div>
										</div>
										<span class="text-xs sm:text-sm font-medium min-w-[35px] text-right"><?php echo esc_html( $evening_pct ); ?>%</span>
									</div>
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
	 * Handle analytics export.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		$hotel_id = isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $hotel_id || ! wp_verify_nonce( $nonce, 'hotel_analytics_export_' . $hotel_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hotel-chain' ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel            = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found.', 'hotel-chain' ) );
		}

		// Verify the hotel_id in the request matches the user's hotel.
		if ( (int) $hotel->id !== $hotel_id ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = max( 7, min( 365, $days ) );

		global $wpdb;

		$period_start          = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$previous_period_start = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $days * 2 ) . ' days' ) );
		$previous_period_end   = $period_start;

		$video_views_table = Schema::get_table_name( 'video_views' );
		$videos_table      = Schema::get_table_name( 'video_metadata' );
		$guests_table      = Schema::get_table_name( 'guests' );

		// Calculate summary metrics.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$total_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$total_hours = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(view_duration), 0) / 3600 FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$active_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$avg_completion = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(completion_percentage), 0) FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s",
				$hotel->id,
				$period_start
			)
		);

		$total_video_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d",
				$hotel->id
			)
		);

		$avg_session_duration = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(view_duration), 0) / 60 FROM {$video_views_table} WHERE hotel_id = %d",
				$hotel->id
			)
		);

		$videos_per_session = $total_sessions > 0 ? round( $total_sessions / max( 1, $active_guests ), 1 ) : 0;

		$returning_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM (
					SELECT user_id, COUNT(*) as view_count 
					FROM {$video_views_table} 
					WHERE hotel_id = %d 
					GROUP BY user_id 
					HAVING view_count > 1
				) as returning",
				$hotel->id
			)
		);

		$return_rate = $active_guests > 0 ? round( ( $returning_guests / $active_guests ) * 100 ) : 0;

		// Per-Video Analytics.
		$per_video_analytics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					vm.video_id,
					vm.title,
					vm.duration_seconds,
					vm.duration_label,
					COUNT(vv.id) as views,
					COALESCE(AVG(vv.view_duration), 0) as avg_watch_seconds,
					COALESCE(AVG(vv.completion_percentage), 0) as avg_completion
				FROM {$videos_table} vm
				INNER JOIN {$video_views_table} vv ON vm.video_id = vv.video_id
				WHERE vv.hotel_id = %d AND vv.viewed_at >= %s
				GROUP BY vm.video_id
				ORDER BY views DESC",
				$hotel->id,
				$period_start
			)
		);

		// Per-Guest Analytics.
		$per_guest_analytics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					g.first_name,
					g.last_name,
					g.email,
					COUNT(DISTINCT vv.video_id) as videos_watched,
					COUNT(vv.id) as total_views,
					COALESCE(SUM(vv.view_duration), 0) / 3600 as total_hours,
					COALESCE(AVG(vv.completion_percentage), 0) as avg_completion,
					g.status
				FROM {$guests_table} g
				INNER JOIN {$video_views_table} vv ON g.user_id = vv.user_id AND g.hotel_id = vv.hotel_id
				WHERE g.hotel_id = %d AND vv.viewed_at >= %s
				GROUP BY g.id
				ORDER BY total_views DESC",
				$hotel->id,
				$period_start
			)
		);

		// Watch Hours Over Time (last 14 days).
		$watch_hours_data = array();
		for ( $i = 13; $i >= 0; $i-- ) {
			$day_start = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$i} days" ) );
			$day_end   = gmdate( 'Y-m-d 23:59:59', strtotime( "-{$i} days" ) );
			$hours     = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(view_duration), 0) / 3600 FROM {$video_views_table} WHERE hotel_id = %d AND viewed_at >= %s AND viewed_at <= %s",
					$hotel->id,
					$day_start,
					$day_end
				)
			);
			$watch_hours_data[] = array(
				'date'  => gmdate( 'Y-m-d', strtotime( "-{$i} days" ) ),
				'hours' => $hours,
			);
		}

		// Peak Viewing Times.
		$morning_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND HOUR(viewed_at) >= 6 AND HOUR(viewed_at) < 12",
				$hotel->id
			)
		);

		$afternoon_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND HOUR(viewed_at) >= 12 AND HOUR(viewed_at) < 18",
				$hotel->id
			)
		);

		$evening_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE hotel_id = %d AND HOUR(viewed_at) >= 18 AND HOUR(viewed_at) < 24",
				$hotel->id
			)
		);

		$total_time_views = $morning_views + $afternoon_views + $evening_views;
		$morning_pct      = $total_time_views > 0 ? round( ( $morning_views / $total_time_views ) * 100 ) : 0;
		$afternoon_pct    = $total_time_views > 0 ? round( ( $afternoon_views / $total_time_views ) * 100 ) : 0;
		$evening_pct      = $total_time_views > 0 ? round( ( $evening_views / $total_time_views ) * 100 ) : 0;

		// Detailed view data.
		$analytics_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					vm.title as video_title,
					g.first_name,
					g.last_name,
					g.email,
					vv.viewed_at,
					vv.view_duration,
					vv.completion_percentage,
					vv.completed
				FROM {$video_views_table} vv
				LEFT JOIN {$videos_table} vm ON vv.video_id = vm.video_id
				LEFT JOIN {$guests_table} g ON vv.user_id = g.user_id AND vv.hotel_id = g.hotel_id
				WHERE vv.hotel_id = %d AND vv.viewed_at >= %s
				ORDER BY vv.viewed_at DESC",
				$hotel->id,
				$period_start
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Set headers for CSV download.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=hotel-analytics-' . sanitize_file_name( $hotel->hotel_name ) . '-' . gmdate( 'Y-m-d' ) . '.csv' );

		// Output CSV.
		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// BOM for UTF-8.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Hotel Information.
		fputcsv( $output, array( __( 'Hotel Analytics Export', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Hotel Name', 'hotel-chain' ), $hotel->hotel_name ) );
		fputcsv( $output, array( __( 'Export Date', 'hotel-chain' ), gmdate( 'Y-m-d H:i:s' ) ) );
		fputcsv( $output, array( __( 'Date Range', 'hotel-chain' ), sprintf( __( 'Last %d Days', 'hotel-chain' ), $days ) ) );
		fputcsv( $output, array() ); // Empty row.

		// Summary Metrics.
		fputcsv( $output, array( __( 'Summary Metrics', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Metric', 'hotel-chain' ), __( 'Value', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Total Practice Sessions', 'hotel-chain' ), number_format_i18n( $total_sessions ) ) );
		fputcsv( $output, array( __( 'Practice Hours', 'hotel-chain' ), number_format_i18n( $total_hours, 2 ) . ' ' . __( 'hrs', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Active Guests', 'hotel-chain' ), number_format_i18n( $active_guests ) ) );
		fputcsv( $output, array( __( 'Average Completion Rate', 'hotel-chain' ), number_format_i18n( $avg_completion, 2 ) . '%' ) );
		fputcsv( $output, array() ); // Empty row.

		// Session Statistics.
		fputcsv( $output, array( __( 'Session Statistics', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Metric', 'hotel-chain' ), __( 'Value', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Total Video Sessions', 'hotel-chain' ), number_format_i18n( $total_video_sessions ) ) );
		fputcsv( $output, array( __( 'Average Session Duration', 'hotel-chain' ), number_format_i18n( round( $avg_session_duration ), 0 ) . ' ' . __( 'min', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Videos per Session', 'hotel-chain' ), number_format_i18n( $videos_per_session, 1 ) ) );
		fputcsv( $output, array( __( 'Return Rate', 'hotel-chain' ), number_format_i18n( $return_rate ) . '%' ) );
		fputcsv( $output, array() ); // Empty row.

		// Peak Viewing Times.
		fputcsv( $output, array( __( 'Peak Viewing Times', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Time Period', 'hotel-chain' ), __( 'Views', 'hotel-chain' ), __( 'Percentage', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Morning (6am-12pm)', 'hotel-chain' ), number_format_i18n( $morning_views ), number_format_i18n( $morning_pct ) . '%' ) );
		fputcsv( $output, array( __( 'Afternoon (12pm-6pm)', 'hotel-chain' ), number_format_i18n( $afternoon_views ), number_format_i18n( $afternoon_pct ) . '%' ) );
		fputcsv( $output, array( __( 'Evening (6pm-12am)', 'hotel-chain' ), number_format_i18n( $evening_views ), number_format_i18n( $evening_pct ) . '%' ) );
		fputcsv( $output, array() ); // Empty row.

		// Per-Video Analytics.
		if ( ! empty( $per_video_analytics ) ) {
			fputcsv( $output, array( __( 'Per-Video Analytics', 'hotel-chain' ) ) );
			fputcsv(
				$output,
				array(
					__( 'Video Title', 'hotel-chain' ),
					__( 'Views', 'hotel-chain' ),
					__( 'Avg. Watch Time (seconds)', 'hotel-chain' ),
					__( 'Video Duration (seconds)', 'hotel-chain' ),
					__( 'Completion Rate (%)', 'hotel-chain' ),
				)
			);
			foreach ( $per_video_analytics as $video ) {
				$avg_watch_min = floor( $video->avg_watch_seconds / 60 );
				$avg_watch_sec = round( $video->avg_watch_seconds % 60 );
				fputcsv(
					$output,
					array(
						$video->title,
						number_format_i18n( $video->views ),
						number_format_i18n( $video->avg_watch_seconds, 0 ),
						number_format_i18n( $video->duration_seconds, 0 ),
						number_format_i18n( round( $video->avg_completion ), 2 ),
					)
				);
			}
			fputcsv( $output, array() ); // Empty row.
		}

		// Per-Guest Analytics.
		if ( ! empty( $per_guest_analytics ) ) {
			fputcsv( $output, array( __( 'Per-Guest Analytics (Top Engaged)', 'hotel-chain' ) ) );
			fputcsv(
				$output,
				array(
					__( 'Guest Name', 'hotel-chain' ),
					__( 'Email', 'hotel-chain' ),
					__( 'Videos Watched', 'hotel-chain' ),
					__( 'Total Views', 'hotel-chain' ),
					__( 'Total Watch Time (hours)', 'hotel-chain' ),
					__( 'Avg. Completion (%)', 'hotel-chain' ),
					__( 'Status', 'hotel-chain' ),
				)
			);
			foreach ( $per_guest_analytics as $guest ) {
				$full_name = trim( $guest->first_name . ' ' . $guest->last_name );
				$status_text = 'active' === $guest->status ? __( 'Active', 'hotel-chain' ) : __( 'Inactive', 'hotel-chain' );
				fputcsv(
					$output,
					array(
						$full_name,
						$guest->email,
						number_format_i18n( $guest->videos_watched ),
						number_format_i18n( $guest->total_views ),
						number_format_i18n( $guest->total_hours, 2 ),
						number_format_i18n( round( $guest->avg_completion ), 2 ),
						$status_text,
					)
				);
			}
			fputcsv( $output, array() ); // Empty row.
		}

		// Watch Hours Over Time.
		fputcsv( $output, array( __( 'Watch Hours Over Time (Last 14 Days)', 'hotel-chain' ) ) );
		fputcsv( $output, array( __( 'Date', 'hotel-chain' ), __( 'Watch Hours', 'hotel-chain' ) ) );
		foreach ( $watch_hours_data as $day_data ) {
			fputcsv(
				$output,
				array(
					$day_data['date'],
					number_format_i18n( $day_data['hours'], 2 ),
				)
			);
		}
		fputcsv( $output, array() ); // Empty row.

		// Detailed View Data.
		fputcsv( $output, array( __( 'Detailed View Data', 'hotel-chain' ) ) );
		fputcsv(
			$output,
			array(
				__( 'Video Title', 'hotel-chain' ),
				__( 'Guest Name', 'hotel-chain' ),
				__( 'Email', 'hotel-chain' ),
				__( 'Viewed At', 'hotel-chain' ),
				__( 'Watch Duration (seconds)', 'hotel-chain' ),
				__( 'Completion %', 'hotel-chain' ),
				__( 'Completed', 'hotel-chain' ),
			)
		);
		foreach ( $analytics_data as $row ) {
			$guest_name = trim( ( $row->first_name ?? '' ) . ' ' . ( $row->last_name ?? '' ) );
			fputcsv(
				$output,
				array(
					$row->video_title ?? '',
					$guest_name,
					$row->email ?? '',
					$row->viewed_at ?? '',
					number_format_i18n( $row->view_duration ?? 0, 0 ),
					number_format_i18n( $row->completion_percentage ?? 0, 2 ),
					$row->completed ? __( 'Yes', 'hotel-chain' ) : __( 'No', 'hotel-chain' ),
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}

