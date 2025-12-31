<?php
/**
 * Account & Access Settings utility class.
 *
 * @package HotelChain
 */

namespace HotelChain\Support;

use HotelChain\Database\Schema;

/**
 * Account & Access Settings utility class.
 */
class AccountSettings {
	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static ?array $settings = null;

	/**
	 * Get all account settings.
	 *
	 * @return array Account settings array.
	 */
	public static function get_all(): array {
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		global $wpdb;
		$table_name = Schema::get_table_name( 'system_settings' );

		// Check if table exists first.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $table_exists ) {
			self::$settings = self::get_defaults();
			return self::$settings;
		}

		$row = $wpdb->get_row( "SELECT account_settings FROM {$table_name} LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row || empty( $row['account_settings'] ) ) {
			self::$settings = self::get_defaults();
			return self::$settings;
		}

		$settings = json_decode( $row['account_settings'], true );
		if ( ! is_array( $settings ) || json_last_error() !== JSON_ERROR_NONE ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging for debugging.
			error_log( 'Hotel Chain: Failed to decode account_settings JSON. Error: ' . json_last_error_msg() );
			self::$settings = self::get_defaults();
			return self::$settings;
		}

		// Merge with defaults to ensure all keys exist.
		self::$settings = wp_parse_args( $settings, self::get_defaults() );
		return self::$settings;
	}

	/**
	 * Get a specific account setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value if not found.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default_value = null ) {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Get default guest access duration (in days).
	 *
	 * @return int Default duration in days.
	 */
	public static function get_default_guest_duration(): int {
		return (int) self::get( 'default_guest_duration', 30 );
	}

	/**
	 * Get expiry warning period (in days).
	 *
	 * @return int Warning period in days.
	 */
	public static function get_expiry_warning_period(): int {
		return (int) self::get( 'expiry_warning_period', 3 );
	}

	/**
	 * Check if guest registration is allowed.
	 *
	 * @return bool True if allowed.
	 */
	public static function is_guest_registration_allowed(): bool {
		return (bool) self::get( 'allow_guest_registration', true );
	}

	/**
	 * Check if email verification is required.
	 *
	 * @return bool True if required.
	 */
	public static function is_email_verification_required(): bool {
		return (bool) self::get( 'require_email_verification', true );
	}

	/**
	 * Check if video requests should be auto-approved.
	 *
	 * @return bool True if auto-approved.
	 */
	public static function is_auto_approve_requests(): bool {
		return (bool) self::get( 'auto_approve_requests', false );
	}

	/**
	 * Check if reactivation is allowed.
	 *
	 * @return bool True if allowed.
	 */
	public static function is_reactivation_allowed(): bool {
		return (bool) self::get( 'allow_reactivation', true );
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	private static function get_defaults(): array {
		return array(
			'default_guest_duration'     => 30,
			'expiry_warning_period'      => 3,
			'allow_guest_registration'   => true,
			'require_email_verification' => true,
			'auto_approve_requests'      => false,
			'allow_reactivation'         => true,
		);
	}

	/**
	 * Clear cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$settings = null;
	}
}
