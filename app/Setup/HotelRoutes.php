<?php
/**
 * Hotel URL routing.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\HotelRepository;

/**
 * Handle hotel custom URLs.
 */
class HotelRoutes implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_hotel_template' ) );
		add_action( 'wp_ajax_save_video_progress', array( $this, 'handle_save_video_progress' ) );
		add_action( 'wp_ajax_nopriv_save_video_progress', array( $this, 'handle_save_video_progress' ) );
		add_action( 'wp_ajax_get_video_progress', array( $this, 'handle_get_video_progress' ) );
		add_action( 'wp_ajax_nopriv_get_video_progress', array( $this, 'handle_get_video_progress' ) );
	}

	/**
	 * Add rewrite rules for hotel URLs.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^hotel/([^/]+)/?$',
			'index.php?hotel_slug=$matches[1]',
			'top'
		);

		// Meditation player route: /hotel/{slug}/meditation/{video_id}
		add_rewrite_rule(
			'^hotel/([^/]+)/meditation/([0-9]+)/?$',
			'index.php?hotel_slug=$matches[1]&meditation_video_id=$matches[2]',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'hotel_slug';
		$vars[] = 'meditation_video_id';
		return $vars;
	}

	/**
	 * Handle hotel template loading.
	 *
	 * @return void
	 */
	public function handle_hotel_template(): void {
		$hotel_slug = get_query_var( 'hotel_slug' );

		if ( empty( $hotel_slug ) ) {
			return;
		}

		// Require guest login before accessing hotel pages.
		if ( ! is_user_logged_in() ) {
			$redirect_to = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			wp_safe_redirect(
				add_query_arg(
					'redirect_to',
					rawurlencode( $redirect_to ),
					home_url( '/guest-login' )
				)
			);
			exit;
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'guest', (array) $current_user->roles, true ) ) {
			$redirect_to = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			wp_safe_redirect(
				add_query_arg(
					'redirect_to',
					rawurlencode( $redirect_to ),
					home_url( '/guest-login' )
				)
			);
			exit;
		}

		$hotel_user = $this->get_hotel_by_slug( $hotel_slug );

		if ( ! $hotel_user ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( '404' );
			exit;
		}

		set_query_var( 'hotel_user', $hotel_user );

		// Check if this is a meditation player request.
		$meditation_video_id = get_query_var( 'meditation_video_id' );
		if ( ! empty( $meditation_video_id ) ) {
			set_query_var( 'meditation_video_id', absint( $meditation_video_id ) );
			load_template( get_template_directory() . '/template-meditation-player.php' );
			exit;
		}

		load_template( get_template_directory() . '/template-hotel.php' );
		exit;
	}

	/**
	 * Get hotel user by slug.
	 *
	 * @param string $slug Hotel slug.
	 * @return \WP_User|null
	 */
	private function get_hotel_by_slug( string $slug ): ?\WP_User {
		$repository = new HotelRepository();
		$hotel = $repository->get_by_slug( $slug );

		if ( ! $hotel ) {
			return null;
		}

		$user = get_user_by( 'id', $hotel->user_id );
		return $user ? $user : null;
	}

	/**
	 * Handle saving video progress via AJAX.
	 *
	 * @return void
	 */
	public function handle_save_video_progress(): void {
		global $wpdb;

		$video_id    = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		$hotel_id    = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;
		$duration    = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 0;
		$percentage  = isset( $_POST['percentage'] ) ? floatval( $_POST['percentage'] ) : 0;
		$completed   = isset( $_POST['completed'] ) ? absint( $_POST['completed'] ) : 0;

		if ( ! $video_id || ! $hotel_id ) {
			wp_send_json_error( array( 'message' => 'Missing required fields' ) );
		}

		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'hotel_chain_video_views';

		// Check if record exists for this user/video/hotel combination.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, completion_percentage FROM {$table} WHERE video_id = %d AND hotel_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1",
				$video_id,
				$hotel_id,
				$user_id
			)
		);

		if ( $existing ) {
			// Update only if new percentage is higher.
			if ( $percentage > floatval( $existing->completion_percentage ) ) {
				$wpdb->update(
					$table,
					array(
						'view_duration'         => $duration,
						'completion_percentage' => $percentage,
						'completed'             => $completed,
						'viewed_at'             => current_time( 'mysql' ),
					),
					array( 'id' => $existing->id ),
					array( '%d', '%f', '%d', '%s' ),
					array( '%d' )
				);
			}
		} else {
			// Insert new record.
			$wpdb->insert(
				$table,
				array(
					'video_id'              => $video_id,
					'hotel_id'              => $hotel_id,
					'user_id'               => $user_id,
					'view_duration'         => $duration,
					'completion_percentage' => $percentage,
					'completed'             => $completed,
					'ip_address'            => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
					'user_agent'            => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
				),
				array( '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s' )
			);
		}

		wp_send_json_success( array( 'percentage' => $percentage ) );
	}

	/**
	 * Handle getting video progress via AJAX.
	 *
	 * @return void
	 */
	public function handle_get_video_progress(): void {
		global $wpdb;

		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;

		if ( ! $video_id || ! $hotel_id ) {
			wp_send_json_error( array( 'message' => 'Missing required fields' ) );
		}

		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'hotel_chain_video_views';

		$progress = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT view_duration, completion_percentage, completed FROM {$table} WHERE video_id = %d AND hotel_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1",
				$video_id,
				$hotel_id,
				$user_id
			)
		);

		if ( $progress ) {
			wp_send_json_success( array(
				'duration'   => (int) $progress->view_duration,
				'percentage' => (float) $progress->completion_percentage,
				'completed'  => (int) $progress->completed,
			) );
		} else {
			wp_send_json_success( array(
				'duration'   => 0,
				'percentage' => 0,
				'completed'  => 0,
			) );
		}
	}
}
