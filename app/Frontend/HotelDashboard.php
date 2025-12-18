<?php
/**
 * Hotel Dashboard service provider.
 *
 * @package HotelChain
 */

namespace HotelChain\Frontend;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;

/**
 * Hotel Dashboard menu and routing.
 */
class HotelDashboard implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'check_hotel_user' ) );
		add_action( 'wp_ajax_hotel_request_video', array( $this, 'handle_video_request' ) );
		add_action( 'wp_ajax_hotel_toggle_video_status', array( $this, 'handle_toggle_video_status' ) );
	}

	/**
	 * Handle AJAX video request from hotel.
	 *
	 * @return void
	 */
	public function handle_video_request(): void {
		check_ajax_referer( 'hotel_video_request', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		if ( ! $video_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid video.', 'hotel-chain' ) ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_send_json_error( array( 'message' => __( 'Hotel not found.', 'hotel-chain' ) ) );
		}

		$assignment_repo = new HotelVideoAssignmentRepository();

		// Check if already assigned or pending.
		$existing = $assignment_repo->get_assignment( $hotel->id, $video_id );
		if ( $existing ) {
			if ( 'active' === $existing->status ) {
				wp_send_json_error( array( 'message' => __( 'Video is already assigned.', 'hotel-chain' ) ) );
			}
			if ( 'pending' === $existing->status ) {
				wp_send_json_error( array( 'message' => __( 'Request already pending.', 'hotel-chain' ) ) );
			}
		}

		$result = $assignment_repo->request( $hotel->id, $video_id, $current_user->ID );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Request sent successfully.', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send request.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Handle AJAX toggle video status by hotel.
	 *
	 * @return void
	 */
	public function handle_toggle_video_status(): void {
		check_ajax_referer( 'hotel_video_request', 'nonce' );

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'hotel-chain' ) ) );
		}

		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $video_id || ! in_array( $new_status, array( 'active', 'inactive' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'hotel-chain' ) ) );
		}

		$hotel_repository = new HotelRepository();
		$hotel = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_send_json_error( array( 'message' => __( 'Hotel not found.', 'hotel-chain' ) ) );
		}

		$assignment_repo = new HotelVideoAssignmentRepository();
		$result = $assignment_repo->update_hotel_status( $hotel->id, $video_id, $new_status );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => 'active' === $new_status
						? __( 'Video activated.', 'hotel-chain' )
						: __( 'Video deactivated.', 'hotel-chain' ),
					'status'  => $new_status,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'hotel-chain' ) ) );
		}
	}

	/**
	 * Check if current user is a hotel user and redirect if needed.
	 *
	 * @return void
	 */
	public function check_hotel_user(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			return;
		}

		// If hotel user tries to access admin dashboard, redirect to hotel dashboard.
		$screen = get_current_screen();
		if ( $screen && 'dashboard' === $screen->id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=hotel-dashboard' ) );
			exit;
		}
	}

	/**
	 * Register hotel dashboard menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Only show to hotel role users.
		if ( ! current_user_can( 'read' ) ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			return;
		}

		add_menu_page(
			__( 'Hotel Dashboard', 'hotel-chain' ),
			__( 'Dashboard', 'hotel-chain' ),
			'read',
			'hotel-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-building',
			2
		);

		add_submenu_page(
			'hotel-dashboard',
			__( 'Video Library', 'hotel-chain' ),
			__( 'Video Library', 'hotel-chain' ),
			'read',
			'hotel-video-library',
			array( $this, 'render_video_library' )
		);
	}

	/**
	 * Render dashboard home page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$current_user = wp_get_current_user();
		if ( ! in_array( 'hotel', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'hotel-chain' ) );
		}

		$hotel_repository = new \HotelChain\Repositories\HotelRepository();
		$hotel = $hotel_repository->get_by_user_id( $current_user->ID );

		if ( ! $hotel ) {
			wp_die( esc_html__( 'Hotel account not found.', 'hotel-chain' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( sprintf( __( 'Welcome, %s', 'hotel-chain' ), $hotel->hotel_name ) ); ?></h1>
			<p><?php esc_html_e( 'Manage your hotel video library and settings.', 'hotel-chain' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render video library page (delegates to HotelVideoLibraryPage).
	 *
	 * @return void
	 */
	public function render_video_library(): void {
		$library_page = new \HotelChain\Frontend\HotelVideoLibraryPage();
		$library_page->render_page();
	}
}
