<?php
/**
 * Theme support setup service.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Theme support setup service.
 */
class ThemeSupport implements ServiceProviderInterface {
	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'enable_supports' ) );
	}

	/**
	 * Enable theme supports.
	 *
	 * @return void
	 */
	public function enable_supports(): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 64,
				'width'       => 64,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
				'navigation-widgets',
				'search-form',
			)
		);
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/main.css' );
		register_nav_menus(
			array(
				'primary' => __( 'Primary Menu', 'hotel-chain' ),
				'footer'  => __( 'Footer Menu', 'hotel-chain' ),
			)
		);
	}
}
