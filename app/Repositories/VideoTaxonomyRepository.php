<?php
/**
 * Video Taxonomy repository for managing categories and tags.
 *
 * @package HotelChain
 */

namespace HotelChain\Repositories;

use HotelChain\Database\Schema;

/**
 * Video Taxonomy repository.
 */
class VideoTaxonomyRepository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function get_table_name(): string {
		return Schema::get_table_name( 'video_taxonomy' );
	}

	/**
	 * Get all categories.
	 *
	 * @return array Array of category objects sorted by sort_order.
	 */
	public function get_categories(): array {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT * FROM {$table} WHERE type = 'category' ORDER BY sort_order ASC, name ASC" );

		return $results ? $results : array();
	}

	/**
	 * Get all tags.
	 *
	 * @return array Array of tag objects sorted by sort_order.
	 */
	public function get_tags(): array {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT * FROM {$table} WHERE type = 'tag' ORDER BY sort_order ASC, name ASC" );

		return $results ? $results : array();
	}

	/**
	 * Get category names as array.
	 *
	 * @return array Array of category names.
	 */
	public function get_category_names(): array {
		$categories = $this->get_categories();
		return array_map(
			function ( $cat ) {
				return $cat->name;
			},
			$categories
		);
	}

	/**
	 * Get tag names as array.
	 *
	 * @return array Array of tag names.
	 */
	public function get_tag_names(): array {
		$tags = $this->get_tags();
		return array_map(
			function ( $tag ) {
				return $tag->name;
			},
			$tags
		);
	}

	/**
	 * Create or update a taxonomy item.
	 *
	 * @param string $name Taxonomy item name.
	 * @param string $type 'category' or 'tag'.
	 * @param int    $sort_order Sort order.
	 * @return int|false Taxonomy ID on success, false on failure.
	 */
	public function create_or_update( string $name, string $type, int $sort_order = 0 ) {
		global $wpdb;
		$table = $this->get_table_name();

		$name = trim( sanitize_text_field( $name ) );
		$type = in_array( $type, array( 'category', 'tag' ), true ) ? $type : 'category';

		if ( empty( $name ) ) {
			return false;
		}

		// Check if exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE name = %s AND type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$name,
				$type
			)
		);

		if ( $existing ) {
			// Update existing.
			$wpdb->update(
				$table,
				array(
					'sort_order' => absint( $sort_order ),
				),
				array(
					'id' => $existing->id,
				),
				array( '%d' ),
				array( '%d' )
			);
			return $existing->id;
		}

		// Create new.
		$result = $wpdb->insert(
			$table,
			array(
				'name'       => $name,
				'type'       => $type,
				'sort_order' => absint( $sort_order ),
			),
			array( '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete a taxonomy item.
	 *
	 * @param int $id Taxonomy item ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete by name and type.
	 *
	 * @param string $name Taxonomy item name.
	 * @param string $type 'category' or 'tag'.
	 * @return bool True on success, false on failure.
	 */
	public function delete_by_name( string $name, string $type ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$type = in_array( $type, array( 'category', 'tag' ), true ) ? $type : 'category';

		$result = $wpdb->delete(
			$table,
			array(
				'name' => sanitize_text_field( $name ),
				'type' => $type,
			),
			array( '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Update sort order for multiple items.
	 *
	 * @param array $items Array of arrays with 'id' and 'sort_order'.
	 * @return bool True on success, false on failure.
	 */
	public function update_sort_orders( array $items ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		foreach ( $items as $item ) {
			if ( ! isset( $item['id'] ) || ! isset( $item['sort_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$table,
				array( 'sort_order' => absint( $item['sort_order'] ) ),
				array( 'id' => absint( $item['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return true;
	}

	/**
	 * Get taxonomy item by name and type.
	 *
	 * @param string $name Taxonomy item name.
	 * @param string $type 'category' or 'tag'.
	 * @return object|null Taxonomy object or null.
	 */
	public function get_by_name( string $name, string $type ) {
		global $wpdb;
		$table = $this->get_table_name();

		$type = in_array( $type, array( 'category', 'tag' ), true ) ? $type : 'category';

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE name = %s AND type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$name,
				$type
			)
		);

		return $item ? $item : null;
	}
}
