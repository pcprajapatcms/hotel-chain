<?php
/**
 * Custom login pages service.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Repositories\HotelRepository;

/**
 * Custom login pages handler.
 */
class CustomLogin implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_hotel_login_page' ) );
		add_action( 'login_init', array( $this, 'handle_custom_login' ), 1 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_styles' ) );
		add_filter( 'login_headerurl', array( $this, 'custom_login_logo_url' ) );
		add_filter( 'login_headertext', array( $this, 'custom_login_logo_text' ) );
		add_filter( 'authenticate', array( $this, 'validate_admin_login' ), 30, 3 );
		add_filter( 'authenticate', array( $this, 'validate_hotel_login' ), 30, 3 );
		add_filter( 'authenticate', array( $this, 'validate_guest_login' ), 30, 3 );
		add_filter( 'wp_login_errors', array( $this, 'handle_login_errors' ), 10, 2 );
		add_filter( 'logout_redirect', array( $this, 'handle_logout_redirect' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'handle_guest_login_page' ) );
	}

	/**
	 * Add rewrite rules for hotel and guest login.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^hotel-login/?$',
			'index.php?hotel_login_page=1',
			'top'
		);
		add_rewrite_rule(
			'^guest-login/?$',
			'index.php?guest_login_page=1',
			'top'
		);
	}

	/**
	 * Add query vars for hotel and guest login.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'hotel_login_page';
		$vars[] = 'guest_login_page';
		return $vars;
	}

	/**
	 * Handle hotel login page rendering.
	 *
	 * @return void
	 */
	public function handle_hotel_login_page(): void {
		if ( ! get_query_var( 'hotel_login_page' ) ) {
			return;
		}

		// Check if user is already logged in.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'hotel', $user->roles, true ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=hotel-dashboard' ) );
				exit;
			}
		}

		// Handle form submission.
		if ( isset( $_POST['log'] ) && isset( $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->process_hotel_login();
		}

		$this->render_hotel_login();
		exit;
	}

	/**
	 * Process hotel login form submission.
	 *
	 * @return void
	 */
	private function process_hotel_login(): void {
		$username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$remember = isset( $_POST['rememberme'] ) ? true : false;

		if ( empty( $username ) || empty( $password ) ) {
			return;
		}

		// Attempt login.
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'login', 'failed', home_url( '/hotel-login' ) ) );
			exit;
		}

		// Check if user is hotel role.
		if ( ! in_array( 'hotel', $user->roles, true ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'hotel_only', home_url( '/hotel-login' ) ) );
			exit;
		}

		// Set auth cookie and redirect.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( admin_url( 'admin.php?page=hotel-dashboard' ) );
		exit;
	}

	/**
	 * Validate hotel login - ensure only hotel users can login via hotel login.
	 *
	 * @param \WP_User|\WP_Error|null $user     User object or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error
	 */
	public function validate_hotel_login( $user, string $username, string $password ) {
		// Skip validation if this is a guest login.
		$referrer = wp_get_referer();
		if ( $referrer && strpos( $referrer, '/guest-login' ) !== false ) {
			return $user;
		}

		// Check if POST has guest_login field.
		if ( isset( $_POST['guest_login'] ) && '1' === $_POST['guest_login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $user;
		}

		// Only validate if we're on the hotel login page (check referrer or POST data).
		$is_hotel_login = false;
		
		if ( $referrer && strpos( $referrer, '/hotel-login' ) !== false ) {
			$is_hotel_login = true;
		}
		
		// Check if POST has hotel_login field.
		if ( isset( $_POST['hotel_login'] ) && '1' === $_POST['hotel_login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$is_hotel_login = true;
		}
		
		// Also check if POST is coming from hotel-login page via redirect_to.
		if ( isset( $_POST['redirect_to'] ) && strpos( wp_unslash( $_POST['redirect_to'] ), 'hotel-dashboard' ) !== false ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$is_hotel_login = true;
		}

		if ( ! $is_hotel_login ) {
			return $user;
		}

		// If authentication failed, return the error.
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// If user is authenticated, check if they're a hotel user.
		if ( $user instanceof \WP_User ) {
			if ( ! in_array( 'hotel', $user->roles, true ) ) {
				return new \WP_Error(
					'hotel_only',
					__( 'This login page is restricted to hotel partners only.', 'hotel-chain' )
				);
			}
		}

		return $user;
	}

	/**
	 * Render hotel login page.
	 *
	 * @return void
	 */
	private function render_hotel_login(): void {
		// Get login errors from URL parameters.
		$errors = new \WP_Error();
		if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors->add( 'login_failed', __( 'Invalid username or password.', 'hotel-chain' ) );
		}
		if ( isset( $_GET['error'] ) && 'hotel_only' === $_GET['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors->add( 'hotel_only', __( 'This login page is restricted to hotel partners only.', 'hotel-chain' ) );
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'hotel-chain-main',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			filemtime( get_template_directory() . '/assets/css/main.css' )
		);

		$login_url = home_url( '/hotel-login' );
		$redirect_to = admin_url( 'admin.php?page=hotel-dashboard' );

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Hotel Login', 'hotel-chain' ); ?> - <?php bloginfo( 'name' ); ?></title>
			<?php wp_head(); ?>
			<style>
				:root {
					--font-serif: 'Georgia', 'Times New Roman', serif;
					--font-script: 'Brush Script MT', cursive;
					--font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
				}
				body {
					background-color: rgb(240, 231, 215);
					margin: 0;
					padding: 0;
				}
				#user_login:focus,
				#user_pass:focus {
					outline: none;
					border-color: rgb(61, 61, 68);
					box-shadow: 0 0 0 2px rgba(61, 61, 68, 0.2);
				}
			</style>
		</head>
		<body>
			<div class="flex-1 overflow-auto p-4 lg:p-8" style="background-color: rgb(240, 231, 215);">
				<div class="max-w-7xl mx-auto">
					<div class="min-h-screen flex">
						<!-- Left Side - Image -->
						<div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
							<img src="https://images.unsplash.com/photo-1761501989065-7c98a5d1f773?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxsdXh1cnklMjBob3RlbCUyMGxvYmJ5JTIwZWxlZ2FudHxlbnwxfHx8fDE3NjM1ODM5Nzl8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral" alt="Luxury hotel interior" class="w-full h-full object-cover" style="filter: saturate(0.5) contrast(1.1);">
							<div class="absolute inset-0 bg-gradient-to-br from-black/50 via-black/30 to-black/50"></div>
							<div class="absolute inset-0 flex flex-col items-center justify-center p-12 text-center">
								<div class="max-w-lg">
									<h1 class="mb-6" style="font-family: var(--font-serif); color: rgb(240, 231, 215); font-size: clamp(2.5rem, 4vw, 3.5rem); letter-spacing: 0.05em; line-height: 1.2; text-transform: uppercase;">PEACEFUL</h1>
									<p class="mb-8" style="font-family: var(--font-script); color: rgb(240, 231, 215); font-size: clamp(2rem, 3vw, 3rem); line-height: 1.3;">Hospitality</p>
									<p class="mb-4" style="font-family: var(--font-sans); color: rgb(240, 231, 215); font-size: 1.25rem; line-height: 1.8; font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase;">Hotel Partner Portal</p>
									<p style="font-family: var(--font-sans); color: rgb(240, 231, 215); font-size: 1.125rem; line-height: 1.8; font-weight: 300;">Manage your meditation library, approve guest requests, and track the transformative impact on your guests.</p>
								</div>
							</div>
						</div>

						<!-- Right Side - Login Form -->
						<div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12" style="background-color: rgb(240, 231, 215);">
							<div class="w-full max-w-md">
								<!-- Mobile Header -->
								<div class="lg:hidden text-center mb-8">
									<h1 class="mb-2" style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 2.5rem; letter-spacing: 0.05em; text-transform: uppercase;">PEACEFUL</h1>
									<p style="font-family: var(--font-script); color: rgb(61, 61, 68); font-size: 2rem;">Hospitality</p>
								</div>

								<!-- Form Header -->
								<div class="text-center mb-8">
									<div class="flex items-center justify-center gap-3 mb-4">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 w-8 h-8" aria-hidden="true" style="color: rgb(61, 61, 68);">
											<path d="M10 12h4"></path>
											<path d="M10 8h4"></path>
											<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
											<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
											<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
										</svg>
										<h2 style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 2rem; letter-spacing: 0.02em;">HOTEL LOGIN</h2>
									</div>
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 1rem;">Access your meditation dashboard</p>
								</div>

								<!-- Error Messages -->
								<?php if ( $errors->has_errors() ) : ?>
									<div class="mb-6 p-4 rounded" style="background-color: rgb(254, 242, 242); border: 2px solid rgb(248, 113, 113);">
										<?php
										foreach ( $errors->get_error_messages() as $message ) {
											echo '<p style="font-family: var(--font-sans); color: rgb(220, 38, 38); font-size: 0.875rem;">' . esc_html( $message ) . '</p>';
										}
										?>
									</div>
								<?php endif; ?>

								<!-- Login Form -->
								<form name="loginform" id="loginform" action="<?php echo esc_url( $login_url ); ?>" method="post" class="space-y-6">
									<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
									<input type="hidden" name="hotel_login" value="1" />

									<div>
										<label for="user_login" class="block mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Hotel Account Email</label>
										<div class="relative">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail absolute left-4 top-1/2 transform -translate-y-1/2" aria-hidden="true" style="color: rgb(122, 122, 122);">
												<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
												<rect x="2" y="4" width="20" height="16" rx="2"></rect>
											</svg>
											<input id="user_login" name="log" type="text" placeholder="hotel@yourproperty.com" value="<?php echo isset( $_POST['log'] ) ? esc_attr( wp_unslash( $_POST['log'] ) ) : ''; ?>" class="w-full pl-12 pr-4 py-3 rounded transition-all" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); color: rgb(61, 61, 68); font-family: var(--font-sans); font-size: 1rem;" required autofocus autocomplete="username">
										</div>
									</div>

									<div>
										<label for="user_pass" class="block mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Password</label>
										<div class="relative">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock absolute left-4 top-1/2 transform -translate-y-1/2" aria-hidden="true" style="color: rgb(122, 122, 122);">
												<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
												<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
											</svg>
											<input id="user_pass" name="pwd" type="password" placeholder="Enter your password" class="w-full pl-12 pr-4 py-3 rounded transition-all" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); color: rgb(61, 61, 68); font-family: var(--font-sans); font-size: 1rem;" required autocomplete="current-password">
										</div>
									</div>

									<div class="flex items-center justify-between">
										<label class="flex items-center gap-2 cursor-pointer">
											<input type="checkbox" name="rememberme" id="rememberme" value="forever" class="w-4 h-4 rounded" style="accent-color: rgb(61, 61, 68);">
											<span style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem;">Remember me</span>
										</label>
										<a href="<?php echo esc_url( wp_lostpassword_url( $redirect_to ) ); ?>" class="hover:opacity-70 transition-opacity" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; text-decoration: underline; font-weight: 500;">Forgot password?</a>
									</div>

									<button type="submit" name="wp-submit" id="wp-submit" class="w-full py-4 rounded shadow-lg hover:shadow-xl transition-all" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; cursor: pointer;">Access Hotel Dashboard</button>
								</form>

								<div class="my-8 flex items-center gap-4">
									<div class="flex-1 h-px" style="background-color: rgb(196, 196, 196);"></div>
									<span style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem;">OR</span>
									<div class="flex-1 h-px" style="background-color: rgb(196, 196, 196);"></div>
								</div>

								<div class="text-center">
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem; margin-bottom: 0.5rem;">Interested in bringing Peaceful Hospitality to your property?</p>
									<button type="button" class="hover:opacity-70 transition-opacity" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 1rem; font-weight: 600;">Contact Us to Partner</button>
								</div>

								<div class="mt-12 text-center">
									<p style="font-family: var(--font-script); color: rgb(122, 122, 122); font-size: 1.25rem;">Transform your guest experience</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Handle guest login page rendering.
	 *
	 * @return void
	 */
	public function handle_guest_login_page(): void {
		if ( ! get_query_var( 'guest_login_page' ) ) {
			return;
		}

		// Check if user is already logged in.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'guest', $user->roles, true ) ) {
				// Get guest record to find associated hotel.
				$guest_repo = new GuestRepository();
				$guest = $guest_repo->get_by_user_id( $user->ID );

				$redirect_to = home_url();

				// If guest is registered with a hotel, redirect to that hotel's page.
				if ( $guest && ! empty( $guest->hotel_id ) ) {
					$hotel_repo = new HotelRepository();
					$hotel = $hotel_repo->get_by_id( (int) $guest->hotel_id );

					if ( $hotel && ! empty( $hotel->hotel_slug ) ) {
						$redirect_to = home_url( '/hotel/' . $hotel->hotel_slug . '/' );
					}
				}

				wp_safe_redirect( $redirect_to );
				exit;
			}
		}

		// Handle form submission.
		if ( isset( $_POST['log'] ) && isset( $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->process_guest_login();
		}

		$this->render_guest_login();
		exit;
	}

	/**
	 * Process guest login form submission.
	 *
	 * @return void
	 */
	private function process_guest_login(): void {
		$username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$remember = isset( $_POST['rememberme'] ) ? true : false;

		if ( empty( $username ) || empty( $password ) ) {
			return;
		}

		// Attempt login.
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'login', 'failed', home_url( '/guest-login' ) ) );
			exit;
		}

		// Check if user is guest role.
		if ( ! in_array( 'guest', $user->roles, true ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'guest_only', home_url( '/guest-login' ) ) );
			exit;
		}

		// Set auth cookie and redirect.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember );
		do_action( 'wp_login', $user->user_login, $user );

		// Get guest record to find associated hotel.
		$guest_repo = new GuestRepository();
		$guest = $guest_repo->get_by_user_id( $user->ID );

		$redirect_to = home_url();

		// If guest is registered with a hotel, redirect to that hotel's page.
		if ( $guest && ! empty( $guest->hotel_id ) ) {
			$hotel_repo = new HotelRepository();
			$hotel = $hotel_repo->get_by_id( (int) $guest->hotel_id );

			if ( $hotel && ! empty( $hotel->hotel_slug ) ) {
				$redirect_to = home_url( '/hotel/' . $hotel->hotel_slug . '/' );
			}
		}

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Validate guest login - ensure only guest users can login via guest login.
	 *
	 * @param \WP_User|\WP_Error|null $user     User object or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error
	 */
	public function validate_guest_login( $user, string $username, string $password ) {
		// Only validate if we're on the guest login page (check referrer or POST data).
		$referrer = wp_get_referer();
		$is_guest_login = false;
		
		if ( $referrer && strpos( $referrer, '/guest-login' ) !== false ) {
			$is_guest_login = true;
		}
		
		// Check if POST has guest_login field.
		if ( isset( $_POST['guest_login'] ) && '1' === $_POST['guest_login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$is_guest_login = true;
		}

		if ( ! $is_guest_login ) {
			return $user;
		}

		// If authentication failed, return the error.
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// If user is authenticated, check if they're a guest user.
		if ( $user instanceof \WP_User ) {
			if ( ! in_array( 'guest', $user->roles, true ) ) {
				return new \WP_Error(
					'guest_only',
					__( 'This login page is restricted to guests only.', 'hotel-chain' )
				);
			}
		}

		return $user;
	}

	/**
	 * Render guest login page.
	 *
	 * @return void
	 */
	private function render_guest_login(): void {
		// Get login errors from URL parameters.
		$errors = new \WP_Error();
		if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors->add( 'login_failed', __( 'Invalid username or password.', 'hotel-chain' ) );
		}
		if ( isset( $_GET['error'] ) && 'guest_only' === $_GET['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors->add( 'guest_only', __( 'This login page is restricted to guests only.', 'hotel-chain' ) );
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'hotel-chain-main',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			filemtime( get_template_directory() . '/assets/css/main.css' )
		);

		$login_url = home_url( '/guest-login' );
		$redirect_to = home_url();

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Guest Login', 'hotel-chain' ); ?> - <?php bloginfo( 'name' ); ?></title>
			<?php wp_head(); ?>
			<style>
				:root {
					--font-serif: 'Georgia', 'Times New Roman', serif;
					--font-script: 'Brush Script MT', cursive;
					--font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
				}
				body {
					background-color: rgb(240, 231, 215);
					margin: 0;
					padding: 0;
				}
				#user_login:focus,
				#user_pass:focus {
					outline: none;
					border-color: rgb(61, 61, 68);
					box-shadow: 0 0 0 2px rgba(61, 61, 68, 0.2);
				}
			</style>
		</head>
		<body>
			<div class="flex-1 overflow-auto p-4 lg:p-8" style="background-color: rgb(240, 231, 215);">
				<div class="max-w-7xl mx-auto">
					<div class="min-h-screen flex">
						<!-- Left Side - Image -->
						<div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
							<img src="https://images.unsplash.com/photo-1758274539654-23fa349cc090?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxtZWRpdGF0aW9uJTIwcGVhY2VmdWwlMjB3b21hbiUyMGNvbnRlbXBsYXRpdmV8ZW58MXx8fHwxNzYzNjc2MTU5fDA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral" alt="Peaceful meditation" class="w-full h-full object-cover" style="filter: saturate(0.5) contrast(1.1);">
							<div class="absolute inset-0 bg-gradient-to-br from-black/50 via-black/30 to-black/50"></div>
							<div class="absolute inset-0 flex flex-col items-center justify-center p-12 text-center">
								<div class="max-w-lg">
									<h1 class="mb-6" style="font-family: var(--font-serif); color: rgb(240, 231, 215); font-size: clamp(2.5rem, 4vw, 3.5rem); letter-spacing: 0.05em; line-height: 1.2; text-transform: uppercase;">WELCOME BACK</h1>
									<p class="mb-8" style="font-family: var(--font-script); color: rgb(240, 231, 215); font-size: clamp(2rem, 3vw, 3rem); line-height: 1.3;">Your peace awaits</p>
									<p style="font-family: var(--font-sans); color: rgb(240, 231, 215); font-size: 1.125rem; line-height: 1.8; font-weight: 300;">Return to your sanctuary. Continue your journey of transformation with guided meditations designed to help you find your calmest, clearest self.</p>
								</div>
							</div>
						</div>

						<!-- Right Side - Login Form -->
						<div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12" style="background-color: rgb(240, 231, 215);">
							<div class="w-full max-w-md">
								<!-- Mobile Header -->
								<div class="lg:hidden text-center mb-8">
									<h1 class="mb-2" style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 2.5rem; letter-spacing: 0.05em; text-transform: uppercase;">PEACEFUL</h1>
									<p style="font-family: var(--font-script); color: rgb(61, 61, 68); font-size: 2rem;">Hospitality</p>
								</div>

								<!-- Form Header -->
								<div class="text-center mb-8">
									<h2 class="mb-2" style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 2rem; letter-spacing: 0.02em;">GUEST LOGIN</h2>
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 1rem;">Access your meditation library</p>
								</div>

								<!-- Error Messages -->
								<?php if ( $errors->has_errors() ) : ?>
									<div class="mb-6 p-4 rounded" style="background-color: rgb(254, 242, 242); border: 2px solid rgb(248, 113, 113);">
										<?php
										foreach ( $errors->get_error_messages() as $message ) {
											echo '<p style="font-family: var(--font-sans); color: rgb(220, 38, 38); font-size: 0.875rem;">' . esc_html( $message ) . '</p>';
										}
										?>
									</div>
								<?php endif; ?>

								<!-- Login Form -->
								<form name="loginform" id="loginform" action="<?php echo esc_url( $login_url ); ?>" method="post" class="space-y-6">
									<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
									<input type="hidden" name="guest_login" value="1" />

									<div>
										<label for="user_login" class="block mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Email Address</label>
										<div class="relative">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail absolute left-4 top-1/2 transform -translate-y-1/2" aria-hidden="true" style="color: rgb(122, 122, 122);">
												<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
												<rect x="2" y="4" width="20" height="16" rx="2"></rect>
											</svg>
											<input id="user_login" name="log" type="text" placeholder="Enter your email" value="<?php echo isset( $_POST['log'] ) ? esc_attr( wp_unslash( $_POST['log'] ) ) : ''; ?>" class="w-full pl-12 pr-4 py-3 rounded transition-all" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); color: rgb(61, 61, 68); font-family: var(--font-sans); font-size: 1rem;" required autofocus autocomplete="username">
										</div>
									</div>

									<div>
										<label for="user_pass" class="block mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Password</label>
										<div class="relative">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock absolute left-4 top-1/2 transform -translate-y-1/2" aria-hidden="true" style="color: rgb(122, 122, 122);">
												<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
												<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
											</svg>
											<input id="user_pass" name="pwd" type="password" placeholder="Enter your password" class="w-full pl-12 pr-4 py-3 rounded transition-all" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); color: rgb(61, 61, 68); font-family: var(--font-sans); font-size: 1rem;" required autocomplete="current-password">
										</div>
									</div>

									<div class="flex items-center justify-between">
										<label class="flex items-center gap-2 cursor-pointer">
											<input type="checkbox" name="rememberme" id="rememberme" value="forever" class="w-4 h-4 rounded" style="accent-color: rgb(61, 61, 68);">
											<span style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem;">Remember me</span>
										</label>
										<a href="<?php echo esc_url( wp_lostpassword_url( $redirect_to ) ); ?>" class="hover:opacity-70 transition-opacity" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; text-decoration: underline; font-weight: 500;">Forgot password?</a>
									</div>

									<button type="submit" name="wp-submit" id="wp-submit" class="w-full py-4 rounded shadow-lg hover:shadow-xl transition-all" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; cursor: pointer;">Continue Your Journey</button>
								</form>

								<div class="my-8 flex items-center gap-4">
									<div class="flex-1 h-px" style="background-color: rgb(196, 196, 196);"></div>
									<span style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem;">OR</span>
									<div class="flex-1 h-px" style="background-color: rgb(196, 196, 196);"></div>
								</div>

								<div class="text-center">
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem; margin-bottom: 0.5rem;">First time here?</p>
									<a href="<?php echo esc_url( home_url( '/register' ) ); ?>" class="hover:opacity-70 transition-opacity inline-block" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 1rem; font-weight: 600; text-decoration: none;">Create Your Account</a>
									<p class="mt-2" style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.75rem; font-style: italic;">You'll need a unique registration link from your hotel</p>
								</div>

								<div class="mt-12 p-6 rounded text-center" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196);">
									<p class="mb-2" style="font-family: var(--font-script); color: rgb(61, 61, 68); font-size: 1.5rem; line-height: 1.4;">Drop your baggage</p>
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem;">Find your center. Carry peace home with you.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Handle custom login page rendering.
	 *
	 * @return void
	 */
	public function handle_custom_login(): void {
		// Check for actions that WordPress should handle normally.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed_actions = array( 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'confirmaction' );
		
		if ( in_array( $action, $allowed_actions, true ) ) {
			// Let WordPress handle these actions normally.
			return;
		}

		// Check if user is already logged in (but not for special actions).
		if ( is_user_logged_in() && ! in_array( $action, $allowed_actions, true ) ) {
			$user = wp_get_current_user();
			$redirect_to = admin_url();

			// Redirect based on user role.
			if ( in_array( 'administrator', $user->roles, true ) ) {
				$redirect_to = admin_url();
			} elseif ( in_array( 'hotel', $user->roles, true ) ) {
				$redirect_to = admin_url( 'admin.php?page=hotel-dashboard' );
			} elseif ( in_array( 'guest', $user->roles, true ) ) {
				// Redirect to hotel page if guest is associated with a hotel.
				$redirect_to = home_url();
			}

			wp_safe_redirect( $redirect_to );
			exit;
		}

		// Determine login type from URL or referrer.
		$login_type = $this->get_login_type();

		// Only intercept for admin login type or default wp-login.php.
		if ( 'admin' === $login_type || empty( $login_type ) ) {
			// If this is a form submission, let WordPress handle it normally.
			// We'll validate via authenticate filter.
			if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->render_admin_login();
				exit;
			}
		}
	}

	/**
	 * Determine login type from URL or context.
	 *
	 * @return string Login type: admin, hotel, or guest.
	 */
	private function get_login_type(): string {
		// Check URL parameter.
		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( in_array( $type, array( 'admin', 'hotel', 'guest' ), true ) ) {
			return $type;
		}

		// Check referrer to determine context.
		$referrer = wp_get_referer();
		if ( $referrer ) {
			if ( strpos( $referrer, '/hotel/' ) !== false ) {
				return 'guest';
			}
			if ( strpos( $referrer, admin_url() ) !== false ) {
				return 'admin';
			}
		}

		// Default to admin for wp-login.php.
		return 'admin';
	}

	/**
	 * Validate admin login - ensure only administrators can login via admin login.
	 *
	 * @param \WP_User|\WP_Error|null $user     User object or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error
	 */
	public function validate_admin_login( $user, string $username, string $password ) {
		// Skip validation if this is a hotel or guest login - check multiple indicators.
		$referrer = wp_get_referer();
		if ( $referrer && ( strpos( $referrer, '/hotel-login' ) !== false || strpos( $referrer, '/guest-login' ) !== false ) ) {
			return $user;
		}

		// Check if POST has hotel_login or guest_login field.
		if ( isset( $_POST['hotel_login'] ) && '1' === $_POST['hotel_login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $user;
		}
		if ( isset( $_POST['guest_login'] ) && '1' === $_POST['guest_login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $user;
		}

		// Also check if POST is coming from hotel-login page via redirect_to.
		if ( isset( $_POST['redirect_to'] ) && strpos( wp_unslash( $_POST['redirect_to'] ), 'hotel-dashboard' ) !== false ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $user;
		}

		// Only validate if we're on the admin login page (default wp-login.php or type=admin).
		$login_type = $this->get_login_type();
		if ( 'admin' !== $login_type && ! empty( $login_type ) ) {
			return $user;
		}

		// If authentication failed, return the error.
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// If user is authenticated, check if they're an administrator.
		if ( $user instanceof \WP_User ) {
			if ( ! in_array( 'administrator', $user->roles, true ) ) {
				// Redirect back with error.
				wp_safe_redirect( add_query_arg( 'error', 'admin_only', wp_login_url() ) );
				exit;
			}
		}

		return $user;
	}

	/**
	 * Render admin login page.
	 *
	 * @return void
	 */
	private function render_admin_login(): void {
		// Get login errors from URL parameters.
		$errors = new \WP_Error();
		if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors->add( 'login_failed', __( 'Invalid username or password.', 'hotel-chain' ) );
		}
		if ( isset( $_GET['error'] ) && 'admin_only' === $_GET['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors->add( 'admin_only', __( 'This login page is restricted to administrators only.', 'hotel-chain' ) );
		}

		// Also check for WordPress default error messages.
		$wp_errors = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'incorrect_password' === $wp_errors || 'invalid_username' === $wp_errors || 'invalid_email' === $wp_errors ) {
			$errors->add( 'login_failed', __( 'Invalid username or password.', 'hotel-chain' ) );
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'hotel-chain-main',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			filemtime( get_template_directory() . '/assets/css/main.css' )
		);

		$login_url = site_url( 'wp-login.php', 'login_post' );
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rememberme = isset( $_POST['rememberme'] ) ? true : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Admin Login', 'hotel-chain' ); ?> - <?php bloginfo( 'name' ); ?></title>
			<?php wp_head(); ?>
			<style>
				:root {
					--font-serif: 'Georgia', 'Times New Roman', serif;
					--font-script: 'Brush Script MT', cursive;
					--font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
				}
				body.login {
					background-color: rgb(240, 231, 215);
					margin: 0;
					padding: 0;
				}
				#login {
					padding: 0;
					width: 100%;
				}
				#user_login:focus,
				#user_pass:focus {
					outline: none;
					border-color: rgb(61, 61, 68);
					box-shadow: 0 0 0 2px rgba(61, 61, 68, 0.2);
				}
			</style>
		</head>
		<body class="login">
			<div class="flex-1 overflow-auto p-4 lg:p-8" style="background-color: rgb(240, 231, 215);">
				<div class="max-w-7xl mx-auto">
					<div class="min-h-screen flex">
						<!-- Left Side - Image -->
						<div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
							<img src="https://images.unsplash.com/photo-1641391400773-dcdd2f5ab7a7?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHx6ZW4lMjBwZWFjZWZ1bCUyMHdhdGVyJTIwcmVmbGVjdGlvbnxlbnwxfHx8fDE3NjM2NzU5MjJ8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral" alt="Peaceful water reflection" class="w-full h-full object-cover" style="filter: saturate(0.5) contrast(1.1);">
							<div class="absolute inset-0 bg-gradient-to-br from-black/60 via-black/40 to-black/60"></div>
							<div class="absolute inset-0 flex flex-col items-center justify-center p-12 text-center">
								<div class="max-w-lg">
									<div class="w-16 h-16 rounded-full mx-auto mb-6 flex items-center justify-center" style="background-color: rgb(240, 231, 215);">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield w-8 h-8" aria-hidden="true" style="color: rgb(61, 61, 68);">
											<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
										</svg>
									</div>
									<h1 class="mb-6" style="font-family: var(--font-serif); color: rgb(240, 231, 215); font-size: clamp(2.5rem, 4vw, 3.5rem); letter-spacing: 0.05em; line-height: 1.2; text-transform: uppercase;">ADMIN PORTAL</h1>
									<p class="mb-8" style="font-family: var(--font-script); color: rgb(240, 231, 215); font-size: clamp(2rem, 3vw, 3rem); line-height: 1.3;">System Management</p>
									<p style="font-family: var(--font-sans); color: rgb(240, 231, 215); font-size: 1.125rem; line-height: 1.8; font-weight: 300;">Manage hotels, videos, and analytics. Oversee the platform that brings peace to thousands of guests worldwide.</p>
								</div>
							</div>
						</div>

						<!-- Right Side - Login Form -->
						<div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12" style="background-color: rgb(240, 231, 215);">
							<div class="w-full max-w-md">
								<!-- Mobile Header -->
								<div class="lg:hidden text-center mb-8">
									<div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background-color: rgb(61, 61, 68);">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield w-8 h-8" aria-hidden="true" style="color: rgb(240, 231, 215);">
											<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
										</svg>
									</div>
									<h1 class="mb-2" style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 2rem; letter-spacing: 0.05em; text-transform: uppercase;">ADMIN PORTAL</h1>
								</div>

								<!-- Form Header -->
								<div class="text-center mb-8">
									<h2 class="mb-2" style="font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 2rem; letter-spacing: 0.02em;">ADMINISTRATOR LOGIN</h2>
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 1rem;">Secure access for Peaceful Hospitality team</p>
								</div>

								<!-- Error Messages -->
								<?php if ( $errors->has_errors() ) : ?>
									<div class="mb-6 p-4 rounded" style="background-color: rgb(254, 242, 242); border: 2px solid rgb(248, 113, 113);">
										<?php
										foreach ( $errors->get_error_messages() as $message ) {
											echo '<p style="font-family: var(--font-sans); color: rgb(220, 38, 38); font-size: 0.875rem;">' . esc_html( $message ) . '</p>';
										}
										?>
									</div>
								<?php endif; ?>

								<!-- Login Form -->
								<form name="loginform" id="loginform" action="<?php echo esc_url( $login_url ); ?>" method="post" class="space-y-6">
									<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />

									<div>
										<label for="user_login" class="block mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Username or Email Address</label>
										<div class="relative">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user absolute left-4 top-1/2 transform -translate-y-1/2" aria-hidden="true" style="color: rgb(122, 122, 122);">
												<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
												<circle cx="12" cy="7" r="4"></circle>
											</svg>
											<input id="user_login" name="log" type="text" placeholder="Username or email" value="<?php echo isset( $_POST['log'] ) ? esc_attr( wp_unslash( $_POST['log'] ) ) : ''; ?>" class="w-full pl-12 pr-4 py-3 rounded transition-all" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); color: rgb(61, 61, 68); font-family: var(--font-sans); font-size: 1rem;" required autofocus autocomplete="username">
										</div>
									</div>

									<div>
										<label for="user_pass" class="block mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Password</label>
										<div class="relative">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock absolute left-4 top-1/2 transform -translate-y-1/2" aria-hidden="true" style="color: rgb(122, 122, 122);">
												<rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
												<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
											</svg>
											<input id="user_pass" name="pwd" type="password" placeholder="Enter your secure password" class="w-full pl-12 pr-4 py-3 rounded transition-all" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196); color: rgb(61, 61, 68); font-family: var(--font-sans); font-size: 1rem;" required>
										</div>
									</div>

									<div class="flex items-center justify-between">
										<label class="flex items-center gap-2 cursor-pointer">
											<input type="checkbox" name="rememberme" id="rememberme" value="forever" class="w-4 h-4 rounded" style="accent-color: rgb(61, 61, 68);">
											<span style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem;">Remember me</span>
										</label>
										<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="hover:opacity-70 transition-opacity" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; text-decoration: underline; font-weight: 500;">Reset password</a>
									</div>

									<button type="submit" name="wp-submit" id="wp-submit" class="w-full py-4 rounded shadow-lg hover:shadow-xl transition-all" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; cursor: pointer;">Access Admin Dashboard</button>
								</form>

								<!-- Security Notice -->
								<div class="mt-8 p-4 rounded" style="background-color: rgb(255, 255, 255); border: 2px solid rgb(196, 196, 196);">
									<div class="flex items-start gap-3">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield w-5 h-5 mt-0.5" aria-hidden="true" style="color: rgb(122, 122, 122);">
											<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
										</svg>
										<div>
											<p class="mb-1" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 500;">Secure Admin Access</p>
											<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem; line-height: 1.5;">This portal is restricted to authorized Peaceful Hospitality administrators only. All login attempts are monitored and logged.</p>
										</div>
									</div>
								</div>

								<div class="mt-8 text-center">
									<p style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.75rem;">Need assistance? Contact your system administrator</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}


	/**
	 * Enqueue login page styles.
	 *
	 * @return void
	 */
	public function enqueue_login_styles(): void {
		wp_enqueue_style(
			'hotel-chain-login',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			filemtime( get_template_directory() . '/assets/css/main.css' )
		);
	}

	/**
	 * Custom login logo URL.
	 *
	 * @param string $url Logo URL.
	 * @return string
	 */
	public function custom_login_logo_url( string $url ): string {
		return home_url();
	}

	/**
	 * Custom login logo text.
	 *
	 * @param string $text Logo text.
	 * @return string
	 */
	public function custom_login_logo_text( string $text ): string {
		return get_bloginfo( 'name' );
	}

	/**
	 * Handle login errors to show on custom page.
	 *
	 * @param \WP_Error $errors      Login errors.
	 * @param string    $redirect_to Redirect URL.
	 * @return \WP_Error
	 */
	public function handle_login_errors( \WP_Error $errors, string $redirect_to ): \WP_Error {
		$login_type = $this->get_login_type();
		if ( 'admin' === $login_type || empty( $login_type ) ) {
			// If there are errors, ensure we show our custom page on next load.
			if ( $errors->has_errors() ) {
				// Errors will be shown via URL parameter on next page load.
			}
		}
		return $errors;
	}

	/**
	 * Handle logout redirect - redirect users to appropriate login page.
	 *
	 * @param string  $redirect_to   Redirect URL.
	 * @param string  $requested_redirect_to Requested redirect URL.
	 * @param WP_User $user          User object.
	 * @return string
	 */
	public function handle_logout_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		// Check user from parameter or current user (before logout completes).
		$current_user = $user instanceof \WP_User ? $user : wp_get_current_user();

		// If user has hotel role, redirect to hotel-login page.
		if ( $current_user instanceof \WP_User && in_array( 'hotel', $current_user->roles, true ) ) {
			return home_url( '/hotel-login' );
		}

		// If user has guest role, redirect to guest-login page.
		if ( $current_user instanceof \WP_User && in_array( 'guest', $current_user->roles, true ) ) {
			return home_url( '/guest-login' );
		}

		// For admin users, redirect to admin login page.
		if ( $current_user instanceof \WP_User && in_array( 'administrator', $current_user->roles, true ) ) {
			return wp_login_url();
		}

		// Default: use requested redirect or login page.
		return $requested_redirect_to ? $requested_redirect_to : wp_login_url();
	}
}
