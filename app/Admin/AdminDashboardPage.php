<?php
/**
 * Admin Dashboard page.
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
use HotelChain\Support\StyleSettings;

/**
 * Admin Dashboard page.
 */
class AdminDashboardPage implements ServiceProviderInterface {
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
			__( 'Admin Dashboard', 'hotel-chain' ),
			__( 'Admin Dashboard', 'hotel-chain' ),
			'manage_options',
			'admin-dashboard',
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			2
		);
	}

	/**
	 * Render the admin dashboard page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$hotel_repo   = new HotelRepository();
		$guest_repo   = new GuestRepository();
		$video_repo   = new VideoRepository();
		$assignment_repo = new HotelVideoAssignmentRepository();

		// Get statistics.
		$total_hotels = $hotel_repo->count();
		$active_hotels = $hotel_repo->count( array( 'status' => 'active' ) );

		// Hotels active this week.
		$week_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$hotels_table = Schema::get_table_name( 'hotels' );
		$active_this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$hotels_table} WHERE status = 'active' AND (created_at >= %s OR updated_at >= %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$week_ago,
				$week_ago
			)
		);

		// Total guests.
		$guests_table = Schema::get_table_name( 'guests' );
		$total_guests = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$guests_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Guests added this month.
		$month_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$guests_this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$month_ago
			)
		);

		// Total videos.
		$total_videos = $video_repo->get_count();

		// Total views.
		$video_views_table = Schema::get_table_name( 'video_views' );
		$total_views = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$video_views_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Views this month vs last month.
		$views_this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE viewed_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$month_ago
			)
		);
		$last_month_start = gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) );
		$last_month_end = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$views_last_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$video_views_table} WHERE viewed_at >= %s AND viewed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$last_month_start,
				$last_month_end
			)
		);
		$views_percentage_change = $views_last_month > 0 ? round( ( ( $views_this_month - $views_last_month ) / $views_last_month ) * 100 ) : 0;

		// Watch hours this month.
		$watch_seconds_this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(view_duration), 0) FROM {$video_views_table} WHERE viewed_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$month_ago
			)
		);
		$watch_hours_this_month = round( $watch_seconds_this_month / 3600 );

		// Top hotels by engagement (this month).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized.
		$top_hotels = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.id,
					h.hotel_name,
					h.hotel_code,
					COUNT(DISTINCT g.id) as total_guests,
					COUNT(DISTINCT CASE WHEN g.status = 'active' THEN g.id END) as active_guests,
					COUNT(DISTINCT vv.id) as total_views,
					COALESCE(SUM(vv.view_duration), 0) as total_watch_seconds
				FROM {$hotels_table} h
				LEFT JOIN {$guests_table} g ON h.id = g.hotel_id
				LEFT JOIN {$video_views_table} vv ON h.id = vv.hotel_id AND vv.viewed_at >= %s
				WHERE h.status = 'active'
				GROUP BY h.id
				ORDER BY total_views DESC, total_watch_seconds DESC
				LIMIT 5",
				$month_ago
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$logo_url = StyleSettings::get_logo_url();
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
						<h1><?php esc_html_e( 'ADMIN – Dashboard', 'hotel-chain' ); ?></h1>
						<p class="text-slate-600"><?php esc_html_e( 'System-wide overview and key metrics across all hotels', 'hotel-chain' ); ?></p>
					</div>
				</div>
				<div class="space-y-6">
					<!-- Statistics Cards -->
					<div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
						<!-- Total Hotels -->
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-8 h-8 text-blue-400" aria-hidden="true">
									<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Total Hotels', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_hotels ) ); ?></div>
							<div class="text-gray-600 text-sm">
								<?php
								printf(
									/* translators: %d: number of active hotels this week. */
									esc_html__( '%d active this week', 'hotel-chain' ),
									esc_html( (string) $active_this_week )
								);
								?>
							</div>
						</div>

						<!-- Total Guests -->
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-8 h-8 text-green-400" aria-hidden="true">
									<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
									<path d="M20 2v4"></path>
									<path d="M22 4h-4"></path>
									<circle cx="4" cy="20" r="2"></circle>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Total Guests', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_guests ) ); ?></div>
							<div class="text-green-700 text-sm">
								<?php
								printf(
									/* translators: %d: number of guests added this month. */
									esc_html__( '+%d this month', 'hotel-chain' ),
									esc_html( (string) $guests_this_month )
								);
								?>
							</div>
						</div>

						<!-- Total Videos -->
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-8 h-8 text-purple-400" aria-hidden="true">
									<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
									<circle cx="12" cy="8" r="2"></circle>
									<path d="M12 10v12"></path>
									<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
									<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Total Videos', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_videos ) ); ?></div>
							<div class="text-gray-600 text-sm"><?php esc_html_e( 'In library', 'hotel-chain' ); ?></div>
						</div>

						<!-- Total Views -->
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-8 h-8 text-orange-400" aria-hidden="true">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $total_views ) ); ?></div>
							<div class="text-green-700 text-sm">
								<?php
								if ( $views_percentage_change > 0 ) {
									printf(
										/* translators: %d: percentage change. */
										esc_html__( '+%d%% vs last month', 'hotel-chain' ),
										esc_html( (string) $views_percentage_change )
									);
								} elseif ( $views_percentage_change < 0 ) {
									printf(
										/* translators: %d: percentage change. */
										esc_html__( '%d%% vs last month', 'hotel-chain' ),
										esc_html( (string) $views_percentage_change )
									);
								} else {
									esc_html_e( 'No change vs last month', 'hotel-chain' );
								}
								?>
							</div>
						</div>

						<!-- Watch Hours -->
						<div class="bg-white rounded p-4 border border-solid border-gray-400">
							<div class="flex items-center gap-3 mb-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-heart w-8 h-8 text-teal-400" aria-hidden="true">
									<path d="M11 14h2a2 2 0 0 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"></path>
									<path d="m14.45 13.39 5.05-4.694C20.196 8 21 6.85 21 5.75a2.75 2.75 0 0 0-4.797-1.837.276.276 0 0 1-.406 0A2.75 2.75 0 0 0 11 5.75c0 1.2.802 2.248 1.5 2.946L16 11.95"></path>
									<path d="m2 15 6 6"></path>
									<path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a1 1 0 0 0-2.75-2.91"></path>
								</svg>
								<div class="text-gray-600"><?php esc_html_e( 'Watch Hours', 'hotel-chain' ); ?></div>
							</div>
							<div class="text-gray-900 mb-1 text-2xl font-semibold"><?php echo esc_html( number_format_i18n( $watch_hours_this_month ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></div>
							<div class="text-gray-600 text-sm"><?php esc_html_e( 'This month', 'hotel-chain' ); ?></div>
						</div>
					</div>

					<!-- Quick Actions -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400"><?php esc_html_e( 'Quick Actions', 'hotel-chain' ); ?></h3>
						<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-chain-accounts' ) ); ?>" class="border border-solid border-gray-400 rounded p-4 text-center hover:bg-gray-50 cursor-pointer no-underline">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart w-12 h-12 text-blue-500 mx-auto mb-2" aria-hidden="true">
									<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path>
								</svg>
								<div><?php esc_html_e( 'Create Hotel', 'hotel-chain' ); ?></div>
								<div class="text-gray-600 mt-1"><?php esc_html_e( 'Add new hotel account', 'hotel-chain' ); ?></div>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-video-upload' ) ); ?>" class="border border-solid border-gray-400 rounded p-4 text-center hover:bg-gray-50 cursor-pointer no-underline">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower2 lucide-flower-2 w-12 h-12 text-purple-500 mx-auto mb-2" aria-hidden="true">
									<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
									<circle cx="12" cy="8" r="2"></circle>
									<path d="M12 10v12"></path>
									<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
									<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
								</svg>
								<div><?php esc_html_e( 'Upload Video', 'hotel-chain' ); ?></div>
								<div class="text-gray-600 mt-1"><?php esc_html_e( 'Add to library', 'hotel-chain' ); ?></div>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=system-analytics' ) ); ?>" class="border border-solid border-gray-400 rounded p-4 text-center hover:bg-gray-50 cursor-pointer no-underline">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sunrise w-12 h-12 text-green-500 mx-auto mb-2" aria-hidden="true">
									<path d="M12 2v8"></path>
									<path d="m4.93 10.93 1.41 1.41"></path>
									<path d="M2 18h2"></path>
									<path d="M20 18h2"></path>
									<path d="m19.07 10.93-1.41 1.41"></path>
									<path d="M22 22H2"></path>
									<path d="m8 6 4-4 4 4"></path>
									<path d="M16 18a4 4 0 0 0-8 0"></path>
								</svg>
								<div><?php esc_html_e( 'System Analytics', 'hotel-chain' ); ?></div>
								<div class="text-gray-600 mt-1"><?php esc_html_e( 'View all metrics', 'hotel-chain' ); ?></div>
							</a>
							<div class="border border-solid border-gray-400 rounded p-4 text-center hover:bg-gray-50 cursor-pointer">
								<div class="w-12 h-12 bg-gray-300 rounded mx-auto mb-2 flex items-center justify-center">
									<div class="text-gray-600">⚙</div>
								</div>
								<div><?php esc_html_e( 'System Settings', 'hotel-chain' ); ?></div>
								<div class="text-gray-600 mt-1"><?php esc_html_e( 'Configure platform', 'hotel-chain' ); ?></div>
							</div>
						</div>
					</div>

					<!-- Top Hotels by Engagement -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400"><?php esc_html_e( 'Top Hotels by Engagement (This Month)', 'hotel-chain' ); ?></h3>
						<?php if ( ! empty( $top_hotels ) ) : ?>
							<!-- Mobile Card View -->
							<div class="md:hidden space-y-3">
								<?php foreach ( $top_hotels as $hotel ) : ?>
									<?php
									$watch_hours = round( (int) $hotel->total_watch_seconds / 3600 );
									?>
									<div class="border border-solid border-gray-400 rounded-lg p-4 bg-white">
										<div class="mb-3 pb-3 border-b border-gray-400">
											<div class="font-semibold text-base mb-1" style="color: rgb(60, 56, 55);"><?php echo esc_html( $hotel->hotel_name ); ?></div>
											<div class="text-sm" style="color: rgb(122, 122, 122);"><?php echo esc_html( $hotel->hotel_code ); ?></div>
										</div>
										<div class="space-y-2">
											<div class="flex justify-between items-center py-2 border-b border-gray-200">
												<span class="text-sm" style="color: rgb(122, 122, 122);"><?php esc_html_e( 'Total Guests', 'hotel-chain' ); ?></span>
												<span class="font-semibold"><?php echo esc_html( number_format_i18n( (int) $hotel->total_guests ) ); ?></span>
											</div>
											<div class="flex justify-between items-center py-2 border-b border-gray-200">
												<span class="text-sm" style="color: rgb(122, 122, 122);"><?php esc_html_e( 'Active Guests', 'hotel-chain' ); ?></span>
												<span class="px-3 py-1 bg-green-100 border border-green-300 rounded text-green-900 text-sm font-semibold">
													<?php echo esc_html( number_format_i18n( (int) $hotel->active_guests ) ); ?>
												</span>
											</div>
											<div class="flex justify-between items-center py-2 border-b border-gray-200">
												<span class="text-sm" style="color: rgb(122, 122, 122);"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></span>
												<span class="font-semibold"><?php echo esc_html( number_format_i18n( (int) $hotel->total_views ) ); ?></span>
											</div>
											<div class="flex justify-between items-center py-2">
												<span class="text-sm" style="color: rgb(122, 122, 122);"><?php esc_html_e( 'Watch Hours', 'hotel-chain' ); ?></span>
												<span class="font-semibold"><?php echo esc_html( number_format_i18n( $watch_hours ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></span>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<!-- Desktop Table View -->
							<div class="hidden md:block overflow-x-auto">
								<div class="border border-solid border-gray-400 rounded overflow-hidden min-w-[600px]">
									<div class="bg-gray-200 border-b border-solid border-gray-400 grid grid-cols-6 gap-4 p-3">
										<div class="col-span-2 font-semibold"><?php esc_html_e( 'Hotel Name', 'hotel-chain' ); ?></div>
										<div class="font-semibold"><?php esc_html_e( 'Total Guests', 'hotel-chain' ); ?></div>
										<div class="font-semibold"><?php esc_html_e( 'Active Guests', 'hotel-chain' ); ?></div>
										<div class="font-semibold"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
										<div class="font-semibold"><?php esc_html_e( 'Watch Hours', 'hotel-chain' ); ?></div>
									</div>
									<?php foreach ( $top_hotels as $hotel ) : ?>
										<?php
										$watch_hours = round( (int) $hotel->total_watch_seconds / 3600 );
										?>
										<div class="grid grid-cols-6 gap-4 p-3 border-b border-solid border-gray-400 last:border-b-0">
											<div class="col-span-2">
												<div class="font-medium"><?php echo esc_html( $hotel->hotel_name ); ?></div>
												<div class="text-gray-600 text-sm"><?php echo esc_html( $hotel->hotel_code ); ?></div>
											</div>
											<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $hotel->total_guests ) ); ?></div>
											<div class="flex items-center">
												<span class="px-3 py-1 bg-green-100 border border-green-300 rounded text-green-900">
													<?php echo esc_html( number_format_i18n( (int) $hotel->active_guests ) ); ?>
												</span>
											</div>
											<div class="flex items-center"><?php echo esc_html( number_format_i18n( (int) $hotel->total_views ) ); ?></div>
											<div class="flex items-center"><?php echo esc_html( number_format_i18n( $watch_hours ) ); ?> <?php esc_html_e( 'hrs', 'hotel-chain' ); ?></div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php else : ?>
							<div class="p-4 text-center text-gray-500">
								<?php esc_html_e( 'No hotel engagement data available yet.', 'hotel-chain' ); ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Guest Activity Status -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400"><?php esc_html_e( 'Guest Activity Status', 'hotel-chain' ); ?></h3>
						<?php
						// Calculate guest activity status.
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
						<div class="space-y-3">
								<div class="p-3 bg-green-50 border border-solid border-green-300 rounded">
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
							<div class="p-3 bg-orange-50 border border-solid border-orange-300 rounded">
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
							<div class="p-3 bg-red-50 border border-solid border-red-300 rounded">
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

					<!-- Recent System Activity -->
					<div class="bg-white rounded p-4 border border-solid border-gray-400">
						<h3 class="mb-4 pb-3 border-b border-solid border-gray-400"><?php esc_html_e( 'Recent System Activity', 'hotel-chain' ); ?></h3>
						<div class="space-y-2">
							<?php
							// Get recent hotels created.
							$recent_hotels = $hotel_repo->get_all(
								array(
									'limit'   => 3,
									'orderby' => 'created_at',
									'order'   => 'DESC',
								)
							);

							// Get recent videos uploaded.
							$recent_videos = $video_repo->get_all(
								array(
									'limit'   => 2,
									'orderby' => 'created_at',
									'order'   => 'DESC',
								)
							);

							$activity_items = array();

							// Add hotel creation activities.
							foreach ( $recent_hotels as $hotel ) {
								// Try to get the hotel's user, otherwise use current admin.
								$hotel_user = get_user_by( 'id', $hotel->user_id );
								$current_user = wp_get_current_user();
								$display_user = $current_user->display_name ? $current_user->display_name : __( 'System', 'hotel-chain' );
								
								$activity_items[] = array(
									'type'    => 'hotel',
									'message' => sprintf(
										/* translators: %s: hotel name. */
										__( 'New hotel account created: %s', 'hotel-chain' ),
										$hotel->hotel_name
									),
									'time'    => $hotel->created_at,
									'user'    => $display_user,
									'color'   => 'green',
								);
							}

							// Add video upload activities.
							foreach ( $recent_videos as $video ) {
								$current_user = wp_get_current_user();
								$display_user = $current_user->display_name ? $current_user->display_name : __( 'System', 'hotel-chain' );
								
								$activity_items[] = array(
									'type'    => 'video',
									'message' => sprintf(
										/* translators: %s: video title. */
										__( 'New video uploaded: %s', 'hotel-chain' ),
										$video->title
									),
									'time'    => $video->created_at,
									'user'    => $display_user,
									'color'   => 'purple',
								);
							}

							// Sort by time (most recent first).
							usort(
								$activity_items,
								function( $a, $b ) {
									return strtotime( $b['time'] ) - strtotime( $a['time'] );
								}
							);

							// Display up to 4 most recent activities.
							$activity_items = array_slice( $activity_items, 0, 4 );

							$color_map = array(
								'green'  => 'border-green-500',
								'blue'   => 'border-blue-500',
								'purple' => 'border-purple-500',
								'orange' => 'border-orange-500',
							);

							if ( ! empty( $activity_items ) ) :
								foreach ( $activity_items as $activity ) :
									$color_class = isset( $color_map[ $activity['color'] ] ) ? $color_map[ $activity['color'] ] : 'border-gray-500';
									$time_ago = human_time_diff( strtotime( $activity['time'] ), current_time( 'timestamp' ) );
									?>
									<div class="p-3 bg-gray-50 border-l-4 <?php echo esc_attr( $color_class ); ?>">
										<div><?php echo esc_html( $activity['message'] ); ?></div>
										<div class="text-gray-600 text-sm">
											<?php
											printf(
												/* translators: 1: time ago, 2: user name. */
												esc_html__( '%1$s ago • Admin: %2$s', 'hotel-chain' ),
												esc_html( $time_ago ),
												esc_html( $activity['user'] )
											);
											?>
										</div>
									</div>
									<?php
								endforeach;
							else :
								?>
								<div class="p-4 text-center text-gray-500">
									<?php esc_html_e( 'No recent activity to display.', 'hotel-chain' ); ?>
								</div>
								<?php
							endif;
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

