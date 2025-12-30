<?php
/**
 * Style Settings utility class.
 *
 * @package HotelChain
 */

namespace HotelChain\Support;

use HotelChain\Database\Schema;

/**
 * Style Settings utility class.
 */
class StyleSettings {
	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static ?array $settings = null;

	/**
	 * Get all style settings.
	 *
	 * @return array Style settings array.
	 */
	public static function get_all(): array {
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		global $wpdb;
		$table_name = Schema::get_table_name( 'system_settings' );

		$row = $wpdb->get_row( "SELECT style_settings FROM {$table_name} LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row || empty( $row['style_settings'] ) ) {
			self::$settings = self::get_defaults();
			return self::$settings;
		}

		$settings = json_decode( $row['style_settings'], true );
		if ( ! is_array( $settings ) ) {
			self::$settings = self::get_defaults();
			return self::$settings;
		}

		self::$settings = wp_parse_args( $settings, self::get_defaults() );
		return self::$settings;
	}

	/**
	 * Get a specific style setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default = null ) {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get typography font name.
	 *
	 * @param string $element Typography element (h1, h2, h3, h4, h5, h6, p).
	 * @return string Font name.
	 */
	public static function get_typography_font( string $element ): string {
		$key = 'typography_' . $element . '_font';
		$defaults = array(
			'h1' => 'Playfair Display',
			'h2' => 'Playfair Display',
			'h3' => 'Playfair Display',
			'h4' => 'Playfair Display',
			'h5' => 'Playfair Display',
			'h6' => 'Playfair Display',
			'p'  => 'Inter',
		);
		return self::get( $key, $defaults[ $element ] ?? 'Inter' );
	}

	/**
	 * Get typography font URL.
	 *
	 * @param string $element Typography element (h1, h2, h3, h4, h5, h6, p).
	 * @return string Font URL.
	 */
	public static function get_typography_font_url( string $element ): string {
		$key = 'typography_' . $element . '_font_url';
		return self::get( $key, '' );
	}

	/**
	 * Get typography font weight.
	 *
	 * @param string $element Typography element (h1, h2, h3, h4, h5, h6, p).
	 * @return string Font weight.
	 */
	public static function get_typography_font_weight( string $element ): string {
		$key = 'typography_' . $element . '_font_weight';
		$defaults = array(
			'h1' => '600',
			'h2' => '600',
			'h3' => '600',
			'h4' => '600',
			'h5' => '600',
			'h6' => '600',
			'p'  => '400',
		);
		return self::get( $key, $defaults[ $element ] ?? '400' );
	}

	/**
	 * Get logo URL.
	 *
	 * @return string Logo URL.
	 */
	public static function get_logo_url(): string {
		return self::get( 'logo_url', '' );
	}

	/**
	 * Get favicon URL.
	 *
	 * @return string Favicon URL.
	 */
	public static function get_favicon_url(): string {
		return self::get( 'favicon_url', '' );
	}

	/**
	 * Get font size (for backward compatibility, returns desktop size).
	 *
	 * @param string $type Font size type (h1, h2, h3, h4, h5, h6, p).
	 * @return int Font size in pixels.
	 */
	public static function get_font_size( string $type ): int {
		// For backward compatibility, return desktop size.
		return self::get_responsive_font_size( $type, 'desktop' );
	}

	/**
	 * Get responsive font size.
	 *
	 * @param string $element Typography element (h1, h2, h3, h4, h5, h6, p).
	 * @param string $breakpoint Breakpoint (mobile, tablet, desktop).
	 * @return int Font size in pixels.
	 */
	public static function get_responsive_font_size( string $element, string $breakpoint ): int {
		$key = 'font_size_' . $element . '_' . $breakpoint;
		$defaults = array(
			'h1_mobile'   => 28,
			'h1_tablet'   => 32,
			'h1_desktop'  => 36,
			'h2_mobile'   => 24,
			'h2_tablet'   => 28,
			'h2_desktop'  => 32,
			'h3_mobile'   => 20,
			'h3_tablet'   => 24,
			'h3_desktop'  => 28,
			'h4_mobile'   => 18,
			'h4_tablet'   => 20,
			'h4_desktop'  => 24,
			'h5_mobile'   => 16,
			'h5_tablet'   => 18,
			'h5_desktop'  => 20,
			'h6_mobile'   => 14,
			'h6_tablet'   => 16,
			'h6_desktop'  => 18,
			'p_mobile'    => 14,
			'p_tablet'    => 16,
			'p_desktop'   => 18,
		);
		$default_key = $element . '_' . $breakpoint;
		return (int) self::get( $key, $defaults[ $default_key ] ?? 16 );
	}

	/**
	 * Get button color.
	 *
	 * @param string $type Button type (primary, secondary, success, info, warning, danger).
	 * @return string Color hex code.
	 */
	public static function get_button_color( string $type ): string {
		$key = 'button_' . $type . '_color';
		return self::get( $key, self::get_defaults()[ $key ] ?? '#1f88ff' );
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	private static function get_defaults(): array {
		return array(
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

