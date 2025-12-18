<?php
/**
 * Video repository for database operations.
 *
 * @package HotelChain
 */

namespace HotelChain\Repositories;

use HotelChain\Database\Schema;

/**
 * Video repository.
 */
class VideoRepository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function get_table_name(): string {
		return Schema::get_table_name( 'video_metadata' );
	}

	/**
	 * Create or update video metadata.
	 *
	 * @param int|null $video_id Internal video ID. If null, a new ID will be generated.
	 * @param array $data     Metadata.
	 * @return int|false Internal video_id on success, false on failure.
	 */
	public function create_or_update( ?int $video_id, array $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$existing = null;
		if ( null !== $video_id ) {
			$existing = $this->get_by_video_id( $video_id );
		}

		if ( $existing ) {
			return $this->update( $video_id, $data );
		}

		$defaults = array(
			'slug'               => '',
			'video_file_id'      => 0,
			'title'              => '',
			'description'        => '',
			'practice_tip'       => '',
			'category'           => '',
			'tags'               => '',
			'thumbnail_id'       => 0,
			'thumbnail_url'      => '',
			'duration_seconds'   => null,
			'duration_label'     => '',
			'file_size'          => null,
			'file_format'        => '',
			'resolution_width'   => null,
			'resolution_height'  => null,
			'default_language'   => '',
			'total_views'        => 0,
			'total_completions'  => 0,
			'avg_completion_rate'=> 0.00,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate a unique internal video ID if not provided.
		if ( null === $video_id ) {
			$video_id = $this->generate_new_video_id();
		}

		// Generate slug if not provided.
		$slug = ! empty( $data['slug'] )
			? sanitize_title( $data['slug'] )
			: $this->generate_unique_slug( (string) $data['title'] );

		$result = $wpdb->insert(
			$table,
			array(
				'video_id'           => absint( $video_id ),
				'slug'               => $slug,
				'video_file_id'      => absint( $data['video_file_id'] ),
				'title'              => sanitize_text_field( $data['title'] ),
				'description'        => wp_kses_post( $data['description'] ),
				'practice_tip'       => wp_kses_post( $data['practice_tip'] ),
				'category'           => sanitize_text_field( $data['category'] ),
				'tags'               => sanitize_text_field( $data['tags'] ),
				'thumbnail_id'       => $data['thumbnail_id'] ? absint( $data['thumbnail_id'] ) : null,
				'thumbnail_url'      => sanitize_text_field( $data['thumbnail_url'] ),
				'duration_seconds'   => $data['duration_seconds'] ? absint( $data['duration_seconds'] ) : null,
				'duration_label'     => sanitize_text_field( $data['duration_label'] ),
				'file_size'          => $data['file_size'] ? absint( $data['file_size'] ) : null,
				'file_format'        => sanitize_text_field( $data['file_format'] ),
				'resolution_width'   => $data['resolution_width'] ? absint( $data['resolution_width'] ) : null,
				'resolution_height'  => $data['resolution_height'] ? absint( $data['resolution_height'] ) : null,
				'default_language'   => sanitize_text_field( $data['default_language'] ),
				'total_views'        => absint( $data['total_views'] ),
				'total_completions'  => absint( $data['total_completions'] ),
				'avg_completion_rate'=> floatval( $data['avg_completion_rate'] ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%f' )
		);

		if ( false === $result ) {
			return false;
		}

		// Return the video_id (internal ID), not the insert_id (primary key).
		return $video_id;
	}

	/**
	 * Update video metadata.
	 *
	 * @param int   $video_id Internal video ID.
	 * @param array $data     Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $video_id, array $data ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$update_data = array();
		$format = array();

		if ( isset( $data['video_file_id'] ) ) {
			$update_data['video_file_id'] = absint( $data['video_file_id'] );
			$format[] = '%d';
		}

		if ( isset( $data['slug'] ) ) {
			$update_data['slug'] = sanitize_title( $data['slug'] );
			$format[]            = '%s';
		}

		if ( isset( $data['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $data['title'] );
			$format[] = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = wp_kses_post( $data['description'] );
			$format[] = '%s';
		}

		if ( isset( $data['practice_tip'] ) ) {
			$update_data['practice_tip'] = wp_kses_post( $data['practice_tip'] );
			$format[] = '%s';
		}

		if ( isset( $data['category'] ) ) {
			$update_data['category'] = sanitize_text_field( $data['category'] );
			$format[] = '%s';
		}

		if ( isset( $data['tags'] ) ) {
			$update_data['tags'] = sanitize_text_field( $data['tags'] );
			$format[] = '%s';
		}

		if ( isset( $data['thumbnail_id'] ) ) {
			$update_data['thumbnail_id'] = $data['thumbnail_id'] ? absint( $data['thumbnail_id'] ) : null;
			$format[] = '%d';
		}

		if ( isset( $data['thumbnail_url'] ) ) {
			$update_data['thumbnail_url'] = sanitize_text_field( $data['thumbnail_url'] );
			$format[] = '%s';
		}

		if ( isset( $data['duration_seconds'] ) ) {
			$update_data['duration_seconds'] = $data['duration_seconds'] ? absint( $data['duration_seconds'] ) : null;
			$format[] = '%d';
		}

		if ( isset( $data['duration_label'] ) ) {
			$update_data['duration_label'] = sanitize_text_field( $data['duration_label'] );
			$format[] = '%s';
		}

		if ( isset( $data['file_size'] ) ) {
			$update_data['file_size'] = $data['file_size'] ? absint( $data['file_size'] ) : null;
			$format[] = '%d';
		}

		if ( isset( $data['file_format'] ) ) {
			$update_data['file_format'] = sanitize_text_field( $data['file_format'] );
			$format[] = '%s';
		}

		if ( isset( $data['resolution_width'] ) ) {
			$update_data['resolution_width'] = $data['resolution_width'] ? absint( $data['resolution_width'] ) : null;
			$format[] = '%d';
		}

		if ( isset( $data['resolution_height'] ) ) {
			$update_data['resolution_height'] = $data['resolution_height'] ? absint( $data['resolution_height'] ) : null;
			$format[] = '%d';
		}

		if ( isset( $data['default_language'] ) ) {
			$update_data['default_language'] = sanitize_text_field( $data['default_language'] );
			$format[] = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'video_id' => $video_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get metadata by video ID.
	 *
	 * @param int $video_id Internal video ID.
	 * @return object|null Metadata object or null.
	 */
	public function get_by_video_id( int $video_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$metadata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE video_id = %d", $video_id ) );

		return $metadata ? $metadata : null;
	}

	/**
	 * Get distinct category names used in video metadata.
	 *
	 * @return array
	 */
	public function get_distinct_categories(): array {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_col( "SELECT DISTINCT category FROM {$table} WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC" );

		return array_map( 'strval', $results ?: array() );
	}

	/**
	 * Get all videos with optional filtering.
	 *
	 * @param array $args Query arguments (category, limit, offset, orderby, order).
	 * @return array Array of video metadata objects.
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'category' => '',
			'limit'    => 24,
			'offset'   => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['category'] ) ) {
			$where[] = 'category = %s';
			$where_values[] = sanitize_text_field( $args['category'] );
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], array( 'created_at', 'title', 'video_id' ), true ) ? $args['orderby'] : 'created_at';
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit_clause = 'LIMIT %d';
			$where_values[] = absint( $args['limit'] );
			if ( $args['offset'] > 0 ) {
				$limit_clause .= ' OFFSET %d';
				$where_values[] = absint( $args['offset'] );
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} {$limit_clause}";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$videos = $wpdb->get_results( $wpdb->prepare( $query, ...$where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$videos = $wpdb->get_results( $query );
		}

		return $videos ? $videos : array();
	}

	/**
	 * Get total count of videos.
	 *
	 * @param string $category Optional category filter.
	 * @return int
	 */
	public function get_count( string $category = '' ): int {
		global $wpdb;
		$table = $this->get_table_name();

		if ( ! empty( $category ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE category = %s", sanitize_text_field( $category ) ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return absint( $count );
	}

	/**
	 * Get metadata by slug.
	 *
	 * @param string $slug Video slug.
	 * @return object|null
	 */
	public function get_by_slug( string $slug ) {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$metadata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );

		return $metadata ? $metadata : null;
	}

	/**
	 * Increment view count.
	 *
	 * @param int $video_id Internal video ID.
	 * @return bool True on success.
	 */
	public function increment_views( int $video_id ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET total_views = total_views + 1 WHERE video_id = %d", $video_id ) );

		return false !== $result;
	}

	/**
	 * Update completion statistics.
	 *
	 * @param int   $video_id Internal video ID.
	 * @param float $completion_rate Completion rate (0-100).
	 * @return bool True on success.
	 */
	public function update_completion_stats( int $video_id, float $completion_rate ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$metadata = $this->get_by_video_id( $video_id );
		if ( ! $metadata ) {
			return false;
		}

		$total_completions = $metadata->total_completions;
		$current_avg = floatval( $metadata->avg_completion_rate );

		// Calculate new average.
		$new_total = $total_completions + 1;
		$new_avg = ( ( $current_avg * $total_completions ) + $completion_rate ) / $new_total;

		$result = $wpdb->update(
			$table,
			array(
				'total_completions'   => $new_total,
				'avg_completion_rate' => $new_avg,
			),
			array( 'video_id' => $video_id ),
			array( '%d', '%f' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Generate a new internal video ID.
	 *
	 * @return int
	 */
	private function generate_new_video_id(): int {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max_id = $wpdb->get_var( "SELECT MAX(video_id) FROM {$table}" );

		return (int) $max_id + 1;
	}

	/**
	 * Generate a unique slug based on the title.
	 *
	 * @param string $title Video title.
	 * @return string
	 */
	private function generate_unique_slug( string $title ): string {
		global $wpdb;
		$table = $this->get_table_name();

		$base_slug = sanitize_title( $title );
		if ( '' === $base_slug ) {
			$base_slug = 'video';
		}

		$slug      = $base_slug;
		$suffix    = 2;

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug ) );
			if ( ! $exists ) {
				break;
			}
			$slug = $base_slug . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}
}
