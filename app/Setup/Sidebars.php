<?php
/**
 * Sidebar registration service.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Sidebar registration service.
 */
class Sidebars implements ServiceProviderInterface {
	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'widgets_init', array( $this, 'register_sidebars' ) );
	}

	/**
	 * Register theme sidebars.
	 *
	 * @return void
	 */
	public function register_sidebars(): void {
		register_sidebar(
			array(
				'name'          => __( 'Primary Sidebar', 'hotel-chain' ),
				'id'            => 'sidebar-1',
				'description'   => __( 'Main sidebar for posts and pages.', 'hotel-chain' ),
				'before_widget' => '<section id="%1$s" class="widget %2$s mb-8">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title text-lg font-semibold mb-4">',
				'after_title'   => '</h2>',
			)
		);
	}
}
