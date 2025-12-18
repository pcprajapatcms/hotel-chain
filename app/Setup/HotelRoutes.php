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
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'hotel_slug';
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

		$hotel_user = $this->get_hotel_by_slug( $hotel_slug );

		if ( ! $hotel_user ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( '404' );
			exit;
		}

		set_query_var( 'hotel_user', $hotel_user );
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
}
