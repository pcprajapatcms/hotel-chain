<?php
/**
 * Asset enqueuing service.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Support\AssetResolver;

/**
 * Asset enqueuing service.
 */
class Assets implements ServiceProviderInterface {
	/**
	 * Asset resolver instance.
	 *
	 * @var AssetResolver
	 */
	private AssetResolver $assets;

	/**
	 * Constructor.
	 *
	 * @param AssetResolver $assets Asset resolver instance.
	 */
	public function __construct( AssetResolver $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ), 999 );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		wp_enqueue_style(
			'hotel-chain-main',
			$this->assets->asset_uri( 'assets/css/main.css' ),
			array(),
			$this->assets->asset_version( 'assets/css/main.css' )
		);

		wp_enqueue_script(
			'hotel-chain-app',
			$this->assets->asset_uri( 'assets/js/app.js' ),
			array(),
			$this->assets->asset_version( 'assets/js/app.js' ),
			true
		);

		wp_localize_script(
			'hotel-chain-app',
			'hotelChain',
			array(
				'rootUrl' => esc_url_raw( home_url() ),
			)
		);
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public function enqueue_editor(): void {
		wp_enqueue_style(
			'hotel-chain-editor',
			$this->assets->asset_uri( 'assets/css/main.css' ),
			array(),
			$this->assets->asset_version( 'assets/css/main.css' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin( string $hook ): void {
		// Only load on our Hotel admin pages.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( 'hotel-chain-accounts', 'hotel-details' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'hotel-chain-admin',
			$this->assets->asset_uri( 'assets/css/main.css' ),
			array(),
			$this->assets->asset_version( 'assets/css/main.css' )
		);

		wp_enqueue_style(
			'hotel-chain-admin-custom',
			$this->assets->asset_uri( 'src/styles/admin.css' ),
			array( 'hotel-chain-admin' ),
			$this->assets->asset_version( 'src/styles/admin.css' )
		);

		wp_enqueue_script(
			'hotel-chain-admin',
			$this->assets->asset_uri( 'assets/js/admin-hotels.js' ),
			array(),
			$this->assets->asset_version( 'assets/js/admin-hotels.js' ),
			true
		);

		wp_localize_script(
			'hotel-chain-admin',
			'hotelChainAdmin',
			array(
				'regCopiedText'  => esc_html__( 'Registration URL copied to clipboard!', 'hotel-chain' ),
				'landCopiedText' => esc_html__( 'Landing page URL copied to clipboard!', 'hotel-chain' ),
			)
		);
	}
}
