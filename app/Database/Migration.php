<?php
/**
 * Database migration service.
 *
 * @package HotelChain
 */

namespace HotelChain\Database;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Database migration service.
 */
class Migration implements ServiceProviderInterface {
	/**
	 * Database version option name.
	 */
	const DB_VERSION_OPTION = 'hotel_chain_db_version';

	/**
	 * Current database version.
	 */
	const DB_VERSION = '1.4.0';

	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_switch_theme', array( $this, 'maybe_create_tables' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	/**
	 * Check if tables need to be created or updated.
	 *
	 * @return void
	 */
	public function maybe_create_tables(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			$this->create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * Create all custom tables.
	 *
	 * @return void
	 */
	public function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_statements = Schema::get_all_table_sql();

		foreach ( $sql_statements as $table_key => $sql ) {
			dbDelta( $sql );
		}

		// Run migrations for existing tables.
		$this->run_migrations();

		// Log if there were any errors.
		if ( ! empty( $wpdb->last_error ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging for debugging database issues.
			error_log( 'Hotel Chain: Database table creation error - ' . $wpdb->last_error );
		}
	}

	/**
	 * Run migrations for existing tables.
	 *
	 * @return void
	 */
	private function run_migrations(): void {
		global $wpdb;

		$assignments_table = Schema::get_table_name( 'hotel_video_assignments' );

		// Add status_by_hotel column if it doesn't exist.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$assignments_table} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'status_by_hotel'
			)
		);

		if ( empty( $column_exists ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized in ALTER TABLE statements.
			$wpdb->query(
				"ALTER TABLE {$assignments_table} 
				ADD COLUMN status_by_hotel varchar(20) DEFAULT 'active' COMMENT 'active, inactive - managed by hotel' AFTER status,
				ADD KEY status_by_hotel (status_by_hotel)"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Add verification columns to guests table.
		$guests_table = Schema::get_table_name( 'guests' );

		// Add verification_token column.
		$token_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$guests_table} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'verification_token'
			)
		);

		if ( empty( $token_exists ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterized in ALTER TABLE statements.
			$wpdb->query(
				"ALTER TABLE {$guests_table} 
				ADD COLUMN verification_token varchar(64) DEFAULT NULL AFTER registration_code,
				ADD COLUMN email_verified_at datetime DEFAULT NULL AFTER verification_token,
				ADD KEY verification_token (verification_token)"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Add website column to hotels table.
		$hotels_table = Schema::get_table_name( 'hotels' );

		$website_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$hotels_table} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'website'
			)
		);

		if ( empty( $website_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$hotels_table} ADD COLUMN website varchar(500) DEFAULT NULL AFTER country" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		// Add welcome_section column to hotels table.
		$welcome_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$hotels_table} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'welcome_section'
			)
		);

		if ( empty( $welcome_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$hotels_table} ADD COLUMN welcome_section longtext DEFAULT NULL COMMENT 'JSON: welcome video, message, steps' AFTER website" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		// Add practice_tip column to video_metadata table.
		$video_metadata_table = Schema::get_table_name( 'video_metadata' );

		$practice_tip_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$video_metadata_table} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'practice_tip'
			)
		);

		if ( empty( $practice_tip_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$video_metadata_table} ADD COLUMN practice_tip longtext DEFAULT NULL COMMENT 'Practice tip for this video' AFTER description" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		// Migrate video taxonomy from options to custom table.
		$this->migrate_video_taxonomy();
	}

	/**
	 * Migrate video categories and tags from WordPress options to custom table.
	 *
	 * @return void
	 */
	private function migrate_video_taxonomy(): void {
		global $wpdb;
		$table = Schema::get_table_name( 'video_taxonomy' );

		// Check if table exists and has data.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $table_exists ) {
			return;
		}

		// Check if migration already done.
		$has_data = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $has_data > 0 ) {
			return; // Already migrated.
		}

		// Get existing categories and tags from options.
		$categories = get_option( 'hotel_chain_video_categories', array() );
		$tags       = get_option( 'hotel_chain_video_tags', array() );

		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		// Normalize categories (split comma-separated values).
		$normalized_categories = array();
		foreach ( $categories as $line ) {
			$parts = explode( ',', (string) $line );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$normalized_categories[] = $part;
				}
			}
		}
		$normalized_categories = array_values( array_unique( $normalized_categories ) );

		// Normalize tags (split comma-separated values).
		$normalized_tags = array();
		foreach ( $tags as $line ) {
			$parts = explode( ',', (string) $line );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$normalized_tags[] = $part;
				}
			}
		}
		$normalized_tags = array_values( array_unique( $normalized_tags ) );

		// Insert categories.
		$sort_order = 0;
		foreach ( $normalized_categories as $category ) {
			$wpdb->insert(
				$table,
				array(
					'name'       => sanitize_text_field( $category ),
					'type'       => 'category',
					'sort_order' => $sort_order++,
				),
				array( '%s', '%s', '%d' )
			);
		}

		// Insert tags.
		$sort_order = 0;
		foreach ( $normalized_tags as $tag ) {
			$wpdb->insert(
				$table,
				array(
					'name'       => sanitize_text_field( $tag ),
					'type'       => 'tag',
					'sort_order' => $sort_order++,
				),
				array( '%s', '%s', '%d' )
			);
		}

		// Optionally delete old options after migration (commented out for safety).
		// delete_option( 'hotel_chain_video_categories' );
		// delete_option( 'hotel_chain_video_tags' );
	}

	/**
	 * Drop all custom tables (for uninstall).
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		global $wpdb;

		$table_names = Schema::get_table_names();

		foreach ( $table_names as $table_name ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Check if tables exist.
	 *
	 * @return bool True if all tables exist.
	 */
	public function tables_exist(): bool {
		global $wpdb;

		$table_names     = Schema::get_table_names();
		$existing_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}hotel_chain_%'" );

		foreach ( $table_names as $table_name ) {
			if ( ! in_array( $table_name, $existing_tables, true ) ) {
				return false;
			}
		}

		return true;
	}
}
