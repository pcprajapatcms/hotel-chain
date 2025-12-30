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
use HotelChain\Repositories\VideoRepository;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

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
		add_action( 'wp_ajax_hotel_chain_get_all_videos', array( $this, 'ajax_get_all_videos' ) );
		add_action( 'wp_ajax_hotel_chain_assign_multiple_videos', array( $this, 'ajax_assign_multiple_videos' ) );
		add_action( 'wp_ajax_hotel_chain_delete_hotel', array( $this, 'ajax_delete_hotel' ) );
	}

	/**
	 * Register a (hidden) submenu page for hotel details.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Use null as parent to hide from menu but keep page accessible.
		add_submenu_page(
			null,
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

		$repository   = new HotelRepository();
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
			$created_ts = time();
		}
		$created_label = date_i18n( 'M j, Y', $created_ts );

		// Get video assignment count.
		$assignment_repository = new HotelVideoAssignmentRepository();
		$hotel_videos          = $assignment_repository->get_hotel_videos( $detail_hotel->id, array( 'status' => 'active' ) );
		$videos_assigned       = count( $hotel_videos );
		?>
		<div class="w-12/12 md:w-10/12 xl:w-8/12 mx-auto">
		<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Hotel Detail View', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-400 pb-3"><?php esc_html_e( 'View and manage hotel details.', 'hotel-chain' ); ?></p>

			<div class="rounded p-2 py-4 md:p-4 border border-solid border-gray-400 bg-white mb-6">
				<div class="mb-4 pb-3 border-b border-solid border-gray-400 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
					<h3 class="text-lg font-semibold"><?php esc_html_e( 'Hotel Detail View', 'hotel-chain' ); ?></h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-chain-accounts' ) ); ?>" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center justify-center gap-2 whitespace-nowrap">
						<?php esc_html_e( 'Back to all hotels', 'hotel-chain' ); ?>
					</a>
				</div>
				<div class="bg-white border border-solid border-gray-400 rounded p-3 md:p-6">
					<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
						<div class="lg:col-span-1">
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
						<div class="lg:col-span-2">
							<h4 class="mb-3 pb-2 border-b border-solid border-gray-400"><?php esc_html_e( 'Account Statistics', 'hotel-chain' ); ?></h4>
							<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
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
							<div class="grid grid-cols-2 gap-3 mb-6">
								<button type="button" id="assign-videos-btn" class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900" data-hotel-id="<?php echo esc_attr( $detail_hotel->id ); ?>"><?php esc_html_e( 'Assign Videos', 'hotel-chain' ); ?></button>
								<button type="button" class="px-4 py-2 bg-purple-200 border-2 border-purple-400 rounded text-purple-900"><?php esc_html_e( 'View Analytics', 'hotel-chain' ); ?></button>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-edit&hotel_id=' . $detail_hotel->id ) ); ?>" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900 text-center"><?php esc_html_e( 'Edit Hotel Info', 'hotel-chain' ); ?></a>
								<button type="button" id="delete-hotel-btn" class="px-4 py-2 bg-red-200 border-2 border-red-400 rounded text-red-900" data-hotel-id="<?php echo esc_attr( $detail_hotel->id ); ?>" data-hotel-name="<?php echo esc_attr( $detail_name ); ?>"><?php esc_html_e( 'Delete Hotel', 'hotel-chain' ); ?></button>
							</div>
							
							<?php if ( ! empty( $detail_reg ) ) : ?>
								<?php
								// Generate QR code for registration URL.
								$qr_code_data_uri = $this->generate_qr_code( $detail_reg );
								?>
								<h4 class="mb-3 pb-2 border-b border-solid border-gray-400"><?php esc_html_e( 'Registration QR Code', 'hotel-chain' ); ?></h4>
								<div class="flex flex-col items-center p-4 bg-gray-50 border border-solid border-gray-400 rounded">
									<div class="mb-3 text-center">
										<p class="text-sm text-gray-600 mb-2"><?php esc_html_e( 'Scan this QR code to access the guest registration page', 'hotel-chain' ); ?></p>
										<?php if ( $qr_code_data_uri ) : ?>
											<img src="<?php echo esc_attr( $qr_code_data_uri ); ?>" alt="<?php esc_attr_e( 'Registration QR Code', 'hotel-chain' ); ?>" class="mx-auto border-2 border-solid border-gray-300 rounded p-2 bg-white" style="max-width: 250px; height: auto;" />
										<?php else : ?>
											<div class="p-4 bg-red-50 border border-red-300 rounded text-red-700 text-sm">
												<?php esc_html_e( 'Error generating QR code. Please check that the QR code library is installed.', 'hotel-chain' ); ?>
											</div>
										<?php endif; ?>
									</div>
									<div class="text-center">
										<p class="text-xs text-gray-500 mb-2"><?php esc_html_e( 'Registration URL:', 'hotel-chain' ); ?></p>
										<p class="text-xs font-mono break-all text-gray-700"><?php echo esc_html( $detail_reg ); ?></p>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Assign Videos Modal -->
			<div id="assign-videos-modal" class="hidden items-center justify-center">
				<div class="bg-white rounded p-4 border border-solid border-gray-400 w-full flex flex-col">
					<!-- Modal Header -->
					<div class="mb-4 pb-3 border-b border-solid border-gray-400 flex items-center justify-between">
						<h3 class="text-lg font-semibold"><?php esc_html_e( 'Assign Videos to Hotel', 'hotel-chain' ); ?></h3>
						<button type="button" id="close-assign-modal" class="text-gray-600 hover:text-gray-900">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M18 6L6 18"></path>
								<path d="M6 6l12 12"></path>
							</svg>
						</button>
					</div>

					<!-- Modal Body -->
					<div class="bg-white border border-solid border-gray-400 rounded p-6 flex-1 flex flex-col" style="min-height: 0;">
						<div class="mb-4 flex items-center justify-between pb-3 border-b border-solid border-gray-300 flex-shrink-0">
							<div>
								<label class="flex items-center gap-2 cursor-pointer">
									<input type="checkbox" id="select-all-videos" class="w-4 h-4">
									<span class="font-semibold text-gray-800"><?php esc_html_e( 'Select All', 'hotel-chain' ); ?></span>
								</label>
							</div>
							<div class="text-sm text-gray-600">
								<span id="selected-count" class="font-semibold">0</span> <?php esc_html_e( 'videos selected', 'hotel-chain' ); ?>
							</div>
						</div>

						<div id="videos-list-container" class="space-y-2 overflow-y-auto flex-1" style="max-height: 400px;">
							<div class="text-center py-8 text-gray-500">
								<?php esc_html_e( 'Loading videos...', 'hotel-chain' ); ?>
							</div>
						</div>
					</div>

					<!-- Modal Footer -->
					<div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-solid border-gray-400">
						<button type="button" id="cancel-assign" class="px-4 py-2 bg-gray-200 border-2 border-gray-400 rounded text-gray-900">
							<?php esc_html_e( 'Cancel', 'hotel-chain' ); ?>
						</button>
						<button type="button" id="assign-selected-videos" class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900" disabled>
							<?php esc_html_e( 'Assign Selected Videos', 'hotel-chain' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function() {
			const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			const nonce = '<?php echo esc_js( wp_create_nonce( 'hotel_chain_assign_videos' ) ); ?>';
			const deleteNonce = '<?php echo esc_js( wp_create_nonce( 'hotel_chain_delete_hotel' ) ); ?>';
			const hotelId = <?php echo esc_js( $detail_hotel->id ); ?>;
			const modal = document.getElementById('assign-videos-modal');
			const assignBtn = document.getElementById('assign-videos-btn');
			const closeBtn = document.getElementById('close-assign-modal');
			const cancelBtn = document.getElementById('cancel-assign');
			const selectAllCheckbox = document.getElementById('select-all-videos');
			const videosContainer = document.getElementById('videos-list-container');
			const assignSelectedBtn = document.getElementById('assign-selected-videos');
			const selectedCountSpan = document.getElementById('selected-count');
			let allVideos = [];
			let assignedVideoIds = [];

			// Open modal
			if (assignBtn) {
				assignBtn.addEventListener('click', function() {
					loadVideos();
					modal.classList.remove('hidden');
					modal.style.display = 'flex';
					
					// Scroll modal into view and to top when it opens
					setTimeout(function() {
						const modalContent = modal.querySelector('.bg-white.rounded.p-4');
						if (modalContent) {
							// Scroll modal container to top
							modalContent.scrollTop = 0;
							// Ensure modal is visible in viewport
							modalContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
						}
						// Also scroll the videos container to top
						const videosContainer = document.getElementById('videos-list-container');
						if (videosContainer) {
							videosContainer.scrollTop = 0;
						}
					}, 100);
				});
			}

			// Close modal
			function closeModal() {
				modal.classList.add('hidden');
				modal.style.display = 'none';
				videosContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><?php echo esc_js( __( 'Loading videos...', 'hotel-chain' ) ); ?></div>';
				selectAllCheckbox.checked = false;
				updateSelectedCount();
			}

			if (closeBtn) closeBtn.addEventListener('click', closeModal);
			if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

			// Close on background click
			modal.addEventListener('click', function(e) {
				if (e.target === modal) {
					closeModal();
				}
			});

			// Load all videos
			function loadVideos() {
				const formData = new FormData();
				formData.append('action', 'hotel_chain_get_all_videos');
				formData.append('hotel_id', hotelId);
				formData.append('nonce', nonce);

				fetch(ajaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						allVideos = data.data.videos || [];
						assignedVideoIds = data.data.assigned_video_ids || [];
						renderVideos();
					} else {
						videosContainer.innerHTML = '<div class="text-center py-8 text-red-500">' + (data.data?.message || '<?php echo esc_js( __( 'Error loading videos.', 'hotel-chain' ) ); ?>') + '</div>';
					}
				})
				.catch(error => {
					console.error('Error:', error);
					videosContainer.innerHTML = '<div class="text-center py-8 text-red-500"><?php echo esc_js( __( 'Error loading videos.', 'hotel-chain' ) ); ?></div>';
				});
			}

			// Render videos list
			function renderVideos() {
				if (allVideos.length === 0) {
					videosContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><?php echo esc_js( __( 'No videos available.', 'hotel-chain' ) ); ?></div>';
					return;
				}

				let html = '';
				allVideos.forEach(function(video) {
					const isAssigned = assignedVideoIds.includes(video.video_id);
					const checked = isAssigned ? 'checked disabled' : '';
					const assignedBadge = isAssigned ? '<span class="ml-2 px-2 py-1 bg-green-200 border border-green-400 text-green-900 text-xs rounded"><?php echo esc_js( __( 'Already Assigned', 'hotel-chain' ) ); ?></span>' : '';
					
					html += `
						<div class="flex items-center gap-3 p-3 border border-solid border-gray-300 rounded hover:bg-gray-50" style="border-color: rgb(196, 196, 196);">
							<input type="checkbox" class="video-checkbox w-4 h-4" value="${video.video_id}" data-video-id="${video.video_id}" ${checked}>
							<div class="flex-1">
								<div class="font-semibold text-gray-900">${video.title || ''} ${assignedBadge}</div>
								<div class="text-sm text-gray-600">${video.category || '<?php echo esc_js( __( 'Uncategorized', 'hotel-chain' ) ); ?>'} • ${video.duration || '0:00'}</div>
							</div>
						</div>
					`;
				});

				videosContainer.innerHTML = html;

				// Add event listeners to checkboxes
				document.querySelectorAll('.video-checkbox').forEach(function(checkbox) {
					checkbox.addEventListener('change', updateSelectedCount);
				});

				updateSelectedCount();
			}

			// Update selected count
			function updateSelectedCount() {
				const checked = document.querySelectorAll('.video-checkbox:checked:not([disabled])');
				const count = checked.length;
				selectedCountSpan.textContent = count;
				assignSelectedBtn.disabled = count === 0;

				// Update select all checkbox
				const allCheckboxes = document.querySelectorAll('.video-checkbox:not([disabled])');
				selectAllCheckbox.checked = allCheckboxes.length > 0 && checked.length === allCheckboxes.length;
			}

			// Select all functionality
			if (selectAllCheckbox) {
				selectAllCheckbox.addEventListener('change', function() {
					document.querySelectorAll('.video-checkbox:not([disabled])').forEach(function(checkbox) {
						checkbox.checked = this.checked;
					}, this);
					updateSelectedCount();
				});
			}

			// Assign selected videos
			if (assignSelectedBtn) {
				assignSelectedBtn.addEventListener('click', function() {
					const checked = document.querySelectorAll('.video-checkbox:checked:not([disabled])');
					const videoIds = Array.from(checked).map(cb => parseInt(cb.value));

					if (videoIds.length === 0) {
						alert('<?php echo esc_js( __( 'Please select at least one video.', 'hotel-chain' ) ); ?>');
						return;
					}

					this.disabled = true;
					this.textContent = '<?php echo esc_js( __( 'Assigning...', 'hotel-chain' ) ); ?>';

					const formData = new FormData();
					formData.append('action', 'hotel_chain_assign_multiple_videos');
					formData.append('hotel_id', hotelId);
					formData.append('video_ids', JSON.stringify(videoIds));
					formData.append('nonce', nonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Videos assigned successfully!', 'hotel-chain' ) ); ?>');
							closeModal();
							// Reload page to update video count
							window.location.reload();
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Error assigning videos.', 'hotel-chain' ) ); ?>');
							this.disabled = false;
							this.textContent = '<?php echo esc_js( __( 'Assign Selected Videos', 'hotel-chain' ) ); ?>';
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'Error assigning videos.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						this.textContent = '<?php echo esc_js( __( 'Assign Selected Videos', 'hotel-chain' ) ); ?>';
					});
				});
			}

			// Delete hotel functionality
			const deleteBtn = document.getElementById('delete-hotel-btn');
			if (deleteBtn) {
				deleteBtn.addEventListener('click', function() {
					const hotelId = this.getAttribute('data-hotel-id');
					const hotelName = this.getAttribute('data-hotel-name');
					
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this hotel? This action cannot be undone. All hotel data, video assignments, guests, and the associated user account will be permanently deleted.', 'hotel-chain' ) ); ?>')) {
						return;
					}

					if (!confirm('<?php echo esc_js( __( 'This is your final warning. Click OK to permanently delete the hotel and all related data.', 'hotel-chain' ) ); ?>')) {
						return;
					}

					this.disabled = true;
					this.textContent = '<?php echo esc_js( __( 'Deleting...', 'hotel-chain' ) ); ?>';

					const formData = new FormData();
					formData.append('action', 'hotel_chain_delete_hotel');
					formData.append('hotel_id', hotelId);
					formData.append('nonce', deleteNonce);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || '<?php echo esc_js( __( 'Hotel deleted successfully.', 'hotel-chain' ) ); ?>');
							// Redirect to hotel accounts page
							window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=hotel-chain-accounts' ) ); ?>';
						} else {
							alert(data.data?.message || '<?php echo esc_js( __( 'Error deleting hotel.', 'hotel-chain' ) ); ?>');
							this.disabled = false;
							this.textContent = '<?php echo esc_js( __( 'Delete Hotel', 'hotel-chain' ) ); ?>';
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('<?php echo esc_js( __( 'Error deleting hotel.', 'hotel-chain' ) ); ?>');
						this.disabled = false;
						this.textContent = '<?php echo esc_js( __( 'Delete Hotel', 'hotel-chain' ) ); ?>';
					});
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Generate QR code data URI for a given URL.
	 *
	 * @param string $url URL to encode in QR code.
	 * @return string|null Data URI of QR code image or null on error.
	 */
	private function generate_qr_code( string $url ): ?string {
		if ( empty( $url ) ) {
			return null;
		}

		try {
			// Check if QR code library is available.
			if ( ! class_exists( 'Endroid\QrCode\QrCode' ) ) {
				return null;
			}

			// Check if GD extension is available (required for PNG).
			if ( ! extension_loaded( 'gd' ) ) {
				return null;
			}

			// Create QR code with high error correction level.
			$qr_code = QrCode::create( $url )
				->setSize( 300 )
				->setMargin( 10 )
				->setErrorCorrectionLevel( new ErrorCorrectionLevelHigh() );

			$writer = new PngWriter();
			$result = $writer->write( $qr_code );

			// Convert to data URI.
			$data_uri = $result->getDataUri();

			return $data_uri;
		} catch ( \Exception $e ) {
			// Log error but don't break the page.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'QR Code generation error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		} catch ( \Throwable $e ) {
			// Catch any other errors.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'QR Code generation error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}
	}

	/**
	 * AJAX handler to get all videos for assignment modal.
	 *
	 * @return void
	 */
	public function ajax_get_all_videos(): void {
		check_ajax_referer( 'hotel_chain_assign_videos', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $hotel_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid hotel ID.', 'hotel-chain' ) ) );
		}

		$video_repository      = new VideoRepository();
		$assignment_repository = new HotelVideoAssignmentRepository();

		// Get all videos.
		$all_videos = $video_repository->get_all( array( 'limit' => -1 ) );

		// Get already assigned video IDs for this hotel.
		$assigned_videos    = $assignment_repository->get_hotel_videos( $hotel_id, array( 'status' => '' ) );
		$assigned_video_ids = array();
		foreach ( $assigned_videos as $assignment ) {
			$assigned_video_ids[] = (int) $assignment->video_id;
		}

		// Format videos for response.
		$format_duration = function ( $seconds ) {
			if ( ! $seconds ) {
				return '0:00';
			}
			$mins = floor( $seconds / 60 );
			$secs = $seconds % 60;
			return sprintf( '%d:%02d', $mins, $secs );
		};

		$videos = array();
		foreach ( $all_videos as $video ) {
			$videos[] = array(
				'video_id' => (int) $video->video_id,
				'title'    => $video->title,
				'category' => $video->category ? $video->category : __( 'Uncategorized', 'hotel-chain' ),
				'duration' => $format_duration( $video->duration_seconds ),
			);
		}

		wp_send_json_success(
			array(
				'videos'             => $videos,
				'assigned_video_ids' => $assigned_video_ids,
			)
		);
	}

	/**
	 * AJAX handler to assign multiple videos to hotel.
	 *
	 * @return void
	 */
	public function ajax_assign_multiple_videos(): void {
		check_ajax_referer( 'hotel_chain_assign_videos', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$hotel_id       = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$video_ids_json = isset( $_POST['video_ids'] ) ? wp_unslash( $_POST['video_ids'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $hotel_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid hotel ID.', 'hotel-chain' ) ) );
		}

		$video_ids = json_decode( $video_ids_json, true );
		if ( ! is_array( $video_ids ) || empty( $video_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid video IDs.', 'hotel-chain' ) ) );
		}

		$assignment_repository = new HotelVideoAssignmentRepository();
		$assigned_count        = 0;
		$skipped_count         = 0;
		$current_user_id       = get_current_user_id();

		foreach ( $video_ids as $video_id ) {
			$video_id = absint( $video_id );
			if ( ! $video_id ) {
				continue;
			}

			$result = $assignment_repository->assign( $hotel_id, $video_id, $current_user_id );
			if ( $result ) {
				++$assigned_count;
			} else {
				++$skipped_count;
			}
		}

		if ( $assigned_count > 0 ) {
			wp_send_json_success(
				array(
					'message'        => sprintf(
						/* translators: %d: number of videos assigned. */
						_n( '%d video assigned successfully.', '%d videos assigned successfully.', $assigned_count, 'hotel-chain' ),
						$assigned_count
					),
					'assigned_count' => $assigned_count,
					'skipped_count'  => $skipped_count,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'No videos were assigned. They may already be assigned.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * AJAX handler to delete hotel and all related data.
	 *
	 * @return void
	 */
	public function ajax_delete_hotel(): void {
		check_ajax_referer( 'hotel_chain_delete_hotel', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;

		if ( ! $hotel_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid hotel ID.', 'hotel-chain' ) ) );
		}

		$repository = new HotelRepository();
		$hotel      = $repository->get_by_id( $hotel_id );

		if ( ! $hotel ) {
			wp_send_json_error( array( 'message' => __( 'Hotel not found.', 'hotel-chain' ) ) );
		}

		$result = $repository->delete( $hotel_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Hotel and all related data deleted successfully.', 'hotel-chain' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete hotel.', 'hotel-chain' ) ) );
		}
	}
}
