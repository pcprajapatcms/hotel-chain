<?php
/**
 * Database Tools admin page.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Database\Schema;
use HotelChain\Database\Migration;
use HotelChain\Support\StyleSettings;

/**
 * Database Tools admin page.
 */
class DatabaseToolsPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_recreate_tables', array( $this, 'handle_recreate_tables' ) );
	}

	/**
	 * Register main menu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Database Tools', 'hotel-chain' ),
			__( 'Database Tools', 'hotel-chain' ),
			'manage_options',
			'hotel-database-tools',
			array( $this, 'render_page' ),
			'dashicons-database',
			9
		);
	}

	/**
	 * Render the database tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$table_names = Schema::get_table_names();
		$tables_info = array();

		foreach ( $table_names as $key => $table_name ) {
			$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row_count  = 0;
			$table_size = '0 KB';

			if ( $exists ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

				// Get table size.
				$size_query = $wpdb->prepare(
					'SELECT ROUND(((data_length + index_length) / 1024), 2) AS size_kb 
					FROM information_schema.TABLES 
					WHERE table_schema = %s AND table_name = %s',
					DB_NAME,
					$table_name
				);
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is already prepared
				$size_result = $wpdb->get_var( $size_query );
				if ( $size_result ) {
					$table_size = number_format( (float) $size_result, 2 ) . ' KB';
					if ( (float) $size_result > 1024 ) {
						$table_size = number_format( (float) $size_result / 1024, 2 ) . ' MB';
					}
				}
			}

			$tables_info[ $key ] = array(
				'name'      => $table_name,
				'exists'    => (bool) $exists,
				'row_count' => $row_count,
				'size'      => $table_size,
			);
		}

		$db_version      = get_option( Migration::DB_VERSION_OPTION, '0.0.0' );
		$current_version = Migration::DB_VERSION;

		$recreated = isset( $_GET['recreated'] ) ? absint( $_GET['recreated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$logo_url  = StyleSettings::get_logo_url();
		?>
		<div class="w-12/12 md:w-10/12 xl:w-8/12 mx-auto">
			<div class="flex items-start gap-4 mb-6 pb-3 border-b border-solid border-gray-300">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<div class="flex-shrink-0">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'hotel-chain' ); ?>" class="h-12 md:h-16 w-auto object-contain" />
					</div>
				<?php endif; ?>
				<div class="flex-1">
					<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Database Tools', 'hotel-chain' ); ?></h1>
					<p class="text-slate-600 text-lg">
						<?php esc_html_e( 'View and manage custom database tables for the Hotel Chain theme.', 'hotel-chain' ); ?>
					</p>
				</div>
			</div>

			<?php if ( $recreated ) : ?>
				<div class="bg-green-50 border border-solid border-green-300 rounded p-3 mb-4 text-sm text-green-900">
					<?php esc_html_e( 'Database tables have been recreated successfully.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<!-- Database Version Info -->
			<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
				<h2 class="text-lg font-semibold mb-3"><?php esc_html_e( 'Database Version', 'hotel-chain' ); ?></h2>
				<div class="grid grid-cols-2 gap-4">
					<div>
						<p class="text-sm text-gray-600"><?php esc_html_e( 'Installed Version:', 'hotel-chain' ); ?></p>
						<p class="text-lg font-mono"><?php echo esc_html( $db_version ); ?></p>
					</div>
					<div>
						<p class="text-sm text-gray-600"><?php esc_html_e( 'Current Version:', 'hotel-chain' ); ?></p>
						<p class="text-lg font-mono"><?php echo esc_html( $current_version ); ?></p>
					</div>
				</div>
				<?php if ( version_compare( $db_version, $current_version, '<' ) ) : ?>
					<div class="mt-3 p-3 bg-yellow-50 border border-solid border-yellow-300 rounded text-yellow-900 text-sm">
						<?php esc_html_e( 'Database schema is outdated. Consider recreating tables to update the schema.', 'hotel-chain' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Tables Status -->
			<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
				<h2 class="text-lg font-semibold mb-3"><?php esc_html_e( 'Custom Tables Status', 'hotel-chain' ); ?></h2>
				<table class="w-full border-collapse">
					<thead>
						<tr class="border-b border-solid border-gray-200">
							<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Table Name', 'hotel-chain' ); ?></th>
							<th class="text-left py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></th>
							<th class="text-right py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Rows', 'hotel-chain' ); ?></th>
							<th class="text-right py-3 px-2 text-sm font-semibold text-gray-700"><?php esc_html_e( 'Size', 'hotel-chain' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tables_info as $key => $info ) : ?>
							<tr class="border-b border-solid border-gray-100">
								<td class="py-3 px-2">
									<code class="text-sm"><?php echo esc_html( $info['name'] ); ?></code>
								</td>
								<td class="py-3 px-2">
									<?php if ( $info['exists'] ) : ?>
										<span class="inline-block px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
											<?php esc_html_e( 'Exists', 'hotel-chain' ); ?>
										</span>
									<?php else : ?>
										<span class="inline-block px-2 py-1 bg-red-100 text-red-800 text-xs rounded">
											<?php esc_html_e( 'Missing', 'hotel-chain' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="py-3 px-2 text-right">
									<?php echo esc_html( number_format_i18n( $info['row_count'] ) ); ?>
								</td>
								<td class="py-3 px-2 text-right">
									<?php echo esc_html( $info['size'] ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Actions -->
			<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
				<h2 class="text-lg font-semibold mb-3"><?php esc_html_e( 'Actions', 'hotel-chain' ); ?></h2>
				<div class="space-y-3">
					<div class="p-4 bg-yellow-50 border border-solid border-yellow-300 rounded">
						<h3 class="font-semibold text-yellow-900 mb-2"><?php esc_html_e( 'Recreate Tables', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-yellow-800 mb-3">
							<?php esc_html_e( 'This will recreate all custom tables. Existing data will be preserved if the schema is compatible. Use with caution!', 'hotel-chain' ); ?>
						</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to recreate all database tables? This action cannot be undone.', 'hotel-chain' ) ); ?>');">
							<?php wp_nonce_field( 'hotel_chain_recreate_tables' ); ?>
							<input type="hidden" name="action" value="hotel_chain_recreate_tables" />
							<button type="submit" class="px-4 py-2 bg-yellow-200 border border-solid border-yellow-400 rounded text-yellow-900 hover:bg-yellow-300">
								<?php esc_html_e( 'Recreate All Tables', 'hotel-chain' ); ?>
							</button>
						</form>
					</div>

					<div class="p-4 bg-blue-50 border border-solid border-blue-300 rounded">
						<h3 class="font-semibold text-blue-900 mb-2"><?php esc_html_e( 'Direct Database Access', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-blue-800 mb-2">
							<?php esc_html_e( 'You can also manage these tables directly using:', 'hotel-chain' ); ?>
						</p>
						<ul class="list-disc list-inside text-sm text-blue-800 space-y-1">
							<li><?php esc_html_e( 'phpMyAdmin (if available)', 'hotel-chain' ); ?></li>
							<li><?php esc_html_e( 'MySQL Workbench or other database tools', 'hotel-chain' ); ?></li>
							<li><?php esc_html_e( 'Command line MySQL client', 'hotel-chain' ); ?></li>
						</ul>
						<p class="text-xs text-blue-700 mt-2">
							<?php esc_html_e( 'Database:', 'hotel-chain' ); ?> <code><?php echo esc_html( DB_NAME ); ?></code>
						</p>
					</div>
				</div>
			</div>

			<!-- Table Information -->
			<div class="bg-white rounded p-4 border border-solid border-gray-300">
				<h2 class="text-lg font-semibold mb-3"><?php esc_html_e( 'Table Information', 'hotel-chain' ); ?></h2>
				<div class="space-y-4">
					<div>
						<h3 class="font-semibold mb-2"><?php esc_html_e( '1. Hotels Table', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-gray-600">
							<?php esc_html_e( 'Stores hotel account information, contact details, license dates, and registration URLs.', 'hotel-chain' ); ?>
						</p>
					</div>
					<div>
						<h3 class="font-semibold mb-2"><?php esc_html_e( '2. Video Metadata Table', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-gray-600">
							<?php esc_html_e( 'Stores video information including title, description, category, tags, duration, and analytics.', 'hotel-chain' ); ?>
						</p>
					</div>
					<div>
						<h3 class="font-semibold mb-2"><?php esc_html_e( '3. Hotel Video Assignments Table', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-gray-600">
							<?php esc_html_e( 'Links videos to hotels with assignment status and hotel-controlled visibility settings.', 'hotel-chain' ); ?>
						</p>
					</div>
					<div>
						<h3 class="font-semibold mb-2"><?php esc_html_e( '4. Guests Table', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-gray-600">
							<?php esc_html_e( 'Stores guest registration information, access periods, and expiration status.', 'hotel-chain' ); ?>
						</p>
					</div>
					<div>
						<h3 class="font-semibold mb-2"><?php esc_html_e( '5. Video Views Table', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-gray-600">
							<?php esc_html_e( 'Tracks video viewing statistics including watch duration, completion percentage, and view history.', 'hotel-chain' ); ?>
						</p>
					</div>
					<div>
						<h3 class="font-semibold mb-2"><?php esc_html_e( '6. Video Taxonomy Table', 'hotel-chain' ); ?></h3>
						<p class="text-sm text-gray-600">
							<?php esc_html_e( 'Stores video categories and tags with sort order for managing taxonomy items.', 'hotel-chain' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle recreate tables request.
	 *
	 * @return void
	 */
	public function handle_recreate_tables(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_recreate_tables' );

		$migration = new Migration();
		$migration->create_tables();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'hotel-database-tools',
					'recreated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

