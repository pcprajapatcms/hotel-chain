<?php
/**
 * Video custom post type and taxonomies.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;

/**
 * Register Video post type and taxonomies.
 */
class Videos implements ServiceProviderInterface {
	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type_and_taxonomies' ) );
	}

	/**
	 * Register custom post type and taxonomies for videos.
	 *
	 * @return void
	 */
	public function register_post_type_and_taxonomies(): void {
		$this->register_post_type();
		$this->register_category_taxonomy();
		$this->register_tag_taxonomy();
	}

	/**
	 * Register the Video post type.
	 *
	 * @return void
	 */
	private function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Videos', 'Post type general name', 'hotel-chain' ),
			'singular_name'         => _x( 'Video', 'Post type singular name', 'hotel-chain' ),
			'menu_name'             => _x( 'Videos', 'Admin Menu text', 'hotel-chain' ),
			'name_admin_bar'        => _x( 'Video', 'Add New on Toolbar', 'hotel-chain' ),
			'add_new'               => __( 'Add New', 'hotel-chain' ),
			'add_new_item'          => __( 'Add New Video', 'hotel-chain' ),
			'new_item'              => __( 'New Video', 'hotel-chain' ),
			'edit_item'             => __( 'Edit Video', 'hotel-chain' ),
			'view_item'             => __( 'View Video', 'hotel-chain' ),
			'all_items'             => __( 'All Videos', 'hotel-chain' ),
			'search_items'          => __( 'Search Videos', 'hotel-chain' ),
			'parent_item_colon'     => __( 'Parent Videos:', 'hotel-chain' ),
			'not_found'             => __( 'No videos found.', 'hotel-chain' ),
			'not_found_in_trash'    => __( 'No videos found in Trash.', 'hotel-chain' ),
			'featured_image'        => __( 'Video Thumbnail', 'hotel-chain' ),
			'set_featured_image'    => __( 'Set video thumbnail', 'hotel-chain' ),
			'remove_featured_image' => __( 'Remove video thumbnail', 'hotel-chain' ),
			'use_featured_image'    => __( 'Use as video thumbnail', 'hotel-chain' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-video-alt3',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'taxonomies'         => array( 'video_category', 'video_tag' ),
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		);

		register_post_type( 'video', $args );
	}

	/**
	 * Register hierarchical category taxonomy for videos.
	 *
	 * @return void
	 */
	private function register_category_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Video Categories', 'taxonomy general name', 'hotel-chain' ),
			'singular_name'     => _x( 'Video Category', 'taxonomy singular name', 'hotel-chain' ),
			'search_items'      => __( 'Search Video Categories', 'hotel-chain' ),
			'all_items'         => __( 'All Video Categories', 'hotel-chain' ),
			'parent_item'       => __( 'Parent Video Category', 'hotel-chain' ),
			'parent_item_colon' => __( 'Parent Video Category:', 'hotel-chain' ),
			'edit_item'         => __( 'Edit Video Category', 'hotel-chain' ),
			'update_item'       => __( 'Update Video Category', 'hotel-chain' ),
			'add_new_item'      => __( 'Add New Video Category', 'hotel-chain' ),
			'new_item_name'     => __( 'New Video Category Name', 'hotel-chain' ),
			'menu_name'         => __( 'Video Categories', 'hotel-chain' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'video-category' ),
		);

		register_taxonomy( 'video_category', array( 'video' ), $args );
	}

	/**
	 * Register non-hierarchical tag taxonomy for videos.
	 *
	 * @return void
	 */
	private function register_tag_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Video Tags', 'taxonomy general name', 'hotel-chain' ),
			'singular_name'              => _x( 'Video Tag', 'taxonomy singular name', 'hotel-chain' ),
			'search_items'               => __( 'Search Video Tags', 'hotel-chain' ),
			'popular_items'              => __( 'Popular Video Tags', 'hotel-chain' ),
			'all_items'                  => __( 'All Video Tags', 'hotel-chain' ),
			'edit_item'                  => __( 'Edit Video Tag', 'hotel-chain' ),
			'update_item'                => __( 'Update Video Tag', 'hotel-chain' ),
			'add_new_item'               => __( 'Add New Video Tag', 'hotel-chain' ),
			'new_item_name'              => __( 'New Video Tag Name', 'hotel-chain' ),
			'separate_items_with_commas' => __( 'Separate video tags with commas', 'hotel-chain' ),
			'add_or_remove_items'        => __( 'Add or remove video tags', 'hotel-chain' ),
			'choose_from_most_used'      => __( 'Choose from the most used video tags', 'hotel-chain' ),
			'menu_name'                  => __( 'Video Tags', 'hotel-chain' ),
		);

		$args = array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'show_in_rest'          => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'video-tag' ),
		);

		register_taxonomy( 'video_tag', array( 'video' ), $args );
	}
}

