<?php
/**
 * Menu Visibility service provider.
 *
 * Hides WordPress admin menu items based on user roles.
 * Configure which menus to hide in: app/Config/MenuVisibility.php
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Config\MenuVisibility as MenuVisibilityConfig;

/**
 * Menu Visibility service provider.
 */
class MenuVisibility implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'hide_menus' ), 999 );
	}

	/**
	 * Hide menu items based on user role.
	 *
	 * @return void
	 */
	public function hide_menus(): void {
		if ( ! is_admin() ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user instanceof \WP_User ) {
			return;
		}

		// Get the user's roles.
		$user_roles = $current_user->roles;
		if ( empty( $user_roles ) ) {
			return;
		}

		// Check each role and hide menus accordingly.
		foreach ( $user_roles as $role ) {
			$hidden_menus = MenuVisibilityConfig::get_hidden_menus_for_role( $role );
			if ( empty( $hidden_menus ) ) {
				continue;
			}

			foreach ( $hidden_menus as $menu_item ) {
				if ( is_array( $menu_item ) ) {
					// Handle submenu hiding (parent => submenu slug).
					foreach ( $menu_item as $parent => $submenu ) {
						remove_submenu_page( $parent, $submenu );
					}
				} else {
					// Handle top-level menu hiding.
					remove_menu_page( $menu_item );
				}
			}
		}
	}
}
