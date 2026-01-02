<?php
/**
 * Menu Visibility Configuration
 *
 * Configure which WordPress admin menu items should be hidden for different user roles.
 * You can hide menus by:
 * - Menu slug (e.g., 'edit.php', 'upload.php')
 * - Menu ID (e.g., 'menu-posts', 'menu-media')
 * - Submenu parent and slug combination
 *
 * @package HotelChain
 */

namespace HotelChain\Config;

/**
 * Menu visibility configuration.
 */
class MenuVisibility {
	/**
	 * Get menu items to hide for each role.
	 *
	 * @return array Array of role => array of menu items to hide.
	 */
	public static function get_hidden_menus(): array {
		return array(
			// Hide menus for admin role.
			'administrator' => array(
				'index.php',
				'edit.php?post_type=page',
				'edit.php',
				'upload.php',
				'edit-comments.php',
				'themes.php',
				'plugins.php',
				'users.php',
				'tools.php',
				'options-general.php',
				'hotel-database-tools',
			),
			// Hide menus for Hotel role.
			'hotel'         => array(
				'index.php',
				'edit.php',
				'upload.php',
				'edit-comments.php',
				'themes.php',
				'plugins.php',
				'users.php',
				'tools.php',
				'options-general.php',
				'profile.php',
			),
		);
	}

	/**
	 * Get menu items to hide for a specific role.
	 *
	 * @param string $role User role.
	 * @return array Array of menu items to hide.
	 */
	public static function get_hidden_menus_for_role( string $role ): array {
		$all_hidden = self::get_hidden_menus();
		return isset( $all_hidden[ $role ] ) ? $all_hidden[ $role ] : array();
	}
}
