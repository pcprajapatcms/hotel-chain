<?php
/**
 * Guest Registration page.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\GuestRepository;

/**
 * Handle guest registration via hotel registration URL.
 */
class GuestRegistration implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'template_include', array( $this, 'load_template' ) );
		add_action( 'wp_ajax_nopriv_guest_register', array( $this, 'handle_registration' ) );
		add_action( 'wp_ajax_guest_register', array( $this, 'handle_registration' ) );
		add_action( 'wp_ajax_nopriv_guest_resend_verification', array( $this, 'handle_resend_verification' ) );
		add_action( 'wp_ajax_guest_resend_verification', array( $this, 'handle_resend_verification' ) );
	}

	/**
	 * Add rewrite rules for /register and /verify-email.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^register/?$',
			'index.php?hotel_guest_register=1',
			'top'
		);
		add_rewrite_rule(
			'^verify-email/?$',
			'index.php?hotel_guest_verify=1',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'hotel_guest_register';
		$vars[] = 'hotel_guest_verify';
		$vars[] = 'hotel';
		$vars[] = 'token';
		return $vars;
	}

	/**
	 * Load registration template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function load_template( string $template ): string {
		if ( get_query_var( 'hotel_guest_register' ) ) {
			$this->render_page();
			exit;
		}

		if ( get_query_var( 'hotel_guest_verify' ) ) {
			$this->handle_email_verification();
			exit;
		}

		return $template;
	}

	/**
	 * Render the registration page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$hotel_code = isset( $_GET['hotel'] ) ? sanitize_text_field( wp_unslash( $_GET['hotel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$hotel = null;
		$error = '';

		if ( $hotel_code ) {
			$repository = new HotelRepository();
			$hotel      = $repository->get_by_code( $hotel_code );

			if ( ! $hotel ) {
				$error = __( 'Invalid hotel code. Please check your registration link.', 'hotel-chain' );
			} elseif ( 'active' !== $hotel->status ) {
				$error = __( 'This hotel is currently not accepting registrations.', 'hotel-chain' );
				$hotel = null;
			}
		} else {
			$error = __( 'No hotel code provided. Please use the registration link provided by your hotel.', 'hotel-chain' );
		}

		// Check if user is already logged in.
		$is_logged_in = is_user_logged_in();
		$current_user = $is_logged_in ? wp_get_current_user() : null;

		// Enqueue main CSS.
		wp_enqueue_style(
			'hotel-chain-main',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			filemtime( get_template_directory() . '/assets/css/main.css' )
		);
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>
			<?php
			/* translators: %s: Hotel name */
			echo esc_html( $hotel ? sprintf( __( 'Register - %s', 'hotel-chain' ), $hotel->hotel_name ) : __( 'Guest Registration', 'hotel-chain' ) );
			?>
			</title>
			<?php wp_head(); ?>
			<style>
				@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
				@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(6px); } }
				.hero-img { filter: saturate(0.4) contrast(1.2) brightness(0.9); }
			</style>
		</head>
		<body class="bg-gray-100 m-0 p-0">
			<!-- Hero Section -->
			<div class="relative h-screen overflow-hidden">
				<div class="absolute inset-0">
					<img src="https://images.unsplash.com/photo-1608812512299-94b1d6bbc0f7?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkcmFtYXRpYyUyMHplbiUyMG1vbWVudCUyMHN1bnJpc2UlMjBwZWFjZWZ1bHxlbnwxfHx8fDE3NjM2Nzc2ODF8MA&ixlib=rb-4.1.0&q=80&w=1080" alt="Transcendent moment of peace" class="w-full h-full object-cover hero-img">
					<div class="absolute inset-0 bg-gradient-to-b from-black/70 via-transparent to-black/80"></div>
					<div class="absolute inset-0 bg-gradient-to-r from-black/40 via-transparent to-black/40"></div>
				</div>
				<div class="relative h-full flex flex-col items-center justify-center px-8 text-center">
					<div class="max-w-5xl">
						<div class="mb-8">
							<h1 class="font-serif text-[#f0e7d7] text-5xl md:text-7xl tracking-widest leading-tight uppercase mb-6" style="text-shadow: 0px 4px 20px rgba(0,0,0,0.7);">DROP</h1>
							<h2 class="font-serif text-[#f0e7d7] text-4xl md:text-6xl tracking-widest leading-tight uppercase mb-8" style="text-shadow: 0px 4px 20px rgba(0,0,0,0.7);">YOUR BAGGAGE</h2>
						</div>
						<div class="mb-12">
							<p class="text-[#f0e7d7] text-4xl md:text-5xl leading-tight mb-6" style="font-family: var(--font-serif); font-style: italic; text-transform: none; letter-spacing: 0.05em; text-shadow: 0px 2px 10px rgba(0,0,0,0.5);">Breathe deeply</p>
						</div>
						<div class="max-w-3xl mx-auto">
							<p class="text-[#f0e7d7] text-3xl md:text-4xl leading-tight mb-4" style="font-family: var(--font-serif); font-style: italic; text-transform: none; letter-spacing: 0.05em; text-shadow: 0px 2px 10px rgba(0,0,0,0.5);">Find your center</p>
							<div class="h-px w-32 bg-[#f0e7d7]/60 mx-auto mb-8"></div>
							<p class="text-[#f0e7d7] text-lg md:text-xl leading-relaxed font-light tracking-wide">Seven guided meditations await you. A signature experience designed to help you carry peace home with you.</p>
						</div>
					</div>
				</div>
				<div class="absolute bottom-12 left-1/2 -translate-x-1/2" style="animation: pulse 2s infinite;">
					<div class="w-6 h-10 border-2 border-[#f0e7d7] rounded-full flex items-start justify-center p-1">
						<div class="w-1 h-2 bg-[#f0e7d7] rounded-full" style="animation: bounce 1s infinite;"></div>
					</div>
					<p class="mt-3 text-center text-[#f0e7d7] text-sm tracking-widest uppercase">Begin</p>
				</div>
			</div>

			<!-- Registration Form -->
			<div class="max-w-2xl mx-auto">
				<div class="text-center mb-12 mt-12">
					<h2 class="font-serif text-gray-700 text-2xl md:text-3xl tracking-wider uppercase mb-4">CREATE YOUR ACCOUNT</h2>
					<p class="text-gray-700 text-xl md:text-2xl mb-2" style="font-family: var(--font-serif); font-style: italic; text-transform: none; letter-spacing: 0.05em;">Your journey begins today</p>
					<p class="text-gray-500 text-base leading-relaxed">Access granted for one year from registration</p>
				</div>
				<div class="bg-white rounded-lg p-6 border border-solid border-gray-300">

					<?php if ( $error ) : ?>
						<div class="bg-red-50 border-2 border-solid border-red-300 rounded-lg p-4 text-red-800 mb-5">
							<?php echo esc_html( $error ); ?>
						</div>
						<div class="text-center">
							<a href="<?php echo esc_url( home_url() ); ?>" class="text-blue-600 hover:underline"><?php esc_html_e( 'Return to homepage', 'hotel-chain' ); ?></a>
						</div>
					<?php elseif ( $hotel ) : ?>

						<!-- Header -->
						<div class="text-center mb-6">
							<div class="w-24 h-24 bg-gray-200 border border-solid border-gray-300 rounded-lg mx-auto mb-4 flex items-center justify-center">
								<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
									<circle cx="9" cy="9" r="2"></circle>
									<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
								</svg>
							</div>
							<h3 class="text-xl font-semibold mb-2">
							<?php
							/* translators: %s: Hotel name */
							echo esc_html( sprintf( __( 'Welcome to %s', 'hotel-chain' ), $hotel->hotel_name ) );
							?>
							</h3>
							<p class="text-gray-500 m-0"><?php esc_html_e( 'Complete registration to access your meditation series', 'hotel-chain' ); ?></p>
						</div>

						<!-- Hotel Info Box -->
						<div class="bg-blue-50 border-2 border-solid border-blue-300 rounded-lg p-4 mb-6">
							<div class="text-blue-900 font-medium">
							<?php
							/* translators: %s: Hotel name */
							echo esc_html( sprintf( __( "You're registering for: %s", 'hotel-chain' ), $hotel->hotel_name ) );
							?>
							</div>
							<div class="text-blue-700 mt-1">
							<?php
							/* translators: %s: Hotel code */
							echo esc_html( sprintf( __( 'Hotel Code: %s', 'hotel-chain' ), $hotel->hotel_code ) );
							?>
							</div>
						</div>

						<!-- Message container -->
						<div id="register-message"></div>

						<!-- Registration Form -->
						<form id="guest-register-form" method="post">
							<input type="hidden" name="action" value="guest_register" />
							<input type="hidden" name="hotel_code" value="<?php echo esc_attr( $hotel->hotel_code ); ?>" />
							<?php wp_nonce_field( 'guest_register_nonce', 'register_nonce' ); ?>

							<div class="mb-4">
								<label class="block mb-1.5 text-gray-700 font-medium" for="full_name"><?php esc_html_e( 'Full Name', 'hotel-chain' ); ?></label>
								<input type="text" id="full_name" name="full_name" class="w-full p-3 border border-solid border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500" placeholder="<?php esc_attr_e( 'Enter your full name', 'hotel-chain' ); ?>" />
							</div>

							<div class="mb-4">
								<label class="block mb-1.5 text-gray-700 font-medium" for="email"><?php esc_html_e( 'Email Address', 'hotel-chain' ); ?></label>
								<input type="email" id="email" name="email" class="w-full p-3 border border-solid border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500" placeholder="<?php esc_attr_e( 'Enter your email address', 'hotel-chain' ); ?>" />
							</div>

							<div class="mb-4">
								<label class="block mb-1.5 text-gray-700 font-medium" for="password"><?php esc_html_e( 'Password', 'hotel-chain' ); ?></label>
								<input type="password" id="password" name="password" class="w-full p-3 border border-solid border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500" placeholder="<?php esc_attr_e( 'Create a password', 'hotel-chain' ); ?>" />
							</div>

							<div class="mb-4">
								<label class="block mb-1.5 text-gray-700 font-medium" for="confirm_password"><?php esc_html_e( 'Confirm Password', 'hotel-chain' ); ?></label>
								<input type="password" id="confirm_password" name="confirm_password" class="w-full p-3 border border-solid border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500" placeholder="<?php esc_attr_e( 'Confirm your password', 'hotel-chain' ); ?>" />
							</div>

							<div class="mb-6">
								<div class="flex items-start gap-3 p-3 border border-solid border-gray-300 rounded-lg">
									<input type="checkbox" id="agree_terms" name="agree_terms" class="w-5 h-5 mt-0.5" required />
									<label for="agree_terms" class="text-gray-700 cursor-pointer"><?php esc_html_e( 'I agree to the Terms of Service and Privacy Policy', 'hotel-chain' ); ?></label>
								</div>
							</div>

							<button type="submit" class="w-full py-3.5 bg-blue-200 border-2 border-solid border-blue-400 rounded-lg text-blue-900 text-base font-semibold cursor-pointer hover:bg-blue-300 disabled:opacity-60 disabled:cursor-not-allowed" id="submit-btn">
								<?php echo esc_html__( 'Register as Guest', 'hotel-chain' ); ?>
							</button>

							<?php if ( ! $is_logged_in ) : ?>
								<div class="text-center mt-4 text-gray-500">
									<?php esc_html_e( 'Already have an account?', 'hotel-chain' ); ?>
									<a href="<?php echo esc_url( site_url( '/guest-login' ) ); ?>" class="text-blue-600 hover:underline"><?php esc_html_e( 'Sign In', 'hotel-chain' ); ?></a>
								</div>
							<?php endif; ?>
						</form>

					<?php endif; ?>

				</div>
			</div>

			<!-- Email Confirmation Screen (hidden by default) -->
			<div id="confirmation-screen" class="max-w-2xl mx-auto mt-12 px-4 hidden">
				<div class="bg-white rounded-lg p-6 border border-solid border-gray-300">
					<div class="text-center">
						<div class="w-20 h-20 bg-green-200 border-2 border-solid border-green-400 rounded-full mx-auto mb-4 flex items-center justify-center">
							<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-700">
								<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
								<path d="m9 11 3 3L22 4"></path>
							</svg>
						</div>
						<h3 class="text-xl font-semibold mb-3"><?php esc_html_e( 'Check Your Email', 'hotel-chain' ); ?></h3>
						<p class="text-gray-700 mb-4"><?php esc_html_e( "We've sent a confirmation email to:", 'hotel-chain' ); ?></p>
						<div class="mb-6 p-3 bg-gray-100 border border-solid border-gray-300 rounded-lg">
							<div class="flex items-center justify-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600">
									<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
									<rect x="2" y="4" width="20" height="16" rx="2"></rect>
								</svg>
								<span id="confirmation-email" class="font-medium"></span>
							</div>
						</div>
						<p class="text-gray-600 mb-6"><?php esc_html_e( 'Click the confirmation link in your email to activate your account and access your meditation series immediately.', 'hotel-chain' ); ?></p>
						<div class="space-y-3">
							<button type="button" id="resend-email-btn" class="w-full px-6 py-3 bg-blue-200 border-2 border-solid border-blue-400 rounded-lg text-blue-900 font-medium hover:bg-blue-300">
								<?php esc_html_e( 'Resend Confirmation Email', 'hotel-chain' ); ?>
							</button>
							<button type="button" id="change-email-btn" class="w-full px-6 py-3 bg-gray-200 border-2 border-solid border-gray-400 rounded-lg text-gray-900 font-medium hover:bg-gray-300">
								<?php esc_html_e( 'Use Different Email', 'hotel-chain' ); ?>
							</button>
						</div>
						<div id="resend-message" class="mt-4"></div>
					</div>
				</div>
			</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const form = document.getElementById('guest-register-form');
				if (!form) return;

				const formContainer = document.querySelector('.max-w-2xl');
				const confirmationScreen = document.getElementById('confirmation-screen');
				const confirmationEmail = document.getElementById('confirmation-email');
				const msgContainer = document.getElementById('register-message');
				const submitBtn = document.getElementById('submit-btn');
				const resendBtn = document.getElementById('resend-email-btn');
				const changeEmailBtn = document.getElementById('change-email-btn');
				const resendMessage = document.getElementById('resend-message');

				const errorClass = 'bg-red-50 border-2 border-solid border-red-300 rounded-lg p-4 text-red-800 mb-5';
				const successClass = 'bg-green-50 border-2 border-solid border-green-300 rounded-lg p-4 text-green-800 mb-5';

				let currentGuestId = null;

				function showConfirmation(email, guestId) {
					currentGuestId = guestId;
					confirmationEmail.textContent = email;
					formContainer.classList.add('hidden');
					confirmationScreen.classList.remove('hidden');
					window.scrollTo({ top: 0, behavior: 'smooth' });
				}

				form.addEventListener('submit', function(e) {
					e.preventDefault();

					const password = document.getElementById('password');
					const confirmPassword = document.getElementById('confirm_password');

					if (password && !password.disabled && password.value !== confirmPassword.value) {
						msgContainer.innerHTML = '<div class="' + errorClass + '"><?php echo esc_js( __( 'Passwords do not match.', 'hotel-chain' ) ); ?></div>';
						return;
					}

					submitBtn.disabled = true;
					submitBtn.textContent = '<?php echo esc_js( __( 'Creating account...', 'hotel-chain' ) ); ?>';

					const formData = new FormData(form);

					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							if (data.data.show_confirmation) {
								showConfirmation(data.data.email, data.data.guest_id);
							} else if (data.data.redirect) {
								window.location.href = data.data.redirect;
							}
						} else {
							msgContainer.innerHTML = '<div class="' + errorClass + '">' + data.data.message + '</div>';
							submitBtn.disabled = false;
							submitBtn.textContent = '<?php echo esc_js( __( 'Register as Guest', 'hotel-chain' ) ); ?>';
						}
					})
					.catch(error => {
						msgContainer.innerHTML = '<div class="' + errorClass + '"><?php echo esc_js( __( 'An error occurred. Please try again.', 'hotel-chain' ) ); ?></div>';
						submitBtn.disabled = false;
						submitBtn.textContent = '<?php echo esc_js( __( 'Register as Guest', 'hotel-chain' ) ); ?>';
					});
				});

				// Resend email handler.
				resendBtn.addEventListener('click', function() {
					if (!currentGuestId) return;

					resendBtn.disabled = true;
					resendBtn.textContent = '<?php echo esc_js( __( 'Sending...', 'hotel-chain' ) ); ?>';

					const formData = new FormData();
					formData.append('action', 'guest_resend_verification');
					formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'guest_register_nonce' ) ); ?>');
					formData.append('guest_id', currentGuestId);

					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							resendMessage.innerHTML = '<div class="' + successClass + '">' + data.data.message + '</div>';
						} else {
							resendMessage.innerHTML = '<div class="' + errorClass + '">' + data.data.message + '</div>';
						}
						resendBtn.disabled = false;
						resendBtn.textContent = '<?php echo esc_js( __( 'Resend Confirmation Email', 'hotel-chain' ) ); ?>';
					})
					.catch(error => {
						resendMessage.innerHTML = '<div class="' + errorClass + '"><?php echo esc_js( __( 'An error occurred.', 'hotel-chain' ) ); ?></div>';
						resendBtn.disabled = false;
						resendBtn.textContent = '<?php echo esc_js( __( 'Resend Confirmation Email', 'hotel-chain' ) ); ?>';
					});
				});

				// Change email handler - go back to form.
				changeEmailBtn.addEventListener('click', function() {
					confirmationScreen.classList.add('hidden');
					formContainer.classList.remove('hidden');
					submitBtn.disabled = false;
					submitBtn.textContent = '<?php echo esc_js( __( 'Register as Guest', 'hotel-chain' ) ); ?>';
					document.getElementById('email').value = '';
					document.getElementById('email').focus();
				});
			});
			</script>

			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Handle AJAX registration.
	 *
	 * @return void
	 */
	public function handle_registration(): void {
		check_ajax_referer( 'guest_register_nonce', 'register_nonce' );

		$hotel_code = isset( $_POST['hotel_code'] ) ? sanitize_text_field( wp_unslash( $_POST['hotel_code'] ) ) : '';
		$full_name  = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $hotel_code || ! $full_name || ! $email ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'hotel-chain' ) ) );
		}

		// Get hotel.
		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_code( $hotel_code );

		if ( ! $hotel || 'active' !== $hotel->status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid hotel.', 'hotel-chain' ) ) );
		}

		$guest_repo = new GuestRepository();

		// Check if already registered for this hotel.
		$existing_guest = $guest_repo->get_by_email_and_hotel( $email, $hotel->id );
		if ( $existing_guest ) {
			if ( 'active' === $existing_guest->status ) {
				wp_send_json_error( array( 'message' => __( 'You are already registered as a guest at this hotel.', 'hotel-chain' ) ) );
			}
			// If pending, resend verification email.
			if ( 'pending' === $existing_guest->status ) {
				$this->send_verification_email( $existing_guest->id, $email, $existing_guest->verification_token, $hotel );
				wp_send_json_success(
					array(
						'show_confirmation' => true,
						'email'             => $email,
						'guest_id'          => $existing_guest->id,
						'message'           => __( 'Verification email resent.', 'hotel-chain' ),
					)
				);
			}
		}

		// Check if WordPress user exists.
		$user_id = 0;
		if ( email_exists( $email ) ) {
			$user    = get_user_by( 'email', $email );
			$user_id = $user->ID;
		} else {
			if ( empty( $password ) || strlen( $password ) < 6 ) {
				wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters.', 'hotel-chain' ) ) );
			}

			// Create WordPress user.
			$username = sanitize_user( strtolower( str_replace( ' ', '', $full_name ) ) . '_' . wp_rand( 100, 999 ) );
			$user_id  = wp_create_user( $username, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}

			// Update user display name.
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $full_name,
					'first_name'   => explode( ' ', $full_name )[0],
					'last_name'    => implode( ' ', array_slice( explode( ' ', $full_name ), 1 ) ),
				)
			);

			// Assign guest role.
			$user = new \WP_User( $user_id );
			$user->set_role( 'guest' );
		}

		// Parse name.
		$name_parts = explode( ' ', $full_name, 2 );
		$first_name = $name_parts[0];
		$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

		// Calculate access dates.
		$access_duration = $hotel->access_duration ? (int) $hotel->access_duration : 30;
		$access_start    = current_time( 'mysql' );
		$access_end      = gmdate( 'Y-m-d H:i:s', strtotime( "+{$access_duration} days" ) );

		// Generate guest code and verification token.
		$guest_code         = 'G-' . strtoupper( wp_generate_password( 8, false ) );
		$verification_token = wp_generate_password( 32, false );

		// Create guest record.
		$guest_id = $guest_repo->create(
			array(
				'hotel_id'           => $hotel->id,
				'user_id'            => $user_id,
				'guest_code'         => $guest_code,
				'first_name'         => $first_name,
				'last_name'          => $last_name,
				'email'              => $email,
				'registration_code'  => $hotel->hotel_code,
				'verification_token' => $verification_token,
				'access_start'       => $access_start,
				'access_end'         => $access_end,
				'status'             => 'pending',
			)
		);

		if ( ! $guest_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create guest record.', 'hotel-chain' ) ) );
		}

		// Send verification email.
		$this->send_verification_email( $guest_id, $email, $verification_token, $hotel );

		wp_send_json_success(
			array(
				'show_confirmation' => true,
				'email'             => $email,
				'guest_id'          => $guest_id,
				'message'           => __( 'Registration successful! Please check your email to verify your account.', 'hotel-chain' ),
			)
		);
	}

	/**
	 * Send verification email.
	 *
	 * @param int    $guest_id Guest ID.
	 * @param string $email    Email address.
	 * @param string $token    Verification token.
	 * @param object $hotel    Hotel object.
	 * @return bool
	 */
	private function send_verification_email( int $guest_id, string $email, string $token, $hotel ): bool {
		$verify_url = add_query_arg( 'token', $token, home_url( '/verify-email' ) );

		/* translators: %s: Hotel name */
		$subject = sprintf( __( 'Verify your email - %s', 'hotel-chain' ), $hotel->hotel_name );

		$message = sprintf(
			__( "Welcome to %1\$s!\n\nPlease click the link below to verify your email address and activate your account:\n\n%2\$s\n\nThis link will expire in 24 hours.\n\nIf you didn't create this account, you can safely ignore this email.\n\nThank you!", 'hotel-chain' ),
			$hotel->hotel_name,
			$verify_url
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $email, $subject, $message, $headers );
	}

	/**
	 * Handle email verification.
	 *
	 * @return void
	 */
	public function handle_email_verification(): void {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$guest_repo = new GuestRepository();
		$guest      = $token ? $guest_repo->get_by_token( $token ) : null;

		// Enqueue CSS.
		wp_enqueue_style(
			'hotel-chain-main',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			filemtime( get_template_directory() . '/assets/css/main.css' )
		);

		$success = false;
		$message = '';
		$hotel   = null;

		if ( ! $token || ! $guest ) {
			$message = __( 'Invalid or expired verification link.', 'hotel-chain' );
		} elseif ( 'active' === $guest->status ) {
			$message    = __( 'Your email has already been verified.', 'hotel-chain' );
			$success    = true;
			$hotel_repo = new HotelRepository();
			$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );
		} else {
			// Verify the email.
			$guest_repo->verify_email( $guest->id );
			$success = true;
			$message = __( 'Your email has been verified successfully!', 'hotel-chain' );

			$hotel_repo = new HotelRepository();
			$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );

			// Auto-login.
			if ( $guest->user_id && ! is_user_logged_in() ) {
				wp_set_current_user( $guest->user_id );
				wp_set_auth_cookie( $guest->user_id );
			}
		}

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Email Verification', 'hotel-chain' ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body class="bg-gray-100 m-0 p-0">
			<div class="max-w-2xl mx-auto mt-12 px-4">
				<div class="bg-white rounded-lg p-6 border border-solid border-gray-300 text-center">
					<?php if ( $success ) : ?>
						<div class="w-20 h-20 bg-green-200 border-2 border-solid border-green-400 rounded-full mx-auto mb-4 flex items-center justify-center">
							<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-700">
								<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
								<path d="m9 11 3 3L22 4"></path>
							</svg>
						</div>
						<h3 class="text-xl font-semibold mb-3"><?php esc_html_e( 'Email Verified!', 'hotel-chain' ); ?></h3>
						<p class="text-gray-600 mb-6"><?php echo esc_html( $message ); ?></p>
						<?php if ( $hotel ) : ?>
							<a href="<?php echo esc_url( home_url( '/hotel/' . $hotel->hotel_slug . '/' ) ); ?>" class="inline-block w-full px-6 py-3 bg-green-200 border-2 border-solid border-green-400 rounded-lg text-green-900 font-semibold hover:bg-green-300">
								<?php esc_html_e( 'Access Your Meditation Series', 'hotel-chain' ); ?>
							</a>
						<?php endif; ?>
					<?php else : ?>
						<div class="w-20 h-20 bg-red-200 border-2 border-solid border-red-400 rounded-full mx-auto mb-4 flex items-center justify-center">
							<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-700">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="15" y1="9" x2="9" y2="15"></line>
								<line x1="9" y1="9" x2="15" y2="15"></line>
							</svg>
						</div>
						<h3 class="text-xl font-semibold mb-3"><?php esc_html_e( 'Verification Failed', 'hotel-chain' ); ?></h3>
						<p class="text-gray-600 mb-6"><?php echo esc_html( $message ); ?></p>
						<a href="<?php echo esc_url( home_url() ); ?>" class="inline-block px-6 py-3 bg-gray-200 border-2 border-solid border-gray-400 rounded-lg text-gray-900 hover:bg-gray-300">
							<?php esc_html_e( 'Return to Homepage', 'hotel-chain' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Handle resend verification email AJAX.
	 *
	 * @return void
	 */
	public function handle_resend_verification(): void {
		check_ajax_referer( 'guest_register_nonce', 'nonce' );

		$guest_id = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;

		if ( ! $guest_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$guest_repo = new GuestRepository();
		$guest      = $guest_repo->get_by_id( $guest_id );

		if ( ! $guest || 'pending' !== $guest->status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid guest or already verified.', 'hotel-chain' ) ) );
		}

		// Generate new token.
		$new_token = wp_generate_password( 32, false );
		$guest_repo->update( $guest_id, array( 'verification_token' => $new_token ) );

		$hotel_repo = new HotelRepository();
		$hotel      = $hotel_repo->get_by_id( $guest->hotel_id );

		if ( ! $hotel ) {
			wp_send_json_error( array( 'message' => __( 'Hotel not found.', 'hotel-chain' ) ) );
		}

		$sent = $this->send_verification_email( $guest_id, $guest->email, $new_token, $hotel );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Verification email sent!', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send email. Please try again.', 'hotel-chain' ) ) );
		}
	}
}