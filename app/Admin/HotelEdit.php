<?php
/**
 * Admin Hotel edit page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;

/**
 * Render hotel edit admin page.
 */
class HotelEdit implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_update_hotel', array( $this, 'handle_update' ) );
	}

	/**
	 * Register a (hidden) submenu page for hotel edit.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Use null as parent to hide from menu but keep page accessible.
		add_submenu_page(
			null,
			__( 'Edit Hotel', 'hotel-chain' ),
			__( 'Edit Hotel', 'hotel-chain' ),
			'manage_options',
			'hotel-edit',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render hotel edit page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for display purposes only.
		$hotel_id = isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0;

		if ( ! $hotel_id ) {
			wp_die( esc_html__( 'Invalid hotel ID.', 'hotel-chain' ) );
		}

		$repository = new HotelRepository();
		$hotel      = $repository->get_by_id( $hotel_id );

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel not found.', 'hotel-chain' ) );
		}

		// Get WordPress user for email if needed.
		$user = get_user_by( 'id', $hotel->user_id );

		$hotel_name      = $hotel->hotel_name ?? '';
		$hotel_code      = $hotel->hotel_code ?? '';
		$contact_email   = $hotel->contact_email ?? ( $user ? $user->user_email : '' );
		$contact_phone   = $hotel->contact_phone ?? '';
		$address         = $hotel->address ?? '';
		$city            = $hotel->city ?? '';
		$country         = $hotel->country ?? '';
		$website         = $hotel->website ?? '';
		$status          = $hotel->status ?? 'active';
		$access_duration = $hotel->access_duration ?? 0;

		// Check for success/error messages.
		$message      = '';
		$message_type = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for display purposes only.
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			$message      = __( 'Hotel information updated successfully.', 'hotel-chain' );
			$message_type = 'success';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for display purposes only.
		} elseif ( isset( $_GET['error'] ) && '1' === $_GET['error'] ) {
			$message      = __( 'Failed to update hotel information. Please try again.', 'hotel-chain' );
			$message_type = 'error';
		}
		?>
		<div class="w-12/12 md:w-10/12 xl:w-8/12 mx-auto">
			<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Edit Hotel Information', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-400 pb-3"><?php esc_html_e( 'Update hotel details and information.', 'hotel-chain' ); ?></p>

			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>

			<div class="rounded p-2 py-4 md:p-4 border border-solid border-gray-400 bg-white mb-6">
				<div class="mb-4 pb-3 border-b border-solid border-gray-400 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
					<h3 class="text-lg font-semibold"><?php esc_html_e( 'Hotel Information', 'hotel-chain' ); ?></h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-details&hotel_id=' . $hotel_id ) ); ?>" class="px-4 py-2 bg-gray-200 border-2 border-gray-400 rounded text-gray-900 whitespace-nowrap text-center">
						<?php esc_html_e( 'Back to Hotel Details', 'hotel-chain' ); ?>
					</a>
				</div>
				<div class="bg-white md:border border-solid border-gray-400 rounded p-0 md:p-6">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'hotel_chain_update_hotel' ); ?>
						<input type="hidden" name="action" value="hotel_chain_update_hotel" />
						<input type="hidden" name="hotel_id" value="<?php echo esc_attr( $hotel_id ); ?>" />

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="hotel_name"><?php esc_html_e( 'Hotel Name', 'hotel-chain' ); ?></label>
									<input type="text" id="hotel_name" name="hotel_name" value="<?php echo esc_attr( $hotel_name ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" required />
								</div>

								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="hotel_code"><?php esc_html_e( 'Hotel Code', 'hotel-chain' ); ?> <span class="text-xs text-red-500 mt-1">(<?php esc_html_e( 'Hotel code cannot be changed', 'hotel-chain' ); ?>)</span></label>
									<input type="text" id="hotel_code" name="hotel_code" value="<?php echo esc_attr( $hotel_code ); ?>" readonly class="w-full px-3 py-2 border border-slate-300 rounded-md bg-slate-50 text-slate-500 cursor-not-allowed" />
								</div>

								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="contact_email"><?php esc_html_e( 'Contact Email', 'hotel-chain' ); ?> <span class="text-xs text-red-500 mt-1">(<?php esc_html_e( 'Contact email cannot be changed', 'hotel-chain' ); ?>)</span></label>
									<input type="email" id="contact_email" name="contact_email" value="<?php echo esc_attr( $contact_email ); ?>" readonly class="w-full px-3 py-2 border border-slate-300 rounded-md bg-slate-50 text-slate-500 cursor-not-allowed" />
									
								</div>

								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="contact_phone"><?php esc_html_e( 'Contact Phone', 'hotel-chain' ); ?></label>
									<input type="text" id="contact_phone" name="contact_phone" value="<?php echo esc_attr( $contact_phone ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
							</div>

							<div>
								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="address"><?php esc_html_e( 'Address', 'hotel-chain' ); ?></label>
									<input type="text" id="address" name="address" value="<?php echo esc_attr( $address ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>

								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="city"><?php esc_html_e( 'City', 'hotel-chain' ); ?></label>
									<input type="text" id="city" name="city" value="<?php echo esc_attr( $city ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>

								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="country"><?php esc_html_e( 'Country', 'hotel-chain' ); ?></label>
									<input type="text" id="country" name="country" value="<?php echo esc_attr( $country ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>

								<div class="mb-4">
									<label class="mb-1 text-sm font-semibold text-slate-800 block" for="website"><?php esc_html_e( 'Website', 'hotel-chain' ); ?></label>
									<input type="url" id="website" name="website" value="<?php echo esc_attr( $website ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								</div>
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="status"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></label>
								<select id="status" name="status" class="w-full max-w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500">
									<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'hotel-chain' ); ?></option>
									<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'hotel-chain' ); ?></option>
								</select>
							</div>

							<div class="mb-4">
								<label class="mb-1 text-sm font-semibold text-slate-800 block" for="access_duration"><?php esc_html_e( 'Access Duration (days)', 'hotel-chain' ); ?></label>
								<input type="number" id="access_duration" name="access_duration" value="<?php echo esc_attr( $access_duration ); ?>" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
								<?php
								$system_default = \HotelChain\Support\AccountSettings::get_default_guest_duration();
								/* translators: %d: System default duration in days */
								$help_text = sprintf( __( 'Number of days guests can access the meditation series. Set to 0 to use system default (%d days).', 'hotel-chain' ), $system_default );
								?>
								<p class="text-xs text-gray-600 mt-1"><?php echo esc_html( $help_text ); ?></p>
							</div>
						</div>

						<div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-3 mt-6 pt-6 border-t border-solid border-gray-400">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-details&hotel_id=' . $hotel_id ) ); ?>" class="px-4 py-2 bg-gray-200 border-2 border-gray-400 rounded text-gray-900 text-center">
								<?php esc_html_e( 'Cancel', 'hotel-chain' ); ?>
							</a>
							<button type="submit" class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900">
								<?php esc_html_e( 'Update Hotel Information', 'hotel-chain' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle hotel update form submission.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_update_hotel' );

		$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;

		if ( ! $hotel_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'hotel-chain-accounts',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$repository = new HotelRepository();
		$hotel      = $repository->get_by_id( $hotel_id );

		if ( ! $hotel ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'hotel-chain-accounts',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Prepare update data.
		$update_data = array();

		if ( isset( $_POST['hotel_name'] ) ) {
			$update_data['hotel_name'] = sanitize_text_field( wp_unslash( $_POST['hotel_name'] ) );
		}

		// Hotel code and email are not editable - do not update them.

		if ( isset( $_POST['contact_phone'] ) ) {
			$update_data['contact_phone'] = sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) );
		}

		if ( isset( $_POST['address'] ) ) {
			$update_data['address'] = sanitize_text_field( wp_unslash( $_POST['address'] ) );
		}

		if ( isset( $_POST['city'] ) ) {
			$update_data['city'] = sanitize_text_field( wp_unslash( $_POST['city'] ) );
		}

		if ( isset( $_POST['country'] ) ) {
			$update_data['country'] = sanitize_text_field( wp_unslash( $_POST['country'] ) );
		}

		if ( isset( $_POST['website'] ) ) {
			$update_data['website'] = esc_url_raw( wp_unslash( $_POST['website'] ) );
		}

		if ( isset( $_POST['status'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_POST['status'] ) );
			// Validate status - only allow 'active' or 'inactive'.
			if ( in_array( $status, array( 'active', 'inactive' ), true ) ) {
				$update_data['status'] = $status;
			}
		}

		if ( isset( $_POST['access_duration'] ) ) {
			$update_data['access_duration'] = absint( $_POST['access_duration'] );
		}

		$result = $repository->update( $hotel_id, $update_data );

		if ( $result ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'hotel-edit',
						'hotel_id' => $hotel_id,
						'updated'  => '1',
					),
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'hotel-edit',
						'hotel_id' => $hotel_id,
						'error'    => '1',
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}
}

