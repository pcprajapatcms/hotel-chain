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
	const DB_VERSION = '1.2.1';

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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE {$assignments_table} 
				ADD COLUMN status_by_hotel varchar(20) DEFAULT 'active' COMMENT 'active, inactive - managed by hotel' AFTER status,
				ADD KEY status_by_hotel (status_by_hotel)"
			);
		}
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

		$table_names = Schema::get_table_names();
		$existing_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}hotel_chain_%'" );

		foreach ( $table_names as $table_name ) {
			if ( ! in_array( $table_name, $existing_tables, true ) ) {
				return false;
			}
		}

		return true;
	}
}
