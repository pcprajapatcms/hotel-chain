<?php
/**
 * Admin page to manage video categories and tags.
 *
 * @package HotelChain
 */

namespace HotelChain\Admin;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\VideoTaxonomyRepository;
use HotelChain\Support\StyleSettings;

/**
 * Video taxonomy settings (categories & tags stored in custom table).
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
	 * Register main menu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Video Categories & Tags', 'hotel-chain' ),
			__( 'Video Categories & Tags', 'hotel-chain' ),
			'manage_options',
			'hotel-video-taxonomy',
			array( $this, 'render_page' ),
			'dashicons-tag',
			7
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

		$taxonomy_repo = new VideoTaxonomyRepository();
		$categories    = $taxonomy_repo->get_categories();
		$tags          = $taxonomy_repo->get_tags();

		$updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$logo_url = StyleSettings::get_logo_url();
		?>
		<div class="flex-1 overflow-auto p-4 lg:p-8 lg:px-0">
			<div class="w-12/12 md:w-10/12 mx-auto p-0">
				<div class="flex items-center gap-4 mb-6 pb-3 border-b border-solid border-gray-300">
					<?php if ( ! empty( $logo_url ) ) : ?>
						<div class="flex-shrink-0">
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'hotel-chain' ); ?>" class="h-12 md:h-16 w-auto object-contain" />
						</div>
					<?php endif; ?>
					<div class="flex-1">
						<h1><?php esc_html_e( 'Video Categories & Tags', 'hotel-chain' ); ?></h1>
						<p class="text-slate-600"><?php esc_html_e( 'Manage reusable categories and tags for training videos. Drag to reorder.', 'hotel-chain' ); ?></p>
					</div>
				</div>

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
								<?php foreach ( $categories as $category ) : ?>
									<div class="taxonomy-item flex items-center gap-2 bg-gray-50 border border-solid border-gray-200 rounded p-2" draggable="true" data-id="<?php echo esc_attr( $category->id ); ?>">
										<input type="hidden" name="category_ids[]" value="<?php echo esc_attr( $category->id ); ?>" />
										<span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 px-1">
											<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
										</span>
										<input type="text" name="categories[]" value="<?php echo esc_attr( $category->name ); ?>" class="flex-1 px-3 py-2 border border-solid border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="<?php esc_attr_e( 'Category name', 'hotel-chain' ); ?>" />
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
								<?php foreach ( $tags as $tag ) : ?>
									<div class="taxonomy-item flex items-center gap-2 bg-gray-50 border border-solid border-gray-200 rounded p-2" draggable="true" data-id="<?php echo esc_attr( $tag->id ); ?>">
										<input type="hidden" name="tag_ids[]" value="<?php echo esc_attr( $tag->id ); ?>" />
										<span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 px-1">
											<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
										</span>
										<input type="text" name="tags[]" value="<?php echo esc_attr( $tag->name ); ?>" class="flex-1 px-3 py-2 border border-solid border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="<?php esc_attr_e( 'Tag name', 'hotel-chain' ); ?>" />
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
		</div>

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
					<input type="hidden" name="${inputName}_ids[]" value="0" />
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

		$taxonomy_repo = new VideoTaxonomyRepository();

		// Get existing items to track what to delete.
		$existing_categories = $taxonomy_repo->get_categories();
		$existing_tags       = $taxonomy_repo->get_tags();
		$existing_cat_ids    = array_map( function( $cat ) {
			return $cat->id;
		}, $existing_categories );
		$existing_tag_ids    = array_map( function( $tag ) {
			return $tag->id;
		}, $existing_tags );

		// Process categories.
		$raw_categories = isset( $_POST['categories'] ) ? (array) wp_unslash( $_POST['categories'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_cat_ids    = isset( $_POST['category_ids'] ) ? (array) wp_unslash( $_POST['category_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$submitted_ids  = array();

		foreach ( $raw_categories as $index => $cat_name ) {
			$cat_name = trim( wp_strip_all_tags( (string) $cat_name ) );
			if ( '' === $cat_name ) {
				continue;
			}

			$cat_id = isset( $raw_cat_ids[ $index ] ) ? absint( $raw_cat_ids[ $index ] ) : 0;
			if ( $cat_id > 0 ) {
				$submitted_ids[] = $cat_id;
			}

			// Create or update category.
			$taxonomy_repo->create_or_update( $cat_name, 'category', $index );
		}

		// Delete removed categories.
		$to_delete = array_diff( $existing_cat_ids, $submitted_ids );
		foreach ( $to_delete as $id ) {
			$taxonomy_repo->delete( $id );
		}

		// Process tags.
		$raw_tags      = isset( $_POST['tags'] ) ? (array) wp_unslash( $_POST['tags'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_tag_ids   = isset( $_POST['tag_ids'] ) ? (array) wp_unslash( $_POST['tag_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$submitted_ids = array();

		foreach ( $raw_tags as $index => $tag_name ) {
			$tag_name = trim( wp_strip_all_tags( (string) $tag_name ) );
			if ( '' === $tag_name ) {
				continue;
			}

			$tag_id = isset( $raw_tag_ids[ $index ] ) ? absint( $raw_tag_ids[ $index ] ) : 0;
			if ( $tag_id > 0 ) {
				$submitted_ids[] = $tag_id;
			}

			// Create or update tag.
			$taxonomy_repo->create_or_update( $tag_name, 'tag', $index );
		}

		// Delete removed tags.
		$to_delete = array_diff( $existing_tag_ids, $submitted_ids );
		foreach ( $to_delete as $id ) {
			$taxonomy_repo->delete( $id );
		}

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
