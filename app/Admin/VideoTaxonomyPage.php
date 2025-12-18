<?php
/**
 * Admin page to manage video categories and tags.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Video taxonomy settings (categories & tags stored in options).
 */
class VideoTaxonomyPage implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hotel_chain_save_video_taxonomy', array( $this, 'handle_save' ) );
	}

	/**
	 * Register submenu page under Hotel Accounts.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'hotel-chain-accounts',
			__( 'Video Categories & Tags', 'hotel-chain' ),
			__( 'Video Taxonomy', 'hotel-chain' ),
			'manage_options',
			'hotel-video-taxonomy',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the taxonomy settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$categories = get_option( 'hotel_chain_video_categories', array() );
		$tags       = get_option( 'hotel_chain_video_tags', array() );

		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		$categories_text = implode( "\n", array_map( 'strval', $categories ) );
		$tags_text       = implode( ", ", array_map( 'strval', $tags ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Video Categories & Tags', 'hotel-chain' ); ?></h1>
			<p><?php esc_html_e( 'Manage reusable categories and tags for training videos. These values are used in the Upload Videos form.', 'hotel-chain' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hotel_chain_save_video_taxonomy' ); ?>
				<input type="hidden" name="action" value="hotel_chain_save_video_taxonomy" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="hotel_chain_video_categories"><?php esc_html_e( 'Video Categories', 'hotel-chain' ); ?></label>
						</th>
						<td>
							<textarea
								id="hotel_chain_video_categories"
								name="hotel_chain_video_categories"
								rows="8"
								class="large-text"
							><?php echo esc_textarea( $categories_text ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'One category per line (e.g., Onboarding, Safety, HR Policies).', 'hotel-chain' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hotel_chain_video_tags"><?php esc_html_e( 'Suggested Tags', 'hotel-chain' ); ?></label>
						</th>
						<td>
							<textarea
								id="hotel_chain_video_tags"
								name="hotel_chain_video_tags"
								rows="4"
								class="large-text"
							><?php echo esc_textarea( $tags_text ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Comma-separated list of suggested tags (e.g., welcome, safety, housekeeping).', 'hotel-chain' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Video Taxonomy', 'hotel-chain' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle save request.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'hotel-chain' ) );
		}

		check_admin_referer( 'hotel_chain_save_video_taxonomy' );

		$raw_categories = isset( $_POST['hotel_chain_video_categories'] ) ? wp_unslash( $_POST['hotel_chain_video_categories'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_tags       = isset( $_POST['hotel_chain_video_tags'] ) ? wp_unslash( $_POST['hotel_chain_video_tags'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Categories: one per line, but also allow comma-separated on a line.
		$categories_lines = preg_split( '/\r\n|\r|\n/', (string) $raw_categories ) ?: array();
		$categories       = array();
		foreach ( $categories_lines as $line ) {
			// Split by comma inside each line so \"Cat A, Cat B\" becomes two categories.
			$parts = explode( ',', $line );
			foreach ( $parts as $part ) {
				$part = trim( wp_strip_all_tags( $part ) );
				if ( '' !== $part ) {
					$categories[] = $part;
				}
			}
		}
		$categories = array_values( array_unique( $categories ) );

		// Tags: comma separated.
		$tags_raw = explode( ',', (string) $raw_tags );
		$tags     = array();
		foreach ( $tags_raw as $tag ) {
			$tag = trim( wp_strip_all_tags( $tag ) );
			if ( '' !== $tag ) {
				$tags[] = $tag;
			}
		}
		$tags = array_values( array_unique( $tags ) );

		update_option( 'hotel_chain_video_categories', $categories );
		update_option( 'hotel_chain_video_tags', $tags );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'hotel-video-taxonomy',
					'updated'   => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
