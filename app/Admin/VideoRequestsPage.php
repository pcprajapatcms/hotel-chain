<?php
/**
 * Admin Video Requests page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelVideoAssignmentRepository;

/**
 * Render Video Requests admin page.
 */
class VideoRequestsPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_approve_request', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_hotel_chain_reject_request', array( $this, 'handle_reject' ) );
		add_action( 'wp_ajax_hotel_chain_ajax_approve_request', array( $this, 'ajax_approve' ) );
		add_action( 'wp_ajax_hotel_chain_ajax_reject_request', array( $this, 'ajax_reject' ) );
	}

	/**
	 * Register submenu under Hotel Accounts.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$assignment_repo = new HotelVideoAssignmentRepository();
		$pending_count = $assignment_repo->get_pending_requests_count();

		$menu_title = __( 'Video Requests', 'hotel-chain' );
		if ( $pending_count > 0 ) {
			$menu_title .= sprintf( ' <span class="awaiting-mod">%d</span>', $pending_count );
		}

		add_submenu_page(
			'hotel-chain-accounts',
			__( 'Video Requests', 'hotel-chain' ),
			$menu_title,
			'manage_options',
			'hotel-video-requests',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle approve request.
	 *
	 * @return void
	 */
	public function handle_approve(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_approve_request' );

		$request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;

		if ( $request_id ) {
			$assignment_repo = new HotelVideoAssignmentRepository();
			$assignment_repo->approve( $request_id, get_current_user_id() );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'hotel-video-requests',
					'approved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX approve request.
	 *
	 * @return void
	 */
	public function ajax_approve(): void {
		check_ajax_referer( 'hotel_chain_video_requests', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$assignment_repo = new HotelVideoAssignmentRepository();
		$result = $assignment_repo->approve( $request_id, get_current_user_id() );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Request approved. Video assigned to hotel.', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to approve request.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * AJAX reject request.
	 *
	 * @return void
	 */
	public function ajax_reject(): void {
		check_ajax_referer( 'hotel_chain_video_requests', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$assignment_repo = new HotelVideoAssignmentRepository();
		$result = $assignment_repo->reject( $request_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Request rejected.', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reject request.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Handle reject request.
	 *
	 * @return void
	 */
	public function handle_reject(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_reject_request' );

		$request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;

		if ( $request_id ) {
			$assignment_repo = new HotelVideoAssignmentRepository();
			$assignment_repo->reject( $request_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'hotel-video-requests',
					'rejected' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render requests page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$approved = isset( $_GET['approved'] ) ? absint( $_GET['approved'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rejected = isset( $_GET['rejected'] ) ? absint( $_GET['rejected'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$assignment_repo = new HotelVideoAssignmentRepository();
		$pending_requests = $assignment_repo->get_pending_requests();
		?>
		<div class="wrap w-8/12 mx-auto">
			<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Video Requests', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-300 pb-3"><?php esc_html_e( 'Review and manage video access requests from hotels.', 'hotel-chain' ); ?></p>

			<?php if ( $approved ) : ?>
				<div class="bg-green-50 border border-solid border-green-300 rounded p-3 mb-4 text-sm text-green-900">
					<?php esc_html_e( 'Request approved successfully. Video has been assigned to the hotel.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $rejected ) : ?>
				<div class="bg-red-50 border border-solid border-red-300 rounded p-3 mb-4 text-sm text-red-900">
					<?php esc_html_e( 'Request rejected successfully.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<div class="bg-white rounded p-4 border border-solid border-gray-300">
				<div class="mb-4 pb-3 border-b border-solid border-gray-300">
					<h3 class="text-lg font-semibold">
						<?php
						printf(
							/* translators: %d: number of pending requests. */
							esc_html__( 'Pending Requests (%d)', 'hotel-chain' ),
							count( $pending_requests )
						);
						?>
					</h3>
				</div>

				<div id="requests-empty-message" class="text-gray-600 py-8 text-center <?php echo empty( $pending_requests ) ? '' : 'hidden'; ?>">
					<?php esc_html_e( 'No pending video requests.', 'hotel-chain' ); ?>
				</div>

				<table id="requests-table" class="w-full <?php echo empty( $pending_requests ) ? 'hidden' : ''; ?>">
					<thead>
						<tr class="border-b border-solid border-gray-200">
							<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Hotel', 'hotel-chain' ); ?></th>
							<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Video', 'hotel-chain' ); ?></th>
							<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Requested', 'hotel-chain' ); ?></th>
							<th class="text-right py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Actions', 'hotel-chain' ); ?></th>
						</tr>
					</thead>
					<tbody id="requests-tbody">
						<?php foreach ( $pending_requests as $request ) : ?>
							<tr class="border-b border-solid border-gray-100 hover:bg-gray-50" data-request-id="<?php echo esc_attr( $request->id ); ?>">
								<td class="py-3 px-2">
									<div class="font-medium text-gray-900"><?php echo esc_html( $request->hotel_name ); ?></div>
									<div class="text-xs text-gray-500"><?php echo esc_html( $request->hotel_code ); ?></div>
								</td>
								<td class="py-3 px-2">
									<div class="text-gray-900"><?php echo esc_html( $request->video_title ?: __( 'Unknown Video', 'hotel-chain' ) ); ?></div>
								</td>
								<td class="py-3 px-2 text-gray-600 text-sm">
									<?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $request->assigned_at ) ) ); ?>
								</td>
								<td class="py-3 px-2 text-right">
									<button type="button" class="ajax-approve-btn inline-block px-3 py-1 bg-green-200 border border-solid border-green-400 rounded text-green-900 text-sm mr-2 hover:bg-green-300" data-request-id="<?php echo esc_attr( $request->id ); ?>">
										<?php esc_html_e( 'Approve', 'hotel-chain' ); ?>
									</button>
									<button type="button" class="ajax-reject-btn inline-block px-3 py-1 bg-red-200 border border-solid border-red-400 rounded text-red-900 text-sm hover:bg-red-300" data-request-id="<?php echo esc_attr( $request->id ); ?>">
										<?php esc_html_e( 'Reject', 'hotel-chain' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div id="ajax-message" class="hidden rounded p-3 mb-4 text-sm"></div>
		</div>

		<script>
		(function() {
			const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			const nonce = '<?php echo esc_js( wp_create_nonce( 'hotel_chain_video_requests' ) ); ?>';
			const tbody = document.getElementById('requests-tbody');
			const table = document.getElementById('requests-table');
			const emptyMsg = document.getElementById('requests-empty-message');
			const ajaxMessage = document.getElementById('ajax-message');

			function showMessage(text, isError) {
				ajaxMessage.textContent = text;
				ajaxMessage.className = 'rounded p-3 mb-4 text-sm ' + (isError ? 'bg-red-50 border border-solid border-red-300 text-red-900' : 'bg-green-50 border border-solid border-green-300 text-green-900');
				ajaxMessage.classList.remove('hidden');
				setTimeout(() => ajaxMessage.classList.add('hidden'), 3000);
			}

			function removeRow(requestId) {
				const row = tbody.querySelector('tr[data-request-id="' + requestId + '"]');
				if (row) {
					row.remove();
				}
				// Check if table is empty
				if (tbody.children.length === 0) {
					table.classList.add('hidden');
					emptyMsg.classList.remove('hidden');
				}
			}

			// Approve buttons
			document.querySelectorAll('.ajax-approve-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					const requestId = this.dataset.requestId;
					this.disabled = true;
					this.textContent = '<?php echo esc_js( __( 'Approving...', 'hotel-chain' ) ); ?>';

					const formData = new FormData();
					formData.append('action', 'hotel_chain_ajax_approve_request');
					formData.append('request_id', requestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, { method: 'POST', body: formData })
						.then(r => r.json())
						.then(data => {
							if (data.success) {
								showMessage(data.data.message, false);
								removeRow(requestId);
							} else {
								showMessage(data.data.message || '<?php echo esc_js( __( 'Error approving request.', 'hotel-chain' ) ); ?>', true);
								this.disabled = false;
								this.textContent = '<?php echo esc_js( __( 'Approve', 'hotel-chain' ) ); ?>';
							}
						})
						.catch(() => {
							showMessage('<?php echo esc_js( __( 'Error approving request.', 'hotel-chain' ) ); ?>', true);
							this.disabled = false;
							this.textContent = '<?php echo esc_js( __( 'Approve', 'hotel-chain' ) ); ?>';
						});
				});
			});

			// Reject buttons
			document.querySelectorAll('.ajax-reject-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to reject this request?', 'hotel-chain' ) ); ?>')) {
						return;
					}

					const requestId = this.dataset.requestId;
					this.disabled = true;
					this.textContent = '<?php echo esc_js( __( 'Rejecting...', 'hotel-chain' ) ); ?>';

					const formData = new FormData();
					formData.append('action', 'hotel_chain_ajax_reject_request');
					formData.append('request_id', requestId);
					formData.append('nonce', nonce);

					fetch(ajaxUrl, { method: 'POST', body: formData })
						.then(r => r.json())
						.then(data => {
							if (data.success) {
								showMessage(data.data.message, false);
								removeRow(requestId);
							} else {
								showMessage(data.data.message || '<?php echo esc_js( __( 'Error rejecting request.', 'hotel-chain' ) ); ?>', true);
								this.disabled = false;
								this.textContent = '<?php echo esc_js( __( 'Reject', 'hotel-chain' ) ); ?>';
							}
						})
						.catch(() => {
							showMessage('<?php echo esc_js( __( 'Error rejecting request.', 'hotel-chain' ) ); ?>', true);
							this.disabled = false;
							this.textContent = '<?php echo esc_js( __( 'Reject', 'hotel-chain' ) ); ?>';
						});
				});
			});
		})();
		</script>
		<?php
	}
}
