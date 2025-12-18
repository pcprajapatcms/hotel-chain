<?php
/**
 * Admin Hotel detail view page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;

/**
 * Render single Hotel detail admin page.
 */
class HotelView implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register a (hidden) submenu page for hotel details.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'hotel-chain-accounts',
			__( 'Hotel Details', 'hotel-chain' ),
			__( 'Hotel Details', 'hotel-chain' ),
			'manage_options',
			'hotel-details',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render hotel detail view.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$detail_id = isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $detail_id ) {
			return;
		}

		$repository = new HotelRepository();
		$detail_hotel = $repository->get_by_id( $detail_id );

		if ( ! $detail_hotel ) {
			// Fallback: try to get by user_id if hotel_id was actually a user_id.
			$detail_hotel = $repository->get_by_user_id( $detail_id );
		}

		if ( ! $detail_hotel ) {
			return;
		}

		// Get WordPress user for email if needed.
		$user = get_user_by( 'id', $detail_hotel->user_id );

		$detail_code    = $detail_hotel->hotel_code ?? '';
		$detail_name    = $detail_hotel->hotel_name ?? '';
		$detail_email   = $detail_hotel->contact_email ?? ( $user ? $user->user_email : '' );
		$detail_phone   = $detail_hotel->contact_phone ?? '';
		$detail_city    = $detail_hotel->city ?? '';
		$detail_country = $detail_hotel->country ?? '';
		$detail_reg     = $detail_hotel->registration_url ?? '';
		$detail_land    = $detail_hotel->landing_url ?? '';
		$detail_status  = $detail_hotel->status ?? 'active';

		$created_ts = $detail_hotel->created_at ? strtotime( $detail_hotel->created_at ) : null;
		if ( ! $created_ts ) {
			$created_ts = current_time( 'timestamp' );
		}
		$created_label = date_i18n( 'M j, Y', $created_ts );

		// Get video assignment count.
		$assignment_repository = new HotelVideoAssignmentRepository();
		$hotel_videos = $assignment_repository->get_hotel_videos( $detail_hotel->id, array( 'status' => 'active' ) );
		$videos_assigned = count( $hotel_videos );
		?>
		<div class="wrap w-8/12 mx-auto">
		<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Hotel Detail View', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-400 pb-3"><?php esc_html_e( 'View and manage hotel details.', 'hotel-chain' ); ?></p>

			<div class="bg-white rounded p-4 border border-solid border-gray-400 bg-purple-50 mb-6">
				<div class="mb-4 pb-3 border-b border-solid border-gray-400 flex items-center justify-between">
					<h3 class="text-lg font-semibold"><?php esc_html_e( 'Hotel Detail View', 'hotel-chain' ); ?></h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-chain-accounts' ) ); ?>" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center gap-2">
						<?php esc_html_e( 'Back to all hotels', 'hotel-chain' ); ?>
					</a>
				</div>
				<div class="bg-white border border-solid border-gray-400 rounded p-6">
					<div class="grid grid-cols-3 gap-6">
						<div class="col-span-1">
							<div class="flex items-center gap-3 mb-4">
								<div class="w-16 h-16 bg-gray-200 border border-solid border-gray-400 rounded flex items-center justify-center">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 w-8 h-8 text-gray-500" aria-hidden="true">
										<path d="M10 12h4"></path>
										<path d="M10 8h4"></path>
										<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
										<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
										<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
									</svg>
								</div>
								<div>
									<div class="mb-1"><?php echo esc_html( $detail_name ); ?></div>
									<div class="text-gray-600"><?php echo esc_html( $detail_code ); ?></div>
								</div>
							</div>
							<div class="space-y-3 mb-4">
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Contact Email', 'hotel-chain' ); ?></div>
									<div><?php echo esc_html( $detail_email ); ?></div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Phone', 'hotel-chain' ); ?></div>
									<div><?php echo esc_html( $detail_phone ); ?></div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Location', 'hotel-chain' ); ?></div>
									<div><?php echo esc_html( trim( $detail_city . ( $detail_city && $detail_country ? ', ' : '' ) . $detail_country ) ); ?></div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></div>
									<?php
									$status_class = 'active' === $detail_status ? 'bg-green-200 border-green-400 text-green-900' : 'bg-gray-200 border-gray-400 text-gray-900';
									$status_label = 'active' === $detail_status ? esc_html__( 'Active', 'hotel-chain' ) : esc_html( ucfirst( $detail_status ) );
									?>
									<span class="px-3 py-1 <?php echo esc_attr( $status_class ); ?> border rounded"><?php echo esc_html( $status_label ); ?></span>
								</div>
							</div>
							<div class="mb-4">
								<div class="mb-2 flex items-center gap-2">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link w-4 h-4 text-blue-600" aria-hidden="true">
										<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
										<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
									</svg>
									<span class="text-gray-700"><?php esc_html_e( 'Registration URL:', 'hotel-chain' ); ?></span>
								</div>
								<div class="p-3 bg-gray-100 border border-solid border-gray-400 rounded font-mono text-xs break-all" id="reg-url-text"><?php echo esc_html( $detail_reg ); ?></div>
								<button type="button" class="w-full mt-2 px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 flex items-center justify-center gap-2" data-hotel-copy="reg">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-copy w-4 h-4" aria-hidden="true">
										<rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
										<path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
									</svg>
									<?php esc_html_e( 'Copy URL', 'hotel-chain' ); ?>
								</button>
							</div>
							<div class="mb-4">
								<div class="mb-2 flex items-center gap-2">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link w-4 h-4 text-blue-600" aria-hidden="true">
										<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
										<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
									</svg>
									<span class="text-gray-700"><?php esc_html_e( 'Landing Page URL:', 'hotel-chain' ); ?></span>
								</div>
								<div class="p-3 bg-gray-100 border border-solid border-gray-400 rounded font-mono text-xs break-all" id="land-url-text"><?php echo esc_html( $detail_land ); ?></div>
								<button type="button" class="w-full mt-2 px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 flex items-center justify-center gap-2" data-hotel-copy="land">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-copy w-4 h-4" aria-hidden="true">
										<rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
										<path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
									</svg>
									<?php esc_html_e( 'Copy URL', 'hotel-chain' ); ?>
								</button>
							</div>
						</div>
						<div class="col-span-2">
							<h4 class="mb-3 pb-2 border-b border-solid border-gray-400"><?php esc_html_e( 'Account Statistics', 'hotel-chain' ); ?></h4>
							<div class="grid grid-cols-3 gap-4 mb-6">
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded text-center">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Total Guests', 'hotel-chain' ); ?></div>
									<div class="text-gray-900">0</div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded text-center">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Active Guests', 'hotel-chain' ); ?></div>
									<div class="text-green-700">0</div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded text-center">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Videos Assigned', 'hotel-chain' ); ?></div>
									<div class="text-gray-900"><?php echo esc_html( number_format_i18n( $videos_assigned ) ); ?></div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded text-center">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Total Views', 'hotel-chain' ); ?></div>
									<div class="text-gray-900">0</div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded text-center">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Watch Hours', 'hotel-chain' ); ?></div>
									<div class="text-gray-900">0</div>
								</div>
								<div class="p-3 bg-gray-50 border border-solid border-gray-400 rounded text-center">
									<div class="text-gray-600 mb-1"><?php esc_html_e( 'Created', 'hotel-chain' ); ?></div>
									<div class="text-gray-900"><?php echo esc_html( $created_label ); ?></div>
								</div>
							</div>
							<h4 class="mb-3 pb-2 border-b border-solid border-gray-400"><?php esc_html_e( 'Admin Actions', 'hotel-chain' ); ?></h4>
							<div class="grid grid-cols-2 gap-3">
								<button type="button" class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900"><?php esc_html_e( 'Assign Videos', 'hotel-chain' ); ?></button>
								<button type="button" class="px-4 py-2 bg-purple-200 border-2 border-purple-400 rounded text-purple-900"><?php esc_html_e( 'View Analytics', 'hotel-chain' ); ?></button>
								<button type="button" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900"><?php esc_html_e( 'Edit Hotel Info', 'hotel-chain' ); ?></button>
								<button type="button" class="px-4 py-2 bg-orange-200 border-2 border-orange-400 rounded text-orange-900"><?php esc_html_e( 'Reset Admin Password', 'hotel-chain' ); ?></button>
								<button type="button" class="px-4 py-2 bg-gray-200 border-2 border-gray-400 rounded text-gray-900"><?php esc_html_e( 'Deactivate Hotel', 'hotel-chain' ); ?></button>
								<button type="button" class="px-4 py-2 bg-red-200 border-2 border-red-400 rounded text-red-900"><?php esc_html_e( 'Delete Hotel', 'hotel-chain' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
