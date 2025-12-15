<?php
/**
 * Asset resolver utility.
 *
 * @package HotelChain
 */

namespace HotelChain\Support;

/**
 * Asset resolver utility.
 */
class AssetResolver {
	/**
	 * Get asset URI.
	 *
	 * @param string $relative_path Relative path to asset.
	 * @return string Asset URI.
	 */
	public function asset_uri( string $relative_path ): string {
		return get_theme_file_uri( $relative_path );
	}

	/**
	 * Get asset version based on file modification time.
	 *
	 * @param string $relative_path Relative path to asset.
	 * @return string Asset version.
	 */
	public function asset_version( string $relative_path ): string {
		$file_path = get_theme_file_path( $relative_path );

		if ( file_exists( $file_path ) ) {
			return (string) filemtime( $file_path );
		}

		return (string) time();
	}
}
