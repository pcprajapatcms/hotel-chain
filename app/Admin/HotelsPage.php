<?php
/**
 * Admin Hotels dashboard page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Render Hotel admin page.
 */
class HotelsPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_create_hotel', array( $this, 'handle_create_hotel' ) );
		add_action( 'admin_post_hotel_chain_export_hotels', array( $this, 'handle_export_hotels' ) );
	}

	/**
	 * Export hotels as CSV.
	 *
	 * @return void
	 */
	public function handle_export_hotels(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_export_hotels' );

		$hotels = get_users(
			array(
				'role'   => 'hotel',
				'number' => -1,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=hotel-accounts-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Could not open output stream.', 'hotel-chain' ) );
		}

		fputcsv(
			$output,
			array(
				'Hotel Name',
				'Code',
				'Contact Email',
				'Contact Phone',
				'City',
				'Country',
				'Access Duration (days)',
				'License Start',
				'License End',
				'Days to Renewal',
				'Registration URL',
				'Landing Page URL',
			)
		);

		foreach ( $hotels as $hotel ) {
			$code            = get_user_meta( $hotel->ID, 'hotel_code', true );
			$name            = get_user_meta( $hotel->ID, 'hotel_name', true );
			$email           = $hotel->user_email;
			$phone           = get_user_meta( $hotel->ID, 'contact_phone', true );
			$city            = get_user_meta( $hotel->ID, 'city', true );
			$country         = get_user_meta( $hotel->ID, 'country', true );
			$access_duration = (int) get_user_meta( $hotel->ID, 'access_duration', true );
			$reg_url         = get_user_meta( $hotel->ID, 'hotel_registration', true );
			$land_url        = get_user_meta( $hotel->ID, 'hotel_landing', true );

			$start_timestamp = strtotime( $hotel->user_registered );
			if ( ! $start_timestamp ) {
				$start_timestamp = time();
			}

			if ( $access_duration > 0 ) {
				$end_timestamp   = $start_timestamp + ( $access_duration * DAY_IN_SECONDS );
				$now_timestamp   = time();
				$days_diff       = (int) ceil( ( $end_timestamp - $now_timestamp ) / DAY_IN_SECONDS );
				$start_label     = gmdate( 'Y-m-d', $start_timestamp );
				$end_label       = gmdate( 'Y-m-d', $end_timestamp );
				$days_to_renewal = $days_diff >= 0 ? (string) $days_diff : 'Expired';
			} else {
				$start_label     = '';
				$end_label       = '';
				$days_to_renewal = '';
			}

			fputcsv(
				$output,
				array(
					$name ? $name : $hotel->display_name,
					$code,
					$email,
					$phone,
					$city,
					$country,
					$access_duration > 0 ? $access_duration : '',
					$start_label,
					$end_label,
					$days_to_renewal,
					$reg_url,
					$land_url,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Hotel Accounts', 'hotel-chain' ),
			__( 'Hotel Accounts', 'hotel-chain' ),
			'manage_options',
			'hotel-chain-accounts',
			array( $this, 'render_page' ),
			'dashicons-building',
			30
		);
	}

	/**
	 * Handle form submission to create hotel.
	 *
	 * @return void
	 */
	public function handle_create_hotel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_create' );

		$data = array(
			'name'       => sanitize_text_field( wp_unslash( $_POST['hotel_name'] ?? '' ) ),
			'code'       => sanitize_text_field( wp_unslash( $_POST['hotel_code'] ?? '' ) ),
			'email'      => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
			'phone'      => sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) ),
			'address'    => sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'city'       => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'country'    => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
			'duration'   => sanitize_text_field( wp_unslash( $_POST['access_duration'] ?? '' ) ),
			'admin_user' => sanitize_user( wp_unslash( $_POST['admin_username'] ?? '' ) ),
		);

		if ( empty( $data['name'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'hotel-chain-accounts',
						'hotel_error' => 'missing_name',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( empty( $data['admin_user'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'hotel-chain-accounts',
						'hotel_error' => 'missing_username',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( empty( $data['email'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'hotel-chain-accounts',
						'hotel_error' => 'missing_email',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$code = $data['code'];
		if ( empty( $code ) ) {
			$code = $this->generate_code();
		}

		$hotel_slug = sanitize_title( $data['name'] );
		$user_id    = $this->create_hotel_user( $data['admin_user'], $data['email'], $data['name'], $hotel_slug );

		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'hotel-chain-accounts',
						'hotel_error' => 'user_creation_failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		update_user_meta( $user_id, 'hotel_code', $code );
		update_user_meta( $user_id, 'hotel_name', $data['name'] );
		update_user_meta( $user_id, 'hotel_slug', $hotel_slug );
		update_user_meta( $user_id, 'contact_phone', $data['phone'] );
		update_user_meta( $user_id, 'address', $data['address'] );
		update_user_meta( $user_id, 'city', $data['city'] );
		update_user_meta( $user_id, 'country', $data['country'] );
		update_user_meta( $user_id, 'access_duration', $data['duration'] );
		update_user_meta( $user_id, 'hotel_registration', $this->registration_url( $code ) );
		update_user_meta( $user_id, 'hotel_landing', $this->landing_url( $hotel_slug ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'hotel-chain-accounts',
					'hotel_created' => $user_id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Create hotel user.
	 *
	 * @param string $username   Username.
	 * @param string $email      Email.
	 * @param string $hotel_name Hotel name.
	 * @param string $hotel_slug Hotel slug.
	 * @return int|\WP_Error User ID or error.
	 */
	private function create_hotel_user( string $username, string $email, string $hotel_name, string $hotel_slug ) {
		// Check for existing username.
		$existing_user = get_user_by( 'login', $username );
		if ( $existing_user ) {
			return new \WP_Error( 'user_exists', __( 'Username already exists.', 'hotel-chain' ) );
		}

		// Check for existing email.
		$existing_email = get_user_by( 'email', $email );
		if ( $existing_email ) {
			return new \WP_Error( 'email_exists', __( 'Email already exists.', 'hotel-chain' ) );
		}

		$password = wp_generate_password( 12, false );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'role'         => 'hotel',
				'display_name' => $hotel_name,
				'first_name'   => $hotel_name,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Send credentials email.
		wp_mail(
			$email,
			sprintf(
				/* translators: %s: hotel name. */
				__( 'Your %s hotel admin account', 'hotel-chain' ),
				$hotel_name
			),
			sprintf(
				/* translators: 1: username, 2: password, 3: login url, 4: hotel url */
				__( "Username: %1\$s\nPassword: %2\$s\nLogin: %3\$s\n\nYour hotel page: %4\$s", 'hotel-chain' ),
				$username,
				$password,
				wp_login_url(),
				home_url( '/hotel/' . $hotel_slug )
			)
		);

		return $user_id;
	}

	/**
	 * Generate hotel code.
	 *
	 * @return string
	 */
	private function generate_code(): string {
		return strtoupper( wp_generate_password( 8, false ) );
	}

	/**
	 * Registration URL.
	 *
	 * @param string $code Hotel code.
	 * @return string
	 */
	private function registration_url( string $code ): string {
		return add_query_arg( 'hotel', $code, home_url( '/register' ) );
	}

	/**
	 * Landing URL.
	 *
	 * @param string $slug Hotel slug.
	 * @return string
	 */
	private function landing_url( string $slug ): string {
		return home_url( '/hotel/' . $slug );
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$search_term = isset( $_GET['hotel_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['hotel_search'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$user_query_args = array(
			'role'    => 'hotel',
			'number'  => 20,
			'orderby' => 'ID',
			'order'   => 'DESC',
		);

		$hotels = get_users( $user_query_args );

		// Filter hotels if search term exists
		if ( ! empty( $search_term ) ) {
			$filtered_hotels = array();
			$search_lower = strtolower( $search_term );

			error_log( 'HOTEL SEARCH: Searching for "' . $search_lower . '" among ' . count( $hotels ) . ' hotels' );

			foreach ( $hotels as $hotel ) {
				$hotel_name = get_user_meta( $hotel->ID, 'hotel_name', true );
				$hotel_code = get_user_meta( $hotel->ID, 'hotel_code', true );
				$email = $hotel->user_email;
				$city = get_user_meta( $hotel->ID, 'city', true );
				$country = get_user_meta( $hotel->ID, 'country', true );

				// Build searchable text - make sure all fields are strings
				$searchable_parts = array_filter( array(
					(string) $hotel_name,
					(string) $hotel_code,
					(string) $email,
					(string) $city,
					(string) $country,
					(string) $hotel->user_login,
					(string) $hotel->display_name,
				) );

				$searchable_text = strtolower( implode( ' ', $searchable_parts ) );

				error_log( 'HOTEL SEARCH: Checking hotel ID ' . $hotel->ID . ' - Name: "' . $hotel_name . '", Code: "' . $hotel_code . '", Searchable: "' . $searchable_text . '"' );

				if ( strpos( $searchable_text, $search_lower ) !== false ) {
					$filtered_hotels[] = $hotel;
					error_log( 'HOTEL SEARCH: MATCH found for hotel ID ' . $hotel->ID );
				}
			}

			error_log( 'HOTEL SEARCH: Found ' . count( $filtered_hotels ) . ' matching hotels out of ' . count( $hotels ) );
			$hotels = $filtered_hotels;
		}

		$created_id = isset( $_GET['hotel_created'] ) ? absint( $_GET['hotel_created'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_type = isset( $_GET['hotel_error'] ) ? sanitize_text_field( wp_unslash( $_GET['hotel_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap w-8/12 mx-auto">
			<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Hotel Account Management', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-400 pb-3"><?php esc_html_e( 'Create and manage hotel accounts with unique registration URLs.', 'hotel-chain' ); ?></p>

			<?php if ( $created_id ) : ?>
				<?php
				$hotel_name = get_user_meta( $created_id, 'hotel_name', true );
				$hotel_code = get_user_meta( $created_id, 'hotel_code', true );
				$reg_url    = get_user_meta( $created_id, 'hotel_registration', true );
				$land_url   = get_user_meta( $created_id, 'hotel_landing', true );
				?>
				<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-400">
					<div class="mb-4 pb-3 border-b-2 border-gray-300">
						<h3 class="text-lg font-semibold"><?php esc_html_e( 'Success: Hotel Created - Unique URL Generated', 'hotel-chain' ); ?></h3>
					</div>
					<div class="bg-white border-2 border-gray-300 rounded p-6">
						<div class="flex items-start gap-4 mb-6">
							<div class="w-12 h-12 bg-green-200 border-2 border-green-400 rounded-full flex items-center justify-center flex-shrink-0">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big w-6 h-6 text-green-700" aria-hidden="true">
									<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
									<path d="m9 11 3 3L22 4"></path>
								</svg>
							</div>
							<div class="flex-1">
								<h3 class="mb-2 font-semibold"><?php esc_html_e( 'Hotel Account Created Successfully', 'hotel-chain' ); ?></h3>
								<div class="mb-4">
									<div class="mb-2 text-gray-600">
										<?php
										printf(
											/* translators: %s: hotel name */
											esc_html__( 'Hotel: %s', 'hotel-chain' ),
											esc_html( $hotel_name )
										);
										?>
									</div>
									<div class="text-gray-600">
										<?php
										printf(
											/* translators: %s: hotel code */
											esc_html__( 'Hotel Code: %s', 'hotel-chain' ),
											esc_html( $hotel_code )
										);
										?>
									</div>
								</div>
							</div>
						</div>
						<div class="mb-6">
							<div class="mb-2 flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link w-5 h-5 text-blue-600" aria-hidden="true">
									<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
									<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
								</svg>
								<span class="text-gray-700 font-medium"><?php esc_html_e( 'Unique Guest Registration URL:', 'hotel-chain' ); ?></span>
							</div>
							<div class="flex gap-2">
								<div class="flex-1 p-4 bg-gray-100 border-2 border-gray-300 rounded font-mono break-all"><?php echo esc_html( $reg_url ); ?></div>
							</div>
						</div>
						<div class="mb-6">
							<div class="mb-2 flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link w-5 h-5 text-blue-600" aria-hidden="true">
									<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
									<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
								</svg>
								<span class="text-gray-700 font-medium"><?php esc_html_e( 'Hotel Landing Page URL:', 'hotel-chain' ); ?></span>
							</div>
							<div class="flex gap-2">
								<div class="flex-1 p-4 bg-gray-100 border-2 border-gray-300 rounded font-mono break-all"><?php echo esc_html( $land_url ); ?></div>
							</div>
						</div>
						<div class="p-4 bg-blue-50 border-2 border-blue-300 rounded">
							<div class="text-blue-900 mb-2 font-medium"><?php esc_html_e( 'What happens next:', 'hotel-chain' ); ?></div>
							<div class="text-blue-800 space-y-1">
								<div>✓ <?php esc_html_e( 'Hotel admin credentials sent to contact email', 'hotel-chain' ); ?></div>
								<div>✓ <?php esc_html_e( 'Unique registration URL can be shared with guests', 'hotel-chain' ); ?></div>
								<div>✓ <?php esc_html_e( 'Hotel admin can log in and configure their profile', 'hotel-chain' ); ?></div>
								<div>✓ <?php esc_html_e( 'Ready to start assigning videos', 'hotel-chain' ); ?></div>
							</div>
						</div>
						<div class="mt-4">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-chain-accounts' ) ); ?>" class="px-6 py-3 bg-green-200 border-2 border-green-400 rounded text-green-900 inline-block hover:bg-green-300">
								<?php esc_html_e( 'Create Another Hotel', 'hotel-chain' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $error_type ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						switch ( $error_type ) {
							case 'missing_name':
								esc_html_e( 'Hotel name is required.', 'hotel-chain' );
								break;
							case 'missing_username':
								esc_html_e( 'Admin username is required.', 'hotel-chain' );
								break;
							case 'missing_email':
								esc_html_e( 'Contact email is required.', 'hotel-chain' );
								break;
							case 'user_creation_failed':
								esc_html_e( 'Failed to create hotel user. Username might already exist or email is invalid.', 'hotel-chain' );
								break;
							default:
								esc_html_e( 'An error occurred. Please try again.', 'hotel-chain' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $created_id ) : ?>
			<div class="bg-white rounded p-4 mb-4 border border-solid border-gray-400">
				<div class="flex justify-between items-center mb-3 border-b border-slate-200 pb-3">
					<h2 class="text-xl font-semibold"><?php esc_html_e( 'Create New Hotel Account', 'hotel-chain' ); ?></h2>
				</div>
				<form id="hotel-create-form" class="bg-white rounded p-6 border border-solid border-gray-400" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'hotel_chain_create' ); ?>
					<input type="hidden" name="action" value="hotel_chain_create_hotel" />
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Hotel Name', 'hotel-chain' ); ?></span>
							<input type="text" name="hotel_name" placeholder="<?php esc_attr_e( 'e.g., Grand Plaza Hotel', 'hotel-chain' ); ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Address', 'hotel-chain' ); ?></span>
							<input type="text" name="address" placeholder="<?php esc_attr_e( '123 Main Street', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Hotel Code', 'hotel-chain' ); ?></span>
							<input type="text" name="hotel_code" placeholder="<?php esc_attr_e( 'GPLZ-2025 (auto-generated)', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'City', 'hotel-chain' ); ?></span>
							<input type="text" name="city" placeholder="<?php esc_attr_e( 'New York', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Contact Email', 'hotel-chain' ); ?> <span class="text-red-600">*</span></span>
							<input type="email" name="contact_email" placeholder="<?php esc_attr_e( 'admin@grandplaza.com', 'hotel-chain' ); ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Country', 'hotel-chain' ); ?></span>
							<input type="text" name="country" placeholder="<?php esc_attr_e( 'United States', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Contact Phone', 'hotel-chain' ); ?></span>
							<input type="text" name="contact_phone" placeholder="<?php esc_attr_e( '+1 (555) 123-4567', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Default Guest Access Duration', 'hotel-chain' ); ?></span>
							<input type="text" name="access_duration" placeholder="<?php esc_attr_e( '30 days', 'hotel-chain' ); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
					</div>

					<hr class="my-4" />

					<h3 class="text-lg font-semibold mb-3"><?php esc_html_e( 'Admin User Credentials', 'hotel-chain' ); ?></h3>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Admin Username', 'hotel-chain' ); ?> <span class="text-red-600">*</span></span>
							<input type="text" name="admin_username" placeholder="<?php esc_attr_e( 'hotel.admin', 'hotel-chain' ); ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand-500" />
						</label>
						<label class="block">
							<span class="block mb-1.5 font-semibold text-sm"><?php esc_html_e( 'Temporary Password', 'hotel-chain' ); ?></span>
							<input type="text" value="<?php esc_attr_e( 'Auto-generated (sent via email)', 'hotel-chain' ); ?>" disabled class="w-full px-3 py-2 border border-slate-300 rounded-md bg-slate-50 text-slate-500" />
						</label>
					</div>
					<div class="mt-6 flex gap-3">
						<button type="submit" class="flex-1 px-6 py-3 bg-green-200 border-2 border-green-400 rounded text-green-900"><?php esc_html_e( 'Create Hotel Account', 'hotel-chain' ); ?></button>
						<button type="reset" class="px-6 py-3 bg-gray-200 border-2 border-gray-400 rounded text-gray-900"><?php esc_html_e( 'Cancel', 'hotel-chain' ); ?></button>
					</div>
				</form>
			</div>
			<?php endif; ?>

			<div class="bg-white rounded p-4 border border-solid border-gray-400">
				<div class="mb-4 pb-3 border-b border-gray-300 flex items-center justify-between">
					<h3 class="text-lg font-semibold">
						<?php
						printf(
							/* translators: %d: number of hotels */
							esc_html__( 'All Hotel Accounts (%d)', 'hotel-chain' ),
							count( $hotels )
						);
						?>
					</h3>
					<div class="flex gap-3">
						<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="flex items-center gap-2 border border-solid border-gray-400 rounded px-4 py-2 bg-white" id="search-form">
							<input type="hidden" name="page" value="hotel-chain-accounts" />
							<button class="bg-transparent border-none p-0 cursor-pointer" id="hotel-search-button">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search w-5 h-5 text-gray-400" aria-hidden="true">
								<path d="m21 21-4.34-4.34"></path>
								<circle cx="11" cy="11" r="8"></circle>
							</svg>
							</button>
							<input
								type="search"
								name="hotel_search"
								value="<?php echo esc_attr( $search_term ); ?>"
								class="border-none focus:outline-none focus:ring-0 text-sm text-gray-700 bg-transparent flex-1 focus:border-none focus:ring-0"
								placeholder="<?php esc_attr_e( 'Search hotels...', 'hotel-chain' ); ?>"
								id="hotel-search-input"
							/>
						</form>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hotel_chain_export_hotels' ), 'hotel_chain_export_hotels' ) ); ?>" class="px-4 py-2 bg-blue-200 border border-blue-400 rounded hover:bg-blue-300 text-blue-900 flex items-center justify-center">
							<?php esc_html_e( 'Export CSV', 'hotel-chain' ); ?>
						</a>
					</div>
				</div>

				<?php if ( $search_term ) : ?>
					<div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
						<strong><?php esc_html_e( 'Search Results:', 'hotel-chain' ); ?></strong>
						<span class="text-blue-700">"<?php echo esc_html( $search_term ); ?>"</span>
						<span class="text-gray-600">(<?php echo count( $hotels ); ?> <?php esc_html_e( 'hotels found', 'hotel-chain' ); ?>)</span>
					</div>
				<?php endif; ?>

				<?php if ( empty( $hotels ) ) : ?>
					<p class="text-slate-600">
						<?php if ( $search_term ) : ?>
							<?php esc_html_e( 'No hotels found matching your search.', 'hotel-chain' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'No hotels registered yet. Create your first hotel above.', 'hotel-chain' ); ?>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<div class="border border-solid border-gray-400 rounded overflow-x-auto">
						<div class="hidden md:block min-w-full">
							<div class="bg-gray-200 border-b-2 border-gray-300 grid grid-cols-12 gap-4 p-3">
								<div class="col-span-3"><?php esc_html_e( 'Hotel Name', 'hotel-chain' ); ?></div>
								<div class="col-span-2"><?php esc_html_e( 'Code', 'hotel-chain' ); ?></div>
								<div class="col-span-2"><?php esc_html_e( 'License Period', 'hotel-chain' ); ?></div>
								<div class="col-span-2"><?php esc_html_e( 'Days to Renewal', 'hotel-chain' ); ?></div>
								<div class="col-span-2"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></div>
								<div class="col-span-1"><?php esc_html_e( 'Actions', 'hotel-chain' ); ?></div>
							</div>
							<?php foreach ( $hotels as $hotel ) : ?>
								<?php
								$code            = get_user_meta( $hotel->ID, 'hotel_code', true );
								$name            = get_user_meta( $hotel->ID, 'hotel_name', true );
								$reg             = get_user_meta( $hotel->ID, 'hotel_registration', true );
								$land            = get_user_meta( $hotel->ID, 'hotel_landing', true );
								$access_duration = (int) get_user_meta( $hotel->ID, 'access_duration', true );

								$start_timestamp = strtotime( $hotel->user_registered );
								if ( ! $start_timestamp ) {
									$start_timestamp = current_time( 'timestamp' );
								}

								if ( $access_duration > 0 ) {
									$end_timestamp   = $start_timestamp + ( $access_duration * DAY_IN_SECONDS );
									$now_timestamp   = current_time( 'timestamp' );
									$days_diff       = (int) ceil( ( $end_timestamp - $now_timestamp ) / DAY_IN_SECONDS );
									$license_period  = date_i18n( 'M j, Y', $start_timestamp ) . "\n" . esc_html__( 'to', 'hotel-chain' ) . ' ' . date_i18n( 'M j, Y', $end_timestamp );
									$days_to_renewal = $days_diff >= 0 ? sprintf( /* translators: %s: days */ esc_html__( '%s days', 'hotel-chain' ), $days_diff ) : esc_html__( 'Expired', 'hotel-chain' );
								} else {
									$license_period  = esc_html__( 'Not set', 'hotel-chain' );
									$days_to_renewal = esc_html__( 'Not set', 'hotel-chain' );
								}
								$detail_url = add_query_arg(
									array(
										'page'     => 'hotel-details',
										'hotel_id' => $hotel->ID,
									),
									admin_url( 'admin.php' )
								);
								?>
								<div class="grid grid-cols-12 gap-4 p-3 border-b border-gray-300 last:border-b-0" data-hotel-row="1">
									<div class="col-span-3 flex items-center gap-2">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 w-4 h-4 text-gray-400 flex-shrink-0" aria-hidden="true">
											<path d="M10 12h4"></path>
											<path d="M10 8h4"></path>
											<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
											<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
											<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
										</svg>
										<span><?php echo esc_html( $name ? $name : $hotel->display_name ); ?></span>
									</div>
									<div class="col-span-2 flex items-center">
										<span class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs"><?php echo esc_html( $code ); ?></span>
									</div>
									<div class="col-span-2 flex items-center text-sm text-gray-700">
										<div>
											<?php
											if ( 'Not set' === $license_period ) {
												echo esc_html( $license_period );
											} else {
												$parts = explode( "\n", $license_period );
												echo esc_html( $parts[0] );
												echo '<br />';
												echo esc_html( $parts[1] );
											}
											?>
										</div>
									</div>
									<div class="col-span-2 flex items-center">
										<span class="font-medium text-green-700"><?php echo esc_html( $days_to_renewal ); ?></span>
									</div>
									<div class="col-span-2 flex items-center">
										<span class="px-2 py-1 bg-green-200 border border-green-400 rounded text-green-900 text-xs"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></span>
									</div>
									<div class="col-span-1 flex items-center gap-1">
										<a class="px-2 py-1 bg-blue-200 border border-blue-400 rounded text-blue-900 hover:bg-blue-300 text-xs inline-block" href="<?php echo esc_url( $detail_url ); ?>">
											<?php esc_html_e( 'View', 'hotel-chain' ); ?>
										</a>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="md:hidden divide-y-2 divide-gray-300">
							<?php foreach ( $hotels as $hotel ) : ?>
								<?php
								$code            = get_user_meta( $hotel->ID, 'hotel_code', true );
								$name            = get_user_meta( $hotel->ID, 'hotel_name', true );
								$reg             = get_user_meta( $hotel->ID, 'hotel_registration', true );
								$land            = get_user_meta( $hotel->ID, 'hotel_landing', true );
								$access_duration = (int) get_user_meta( $hotel->ID, 'access_duration', true );

								$start_timestamp = strtotime( $hotel->user_registered );
								if ( ! $start_timestamp ) {
									$start_timestamp = current_time( 'timestamp' );
								}

								if ( $access_duration > 0 ) {
									$end_timestamp   = $start_timestamp + ( $access_duration * DAY_IN_SECONDS );
									$now_timestamp   = current_time( 'timestamp' );
									$days_diff       = (int) ceil( ( $end_timestamp - $now_timestamp ) / DAY_IN_SECONDS );
									$license_period  = date_i18n( 'M j, Y', $start_timestamp ) . "\n" . esc_html__( 'to', 'hotel-chain' ) . ' ' . date_i18n( 'M j, Y', $end_timestamp );
									$days_to_renewal = $days_diff >= 0 ? sprintf( /* translators: %s: days */ esc_html__( '%s days', 'hotel-chain' ), $days_diff ) : esc_html__( 'Expired', 'hotel-chain' );
								} else {
									$license_period  = esc_html__( 'Not set', 'hotel-chain' );
									$days_to_renewal = esc_html__( 'Not set', 'hotel-chain' );
								}
								$detail_url = add_query_arg(
									array(
										'page'     => 'hotel-details',
										'hotel_id' => $hotel->ID,
									),
									admin_url( 'admin.php' )
								);
								?>
								<div class="p-4 space-y-3" data-hotel-card="1">
									<div class="flex items-start justify-between">
										<div class="flex items-center gap-2">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 w-5 h-5 text-gray-400" aria-hidden="true">
												<path d="M10 12h4"></path>
												<path d="M10 8h4"></path>
												<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
												<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
												<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
											</svg>
											<div>
												<div class="mb-1"><?php echo esc_html( $name ? $name : $hotel->display_name ); ?></div>
												<span class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs"><?php echo esc_html( $code ); ?></span>
											</div>
										</div>
										<span class="px-2 py-1 bg-green-200 border border-green-400 rounded text-green-900 text-xs"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></span>
									</div>
									<div class="grid grid-cols-2 gap-3 text-sm">
										<div>
											<div class="text-gray-600 mb-1"><?php esc_html_e( 'License Period', 'hotel-chain' ); ?></div>
											<div class="text-gray-700">
												<?php
												if ( 'Not set' === $license_period ) {
													echo esc_html( $license_period );
												} else {
													$parts = explode( "\n", $license_period );
													echo esc_html( $parts[0] );
													echo '<br />';
													echo esc_html( $parts[1] );
												}
												?>
											</div>
										</div>
										<div>
											<div class="text-gray-600 mb-1"><?php esc_html_e( 'Days to Renewal', 'hotel-chain' ); ?></div>
											<div class="font-medium text-green-700"><?php echo esc_html( $days_to_renewal ); ?></div>
										</div>
									</div>
									<button class="w-full px-3 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900 hover:bg-blue-300" onclick="window.location.href='<?php echo esc_url( $detail_url ); ?>'; return false;"><?php esc_html_e( 'View Details', 'hotel-chain' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

