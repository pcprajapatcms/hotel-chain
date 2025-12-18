<?php
/**
 * Admin database tools page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Database\Schema;

/**
 * Simple DB tools page to run migrations manually.
 */
class DatabaseToolsPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_migrate_video_table', array( $this, 'handle_migrate_video_table' ) );
	}

	/**
	 * Register a submenu page under Hotel Accounts.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'hotel-chain-accounts',
			__( 'Database Tools', 'hotel-chain' ),
			__( 'DB Tools', 'hotel-chain' ),
			'manage_options',
			'hotel-db-tools',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$video_migrated = isset( $_GET['video_migrated'] ) ? (bool) absint( $_GET['video_migrated'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$columns_added = isset( $_GET['columns_added'] ) ? absint( $_GET['columns_added'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_msg = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Show current table status.
		global $wpdb;
		$table_name = Schema::get_table_name( 'video_metadata' );
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
		$existing_columns = array();
		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}" );
		}

		$expected_columns = array( 'id', 'video_id', 'video_file_id', 'slug', 'title', 'description', 'category', 'tags', 'thumbnail_id', 'thumbnail_url', 'duration_seconds', 'duration_label', 'file_size', 'file_format', 'resolution_width', 'resolution_height', 'default_language', 'total_views', 'total_completions', 'avg_completion_rate', 'created_at', 'updated_at' );
		$missing_columns = array_diff( $expected_columns, $existing_columns );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Database Tools', 'hotel-chain' ); ?></h1>
			<p><?php esc_html_e( 'Use this tool to add missing columns to the video metadata table.', 'hotel-chain' ); ?></p>

			<?php if ( $video_migrated ) : ?>
				<?php if ( ! empty( $error_msg ) ) : ?>
					<div class="notice notice-error is-dismissible">
						<p><strong><?php esc_html_e( 'Migration Error:', 'hotel-chain' ); ?></strong> <?php echo esc_html( $error_msg ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							if ( $columns_added > 0 ) {
								printf(
									/* translators: %d: number of columns */
									esc_html__( 'Video metadata table migration completed. %d column(s) were added.', 'hotel-chain' ),
									$columns_added
								);
							} else {
								esc_html_e( 'Video metadata table migration completed. All columns already exist.', 'hotel-chain' );
							}
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! $table_exists ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Warning: The video_metadata table does not exist. It will be created when you run the migration.', 'hotel-chain' ); ?></p>
				</div>
			<?php elseif ( ! empty( $missing_columns ) ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Missing columns:', 'hotel-chain' ); ?></strong>
						<?php echo esc_html( implode( ', ', $missing_columns ) ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'All expected columns are present in the table.', 'hotel-chain' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hotel_chain_migrate_video_table' ); ?>
				<input type="hidden" name="action" value="hotel_chain_migrate_video_table" />
				<?php submit_button( __( 'Run Video Table Migration', 'hotel-chain' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the migrate video table action.
	 *
	 * @return void
	 */
	public function handle_migrate_video_table(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_migrate_video_table' );

		global $wpdb;
		$table_name = Schema::get_table_name( 'video_metadata' );
		$added_count = 0;
		$errors = array();

		// Check if table exists first.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( ! $table_exists ) {
			// Table doesn't exist, create it using dbDelta.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$sql = Schema::get_video_metadata_table_sql();
			dbDelta( $sql );
			// If table was created, all columns were added.
			if ( empty( $wpdb->last_error ) ) {
				$added_count = 7; // slug, title, description, category, tags, thumbnail_id, thumbnail_url
			} else {
				$errors[] = 'Failed to create table: ' . $wpdb->last_error;
			}
		} else {
			// Table exists - add missing columns explicitly.
			$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$columns_to_add = array(
				'slug'          => array(
					'type'    => 'varchar(255) NOT NULL',
					'after'   => 'video_file_id',
				),
				'title'         => array(
					'type'    => 'varchar(255) NOT NULL',
					'after'   => 'slug',
				),
				'description'   => array(
					'type'    => 'longtext NULL',
					'after'   => 'title',
				),
				'category'      => array(
					'type'    => 'varchar(255) DEFAULT NULL',
					'after'   => 'description',
				),
				'tags'          => array(
					'type'    => 'text NULL',
					'after'   => 'category',
				),
				'thumbnail_id'  => array(
					'type'    => "bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Attachment ID for thumbnail'",
					'after'   => 'tags',
				),
				'thumbnail_url' => array(
					'type'    => 'varchar(500) DEFAULT NULL',
					'after'   => 'thumbnail_id',
				),
			);

			$added_count = 0;
			$errors = array();
			foreach ( $columns_to_add as $column_name => $column_def ) {
				if ( ! in_array( $column_name, $existing_columns, true ) ) {
					// Build ALTER TABLE statement - use AFTER only if the 'after' column exists.
					$after_clause = '';
					if ( ! empty( $column_def['after'] ) && in_array( $column_def['after'], $existing_columns, true ) ) {
						$after_clause = ' AFTER ' . $column_def['after'];
					}

					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$alter_sql = "ALTER TABLE {$table_name} ADD COLUMN {$column_name} {$column_def['type']}{$after_clause}";
					$result = $wpdb->query( $alter_sql );

					if ( false !== $result && empty( $wpdb->last_error ) ) {
						$added_count++;
						// Update existing_columns array for next iteration so subsequent columns can use AFTER.
						$existing_columns[] = $column_name;
					} else {
						$error_msg = $wpdb->last_error ? $wpdb->last_error : 'Unknown error';
						error_log( "Hotel Chain: Failed to add column {$column_name} - " . $error_msg );
						$errors[] = sprintf( 'Failed to add column "%s": %s', $column_name, $error_msg );
						// Clear the error for next iteration.
						$wpdb->last_error = '';
					}
				}
			}

			// Add indexes if they don't exist.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$indexes_result = $wpdb->get_results( "SHOW INDEX FROM {$table_name}", ARRAY_A );
			$existing_index_names = array();
			if ( ! empty( $indexes_result ) ) {
				foreach ( $indexes_result as $index_row ) {
					$existing_index_names[] = $index_row['Key_name'];
				}
			}
			$existing_index_names = array_unique( $existing_index_names );

			if ( ! in_array( 'slug', $existing_index_names, true ) && in_array( 'slug', $existing_columns, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$table_name} ADD UNIQUE INDEX slug (slug)" );
			}

			if ( ! in_array( 'title', $existing_index_names, true ) && in_array( 'title', $existing_columns, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$table_name} ADD INDEX title (title)" );
			}

			if ( ! in_array( 'category', $existing_index_names, true ) && in_array( 'category', $existing_columns, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$table_name} ADD INDEX category (category)" );
			}

			if ( ! empty( $wpdb->last_error ) ) {
				error_log( 'Hotel Chain: Video metadata migration error - ' . $wpdb->last_error );
			}
		}

		$redirect_args = array(
			'page'           => 'hotel-db-tools',
			'video_migrated' => 1,
			'columns_added'  => $added_count,
		);

		if ( ! empty( $errors ) ) {
			$redirect_args['error'] = implode( '; ', $errors );
		}

		wp_safe_redirect(
			add_query_arg( $redirect_args, admin_url( 'admin.php' ) )
		);
		exit;
	}
}

