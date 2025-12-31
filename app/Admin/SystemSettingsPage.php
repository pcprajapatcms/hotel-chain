<?php
/**
 * System Settings page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Database\Schema;
use HotelChain\Support\StyleSettings;
use HotelChain\Support\AccountSettings;

/**
 * System Settings page.
 */
class SystemSettingsPage implements ServiceProviderInterface {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_save_system_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_hotel_chain_reset_system_settings', array( $this, 'handle_reset_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader' ) );
		add_action( 'wp_ajax_system_settings_upload_media', array( $this, 'handle_media_upload' ) );
		add_action( 'wp_ajax_system_settings_delete_media', array( $this, 'handle_media_delete' ) );
	}

	/**
	 * Register admin menu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'System Settings', 'hotel-chain' ),
			__( 'System Settings', 'hotel-chain' ),
			'manage_options',
			'system-settings',
			array( $this, 'render_page' ),
			'dashicons-admin-settings',
			10
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings array.
	 */
	private function get_default_settings(): array {
		return array(
			'account' => array(
				'default_guest_duration'    => 30,
				'expiry_warning_period'      => 3,
				'allow_guest_registration'   => true,
				'require_email_verification' => true,
				'auto_approve_requests'       => false,
				'allow_reactivation'         => true,
			),
			'content' => array(
				'max_video_size'        => 2,
				'signed_url_expiration' => 1,
				'supported_formats'      => array( 'MP4', 'MOV', 'AVI', 'WebM' ),
				'enable_download'        => false,
				'auto_play_next'         => true,
				'track_analytics'        => true,
			),
			'email' => array(
				'from_email'                      => 'noreply@videoplatform.com',
				'from_name'                       => 'Video Platform',
				'email_registration_confirmation' => true,
				'email_access_approved'            => true,
				'email_expiry_warning'             => true,
				'email_admin_alerts'               => true,
			),
			'style' => array(
				'typography_h1_font'        => 'Playfair Display',
				'typography_h1_font_url'     => '',
				'typography_h1_font_weight'  => '600',
				'typography_h2_font'        => 'Playfair Display',
				'typography_h2_font_url'     => '',
				'typography_h2_font_weight'  => '600',
				'typography_h3_font'        => 'Playfair Display',
				'typography_h3_font_url'     => '',
				'typography_h3_font_weight'  => '600',
				'typography_h4_font'        => 'Playfair Display',
				'typography_h4_font_url'     => '',
				'typography_h4_font_weight'  => '600',
				'typography_h5_font'        => 'Playfair Display',
				'typography_h5_font_url'     => '',
				'typography_h5_font_weight'  => '600',
				'typography_h6_font'        => 'Playfair Display',
				'typography_h6_font_url'     => '',
				'typography_h6_font_weight'  => '600',
				'typography_p_font'          => 'Inter',
				'typography_p_font_url'      => '',
				'typography_p_font_weight'   => '400',
				'logo_id'               => 0,
				'logo_url'              => '',
				'favicon_id'            => 0,
				'favicon_url'           => '',
				'font_size_h1_mobile'   => 28,
				'font_size_h1_tablet'   => 32,
				'font_size_h1_desktop'  => 36,
				'font_size_h2_mobile'   => 24,
				'font_size_h2_tablet'   => 28,
				'font_size_h2_desktop'  => 32,
				'font_size_h3_mobile'   => 20,
				'font_size_h3_tablet'   => 24,
				'font_size_h3_desktop'  => 28,
				'font_size_h4_mobile'   => 18,
				'font_size_h4_tablet'   => 20,
				'font_size_h4_desktop'  => 24,
				'font_size_h5_mobile'   => 16,
				'font_size_h5_tablet'   => 18,
				'font_size_h5_desktop'  => 20,
				'font_size_h6_mobile'   => 14,
				'font_size_h6_tablet'   => 16,
				'font_size_h6_desktop'  => 18,
				'font_size_p_mobile'    => 14,
				'font_size_p_tablet'    => 16,
				'font_size_p_desktop'   => 18,
				'button_primary_color'  => '#1f88ff',
				'button_secondary_color' => '#6b7280',
				'button_success_color'  => '#10b981',
				'button_info_color'     => '#3b82f6',
				'button_warning_color'  => '#f59e0b',
				'button_danger_color'   => '#ef4444',
			),
		);
	}

	/**
	 * Get current settings.
	 *
	 * @return array Settings array.
	 */
	private function get_settings(): array {
		global $wpdb;
		$table_name = Schema::get_table_name( 'system_settings' );

		// Get settings from database.
		$row = $wpdb->get_row( "SELECT * FROM {$table_name} LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			// Return defaults if no row exists.
			return $this->get_default_settings();
		}

		// Decode JSON columns.
		$account_settings = ! empty( $row['account_settings'] ) ? json_decode( $row['account_settings'], true ) : array();
		$content_settings = ! empty( $row['content_settings'] ) ? json_decode( $row['content_settings'], true ) : array();
		$email_settings   = ! empty( $row['email_settings'] ) ? json_decode( $row['email_settings'], true ) : array();
		$style_settings   = ! empty( $row['style_settings'] ) ? json_decode( $row['style_settings'], true ) : array();

		// Merge with defaults.
		$defaults = $this->get_default_settings();
		return array(
			'account' => wp_parse_args( $account_settings, $defaults['account'] ),
			'content' => wp_parse_args( $content_settings, $defaults['content'] ),
			'email'   => wp_parse_args( $email_settings, $defaults['email'] ),
			'style'   => wp_parse_args( $style_settings, $defaults['style'] ),
		);
	}

	/**
	 * Handle save settings form submission.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_save_system_settings' );

		global $wpdb;
		$table_name = Schema::get_table_name( 'system_settings' );

		// Organize settings into categories.
		$account_settings = array(
			'default_guest_duration'    => isset( $_POST['default_guest_duration'] ) ? absint( $_POST['default_guest_duration'] ) : 30, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'expiry_warning_period'      => isset( $_POST['expiry_warning_period'] ) ? absint( $_POST['expiry_warning_period'] ) : 3, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'allow_guest_registration'   => isset( $_POST['allow_guest_registration'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'require_email_verification' => isset( $_POST['require_email_verification'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'auto_approve_requests'       => isset( $_POST['auto_approve_requests'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'allow_reactivation'         => isset( $_POST['allow_reactivation'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$content_settings = array(
			'max_video_size'        => isset( $_POST['max_video_size'] ) ? absint( $_POST['max_video_size'] ) : 2, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'signed_url_expiration' => isset( $_POST['signed_url_expiration'] ) ? absint( $_POST['signed_url_expiration'] ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'supported_formats'      => isset( $_POST['supported_formats'] ) ? array_map( 'sanitize_text_field', (array) $_POST['supported_formats'] ) : array(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'enable_download'        => isset( $_POST['enable_download'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'auto_play_next'         => isset( $_POST['auto_play_next'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'track_analytics'        => isset( $_POST['track_analytics'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$email_settings = array(
			'from_email'                      => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : 'noreply@videoplatform.com', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'from_name'                       => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : 'Video Platform', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email_registration_confirmation' => isset( $_POST['email_registration_confirmation'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email_access_approved'            => isset( $_POST['email_access_approved'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email_expiry_warning'             => isset( $_POST['email_expiry_warning'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email_admin_alerts'               => isset( $_POST['email_admin_alerts'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$style_settings = array(
			'typography_h1_font'        => isset( $_POST['typography_h1_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h1_font'] ) ) : 'Playfair Display', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h1_font_url'     => isset( $_POST['typography_h1_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_h1_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h1_font_weight'  => isset( $_POST['typography_h1_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h1_font_weight'] ) ) : '600', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h2_font'        => isset( $_POST['typography_h2_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h2_font'] ) ) : 'Playfair Display', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h2_font_url'     => isset( $_POST['typography_h2_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_h2_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h2_font_weight'  => isset( $_POST['typography_h2_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h2_font_weight'] ) ) : '600', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h3_font'        => isset( $_POST['typography_h3_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h3_font'] ) ) : 'Playfair Display', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h3_font_url'     => isset( $_POST['typography_h3_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_h3_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h3_font_weight'  => isset( $_POST['typography_h3_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h3_font_weight'] ) ) : '600', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h4_font'        => isset( $_POST['typography_h4_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h4_font'] ) ) : 'Playfair Display', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h4_font_url'     => isset( $_POST['typography_h4_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_h4_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h4_font_weight'  => isset( $_POST['typography_h4_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h4_font_weight'] ) ) : '600', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h5_font'        => isset( $_POST['typography_h5_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h5_font'] ) ) : 'Playfair Display', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h5_font_url'     => isset( $_POST['typography_h5_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_h5_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h5_font_weight'  => isset( $_POST['typography_h5_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h5_font_weight'] ) ) : '600', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h6_font'        => isset( $_POST['typography_h6_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h6_font'] ) ) : 'Playfair Display', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h6_font_url'     => isset( $_POST['typography_h6_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_h6_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_h6_font_weight'  => isset( $_POST['typography_h6_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_h6_font_weight'] ) ) : '600', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_p_font'          => isset( $_POST['typography_p_font'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_p_font'] ) ) : 'Inter', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_p_font_url'      => isset( $_POST['typography_p_font_url'] ) ? esc_url_raw( wp_unslash( $_POST['typography_p_font_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'typography_p_font_weight'   => isset( $_POST['typography_p_font_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['typography_p_font_weight'] ) ) : '400', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'logo_id'               => isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'logo_url'              => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'favicon_id'            => isset( $_POST['favicon_id'] ) ? absint( $_POST['favicon_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'favicon_url'           => isset( $_POST['favicon_url'] ) ? esc_url_raw( wp_unslash( $_POST['favicon_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h1_mobile'   => isset( $_POST['font_size_h1_mobile'] ) ? absint( $_POST['font_size_h1_mobile'] ) : 28, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h1_tablet'   => isset( $_POST['font_size_h1_tablet'] ) ? absint( $_POST['font_size_h1_tablet'] ) : 32, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h1_desktop'  => isset( $_POST['font_size_h1_desktop'] ) ? absint( $_POST['font_size_h1_desktop'] ) : 36, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h2_mobile'   => isset( $_POST['font_size_h2_mobile'] ) ? absint( $_POST['font_size_h2_mobile'] ) : 24, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h2_tablet'   => isset( $_POST['font_size_h2_tablet'] ) ? absint( $_POST['font_size_h2_tablet'] ) : 28, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h2_desktop'  => isset( $_POST['font_size_h2_desktop'] ) ? absint( $_POST['font_size_h2_desktop'] ) : 32, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h3_mobile'   => isset( $_POST['font_size_h3_mobile'] ) ? absint( $_POST['font_size_h3_mobile'] ) : 20, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h3_tablet'   => isset( $_POST['font_size_h3_tablet'] ) ? absint( $_POST['font_size_h3_tablet'] ) : 24, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h3_desktop'  => isset( $_POST['font_size_h3_desktop'] ) ? absint( $_POST['font_size_h3_desktop'] ) : 28, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h4_mobile'   => isset( $_POST['font_size_h4_mobile'] ) ? absint( $_POST['font_size_h4_mobile'] ) : 18, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h4_tablet'   => isset( $_POST['font_size_h4_tablet'] ) ? absint( $_POST['font_size_h4_tablet'] ) : 20, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h4_desktop'  => isset( $_POST['font_size_h4_desktop'] ) ? absint( $_POST['font_size_h4_desktop'] ) : 24, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h5_mobile'   => isset( $_POST['font_size_h5_mobile'] ) ? absint( $_POST['font_size_h5_mobile'] ) : 16, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h5_tablet'   => isset( $_POST['font_size_h5_tablet'] ) ? absint( $_POST['font_size_h5_tablet'] ) : 18, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h5_desktop'  => isset( $_POST['font_size_h5_desktop'] ) ? absint( $_POST['font_size_h5_desktop'] ) : 20, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h6_mobile'   => isset( $_POST['font_size_h6_mobile'] ) ? absint( $_POST['font_size_h6_mobile'] ) : 14, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h6_tablet'   => isset( $_POST['font_size_h6_tablet'] ) ? absint( $_POST['font_size_h6_tablet'] ) : 16, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_h6_desktop'  => isset( $_POST['font_size_h6_desktop'] ) ? absint( $_POST['font_size_h6_desktop'] ) : 18, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_p_mobile'    => isset( $_POST['font_size_p_mobile'] ) ? absint( $_POST['font_size_p_mobile'] ) : 14, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_p_tablet'    => isset( $_POST['font_size_p_tablet'] ) ? absint( $_POST['font_size_p_tablet'] ) : 16, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'font_size_p_desktop'   => isset( $_POST['font_size_p_desktop'] ) ? absint( $_POST['font_size_p_desktop'] ) : 18, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_primary_color'  => isset( $_POST['button_primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_primary_color'] ) ) : '#1f88ff', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_secondary_color' => isset( $_POST['button_secondary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_secondary_color'] ) ) : '#6b7280', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_success_color'  => isset( $_POST['button_success_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_success_color'] ) ) : '#10b981', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_info_color'     => isset( $_POST['button_info_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_info_color'] ) ) : '#3b82f6', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_warning_color'  => isset( $_POST['button_warning_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_warning_color'] ) ) : '#f59e0b', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_danger_color'   => isset( $_POST['button_danger_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_danger_color'] ) ) : '#ef4444', // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		// Check if row exists.
		$existing = $wpdb->get_row( "SELECT id FROM {$table_name} LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			// Update existing row.
			$wpdb->update(
				$table_name,
				array(
					'account_settings' => wp_json_encode( $account_settings ),
					'content_settings' => wp_json_encode( $content_settings ),
					'email_settings'   => wp_json_encode( $email_settings ),
					'style_settings'   => wp_json_encode( $style_settings ),
				),
				array( 'id' => $existing['id'] ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new row.
			$wpdb->insert(
				$table_name,
				array(
					'account_settings' => wp_json_encode( $account_settings ),
					'content_settings' => wp_json_encode( $content_settings ),
					'email_settings'   => wp_json_encode( $email_settings ),
					'style_settings'   => wp_json_encode( $style_settings ),
				),
				array( '%s', '%s', '%s', '%s' )
			);
		}

		// Clear caches.
		AccountSettings::clear_cache();
		StyleSettings::clear_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'system-settings',
					'settings_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle reset settings form submission.
	 *
	 * @return void
	 */
	public function handle_reset_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_reset_system_settings' );

		global $wpdb;
		$table_name = Schema::get_table_name( 'system_settings' );
		$defaults   = $this->get_default_settings();

		// Check if row exists.
		$existing = $wpdb->get_row( "SELECT id FROM {$table_name} LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			// Update with defaults.
			$wpdb->update(
				$table_name,
				array(
					'account_settings' => wp_json_encode( $defaults['account'] ),
					'content_settings' => wp_json_encode( $defaults['content'] ),
					'email_settings'   => wp_json_encode( $defaults['email'] ),
					'style_settings'   => wp_json_encode( $defaults['style'] ),
				),
				array( 'id' => $existing['id'] ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert defaults.
			$wpdb->insert(
				$table_name,
				array(
					'account_settings' => wp_json_encode( $defaults['account'] ),
					'content_settings' => wp_json_encode( $defaults['content'] ),
					'email_settings'   => wp_json_encode( $defaults['email'] ),
					'style_settings'   => wp_json_encode( $defaults['style'] ),
				),
				array( '%s', '%s', '%s', '%s' )
			);
		}

		// Clear caches.
		AccountSettings::clear_cache();
		StyleSettings::clear_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'system-settings',
					'settings_reset' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue media uploader scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_media_uploader( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'system-settings' === $page ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Get popular Google Fonts list.
	 *
	 * @return array Array of font names and URLs.
	 */
	private function get_google_fonts(): array {
		return array(
			'Inter'              => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
			'Playfair Display'  => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap',
			'Roboto'            => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
			'Open Sans'         => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap',
			'Lato'              => 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
			'Montserrat'        => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap',
			'Poppins'           => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
			'Raleway'           => 'https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700&display=swap',
			'Oswald'            => 'https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&display=swap',
			'Roboto Condensed'  => 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;500;600;700&display=swap',
			'Ubuntu'            => 'https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;600;700&display=swap',
			'Lobster'           => 'https://fonts.googleapis.com/css2?family=Lobster&display=swap',
			'Pacifico'          => 'https://fonts.googleapis.com/css2?family=Pacifico&display=swap',
			'Anton'             => 'https://fonts.googleapis.com/css2?family=Anton&display=swap',
			'Exo 2'             => 'https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700&display=swap',
			'Bebas Neue'        => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap',
			'Bitter'            => 'https://fonts.googleapis.com/css2?family=Bitter:wght@300;400;500;600;700&display=swap',
			'Fira Sans'         => 'https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&display=swap',
			'Jura'              => 'https://fonts.googleapis.com/css2?family=Jura:wght@300;400;500;600;700&display=swap',
			'Kanit'             => 'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap',
			'Kaushan Script'    => 'https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap',
			'Lobster Two'       => 'https://fonts.googleapis.com/css2?family=Lobster+Two:wght@300;400;700&display=swap',
			'Noto Serif'        => 'https://fonts.googleapis.com/css2?family=Noto+Serif:wght@300;400;500;600;700&display=swap',
			'Merriweather'      => 'https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&display=swap',
			'Source Sans Pro'   => 'https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap',
		);
	}

	/**
	 * Render toggle switch HTML.
	 *
	 * @param string $name Setting name.
	 * @param bool   $checked Whether checked.
	 * @param string $label Label text.
	 * @param string $description Description text.
	 * @return void
	 */
	private function render_toggle( string $name, bool $checked, string $label, string $description ): void {
		$toggle_id = 'toggle_' . $name;
		$toggle_class = $checked ? 'toggle-switch-on' : 'toggle-switch-off';
		?>
		<div class="flex items-center justify-between p-3 border border-solid border-gray-400 rounded">
			<div>
				<div class="mb-1"><h5><?php echo esc_html( $label ); ?></h5></div>
				<div class="text-gray-600"><p><?php echo esc_html( $description ); ?></p></div>
			</div>
			<label for="<?php echo esc_attr( $toggle_id ); ?>" class="toggle-switch-label">
				<input type="checkbox" id="<?php echo esc_attr( $toggle_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> class="toggle-switch-input" />
				<span class="toggle-switch <?php echo esc_attr( $toggle_class ); ?>">
					<span class="toggle-switch-slider"></span>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render the system settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$settings_saved = isset( $_GET['settings_saved'] ) ? absint( $_GET['settings_saved'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings_reset = isset( $_GET['settings_reset'] ) ? absint( $_GET['settings_reset'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Flatten settings for easier access in templates.
		$s = array_merge(
			$settings['account'] ?? array(),
			$settings['content'] ?? array(),
			$settings['email'] ?? array(),
			$settings['style'] ?? array()
		);

		$logo_url = StyleSettings::get_logo_url();
		?>
		<div class="flex-1 overflow-auto p-4 lg:p-8 lg:px-0">
			<div class="w-12/12 md:w-10/12 mx-auto p-0">
				<div class="space-y-6">
					<div class="flex items-center gap-4 mb-6 pb-3 border-b border-solid border-gray-400">
						<?php if ( ! empty( $logo_url ) ) : ?>
							<div class="flex-shrink-0">
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'hotel-chain' ); ?>" class="h-12 md:h-16 w-auto object-contain" />
							</div>
						<?php endif; ?>
						<div class="flex-1">
							<h1><?php esc_html_e( 'ADMIN – System Settings', 'hotel-chain' ); ?></h1>
							<p class="text-slate-600"><?php esc_html_e( 'Configure platform-wide settings and defaults', 'hotel-chain' ); ?></p>
						</div>
					</div>

					<?php if ( $settings_saved ) : ?>
						<div class="bg-green-50 border-2 border-green-400 rounded p-4 mb-4">
							<p class="text-green-900 font-medium"><?php esc_html_e( 'Settings saved successfully.', 'hotel-chain' ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $settings_reset ) : ?>
						<div class="bg-blue-50 border-2 border-blue-400 rounded p-4 mb-4">
							<p class="text-blue-900 font-medium"><?php esc_html_e( 'Settings reset to defaults.', 'hotel-chain' ); ?></p>
						</div>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'hotel_chain_save_system_settings' ); ?>
						<input type="hidden" name="action" value="hotel_chain_save_system_settings" />

						<!-- Account & Access Settings -->
						<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-400">
							<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield w-5 h-5 text-blue-600" aria-hidden="true">
									<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
								</svg>
								<?php esc_html_e( 'Account & Access Settings', 'hotel-chain' ); ?>
							</h3>
							<div class="space-y-4">
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Default Guest Access Duration', 'hotel-chain' ); ?></div>
										<div class="flex items-center gap-3">
											<input type="number" name="default_guest_duration" value="<?php echo esc_attr( (string) $s['default_guest_duration'] ); ?>" min="1" max="365" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white" />
											<span class="text-gray-600"><?php esc_html_e( 'days', 'hotel-chain' ); ?></span>
										</div>
										<div class="text-gray-600 mt-1 text-sm"><?php esc_html_e( 'Default period for new guest accounts', 'hotel-chain' ); ?></div>
									</div>
									<div>
										<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Expiry Warning Period', 'hotel-chain' ); ?></div>
										<div class="flex items-center gap-3">
											<input type="number" name="expiry_warning_period" value="<?php echo esc_attr( (string) $s['expiry_warning_period'] ); ?>" min="1" max="30" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white" />
											<span class="text-gray-600"><?php esc_html_e( 'days before expiry', 'hotel-chain' ); ?></span>
										</div>
										<div class="text-gray-600 mt-1 text-sm"><?php esc_html_e( 'When to send expiration alerts', 'hotel-chain' ); ?></div>
									</div>
								</div>
								<div class="border-t border-solid border-gray-400 pt-4">
									<div class="mb-3 text-gray-700 text-sm"><?php esc_html_e( 'Access Control Options', 'hotel-chain' ); ?></div>
									<div class="space-y-3">
										<?php
										$this->render_toggle(
											'allow_guest_registration',
											$s['allow_guest_registration'],
											__( 'Allow Guest Self-Registration', 'hotel-chain' ),
											__( 'Guests can register via hotel unique links', 'hotel-chain' )
										);
										$this->render_toggle(
											'require_email_verification',
											$s['require_email_verification'],
											__( 'Require Email Verification', 'hotel-chain' ),
											__( 'Guests must verify email before accessing videos', 'hotel-chain' )
										);
										$this->render_toggle(
											'auto_approve_requests',
											$s['auto_approve_requests'],
											__( 'Auto-Approve Video Requests', 'hotel-chain' ),
											__( 'Automatically approve all guest video requests', 'hotel-chain' )
										);
										/* $this->render_toggle(
											'allow_reactivation',
											$s['allow_reactivation'],
											__( 'Allow Reactivation Requests', 'hotel-chain' ),
											__( 'Guests can request account reactivation after expiry', 'hotel-chain' )
										); */
										?>
									</div>
								</div>
							</div>
						</div>

						<!-- Video & Content Settings -->
						<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-400">
							<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings w-5 h-5 text-purple-600" aria-hidden="true">
									<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"></path>
									<circle cx="12" cy="12" r="3"></circle>
								</svg>
								<?php esc_html_e( 'Video & Content Settings', 'hotel-chain' ); ?>
							</h3>
							<div class="space-y-4">
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Maximum Video File Size', 'hotel-chain' ); ?></div>
										<div class="flex items-center gap-3">
											<input type="number" name="max_video_size" value="<?php echo esc_attr( (string) $s['max_video_size'] ); ?>" min="1" max="10" class="flex-1 border border-solid border-gray-400 rounded p-2 bg-white" />
											<span class="text-gray-600"><?php esc_html_e( 'GB', 'hotel-chain' ); ?></span>
										</div>
									</div>
									<div>
										<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Signed URL Expiration', 'hotel-chain' ); ?></div>
										<div class="flex items-center gap-3">
											<input type="number" name="signed_url_expiration" value="<?php echo esc_attr( (string) $s['signed_url_expiration'] ); ?>" min="1" max="24" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white" />
											<span class="text-gray-600"><?php esc_html_e( 'hour', 'hotel-chain' ); ?></span>
										</div>
									</div>
								</div>
								<div>
									<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Supported Video Formats', 'hotel-chain' ); ?></div>
									<div class="flex flex-wrap gap-2">
										<?php
										$all_formats = array( 'MP4', 'MOV', 'AVI', 'WebM', 'MKV', 'FLV' );
										$selected_formats = $s['supported_formats'];
										foreach ( $all_formats as $format ) :
											$is_selected = in_array( $format, $selected_formats, true );
											?>
											<label class="inline-flex items-center cursor-pointer">
												<input type="checkbox" name="supported_formats[]" value="<?php echo esc_attr( $format ); ?>" <?php checked( $is_selected ); ?> class="hidden peer" />
												<span class="px-3 py-1 bg-purple-100 border border-purple-300 rounded text-purple-900 peer-checked:bg-purple-200 peer-checked:border-purple-400 transition-colors">
													<?php echo esc_html( $format ); ?>
												</span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
								<div class="border-t border-solid border-gray-400 pt-4">
									<div class="mb-3 text-gray-700 text-sm"><?php esc_html_e( 'Video Player Options', 'hotel-chain' ); ?></div>
									<div class="space-y-3">
										<?php
										$this->render_toggle(
											'enable_download',
											$s['enable_download'],
											__( 'Enable Download Option', 'hotel-chain' ),
											__( 'Allow guests to download videos for offline viewing', 'hotel-chain' )
										);
										$this->render_toggle(
											'auto_play_next',
											$s['auto_play_next'],
											__( 'Auto-Play Next Video', 'hotel-chain' ),
											__( 'Automatically play next video in queue', 'hotel-chain' )
										);
										$this->render_toggle(
											'track_analytics',
											$s['track_analytics'],
											__( 'Track Analytics', 'hotel-chain' ),
											__( 'Collect viewing analytics and engagement data', 'hotel-chain' )
										);
										?>
									</div>
								</div>
							</div>
						</div>

						<!-- Email & Notification Settings -->
						<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-400">
							<h3 class="mb-4 pb-3 border-b border-solid border-gray-400 flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail w-5 h-5 text-green-600" aria-hidden="true">
									<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
									<rect x="2" y="4" width="20" height="16" rx="2"></rect>
								</svg>
								<?php esc_html_e( 'Email & Notification Settings', 'hotel-chain' ); ?>
							</h3>
							<div class="space-y-4">
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div class="mb-4">
										<div class="mb-1 text-sm"><?php esc_html_e( 'From Email Address', 'hotel-chain' ); ?></div>
										<input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>" class="w-full rounded p-2 bg-white border border-solid border-gray-400" />
									</div>
									<div class="mb-4">
										<div class="mb-1 text-sm"><?php esc_html_e( 'From Name', 'hotel-chain' ); ?></div>
										<input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>" class="w-full rounded p-2 bg-white border border-solid border-gray-400" />
									</div>
								</div>
								<div class="border-t border-solid border-gray-400 pt-4">
									<div class="mb-3 text-gray-700 text-sm"><?php esc_html_e( 'Email Notifications', 'hotel-chain' ); ?></div>
									<div class="space-y-3">
										<?php
										$this->render_toggle(
											'email_registration_confirmation',
											$s['email_registration_confirmation'],
											__( 'Guest Registration Confirmation', 'hotel-chain' ),
											__( 'Send email when guest creates account', 'hotel-chain' )
										);
										$this->render_toggle(
											'email_access_approved',
											$s['email_access_approved'],
											__( 'Video Access Approved', 'hotel-chain' ),
											__( 'Notify guest when video access is granted', 'hotel-chain' )
										);
										$this->render_toggle(
											'email_expiry_warning',
											$s['email_expiry_warning'],
											__( 'Account Expiry Warning', 'hotel-chain' ),
											__( 'Alert guest before account expires', 'hotel-chain' )
										);
										$this->render_toggle(
											'email_admin_alerts',
											$s['email_admin_alerts'],
											__( 'Hotel Admin Alerts', 'hotel-chain' ),
											__( 'Notify hotel admins of new requests', 'hotel-chain' )
										);
										?>
									</div>
								</div>
							</div>
						</div>

						<!-- Style Settings -->
						<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-400">
							<h3 class="mb-4 pb-3 flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-palette w-5 h-5 text-pink-600" aria-hidden="true">
									<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>
									<circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>
									<circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>
									<circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>
									<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path>
								</svg>
								<?php esc_html_e( 'Style Settings', 'hotel-chain' ); ?>
							</h3>
							<div class="space-y-6">
								<!-- Logo & Favicon -->
								<div class="border-t border-solid border-gray-400 pt-4">
									<h4 class="mb-3 text-gray-700 text-sm border-b border-gray-300 pb-2"><?php esc_html_e( 'Branding', 'hotel-chain' ); ?></h4>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<!-- Logo Uploader -->
										<div>
											<label class="block mb-2 text-gray-700"><?php esc_html_e( 'Logo', 'hotel-chain' ); ?></label>
											<div id="logo-drop-zone" class="border border-solid border-gray-400 border-dashed rounded p-6 text-center bg-gray-50 transition-colors cursor-pointer hover:border-blue-400 hover:bg-blue-50">
												<input type="hidden" name="logo_id" id="logo_id" value="<?php echo esc_attr( (string) $s['logo_id'] ); ?>" />
												<input type="hidden" name="logo_url" id="logo_url" value="<?php echo esc_attr( $s['logo_url'] ); ?>" />
												<input type="file" id="logo-file" accept="image/png,image/jpeg,image/jpg,image/svg+xml" class="hidden" />
												<div id="logo-preview" class="<?php echo ! empty( $s['logo_url'] ) ? '' : 'hidden '; ?>mb-4">
													<img id="logo-img" src="<?php echo esc_url( $s['logo_url'] ); ?>" alt="Logo" class="w-full rounded object-contain mx-auto" style="max-height: 200px;" />
													<button type="button" id="remove-logo-btn" class="mt-2 px-3 py-1 bg-red-200 border-2 border-red-400 rounded text-red-900 text-sm">
														<?php esc_html_e( 'Remove Logo', 'hotel-chain' ); ?>
													</button>
												</div>
												<div id="logo-uploading" class="hidden mb-4">
													<div class="w-16 h-16 mx-auto mb-3">
														<svg class="animate-spin w-full h-full text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
															<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
															<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
														</svg>
													</div>
													<p class="text-blue-600 text-sm font-medium"><?php esc_html_e( 'Uploading logo...', 'hotel-chain' ); ?></p>
													<div class="w-full bg-gray-200 rounded-full h-2 mt-2">
														<div id="logo-progress-bar" class="bg-blue-500 h-2 rounded-full transition-all" style="width: 0%"></div>
													</div>
													<p id="logo-progress-text" class="text-gray-500 text-xs mt-1">0%</p>
												</div>
												<div id="logo-placeholder" class="<?php echo empty( $s['logo_url'] ) ? '' : 'hidden '; ?>mb-4">
													<div class="w-16 h-16 mx-auto bg-gray-200 border border-solid border-gray-400 rounded flex items-center justify-center mb-3">
														<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
															<circle cx="9" cy="9" r="2"></circle>
															<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
														</svg>
													</div>
													<p class="text-gray-700 text-sm font-medium"><?php esc_html_e( 'Drop logo here or click to browse', 'hotel-chain' ); ?></p>
													<p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'PNG, JPG or SVG. Recommended 300x100px', 'hotel-chain' ); ?></p>
												</div>
											</div>
										</div>
										
										<!-- Favicon Uploader -->
										<div>
											<label class="block mb-2 text-gray-700"><?php esc_html_e( 'Favicon', 'hotel-chain' ); ?></label>
											<div id="favicon-drop-zone" class="border border-solid border-gray-400 border-dashed rounded p-6 text-center bg-gray-50 transition-colors cursor-pointer hover:border-blue-400 hover:bg-blue-50">
												<input type="hidden" name="favicon_id" id="favicon_id" value="<?php echo esc_attr( (string) $s['favicon_id'] ); ?>" />
												<input type="hidden" name="favicon_url" id="favicon_url" value="<?php echo esc_attr( $s['favicon_url'] ); ?>" />
												<input type="file" id="favicon-file" accept="image/png,image/jpeg,image/jpg,image/x-icon,image/svg+xml" class="hidden" />
												<div id="favicon-preview" class="<?php echo ! empty( $s['favicon_url'] ) ? '' : 'hidden '; ?>mb-4">
													<img id="favicon-img" src="<?php echo esc_url( $s['favicon_url'] ); ?>" alt="Favicon" class="w-32 h-32 rounded object-contain mx-auto" />
													<button type="button" id="remove-favicon-btn" class="mt-2 px-3 py-1 bg-red-200 border-2 border-red-400 rounded text-red-900 text-sm">
														<?php esc_html_e( 'Remove Favicon', 'hotel-chain' ); ?>
													</button>
												</div>
												<div id="favicon-uploading" class="hidden mb-4">
													<div class="w-16 h-16 mx-auto mb-3">
														<svg class="animate-spin w-full h-full text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
															<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
															<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
														</svg>
													</div>
													<p class="text-blue-600 text-sm font-medium"><?php esc_html_e( 'Uploading favicon...', 'hotel-chain' ); ?></p>
													<div class="w-full bg-gray-200 rounded-full h-2 mt-2">
														<div id="favicon-progress-bar" class="bg-blue-500 h-2 rounded-full transition-all" style="width: 0%"></div>
													</div>
													<p id="favicon-progress-text" class="text-gray-500 text-xs mt-1">0%</p>
												</div>
												<div id="favicon-placeholder" class="<?php echo empty( $s['favicon_url'] ) ? '' : 'hidden '; ?>mb-4">
													<div class="w-16 h-16 mx-auto bg-gray-200 border border-solid border-gray-400 rounded flex items-center justify-center mb-3">
														<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
															<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
															<circle cx="9" cy="9" r="2"></circle>
															<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
														</svg>
													</div>
													<p class="text-gray-700 text-sm font-medium"><?php esc_html_e( 'Drop favicon here or click to browse', 'hotel-chain' ); ?></p>
													<p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'PNG, JPG, ICO or SVG. Recommended 32x32px or 64x64px', 'hotel-chain' ); ?></p>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- Typography -->
								<div class="border-t border-solid border-gray-400 pt-4">
									<h4 class="mb-3 text-gray-700 text-sm pb-2 border-b border-gray-300"><?php esc_html_e( 'Typography', 'hotel-chain' ); ?></h4>
									<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
										<?php
										$google_fonts = $this->get_google_fonts();
										$typography_elements = array(
											'h1' => __( 'H1', 'hotel-chain' ),
											'h2' => __( 'H2', 'hotel-chain' ),
											'h3' => __( 'H3', 'hotel-chain' ),
											'h4' => __( 'H4', 'hotel-chain' ),
											'h5' => __( 'H5', 'hotel-chain' ),
											'h6' => __( 'H6', 'hotel-chain' ),
											'p'  => __( 'P', 'hotel-chain' ),
										);
										foreach ( $typography_elements as $element => $label ) :
											$font_key = 'typography_' . $element . '_font';
											$font_url_key = 'typography_' . $element . '_font_url';
											$font_weight_key = 'typography_' . $element . '_font_weight';
											$current_font = $s[ $font_key ] ?? 'Inter';
											$current_font_url = $s[ $font_url_key ] ?? '';
											$current_font_weight = $s[ $font_weight_key ] ?? ( 'p' === $element ? '400' : '600' );
											$font_weights = array(
												'100' => __( '100', 'hotel-chain' ),
												'200' => __( '200', 'hotel-chain' ),
												'300' => __( '300', 'hotel-chain' ),
												'400' => __( '400', 'hotel-chain' ),
												'500' => __( '500', 'hotel-chain' ),
												'600' => __( '600', 'hotel-chain' ),
												'700' => __( '700', 'hotel-chain' ),
												'800' => __( '800', 'hotel-chain' ),
												'900' => __( '900', 'hotel-chain' ),
											);
											?>
											<div class="border border-solid border-gray-300 rounded p-3 bg-white">
												<div class="mb-2 text-gray-700 text-xs font-semibold"><?php echo esc_html( $label ); ?></div>
												<select name="typography_<?php echo esc_attr( $element ); ?>_font" id="typography_<?php echo esc_attr( $element ); ?>_font" class="typography-font-select w-full border border-solid border-gray-300 rounded p-1.5 bg-white text-sm mb-2">
													<?php
													foreach ( $google_fonts as $font_name => $font_url ) :
														?>
														<option value="<?php echo esc_attr( $font_name ); ?>" data-url="<?php echo esc_attr( $font_url ); ?>" <?php selected( $current_font, $font_name ); ?>>
															<?php echo esc_html( $font_name ); ?>
														</option>
													<?php endforeach; ?>
												</select>
												<input type="hidden" name="typography_<?php echo esc_attr( $element ); ?>_font_url" id="typography_<?php echo esc_attr( $element ); ?>_font_url" value="<?php echo esc_attr( $current_font_url ); ?>" />
												<select name="typography_<?php echo esc_attr( $element ); ?>_font_weight" id="typography_<?php echo esc_attr( $element ); ?>_font_weight" class="typography-weight-select w-full border border-solid border-gray-300 rounded p-1.5 bg-white text-sm">
													<?php
													foreach ( $font_weights as $weight => $weight_label ) :
														?>
														<option value="<?php echo esc_attr( $weight ); ?>" <?php selected( $current_font_weight, $weight ); ?>>
															<?php echo esc_html( $weight_label ); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</div>
										<?php endforeach; ?>
									</div>
								</div>

								<!-- Font Sizes (Responsive) -->
								<div class="pt-4 border-t border-solid border-gray-400">
									<h4 class="mb-3 text-gray-700 text-sm pb-2 border-b border-gray-300"><?php esc_html_e( 'Font Sizes (Responsive)', 'hotel-chain' ); ?></h4>
									<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
										<?php
										$typography_elements = array(
											'h1' => array( 'label' => __( 'H1', 'hotel-chain' ), 'mobile' => array( 'min' => 20, 'max' => 36, 'default' => 28 ), 'tablet' => array( 'min' => 24, 'max' => 40, 'default' => 32 ), 'desktop' => array( 'min' => 28, 'max' => 48, 'default' => 36 ) ),
											'h2' => array( 'label' => __( 'H2', 'hotel-chain' ), 'mobile' => array( 'min' => 18, 'max' => 32, 'default' => 24 ), 'tablet' => array( 'min' => 20, 'max' => 36, 'default' => 28 ), 'desktop' => array( 'min' => 24, 'max' => 40, 'default' => 32 ) ),
											'h3' => array( 'label' => __( 'H3', 'hotel-chain' ), 'mobile' => array( 'min' => 16, 'max' => 28, 'default' => 20 ), 'tablet' => array( 'min' => 18, 'max' => 32, 'default' => 24 ), 'desktop' => array( 'min' => 20, 'max' => 36, 'default' => 28 ) ),
											'h4' => array( 'label' => __( 'H4', 'hotel-chain' ), 'mobile' => array( 'min' => 14, 'max' => 24, 'default' => 18 ), 'tablet' => array( 'min' => 16, 'max' => 28, 'default' => 20 ), 'desktop' => array( 'min' => 18, 'max' => 32, 'default' => 24 ) ),
											'h5' => array( 'label' => __( 'H5', 'hotel-chain' ), 'mobile' => array( 'min' => 12, 'max' => 20, 'default' => 16 ), 'tablet' => array( 'min' => 14, 'max' => 24, 'default' => 18 ), 'desktop' => array( 'min' => 16, 'max' => 28, 'default' => 20 ) ),
											'h6' => array( 'label' => __( 'H6', 'hotel-chain' ), 'mobile' => array( 'min' => 10, 'max' => 18, 'default' => 14 ), 'tablet' => array( 'min' => 12, 'max' => 20, 'default' => 16 ), 'desktop' => array( 'min' => 14, 'max' => 24, 'default' => 18 ) ),
											'p'  => array( 'label' => __( 'P', 'hotel-chain' ), 'mobile' => array( 'min' => 10, 'max' => 18, 'default' => 14 ), 'tablet' => array( 'min' => 12, 'max' => 20, 'default' => 16 ), 'desktop' => array( 'min' => 14, 'max' => 24, 'default' => 18 ) ),
										);
										foreach ( $typography_elements as $element => $config ) :
											$mobile_key  = 'font_size_' . $element . '_mobile';
											$tablet_key  = 'font_size_' . $element . '_tablet';
											$desktop_key = 'font_size_' . $element . '_desktop';
											?>
											<div class="border border-solid border-gray-300 rounded p-3 bg-white">
												<div class="mb-2 text-gray-700 text-xs font-semibold"><?php echo esc_html( $config['label'] ); ?></div>
												<div class="grid grid-cols-3 gap-2">
													<div>
														<div class="mb-1 flex items-center justify-center">
															<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-smartphone text-gray-500" aria-hidden="true" title="<?php esc_attr_e( 'Mobile', 'hotel-chain' ); ?>">
																<rect width="14" height="20" x="5" y="2" rx="2" ry="2"></rect>
																<path d="M12 18h.01"></path>
															</svg>
														</div>
														<div class="flex items-center gap-1">
															<input type="number" name="<?php echo esc_attr( $mobile_key ); ?>" value="<?php echo esc_attr( (string) ( $s[ $mobile_key ] ?? $config['mobile']['default'] ) ); ?>" min="<?php echo esc_attr( (string) $config['mobile']['min'] ); ?>" max="<?php echo esc_attr( (string) $config['mobile']['max'] ); ?>" class="w-full border border-solid border-gray-300 rounded p-1.5 bg-white text-sm" />
															<span class="text-gray-500 text-xs">px</span>
														</div>
													</div>
													<div>
														<div class="mb-1 flex items-center justify-center">
															<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-tablet text-gray-500" aria-hidden="true" title="<?php esc_attr_e( 'Tablet', 'hotel-chain' ); ?>">
																<rect width="18" height="20" x="3" y="4" rx="2" ry="2"></rect>
																<line x1="7" y1="20" x2="17" y2="20"></line>
															</svg>
														</div>
														<div class="flex items-center gap-1">
															<input type="number" name="<?php echo esc_attr( $tablet_key ); ?>" value="<?php echo esc_attr( (string) ( $s[ $tablet_key ] ?? $config['tablet']['default'] ) ); ?>" min="<?php echo esc_attr( (string) $config['tablet']['min'] ); ?>" max="<?php echo esc_attr( (string) $config['tablet']['max'] ); ?>" class="w-full border border-solid border-gray-300 rounded p-1.5 bg-white text-sm" />
															<span class="text-gray-500 text-xs">px</span>
														</div>
													</div>
													<div>
														<div class="mb-1 flex items-center justify-center">
															<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-monitor text-gray-500" aria-hidden="true" title="<?php esc_attr_e( 'Desktop', 'hotel-chain' ); ?>">
																<rect width="20" height="14" x="2" y="3" rx="2"></rect>
																<line x1="8" y1="21" x2="16" y2="21"></line>
																<line x1="12" y1="17" x2="12" y2="21"></line>
															</svg>
														</div>
														<div class="flex items-center gap-1">
															<input type="number" name="<?php echo esc_attr( $desktop_key ); ?>" value="<?php echo esc_attr( (string) ( $s[ $desktop_key ] ?? $config['desktop']['default'] ) ); ?>" min="<?php echo esc_attr( (string) $config['desktop']['min'] ); ?>" max="<?php echo esc_attr( (string) $config['desktop']['max'] ); ?>" class="w-full border border-solid border-gray-300 rounded p-1.5 bg-white text-sm" />
															<span class="text-gray-500 text-xs">px</span>
														</div>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								</div>

								<!-- Button Colors -->
								<div class="border-t-2 border-gray-300 pt-4 hidden">
									<h4 class="mb-3 text-gray-700 text-sm border-b border-gray-300 pb-2"><?php esc_html_e( 'Button Colors', 'hotel-chain' ); ?></h4>
									<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
										<div>
											<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Primary', 'hotel-chain' ); ?></div>
											<div class="flex items-center gap-2">
												<input type="color" name="button_primary_color" value="<?php echo esc_attr( $s['button_primary_color'] ); ?>" class="w-16 h-10 border-2 border-gray-300 rounded cursor-pointer color-picker" />
												<input type="text" value="<?php echo esc_attr( $s['button_primary_color'] ); ?>" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white font-mono text-sm color-text" />
											</div>
										</div>
										<div>
											<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Secondary', 'hotel-chain' ); ?></div>
											<div class="flex items-center gap-2">
												<input type="color" name="button_secondary_color" value="<?php echo esc_attr( $s['button_secondary_color'] ); ?>" class="w-16 h-10 border-2 border-gray-300 rounded cursor-pointer color-picker" />
												<input type="text" value="<?php echo esc_attr( $s['button_secondary_color'] ); ?>" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white font-mono text-sm color-text" />
											</div>
										</div>
										<div>
											<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Success', 'hotel-chain' ); ?></div>
											<div class="flex items-center gap-2">
												<input type="color" name="button_success_color" value="<?php echo esc_attr( $s['button_success_color'] ); ?>" class="w-16 h-10 border-2 border-gray-300 rounded cursor-pointer color-picker" />
												<input type="text" value="<?php echo esc_attr( $s['button_success_color'] ); ?>" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white font-mono text-sm color-text" />
											</div>
										</div>
										<div>
											<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Info', 'hotel-chain' ); ?></div>
											<div class="flex items-center gap-2">
												<input type="color" name="button_info_color" value="<?php echo esc_attr( $s['button_info_color'] ); ?>" class="w-16 h-10 border-2 border-gray-300 rounded cursor-pointer color-picker" />
												<input type="text" value="<?php echo esc_attr( $s['button_info_color'] ); ?>" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white font-mono text-sm color-text" />
											</div>
										</div>
										<div>
											<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Warning', 'hotel-chain' ); ?></div>
											<div class="flex items-center gap-2">
												<input type="color" name="button_warning_color" value="<?php echo esc_attr( $s['button_warning_color'] ); ?>" class="w-16 h-10 border-2 border-gray-300 rounded cursor-pointer color-picker" />
												<input type="text" value="<?php echo esc_attr( $s['button_warning_color'] ); ?>" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white font-mono text-sm color-text" />
											</div>
										</div>
										<div>
											<div class="mb-2 text-gray-700 text-sm"><?php esc_html_e( 'Danger', 'hotel-chain' ); ?></div>
											<div class="flex items-center gap-2">
												<input type="color" name="button_danger_color" value="<?php echo esc_attr( $s['button_danger_color'] ); ?>" class="w-16 h-10 border-2 border-gray-300 rounded cursor-pointer color-picker" />
												<input type="text" value="<?php echo esc_attr( $s['button_danger_color'] ); ?>" class="flex-1 border-2 border-gray-300 rounded p-2 bg-white font-mono text-sm color-text" />
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Save Actions -->
						<div class="bg-gray-50 rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
							<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
								<div class="text-gray-600 text-sm"><?php esc_html_e( 'Changes will affect all hotels and guests system-wide', 'hotel-chain' ); ?></div>
								<div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
									<?php
									$reset_url = wp_nonce_url(
										add_query_arg(
											array(
												'action' => 'hotel_chain_reset_system_settings',
											),
											admin_url( 'admin-post.php' )
										),
										'hotel_chain_reset_system_settings'
									);
									?>
									<a href="<?php echo esc_url( $reset_url ); ?>" class="px-6 py-3 bg-gray-200 border-2 border-gray-400 rounded text-gray-900 text-center" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all settings to defaults?', 'hotel-chain' ) ); ?>');">
										<?php esc_html_e( 'Reset to Defaults', 'hotel-chain' ); ?>
									</a>
									<button type="submit" class="px-6 py-3 bg-green-200 border-2 border-green-400 rounded text-green-900 flex items-center justify-center gap-2 w-full sm:w-auto">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save w-4 h-4" aria-hidden="true">
											<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
											<path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
											<path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
										</svg>
										<?php esc_html_e( 'Save All Settings', 'hotel-chain' ); ?>
									</button>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<script>
		(function() {
			document.addEventListener('DOMContentLoaded', function() {
				// Typography font selection - update hidden URL field when font is selected
				const typographySelects = document.querySelectorAll('.typography-font-select');
				typographySelects.forEach(function(select) {
					// Initialize URL on page load
					const selectedOption = select.options[select.selectedIndex];
					const fontUrl = selectedOption.getAttribute('data-url');
					const hiddenInput = document.getElementById(select.id.replace('_font', '_font_url'));
					if (hiddenInput && fontUrl && !hiddenInput.value) {
						hiddenInput.value = fontUrl;
					}
					
					// Update URL when font changes
					select.addEventListener('change', function() {
						const selectedOption = this.options[this.selectedIndex];
						const fontUrl = selectedOption.getAttribute('data-url');
						const hiddenInput = document.getElementById(this.id.replace('_font', '_font_url'));
						if (hiddenInput && fontUrl) {
							hiddenInput.value = fontUrl;
						}
					});
				});

				// Update toggle switches when checkboxes change
				const toggleInputs = document.querySelectorAll('.toggle-switch-input');
				toggleInputs.forEach(function(input) {
					updateToggleSwitch(input);
					input.addEventListener('change', function() {
						updateToggleSwitch(this);
					});
				});
				
				function updateToggleSwitch(checkbox) {
					const toggleSwitch = checkbox.nextElementSibling;
					if (toggleSwitch && toggleSwitch.classList.contains('toggle-switch')) {
						if (checkbox.checked) {
							toggleSwitch.classList.remove('toggle-switch-off');
							toggleSwitch.classList.add('toggle-switch-on');
						} else {
							toggleSwitch.classList.remove('toggle-switch-on');
							toggleSwitch.classList.add('toggle-switch-off');
						}
					}
				}

				// Logo uploader with drag & drop and progress bar
				const logoDropZone = document.getElementById('logo-drop-zone');
				const logoFileInput = document.getElementById('logo-file');
				const logoId = document.getElementById('logo_id');
				const logoUrl = document.getElementById('logo_url');
				const logoPreview = document.getElementById('logo-preview');
				const logoPlaceholder = document.getElementById('logo-placeholder');
				const logoUploading = document.getElementById('logo-uploading');
				const logoImg = document.getElementById('logo-img');
				const logoProgressBar = document.getElementById('logo-progress-bar');
				const logoProgressText = document.getElementById('logo-progress-text');
				const removeLogoBtn = document.getElementById('remove-logo-btn');
				const uploadNonce = '<?php echo esc_js( wp_create_nonce( 'system_settings_upload' ) ); ?>';
				const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

				// Upload file via AJAX
				function uploadLogoFile(file) {
					if (!file || !file.type.startsWith('image/')) {
						alert('<?php echo esc_js( __( 'Please select a valid image file', 'hotel-chain' ) ); ?>');
						return;
					}
					if (file.size > 5 * 1024 * 1024) {
						alert('<?php echo esc_js( __( 'Logo file must be less than 5MB', 'hotel-chain' ) ); ?>');
						return;
					}

					logoPlaceholder.classList.add('hidden');
					logoPreview.classList.add('hidden');
					logoUploading.classList.remove('hidden');
					logoProgressBar.style.width = '0%';
					logoProgressText.textContent = '0%';

					const formData = new FormData();
					formData.append('action', 'system_settings_upload_media');
					formData.append('nonce', uploadNonce);
					formData.append('file', file);
					formData.append('type', 'image');

					const xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxUrl, true);

					xhr.upload.onprogress = function(e) {
						if (e.lengthComputable) {
							const percent = Math.round((e.loaded / e.total) * 100);
							logoProgressBar.style.width = percent + '%';
							logoProgressText.textContent = percent + '%';
						}
					};

					xhr.onload = function() {
						if (xhr.status === 200) {
							try {
								const response = JSON.parse(xhr.responseText);
								if (response.success) {
									if (logoId) logoId.value = response.data.attachment_id;
									if (logoUrl) logoUrl.value = response.data.url;
									if (logoImg) logoImg.src = response.data.url;
									logoUploading.classList.add('hidden');
									logoPreview.classList.remove('hidden');
								} else {
									alert(response.data || '<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
									logoUploading.classList.add('hidden');
									logoPlaceholder.classList.remove('hidden');
								}
							} catch (e) {
								alert('<?php echo esc_js( __( 'Invalid response', 'hotel-chain' ) ); ?>');
								logoUploading.classList.add('hidden');
								logoPlaceholder.classList.remove('hidden');
							}
						} else {
							alert('<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
							logoUploading.classList.add('hidden');
							logoPlaceholder.classList.remove('hidden');
						}
					};

					xhr.onerror = function() {
						alert('<?php echo esc_js( __( 'Network error', 'hotel-chain' ) ); ?>');
						logoUploading.classList.add('hidden');
						logoPlaceholder.classList.remove('hidden');
					};

					xhr.send(formData);
				}

				// Click to browse
				if (logoDropZone) {
					logoDropZone.addEventListener('click', function() {
						logoFileInput.click();
					});
				}

				// File input change
				if (logoFileInput) {
					logoFileInput.addEventListener('change', function(e) {
						if (e.target.files && e.target.files[0]) {
							uploadLogoFile(e.target.files[0]);
						}
					});
				}

				// Drag and drop
				if (logoDropZone) {
					logoDropZone.addEventListener('dragover', function(e) {
						e.preventDefault();
						e.stopPropagation();
						logoDropZone.style.borderColor = 'rgb(59, 130, 246)';
						logoDropZone.style.backgroundColor = 'rgb(239, 246, 255)';
					});

					logoDropZone.addEventListener('dragleave', function(e) {
						e.preventDefault();
						e.stopPropagation();
						logoDropZone.style.borderColor = 'rgb(196, 196, 196)';
						logoDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
					});

					logoDropZone.addEventListener('drop', function(e) {
						e.preventDefault();
						e.stopPropagation();
						logoDropZone.style.borderColor = 'rgb(196, 196, 196)';
						logoDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
						if (e.dataTransfer.files && e.dataTransfer.files[0]) {
							uploadLogoFile(e.dataTransfer.files[0]);
						}
					});
				}

				// Remove logo
				if (removeLogoBtn) {
					removeLogoBtn.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();
						if (logoId && logoId.value) {
							const formData = new FormData();
							formData.append('action', 'system_settings_delete_media');
							formData.append('nonce', uploadNonce);
							formData.append('attachment_id', logoId.value);
							fetch(ajaxUrl, { method: 'POST', body: formData })
								.then(response => response.json())
								.then(data => {
									if (!data.success) {
										console.error('Delete failed:', data.data);
									}
								})
								.catch(error => console.error('Error:', error));
						}
						if (logoId) logoId.value = '';
						if (logoUrl) logoUrl.value = '';
						if (logoImg) logoImg.removeAttribute('src');
						logoPreview.classList.add('hidden');
						logoPlaceholder.classList.remove('hidden');
					});
				}

				// Favicon uploader with drag & drop and progress bar
				const faviconDropZone = document.getElementById('favicon-drop-zone');
				const faviconFileInput = document.getElementById('favicon-file');
				const faviconId = document.getElementById('favicon_id');
				const faviconUrl = document.getElementById('favicon_url');
				const faviconPreview = document.getElementById('favicon-preview');
				const faviconPlaceholder = document.getElementById('favicon-placeholder');
				const faviconUploading = document.getElementById('favicon-uploading');
				const faviconImg = document.getElementById('favicon-img');
				const faviconProgressBar = document.getElementById('favicon-progress-bar');
				const faviconProgressText = document.getElementById('favicon-progress-text');
				const removeFaviconBtn = document.getElementById('remove-favicon-btn');

				// Upload file via AJAX
				function uploadFaviconFile(file) {
					if (!file || !file.type.startsWith('image/')) {
						alert('<?php echo esc_js( __( 'Please select a valid image file', 'hotel-chain' ) ); ?>');
						return;
					}
					if (file.size > 2 * 1024 * 1024) {
						alert('<?php echo esc_js( __( 'Favicon file must be less than 2MB', 'hotel-chain' ) ); ?>');
						return;
					}

					faviconPlaceholder.classList.add('hidden');
					faviconPreview.classList.add('hidden');
					faviconUploading.classList.remove('hidden');
					faviconProgressBar.style.width = '0%';
					faviconProgressText.textContent = '0%';

					const formData = new FormData();
					formData.append('action', 'system_settings_upload_media');
					formData.append('nonce', uploadNonce);
					formData.append('file', file);
					formData.append('type', 'image');

					const xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxUrl, true);

					xhr.upload.onprogress = function(e) {
						if (e.lengthComputable) {
							const percent = Math.round((e.loaded / e.total) * 100);
							faviconProgressBar.style.width = percent + '%';
							faviconProgressText.textContent = percent + '%';
						}
					};

					xhr.onload = function() {
						if (xhr.status === 200) {
							try {
								const response = JSON.parse(xhr.responseText);
								if (response.success) {
									if (faviconId) faviconId.value = response.data.attachment_id;
									if (faviconUrl) faviconUrl.value = response.data.url;
									if (faviconImg) faviconImg.src = response.data.url;
									faviconUploading.classList.add('hidden');
									faviconPreview.classList.remove('hidden');
								} else {
									alert(response.data || '<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
									faviconUploading.classList.add('hidden');
									faviconPlaceholder.classList.remove('hidden');
								}
							} catch (e) {
								alert('<?php echo esc_js( __( 'Invalid response', 'hotel-chain' ) ); ?>');
								faviconUploading.classList.add('hidden');
								faviconPlaceholder.classList.remove('hidden');
							}
						} else {
							alert('<?php echo esc_js( __( 'Upload failed', 'hotel-chain' ) ); ?>');
							faviconUploading.classList.add('hidden');
							faviconPlaceholder.classList.remove('hidden');
						}
					};

					xhr.onerror = function() {
						alert('<?php echo esc_js( __( 'Network error', 'hotel-chain' ) ); ?>');
						faviconUploading.classList.add('hidden');
						faviconPlaceholder.classList.remove('hidden');
					};

					xhr.send(formData);
				}

				// Click to browse
				if (faviconDropZone) {
					faviconDropZone.addEventListener('click', function() {
						faviconFileInput.click();
					});
				}

				// File input change
				if (faviconFileInput) {
					faviconFileInput.addEventListener('change', function(e) {
						if (e.target.files && e.target.files[0]) {
							uploadFaviconFile(e.target.files[0]);
						}
					});
				}

				// Drag and drop
				if (faviconDropZone) {
					faviconDropZone.addEventListener('dragover', function(e) {
						e.preventDefault();
						e.stopPropagation();
						faviconDropZone.style.borderColor = 'rgb(59, 130, 246)';
						faviconDropZone.style.backgroundColor = 'rgb(239, 246, 255)';
					});

					faviconDropZone.addEventListener('dragleave', function(e) {
						e.preventDefault();
						e.stopPropagation();
						faviconDropZone.style.borderColor = 'rgb(196, 196, 196)';
						faviconDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
					});

					faviconDropZone.addEventListener('drop', function(e) {
						e.preventDefault();
						e.stopPropagation();
						faviconDropZone.style.borderColor = 'rgb(196, 196, 196)';
						faviconDropZone.style.backgroundColor = 'rgb(249, 250, 251)';
						if (e.dataTransfer.files && e.dataTransfer.files[0]) {
							uploadFaviconFile(e.dataTransfer.files[0]);
						}
					});
				}

				// Remove favicon
				if (removeFaviconBtn) {
					removeFaviconBtn.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();
						if (faviconId && faviconId.value) {
							const formData = new FormData();
							formData.append('action', 'system_settings_delete_media');
							formData.append('nonce', uploadNonce);
							formData.append('attachment_id', faviconId.value);
							fetch(ajaxUrl, { method: 'POST', body: formData })
								.then(response => response.json())
								.then(data => {
									if (!data.success) {
										console.error('Delete failed:', data.data);
									}
								})
								.catch(error => console.error('Error:', error));
						}
						if (faviconId) faviconId.value = '';
						if (faviconUrl) faviconUrl.value = '';
						if (faviconImg) faviconImg.removeAttribute('src');
						faviconPreview.classList.add('hidden');
						faviconPlaceholder.classList.remove('hidden');
					});
				}

				// Color picker synchronization
				const colorPickers = document.querySelectorAll('.color-picker');
				colorPickers.forEach(function(colorPicker) {
					const textInput = colorPicker.nextElementSibling;
					if (textInput && textInput.classList.contains('color-text')) {
						// Update text input when color picker changes
						colorPicker.addEventListener('input', function() {
							textInput.value = this.value;
						});
						// Update color picker when text input changes
						textInput.addEventListener('input', function() {
							const hexPattern = /^#[0-9A-F]{6}$/i;
							if (hexPattern.test(this.value)) {
								colorPicker.value = this.value;
							}
						});
						// Sync on blur to handle paste
						textInput.addEventListener('blur', function() {
							const hexPattern = /^#[0-9A-F]{6}$/i;
							if (hexPattern.test(this.value)) {
								colorPicker.value = this.value;
							} else if (this.value && !this.value.startsWith('#')) {
								// Auto-add # if missing
								this.value = '#' + this.value;
								if (hexPattern.test(this.value)) {
									colorPicker.value = this.value;
								}
							}
						});
					}
				});

				// Sync all color text inputs to color pickers before form submission
				const form = document.querySelector('form[method="post"]');
				if (form) {
					form.addEventListener('submit', function() {
						colorPickers.forEach(function(colorPicker) {
							const textInput = colorPicker.nextElementSibling;
							if (textInput && textInput.classList.contains('color-text')) {
								const hexPattern = /^#[0-9A-F]{6}$/i;
								let value = textInput.value.trim();
								if (value && !value.startsWith('#')) {
									value = '#' + value;
								}
								if (hexPattern.test(value)) {
									colorPicker.value = value;
								}
							}
						});
					});
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle media upload via AJAX.
	 *
	 * @return void
	 */
	public function handle_media_upload(): void {
		check_ajax_referer( 'system_settings_upload', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded', 'hotel-chain' ) );
		}

		$file = $_FILES['file'];
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		// Validate file type - only images for logo and favicon.
		$allowed_image = array( 'image/jpeg', 'image/png', 'image/jpg', 'image/svg+xml', 'image/x-icon' );

		if ( 'image' !== $type || ! in_array( $file['type'], $allowed_image, true ) ) {
			wp_send_json_error( __( 'Invalid image format. Please upload PNG, JPG, SVG or ICO.', 'hotel-chain' ) );
		}

		// Use WordPress media handling.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( $upload['error'] );
		}

		// Create attachment.
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		// Generate metadata.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => $upload['url'],
			)
		);
	}

	/**
	 * Handle media delete via AJAX.
	 *
	 * @return void
	 */
	public function handle_media_delete(): void {
		check_ajax_referer( 'system_settings_upload', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'hotel-chain' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Invalid attachment ID', 'hotel-chain' ) );
		}

		// Delete from WordPress (and AWS if using offload plugin).
		$deleted = wp_delete_attachment( $attachment_id, true );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'File deleted', 'hotel-chain' ) ) );
		} else {
			wp_send_json_error( __( 'Failed to delete file', 'hotel-chain' ) );
		}
	}
}



