<?php
/**
 * Custom roles service.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Register custom roles.
 */
class Roles implements ServiceProviderInterface {
	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_switch_theme', array( $this, 'add_roles' ) );
		add_action( 'init', array( $this, 'ensure_roles' ), 1 );
		add_filter( 'editable_roles', array( $this, 'add_editable_roles' ) );
	}

	/**
	 * Add Hotel and Guest roles.
	 *
	 * @return void
	 */
	public function add_roles(): void {
		$this->create_roles();
	}

	/**
	 * Ensure roles exist on every page load (idempotent).
	 *
	 * @return void
	 */
	public function ensure_roles(): void {
		$this->create_roles();
	}

	/**
	 * Create roles if they do not already exist.
	 *
	 * @return void
	 */
	private function create_roles(): void {
		if ( ! get_role( 'hotel' ) ) {
			add_role(
				'hotel',
				__( 'Hotel', 'hotel-chain' ),
				array(
					'read'                   => true,
					'edit_posts'             => true,
					'edit_published_posts'   => true,
					'publish_posts'          => true,
					'upload_files'           => true,
					'delete_posts'           => false,
					'delete_published_posts' => false,
				)
			);
		}

		if ( ! get_role( 'guest' ) ) {
			add_role(
				'guest',
				__( 'Guest', 'hotel-chain' ),
				array(
					'read'         => true,
					'edit_posts'   => false,
					'upload_files' => false,
				)
			);
		}
	}

	/**
	 * Make custom roles editable in admin.
	 *
	 * @param array $roles Existing roles.
	 * @return array Modified roles.
	 */
	public function add_editable_roles( array $roles ): array {
		return $roles;
	}
}
