<?php
/**
 * Database schema definitions.
 *
 * @package HotelChain
 */

namespace HotelChain\Database;

/**
 * Database schema definitions.
 */
class Schema {
	/**
	 * Get table name with WordPress prefix.
	 *
	 * @param string $table Table name without prefix.
	 * @return string Full table name.
	 */
	public static function get_table_name( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . 'hotel_chain_' . $table;
	}

	/**
	 * Get all table names.
	 *
	 * @return array Array of table names.
	 */
	public static function get_table_names(): array {
		return array(
			'hotels'                  => self::get_table_name( 'hotels' ),
			'hotel_video_assignments' => self::get_table_name( 'hotel_video_assignments' ),
			'video_metadata'          => self::get_table_name( 'video_metadata' ),
			'guests'                  => self::get_table_name( 'guests' ),
			'video_views'             => self::get_table_name( 'video_views' ),
		);
	}

	/**
	 * Get SQL for creating hotels table.
	 *
	 * @return string SQL statement.
	 */
	public static function get_hotels_table_sql(): string {
		global $wpdb;
		$table_name      = self::get_table_name( 'hotels' );
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			hotel_code varchar(50) NOT NULL,
			hotel_name varchar(255) NOT NULL,
			hotel_slug varchar(255) NOT NULL,
			contact_email varchar(255) NOT NULL,
			contact_phone varchar(50) DEFAULT NULL,
			address varchar(255) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			country varchar(100) DEFAULT NULL,
			website varchar(500) DEFAULT NULL,
			welcome_section longtext DEFAULT NULL COMMENT 'JSON: welcome video, message, steps',
			access_duration int(11) DEFAULT 0 COMMENT 'Duration in days',
			license_start datetime DEFAULT NULL,
			license_end datetime DEFAULT NULL,
			registration_url varchar(500) DEFAULT NULL,
			landing_url varchar(500) DEFAULT NULL,
			status varchar(20) DEFAULT 'active' COMMENT 'active, inactive, suspended',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY hotel_code (hotel_code),
			UNIQUE KEY hotel_slug (hotel_slug),
			UNIQUE KEY user_id (user_id),
			KEY status (status),
			KEY hotel_name (hotel_name),
			KEY city (city),
			KEY country (country)
		) {$charset_collate};";
	}

	/**
	 * Get SQL for creating hotel_video_assignments table.
	 *
	 * @return string SQL statement.
	 */
	public static function get_hotel_video_assignments_table_sql(): string {
		global $wpdb;
		$table_name      = self::get_table_name( 'hotel_video_assignments' );
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hotel_id bigint(20) UNSIGNED NOT NULL,
			video_id bigint(20) UNSIGNED NOT NULL COMMENT 'WordPress post ID',
			assigned_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID who assigned',
			assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'active' COMMENT 'pending, active, inactive',
			status_by_hotel varchar(20) DEFAULT 'active' COMMENT 'active, inactive - managed by hotel',
			PRIMARY KEY (id),
			UNIQUE KEY hotel_video (hotel_id, video_id),
			KEY hotel_id (hotel_id),
			KEY video_id (video_id),
			KEY status (status),
			KEY status_by_hotel (status_by_hotel)
		) {$charset_collate};";
	}

	/**
	 * Get SQL for creating video_metadata table.
	 *
	 * @return string SQL statement.
	 */
	public static function get_video_metadata_table_sql(): string {
		global $wpdb;
		$table_name      = self::get_table_name( 'video_metadata' );
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			video_id bigint(20) UNSIGNED NOT NULL COMMENT 'Internal video ID',
			video_file_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for video file',
			slug varchar(255) NOT NULL,
			title varchar(255) NOT NULL,
			description longtext NULL,
			practice_tip longtext NULL COMMENT 'Practice tip for this video',
			category varchar(255) DEFAULT NULL,
			tags text NULL,
			thumbnail_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for thumbnail',
			thumbnail_url varchar(500) DEFAULT NULL,
			duration_seconds int(11) DEFAULT NULL,
			duration_label varchar(50) DEFAULT NULL,
			file_size bigint(20) DEFAULT NULL COMMENT 'Size in bytes',
			file_format varchar(20) DEFAULT NULL,
			resolution_width int(11) DEFAULT NULL,
			resolution_height int(11) DEFAULT NULL,
			default_language varchar(50) DEFAULT NULL,
			total_views int(11) DEFAULT 0,
			total_completions int(11) DEFAULT 0,
			avg_completion_rate decimal(5,2) DEFAULT 0.00,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY video_id (video_id),
			UNIQUE KEY slug (slug),
			KEY video_file_id (video_file_id),
			KEY title (title),
			KEY category (category),
			KEY default_language (default_language)
		) {$charset_collate};";
	}

	/**
	 * Get SQL for creating guests table.
	 *
	 * @return string SQL statement.
	 */
	public static function get_guests_table_sql(): string {
		global $wpdb;
		$table_name      = self::get_table_name( 'guests' );
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hotel_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress user ID if registered',
			guest_code varchar(50) DEFAULT NULL,
			first_name varchar(100) DEFAULT NULL,
			last_name varchar(100) DEFAULT NULL,
			email varchar(255) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			registration_code varchar(50) DEFAULT NULL COMMENT 'Hotel registration code used',
			verification_token varchar(64) DEFAULT NULL,
			email_verified_at datetime DEFAULT NULL,
			access_start datetime DEFAULT NULL,
			access_end datetime DEFAULT NULL,
			status varchar(20) DEFAULT 'pending' COMMENT 'pending, active, expired, revoked',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY hotel_id (hotel_id),
			KEY user_id (user_id),
			KEY guest_code (guest_code),
			KEY registration_code (registration_code),
			KEY verification_token (verification_token),
			KEY status (status),
			KEY email (email)
		) {$charset_collate};";
	}

	/**
	 * Get SQL for creating video_views table.
	 *
	 * @return string SQL statement.
	 */
	public static function get_video_views_table_sql(): string {
		global $wpdb;
		$table_name      = self::get_table_name( 'video_views' );
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			video_id bigint(20) UNSIGNED NOT NULL COMMENT 'WordPress post ID',
			hotel_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress user ID',
			viewed_at datetime DEFAULT CURRENT_TIMESTAMP,
			view_duration int(11) DEFAULT NULL COMMENT 'Seconds watched',
			completion_percentage decimal(5,2) DEFAULT NULL,
			completed tinyint(1) DEFAULT 0,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY video_id (video_id),
			KEY hotel_id (hotel_id),
			KEY user_id (user_id),
			KEY viewed_at (viewed_at),
			KEY completed (completed)
		) {$charset_collate};";
	}

	/**
	 * Get all table creation SQL statements.
	 *
	 * @return array Array of SQL statements.
	 */
	public static function get_all_table_sql(): array {
		return array(
			'hotels'                  => self::get_hotels_table_sql(),
			'hotel_video_assignments' => self::get_hotel_video_assignments_table_sql(),
			'video_metadata'          => self::get_video_metadata_table_sql(),
			'guests'                  => self::get_guests_table_sql(),
			'video_views'             => self::get_video_views_table_sql(),
		);
	}
}
