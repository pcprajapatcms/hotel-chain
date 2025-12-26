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
	 * Register service hooks (custom routing for video slugs).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_routes' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'template_include', array( $this, 'handle_template' ) );
	}

	/**
	 * Register pretty permalinks for videos using custom table.
	 *
	 * URLs will look like: /videos/{video-slug}/
	 *
	 * @return void
	 */
	public function register_routes(): void {
		add_rewrite_rule(
			'^videos/([^/]+)/?$',
			'index.php?hotel_chain_video_slug=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'hotel_chain_video_slug';
		return $vars;
	}

	/**
	 * Load a custom template when viewing a video by slug.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function handle_template( string $template ): string {
		$video_slug = get_query_var( 'hotel_chain_video_slug' );

		if ( ! $video_slug ) {
			return $template;
		}

		// Look for a theme template named template-video.php.
		$new_template = locate_template( 'template-video.php' );

		return $new_template ? $new_template : $template;
	}
}
