<?php
/**
 * System Analytics page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;
use HotelChain\Database\Schema;

/**
 * System Analytics page.
 */
class SystemAnalyticsPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register admin menu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'System Analytics', 'hotel-chain' ),
			__( 'System Analytics', 'hotel-chain' ),
			'manage_options',
			'system-analytics',
			array( $this, 'render_page' ),
			'dashicons-chart-bar',
			3
		);
	}

	/**
	 * Render the system analytics page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$hotel_repo      = new HotelRepository();
		$guest_repo      = new GuestRepository();
		$video_repo      = new VideoRepository();
		$assignment_repo = new HotelVideoAssignmentRepository();

		// Date range: Last 30 days.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = max( 7, min( 365, $days ) ); // Between 7 and 365 days.

		$period_start = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$previous_period_start = gmdate( 'Y-m-d H:i:s', strtotime( "-" . ( $days * 2 ) . " days" ) );
		$previous_period_end = $period_start;

		$hotels_table     = Schema::get_table_name( 'hotels' );
		$guests_table     = Schema::get_table_name( 'guests' );
		$video_views_table = Schema::get_table_name( 'video_views' );
		$assignments_table = Schema::get_table_name( 'hotel_video_assignments' );

		// Active Hotels.
		$active_hotels = $hotel_repo->count( array( 'status' => 'active' ) );
		$active_hotels_previous = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$hotels_table} WHERE status = 'active' AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$period_start
			)
		);
		$active_hotels_change = $active_hotels - $active_hotels_previous;

		// Total Guests.
		$total_guests = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$guests_this_period = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$period_start
			)
		);

		// Total Videos.
		$total_videos = $video_repo->get_count();

		// Total Views.
		$total_views = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$video_views_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$views_this_period = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE viewed_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$period_start
			)
		);
		$views_previous_period = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE viewed_at >= %s AND viewed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$previous_period_start,
				$previous_period_end
			)
		);
		$views_percentage_change = $views_previous_period > 0 ? round( ( ( $views_this_period - $views_previous_period ) / $views_previous_period ) * 100 ) : 0;

		// Average Completion Rate.
		$avg_completion = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(completion_percentage), 0) FROM {$video_views_table} WHERE viewed_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$period_start
			)
		);
		$avg_completion_previous = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(completion_percentage), 0) FROM {$video_views_table} WHERE viewed_at >= %s AND viewed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$previous_period_start,
				$previous_period_end
			)
		);
		$completion_change = $avg_completion - $avg_completion_previous;

		// Platform Growth (Last 12 Months).
		$monthly_growth = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$month_start = gmdate( 'Y-m-01 00:00:00', strtotime( "-{$i} months" ) );
			$month_end = gmdate( 'Y-m-t 23:59:59', strtotime( "-{$i} months" ) );
			$month_views = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$video_views_table} WHERE viewed_at >= %s AND viewed_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$month_start,
					$month_end
				)
			);
			$monthly_growth[] = $month_views;
		}
		$max_monthly_views = max( $monthly_growth ) ?: 1;

		// Video Views by Category.
		$videos_table = Schema::get_table_name( 'video_metadata' );
		$category_views = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					vm.category,
					COUNT(vv.id) as view_count
				FROM {$video_views_table} vv
				INNER JOIN {$videos_table} vm ON vv.video_id = vm.video_id
				WHERE vv.viewed_at >= %s AND vm.category IS NOT NULL AND vm.category != ''
				GROUP BY vm.category
				ORDER BY view_count DESC
				LIMIT 5",
				$period_start
			)
		);
		$total_category_views = array_sum( array_column( $category_views, 'view_count' ) );

		// Cross-Hotel Engagement Comparison.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$hotel_engagement = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.id,
					h.hotel_name,
					COUNT(DISTINCT g.id) as total_guests,
					COUNT(DISTINCT hva.video_id) as assigned_videos,
					COUNT(DISTINCT vv.id) as total_views,
					COALESCE(SUM(vv.view_duration), 0) as total_watch_seconds,
					COALESCE(AVG(vv.completion_percentage), 0) as avg_completion
				FROM {$hotels_table} h
				LEFT JOIN {$guests_table} g ON h.id = g.hotel_id
				LEFT JOIN {$assignments_table} hva ON h.id = hva.hotel_id
				LEFT JOIN {$video_views_table} vv ON h.id = vv.hotel_id AND vv.viewed_at >= %s
				WHERE h.status = 'active'
				GROUP BY h.id
				ORDER BY total_views DESC, avg_completion DESC
				LIMIT 10",
				$period_start
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Calculate engagement scores (0-100).
		$max_views = 0;
		$max_completion = 0;
		foreach ( $hotel_engagement as $hotel ) {
			$max_views = max( $max_views, (int) $hotel->total_views );
			$max_completion = max( $max_completion, (float) $hotel->avg_completion );
		}
		$max_views = $max_views ?: 1;
		$max_completion = $max_completion ?: 1;
		foreach ( $hotel_engagement as $hotel ) {
			$hotel->engagement_score = round(
				( ( (int) $hotel->total_views / $max_views ) * 50 ) +
				( ( (float) $hotel->avg_completion / $max_completion ) * 50 )
			);
		}

		// Top Performing Videos.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$top_videos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					vm.video_id,
					vm.title,
					COUNT(DISTINCT hva.hotel_id) as hotels_using,
					COUNT(vv.id) as total_views,
					COALESCE(AVG(vv.view_duration), 0) as avg_watch_seconds,
					vm.duration_label,
					COALESCE(AVG(vv.completion_percentage), 0) as avg_completion
				FROM {$videos_table} vm
				LEFT JOIN {$assignments_table} hva ON vm.video_id = hva.video_id
				LEFT JOIN {$video_views_table} vv ON vm.video_id = vv.video_id AND vv.viewed_at >= %s
				GROUP BY vm.video_id
				HAVING total_views > 0
				ORDER BY total_views DESC, avg_completion DESC
				LIMIT 10",
				$period_start
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Guest Activity Status.
		$active_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE status = 'active' AND (access_end IS NULL OR access_end > %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' )
			)
		);
		$expiring_soon = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE status = 'active' AND access_end IS NOT NULL AND access_end > %s AND access_end <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) )
			)
		);
		$expired_guests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE (status = 'expired' OR (access_end IS NOT NULL AND access_end <= %s))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' )
			)
		);

		?>
		<div class="flex-1 overflow-auto p-4 lg:p-8" style="background-color: rgb(240, 231, 215);">
			<div class="max-w-7xl mx-auto">
				<div class="space-y-6">
					<div class="mb-6 pb-4" style="border-bottom: 2px solid rgb(196, 196, 196);">
						<h1 class="mb-1" style="color: rgb(60, 56, 55); font-family: var(--font-serif);"><?php esc_html_e( 'ADMIN – System Analytics', 'hotel-chain' ); ?></h1>
						<p style="color: rgb(122, 122, 122); font-family: var(--font-sans);"><?php esc_html_e( 'Cross-hotel analytics and engagement metrics', 'hotel-chain' ); ?></p>
					</div>

					<!-- Date Range Selector -->
					<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
						<div class="flex items-center justify-between">
							<div class="flex gap-4">
								<div class="flex items-center gap-2 border-2 border-gray-300 rounded px-4 py-2">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar w-5 h-5 text-gray-600" aria-hidden="true">
										<path d="M8 2v4"></path>
										<path d="M16 2v4"></path>
										<rect width="18" height="18" x="3" y="4" rx="2"></rect>
										<path d="M3 10h18"></path>
									</svg>
									<span class="text-gray-600"><?php esc_html_e( 'Date Range:', 'hotel-chain' ); ?></span>
									<select name="days" onchange="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=system-analytics' ) ); ?>&days=' + this.value" class="border-0 bg-transparent">
										<option value="7" <?php selected( $days, 7 ); ?>><?php esc_html_e( 'Last 7 Days', 'hotel-chain' ); ?></option>
										<option value="30" <?php selected( $days, 30 ); ?>><?php esc_html_e( 'Last 30 Days', 'hotel-chain' ); ?></option>
										<option value="90" <?php selected( $days, 90 ); ?>><?php esc_html_e( 'Last 90 Days', 'hotel-chain' ); ?></option>
										<option value="365" <?php selected( $days, 365 ); ?>><?php esc_html_e( 'Last Year', 'hotel-chain' ); ?></option>
									</select>
								</div>
								<div class="flex items-center gap-2 border-2 border-gray-300 rounded px-4 py-2">
									<span class="text-gray-600"><?php esc_html_e( 'Compare:', 'hotel-chain' ); ?></span>
									<span><?php esc_html_e( 'Previous Period', 'hotel-chain' ); ?></span>
								</div>
							</div>
							<button class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center gap-2" onclick="exportReport()">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download w-4 h-4" aria-hidden="true">
									<path d="M12 15V3"></path>
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<path d="m7 10 5 5 5-5"></path>
								</svg>
								<?php esc_html_e( 'Export Full Report', 'hotel-chain' ); ?>
							</button>
						</div>
					</div>

					<!-- Statistics Cards -->
					<div class="grid grid-cols-5 gap-4">
						<!-- Active Hotels -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 w-8 h-8 text-blue-400" aria-hidden="true">
									<path d="M10 12h4"></path>
									<path d="M10 8h4"></path>
									<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
									<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
									<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Active Hotels', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $active_hotels ) ); ?></div>
							<?php if ( $active_hotels_change > 0 ) : ?>
								<div class="text-green-700 flex items-center gap-1">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up w-4 h-4" aria-hidden="true">
										<path d="M16 7h6v6"></path>
										<path d="m22 7-8.5 8.5-5-5L2 17"></path>
									</svg>
									<span>
										<?php
										printf(
											/* translators: %d: number of hotels. */
											esc_html__( '+%d this month', 'hotel-chain' ),
											esc_html( (string) $active_hotels_change )
										);
										?>
									</span>
								</div>
							<?php endif; ?>
						</div>

						<!-- Total Guests -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-8 h-8 text-green-400" aria-hidden="true">
									<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
									<path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
									<path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
									<circle cx="9" cy="7" r="4"></circle>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Total Guests', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_guests ) ); ?></div>
							<div class="text-green-700 flex items-center gap-1">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up w-4 h-4" aria-hidden="true">
									<path d="M16 7h6v6"></path>
									<path d="m22 7-8.5 8.5-5-5L2 17"></path>
								</svg>
								<span>
									<?php
									printf(
										/* translators: %d: number of guests. */
										esc_html__( '+%d this month', 'hotel-chain' ),
										esc_html( (string) $guests_this_period )
									);
									?>
								</span>
							</div>
						</div>

						<!-- System Videos -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-8 h-8 text-purple-400" aria-hidden="true">
									<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
									<rect x="2" y="6" width="14" height="12" rx="2"></rect>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'System Videos', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_videos ) ); ?></div>
							<div class="text-gray-600"><?php esc_html_e( 'In library', 'hotel-chain' ); ?></div>
						</div>

						<!-- Total Views -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye w-8 h-8 text-orange-400" aria-hidden="true">
									<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path>
									<circle cx="12" cy="12" r="3"></circle>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_views ) ); ?></div>
							<?php if ( $views_percentage_change > 0 ) : ?>
								<div class="text-green-700 flex items-center gap-1">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up w-4 h-4" aria-hidden="true">
										<path d="M16 7h6v6"></path>
										<path d="m22 7-8.5 8.5-5-5L2 17"></path>
									</svg>
									<span>
										<?php
										printf(
											/* translators: %d: percentage change. */
											esc_html__( '+%d%% vs last month', 'hotel-chain' ),
											esc_html( (string) $views_percentage_change )
										);
										?>
									</span>
								</div>
							<?php endif; ?>
						</div>

						<!-- Avg Completion -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<div class="flex items-center gap-3 mb-2">
								<div class="w-8 h-8 bg-teal-400 rounded flex items-center justify-center text-white font-semibold">%</div>
								<div class="text-gray-600"><?php esc_html_e( 'Avg Completion', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $avg_completion, 0 ) ); ?>%</div>
							<?php if ( $completion_change > 0 ) : ?>
								<div class="text-green-700 flex items-center gap-1">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up w-4 h-4" aria-hidden="true">
										<path d="M16 7h6v6"></path>
										<path d="m22 7-8.5 8.5-5-5L2 17"></path>
									</svg>
									<span>
										<?php
										printf(
											/* translators: %d: percentage improvement. */
											esc_html__( '+%d%% improvement', 'hotel-chain' ),
											esc_html( (string) round( $completion_change ) )
										);
										?>
									</span>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Charts Section -->
					<div class="grid grid-cols-2 gap-6">
						<!-- Platform Growth Chart -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Platform Growth (Last 12 Months)', 'hotel-chain' ); ?></h3>
							<div class="border-2 border-gray-300 rounded p-6 bg-gray-50 h-64 flex items-end justify-between gap-2">
								<?php foreach ( $monthly_growth as $index => $views ) : ?>
									<?php
									$height_percentage = ( $views / $max_monthly_views ) * 100;
									$height_percentage = max( 5, min( 100, $height_percentage ) ); // Minimum 5% for visibility.
									?>
									<div class="flex-1 flex flex-col items-center">
										<div class="w-full bg-blue-300 border-2 border-blue-500 rounded-t" style="height: <?php echo esc_attr( $height_percentage ); ?>%;"></div>
										<div class="text-gray-600 mt-2 text-xs"><?php echo esc_html( (string) ( $index + 1 ) ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="mt-2 text-gray-600 text-center"><?php esc_html_e( 'Months', 'hotel-chain' ); ?></div>
						</div>

						<!-- Video Views by Category -->
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Video Views by Category', 'hotel-chain' ); ?></h3>
							<?php if ( ! empty( $category_views ) ) : ?>
								<div class="flex items-center justify-center mb-4">
									<div class="relative w-48 h-48">
										<div class="w-full h-full border-4 border-gray-300 rounded-full bg-gradient-to-br from-blue-300 via-purple-300 to-green-300"></div>
										<div class="absolute inset-0 flex items-center justify-center">
											<div class="w-20 h-20 bg-white border-4 border-gray-300 rounded-full"></div>
										</div>
									</div>
								</div>
								<div class="space-y-2">
									<?php
									$color_map = array(
										array( 'bg' => 'rgb(147, 197, 253)', 'border' => 'rgb(59, 130, 246)' ), // blue
										array( 'bg' => 'rgb(196, 181, 253)', 'border' => 'rgb(147, 51, 234)' ), // purple
										array( 'bg' => 'rgb(134, 239, 172)', 'border' => 'rgb(34, 197, 94)' ), // green
										array( 'bg' => 'rgb(253, 186, 116)', 'border' => 'rgb(249, 115, 22)' ), // orange
										array( 'bg' => 'rgb(94, 234, 212)', 'border' => 'rgb(20, 184, 166)' ), // teal
									);
									foreach ( $category_views as $index => $category ) :
										$color = $color_map[ $index % count( $color_map ) ];
										$percentage = $total_category_views > 0 ? round( ( $category->view_count / $total_category_views ) * 100 ) : 0;
										?>
										<div class="flex items-center justify-between">
											<div class="flex items-center gap-2">
												<div class="w-4 h-4 rounded" style="background-color: <?php echo esc_attr( $color['bg'] ); ?>; border: 2px solid <?php echo esc_attr( $color['border'] ); ?>;"></div>
												<span><?php echo esc_html( $category->category ); ?></span>
											</div>
											<span class="text-gray-900"><?php echo esc_html( number_format_i18n( (int) $category->view_count ) ); ?> <?php esc_html_e( 'views', 'hotel-chain' ); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<div class="p-4 text-center text-gray-500">
									<?php esc_html_e( 'No category data available yet.', 'hotel-chain' ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Cross-Hotel Engagement Comparison -->
					<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
						<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Cross-Hotel Engagement Comparison', 'hotel-chain' ); ?></h3>
						<?php if ( ! empty( $hotel_engagement ) ) : ?>
							<div class="border-2 border-gray-300 rounded overflow-hidden">
								<div class="bg-gray-200 border-b-2 border-gray-300 grid grid-cols-8 gap-4 p-3">
									<div class="col-span-2 font-semibold"><?php esc_html_e( 'Hotel Name', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Guests', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Videos', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Watch Hours', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Avg Completion', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Engagement Score', 'hotel-chain' ); ?></div>
								</div>
								<?php foreach ( $hotel_engagement as $hotel ) : ?>
									<?php
									$watch_hours = round( (int) $hotel->total_watch_seconds / 3600 );
									$completion = round( (float) $hotel->avg_completion );
									?>
									<div class="grid grid-cols-8 gap-4 p-3 border-b-2 border-gray-300 last:border-b-0">
										<div class="col-span-2 flex items-center">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 w-5 h-5 text-gray-400 mr-2" aria-hidden="true">
												<path d="M10 12h4"></path>
												<path d="M10 8h4"></path>
												<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
												<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
												<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
											</svg>
											<?php echo esc_html( $hotel->hotel_name ); ?>
										</div>
										<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $hotel->total_guests ) ); ?></div>
										<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $hotel->assigned_videos ) ); ?></div>
										<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $hotel->total_views ) ); ?></div>
										<div class="flex items-center"><?php echo esc_html( number_format_i18n( $watch_hours ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></div>
										<div class="flex items-center">
											<span class="px-3 py-1 bg-green-100 border border-green-300 rounded text-green-900"><?php echo esc_html( (string) $completion ); ?>%</span>
										</div>
										<div class="flex items-center">
											<div class="flex items-center gap-2">
												<div class="flex-1 w-20 bg-gray-200 border border-gray-300 rounded h-2">
													<div class="bg-blue-500 border-r border-blue-600 h-full rounded-l" style="width: <?php echo esc_attr( (string) $hotel->engagement_score ); ?>%;"></div>
												</div>
												<span><?php echo esc_html( (string) $hotel->engagement_score ); ?></span>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="p-4 text-center text-gray-500">
								<?php esc_html_e( 'No hotel engagement data available yet.', 'hotel-chain' ); ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Top Performing Videos -->
					<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
						<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Top Performing Videos (System-Wide)', 'hotel-chain' ); ?></h3>
						<?php if ( ! empty( $top_videos ) ) : ?>
							<div class="border-2 border-gray-300 rounded overflow-hidden">
								<div class="bg-gray-200 border-b-2 border-gray-300 grid grid-cols-7 gap-4 p-3">
									<div class="col-span-2 font-semibold"><?php esc_html_e( 'Video Title', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Hotels Using', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Avg Watch Time', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Completion Rate', 'hotel-chain' ); ?></div>
									<div class="font-semibold"><?php esc_html_e( 'Engagement', 'hotel-chain' ); ?></div>
								</div>
								<?php foreach ( $top_videos as $video ) : ?>
									<?php
									$avg_watch_seconds = (int) $video->avg_watch_seconds;
									$avg_watch_minutes = floor( $avg_watch_seconds / 60 );
									$avg_watch_secs = $avg_watch_seconds % 60;
									$avg_watch_time = sprintf( '%d:%02d', $avg_watch_minutes, $avg_watch_secs );
									$completion = round( (float) $video->avg_completion );
									$engagement = $completion >= 80 ? 'High' : ( $completion >= 60 ? 'Medium' : 'Low' );
									$engagement_color = $completion >= 80 ? 'green' : ( $completion >= 60 ? 'blue' : 'gray' );
									?>
									<div class="grid grid-cols-7 gap-4 p-3 border-b-2 border-gray-300 last:border-b-0">
										<div class="col-span-2 flex items-center">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video w-5 h-5 text-gray-400 mr-2" aria-hidden="true">
												<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path>
												<rect x="2" y="6" width="14" height="12" rx="2"></rect>
											</svg>
											<?php echo esc_html( $video->title ); ?>
										</div>
										<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $video->hotels_using ) ); ?> <?php esc_html_e( 'hotels', 'hotel-chain' ); ?></div>
										<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $video->total_views ) ); ?></div>
										<div class="flex items-center text-gray-700"><?php echo esc_html( $avg_watch_time ); ?> / <?php echo esc_html( $video->duration_label ?: 'N/A' ); ?></div>
										<div class="flex items-center">
											<span class="px-3 py-1 bg-green-100 border border-green-300 rounded text-green-900"><?php echo esc_html( (string) $completion ); ?>%</span>
										</div>
										<div class="flex items-center">
											<?php
											$engagement_styles = array(
												'green' => array( 'bg' => 'rgb(187, 247, 208)', 'border' => 'rgb(74, 222, 128)', 'text' => 'rgb(20, 83, 45)' ),
												'blue'  => array( 'bg' => 'rgb(191, 219, 254)', 'border' => 'rgb(96, 165, 250)', 'text' => 'rgb(30, 64, 175)' ),
												'gray'  => array( 'bg' => 'rgb(229, 231, 235)', 'border' => 'rgb(156, 163, 175)', 'text' => 'rgb(55, 65, 81)' ),
											);
											$style = $engagement_styles[ $engagement_color ] ?? $engagement_styles['gray'];
											?>
											<span class="px-3 py-1 rounded" style="background-color: <?php echo esc_attr( $style['bg'] ); ?>; border: 1px solid <?php echo esc_attr( $style['border'] ); ?>; color: <?php echo esc_attr( $style['text'] ); ?>;"><?php echo esc_html( $engagement ); ?></span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="p-4 text-center text-gray-500">
								<?php esc_html_e( 'No video performance data available yet.', 'hotel-chain' ); ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Guest Activity Status -->
					<div class="grid grid-cols-1 gap-6">
						<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<h3 class="mb-4 pb-3 border-b-2 border-gray-300"><?php esc_html_e( 'Guest Activity Status', 'hotel-chain' ); ?></h3>
							<div class="space-y-3">
								<div class="p-3 bg-green-50 border-2 border-green-300 rounded">
									<div class="text-green-900 mb-1 font-semibold"><?php esc_html_e( 'Active Guests', 'hotel-chain' ); ?></div>
									<div class="text-gray-900">
										<?php
										printf(
											/* translators: 1: number of active guests, 2: percentage. */
											esc_html__( '%1$s (%2$d%%)', 'hotel-chain' ),
											esc_html( number_format_i18n( $active_guests ) ),
											esc_html( $total_guests > 0 ? round( ( $active_guests / $total_guests ) * 100 ) : 0 )
										);
										?>
									</div>
								</div>
								<div class="p-3 bg-orange-50 border-2 border-orange-300 rounded">
									<div class="text-orange-900 mb-1 font-semibold"><?php esc_html_e( 'Expiring Soon', 'hotel-chain' ); ?></div>
									<div class="text-gray-900">
										<?php
										printf(
											/* translators: 1: number of expiring guests, 2: percentage. */
											esc_html__( '%1$s (%2$d%%)', 'hotel-chain' ),
											esc_html( number_format_i18n( $expiring_soon ) ),
											esc_html( $total_guests > 0 ? round( ( $expiring_soon / $total_guests ) * 100 ) : 0 )
										);
										?>
									</div>
								</div>
								<div class="p-3 bg-red-50 border-2 border-red-300 rounded">
									<div class="text-red-900 mb-1 font-semibold"><?php esc_html_e( 'Expired', 'hotel-chain' ); ?></div>
									<div class="text-gray-900">
										<?php
										printf(
											/* translators: 1: number of expired guests, 2: percentage. */
											esc_html__( '%1$s (%2$d%%)', 'hotel-chain' ),
											esc_html( number_format_i18n( $expired_guests ) ),
											esc_html( $total_guests > 0 ? round( ( $expired_guests / $total_guests ) * 100 ) : 0 )
										);
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script>
		function exportReport() {
			alert('<?php echo esc_js( __( 'Export functionality will be implemented soon.', 'hotel-chain' ) ); ?>');
		}
		</script>
		<?php
	}
}

