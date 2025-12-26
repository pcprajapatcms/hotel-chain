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
		// Only load on our Hotel and Video admin pages.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( 'hotel-chain-accounts', 'hotel-details', 'hotel-edit', 'hotel-video-upload', 'hotel-video-library', 'hotel-video-requests', 'hotel-video-taxonomy', 'hotel-profile', 'hotel-dashboard' ), true ) ) {
			return;
		}

		// Enqueue editor scripts for pages that use wp_editor.
		if ( in_array( $page, array( 'hotel-video-library', 'hotel-video-upload' ), true ) ) {
			wp_enqueue_editor();
			wp_enqueue_media();
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

		$video_tags = array();
		if ( taxonomy_exists( 'video_tag' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'video_tag',
					'hide_empty' => false,
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				$video_tags = wp_list_pluck( $terms, 'name' );
			}
		}

		wp_localize_script(
			'hotel-chain-admin',
			'hotelChainAdmin',
			array(
				'regCopiedText'         => esc_html__( 'Registration URL copied to clipboard!', 'hotel-chain' ),
				'landCopiedText'        => esc_html__( 'Landing page URL copied to clipboard!', 'hotel-chain' ),
				'videoUploadUrl'        => esc_url_raw( admin_url( 'edit.php?post_type=video&page=hotel-video-upload' ) ),
				'uploadPreparingText'   => esc_html__( 'Preparing upload...', 'hotel-chain' ),
				'uploadInProgressText'  => esc_html__( 'Uploading...', 'hotel-chain' ),
				'uploadFinishingText'   => esc_html__( 'Finalizing upload...', 'hotel-chain' ),
				'uploadErrorText'       => esc_html__( 'Upload failed. Please try again.', 'hotel-chain' ),
				'videoTags'             => $video_tags,
				'hotelNameRequired'     => esc_html__( 'Hotel name is required.', 'hotel-chain' ),
				'contactEmailRequired'  => esc_html__( 'Contact email is required.', 'hotel-chain' ),
				'contactEmailInvalid'   => esc_html__( 'Please enter a valid email address.', 'hotel-chain' ),
				'adminUsernameRequired' => esc_html__( 'Admin username is required.', 'hotel-chain' ),
			)
		);
	}
}
