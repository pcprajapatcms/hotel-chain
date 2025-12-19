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
			// Hide menus for Administrator role.
			'administrator' => array(
				// Example: Hide Posts menu.
				// 'edit.php',
				// Example: Hide Media menu.
				// 'upload.php',
				// Example: Hide Comments menu.
				// 'edit-comments.php',
				// Example: Hide Appearance menu.
				// 'themes.php',
				// Example: Hide Plugins menu.
				// 'plugins.php',
				// Example: Hide Users menu.
				// 'users.php',
				// Example: Hide Tools menu.
				// 'tools.php',
				// Example: Hide Settings menu.
				// 'options-general.php',
				// Example: Hide specific submenu (parent => submenu slug).
				// array( 'edit.php' => 'edit.php' ), // Posts > All Posts.
			),

			// Hide menus for Hotel role.
			'hotel' => array(
				'index.php',
				'edit.php',
				'upload.php',
				'edit-comments.php',
				'themes.php',
				'plugins.php',
				'users.php',
				'tools.php',
				'options-general.php',
			),

			// Hide menus for Guest role.
			'guest' => array(
				// Example: Hide Posts menu.
				// 'edit.php',
				// Example: Hide Media menu.
				// 'upload.php',
				// Example: Hide Comments menu.
				// 'edit-comments.php',
				// Example: Hide Appearance menu.
				// 'themes.php',
				// Example: Hide Plugins menu.
				// 'plugins.php',
				// Example: Hide Users menu.
				// 'users.php',
				// Example: Hide Tools menu.
				// 'tools.php',
				// Example: Hide Settings menu.
				// 'options-general.php',
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
