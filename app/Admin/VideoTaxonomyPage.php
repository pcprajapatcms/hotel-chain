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
			__( 'Video Categories & Tags', 'hotel-chain' ),
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

		$updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap w-8/12 mx-auto">
			<h1 class="text-2xl font-bold mb-2"><?php esc_html_e( 'Video Categories & Tags', 'hotel-chain' ); ?></h1>
			<p class="text-slate-600 mb-6 text-lg border-b border-solid border-gray-300 pb-3"><?php esc_html_e( 'Manage reusable categories and tags for training videos. Drag to reorder.', 'hotel-chain' ); ?></p>

			<?php if ( $updated ) : ?>
				<div class="bg-green-50 border border-solid border-green-300 rounded p-3 mb-4 text-sm text-green-900">
					<?php esc_html_e( 'Taxonomy saved successfully.', 'hotel-chain' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="taxonomy-form">
				<?php wp_nonce_field( 'hotel_chain_save_video_taxonomy' ); ?>
				<input type="hidden" name="action" value="hotel_chain_save_video_taxonomy" />

				<!-- Categories Section -->
				<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
					<div class="mb-4 pb-3 border-b border-solid border-gray-300 flex items-center justify-between">
						<h3 class="text-lg font-semibold"><?php esc_html_e( 'Video Categories', 'hotel-chain' ); ?></h3>
						<button type="button" id="add-category-btn" class="px-4 py-2 bg-green-200 border border-solid border-green-400 rounded text-green-900 text-sm hover:bg-green-300">
							+ <?php esc_html_e( 'Add Category', 'hotel-chain' ); ?>
						</button>
					</div>
					<div id="categories-list" class="space-y-2">
						<?php if ( empty( $categories ) ) : ?>
							<p class="text-gray-500 text-sm py-4 text-center" id="no-categories-msg"><?php esc_html_e( 'No categories yet. Click "Add Category" to create one.', 'hotel-chain' ); ?></p>
						<?php else : ?>
							<?php foreach ( $categories as $index => $category ) : ?>
								<div class="taxonomy-item flex items-center gap-2 bg-gray-50 border border-solid border-gray-200 rounded p-2" draggable="true">
									<span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 px-1">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
									</span>
									<input type="text" name="categories[]" value="<?php echo esc_attr( $category ); ?>" class="flex-1 px-3 py-2 border border-solid border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="<?php esc_attr_e( 'Category name', 'hotel-chain' ); ?>" />
									<button type="button" class="remove-item-btn px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded" title="<?php esc_attr_e( 'Remove', 'hotel-chain' ); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
									</button>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Tags Section -->
				<div class="bg-white rounded p-4 mb-6 border border-solid border-gray-300">
					<div class="mb-4 pb-3 border-b border-solid border-gray-300 flex items-center justify-between">
						<h3 class="text-lg font-semibold"><?php esc_html_e( 'Suggested Tags', 'hotel-chain' ); ?></h3>
						<button type="button" id="add-tag-btn" class="px-4 py-2 bg-blue-200 border border-solid border-blue-400 rounded text-blue-900 text-sm hover:bg-blue-300">
							+ <?php esc_html_e( 'Add Tag', 'hotel-chain' ); ?>
						</button>
					</div>
					<div id="tags-list" class="space-y-2">
						<?php if ( empty( $tags ) ) : ?>
							<p class="text-gray-500 text-sm py-4 text-center" id="no-tags-msg"><?php esc_html_e( 'No tags yet. Click "Add Tag" to create one.', 'hotel-chain' ); ?></p>
						<?php else : ?>
							<?php foreach ( $tags as $index => $tag ) : ?>
								<div class="taxonomy-item flex items-center gap-2 bg-gray-50 border border-solid border-gray-200 rounded p-2" draggable="true">
									<span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 px-1">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
									</span>
									<input type="text" name="tags[]" value="<?php echo esc_attr( $tag ); ?>" class="flex-1 px-3 py-2 border border-solid border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="<?php esc_attr_e( 'Tag name', 'hotel-chain' ); ?>" />
									<button type="button" class="remove-item-btn px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded" title="<?php esc_attr_e( 'Remove', 'hotel-chain' ); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
									</button>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<button type="submit" class="px-6 py-3 bg-green-200 border-2 border-green-400 rounded text-green-900 font-medium hover:bg-green-300">
					<?php esc_html_e( 'Save Changes', 'hotel-chain' ); ?>
				</button>
			</form>
		</div>

		<style>
			.taxonomy-item.dragging { opacity: 0.5; background: #e0e7ff; }
			.taxonomy-item.drag-over { border-color: #3b82f6; border-style: dashed; }
		</style>

		<script>
		(function() {
			// Template for new items
			function createItem(inputName, placeholder) {
				const div = document.createElement('div');
				div.className = 'taxonomy-item flex items-center gap-2 bg-gray-50 border border-solid border-gray-200 rounded p-2';
				div.draggable = true;
				div.innerHTML = `
					<span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 px-1">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
					</span>
					<input type="text" name="${inputName}[]" value="" class="flex-1 px-3 py-2 border border-solid border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="${placeholder}" />
					<button type="button" class="remove-item-btn px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded" title="<?php echo esc_js( __( 'Remove', 'hotel-chain' ) ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
					</button>
				`;
				return div;
			}

			// Add category
			document.getElementById('add-category-btn').addEventListener('click', function() {
				const list = document.getElementById('categories-list');
				const noMsg = document.getElementById('no-categories-msg');
				if (noMsg) noMsg.remove();
				const item = createItem('categories', '<?php echo esc_js( __( 'Category name', 'hotel-chain' ) ); ?>');
				list.appendChild(item);
				item.querySelector('input').focus();
				initDragDrop(list);
			});

			// Add tag
			document.getElementById('add-tag-btn').addEventListener('click', function() {
				const list = document.getElementById('tags-list');
				const noMsg = document.getElementById('no-tags-msg');
				if (noMsg) noMsg.remove();
				const item = createItem('tags', '<?php echo esc_js( __( 'Tag name', 'hotel-chain' ) ); ?>');
				list.appendChild(item);
				item.querySelector('input').focus();
				initDragDrop(list);
			});

			// Remove item (event delegation)
			document.addEventListener('click', function(e) {
				if (e.target.closest('.remove-item-btn')) {
					const item = e.target.closest('.taxonomy-item');
					if (item && confirm('<?php echo esc_js( __( 'Remove this item?', 'hotel-chain' ) ); ?>')) {
						item.remove();
					}
				}
			});

			// Drag and drop
			function initDragDrop(container) {
				const items = container.querySelectorAll('.taxonomy-item');
				let draggedItem = null;

				items.forEach(item => {
					item.addEventListener('dragstart', function(e) {
						draggedItem = this;
						this.classList.add('dragging');
						e.dataTransfer.effectAllowed = 'move';
					});

					item.addEventListener('dragend', function() {
						this.classList.remove('dragging');
						container.querySelectorAll('.taxonomy-item').forEach(i => i.classList.remove('drag-over'));
						draggedItem = null;
					});

					item.addEventListener('dragover', function(e) {
						e.preventDefault();
						e.dataTransfer.dropEffect = 'move';
						if (this !== draggedItem) {
							this.classList.add('drag-over');
						}
					});

					item.addEventListener('dragleave', function() {
						this.classList.remove('drag-over');
					});

					item.addEventListener('drop', function(e) {
						e.preventDefault();
						this.classList.remove('drag-over');
						if (draggedItem && this !== draggedItem) {
							const allItems = [...container.querySelectorAll('.taxonomy-item')];
							const draggedIdx = allItems.indexOf(draggedItem);
							const targetIdx = allItems.indexOf(this);
							if (draggedIdx < targetIdx) {
								this.parentNode.insertBefore(draggedItem, this.nextSibling);
							} else {
								this.parentNode.insertBefore(draggedItem, this);
							}
						}
					});
				});
			}

			// Initialize drag/drop on page load
			initDragDrop(document.getElementById('categories-list'));
			initDragDrop(document.getElementById('tags-list'));
		})();
		</script>
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

		// Categories: array from repeater fields.
		$raw_categories = isset( $_POST['categories'] ) ? (array) wp_unslash( $_POST['categories'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$categories     = array();
		foreach ( $raw_categories as $cat ) {
			$cat = trim( wp_strip_all_tags( (string) $cat ) );
			if ( '' !== $cat ) {
				$categories[] = $cat;
			}
		}

		// Tags: array from repeater fields.
		$raw_tags = isset( $_POST['tags'] ) ? (array) wp_unslash( $_POST['tags'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tags     = array();
		foreach ( $raw_tags as $tag ) {
			$tag = trim( wp_strip_all_tags( (string) $tag ) );
			if ( '' !== $tag ) {
				$tags[] = $tag;
			}
		}

		update_option( 'hotel_chain_video_categories', $categories );
		update_option( 'hotel_chain_video_tags', $tags );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'hotel-video-taxonomy',
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
