<?php
/**
 * Hotel-Video assignment repository.
 *
 * @package HotelChain
 */

namespace HotelChain\Repositories;

use HotelChain\Database\Schema;

/**
 * Hotel-Video assignment repository.
 */
class HotelVideoAssignmentRepository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function get_table_name(): string {
		return Schema::get_table_name( 'hotel_video_assignments' );
	}

	/**
	 * Assign video to hotel.
	 *
	 * @param int $hotel_id    Hotel ID.
	 * @param int $video_id    Internal video ID from video_metadata table.
	 * @param int $assigned_by User ID who assigned (optional).
	 * @return int|false Assignment ID on success, false on failure.
	 */
	public function assign( int $hotel_id, int $video_id, int $assigned_by = 0 ) {
		global $wpdb;
		$table = $this->get_table_name();

		// Check if already assigned.
		$existing = $this->get_assignment( $hotel_id, $video_id );
		if ( $existing ) {
			// Update status to active if it was inactive.
			if ( 'inactive' === $existing->status ) {
				return $this->update_status( $existing->id, 'active' );
			}
			return $existing->id;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'hotel_id'    => absint( $hotel_id ),
				'video_id'    => absint( $video_id ),
				'assigned_by' => $assigned_by ? absint( $assigned_by ) : null,
				'status'      => 'active',
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Remove assignment (set to inactive).
	 *
	 * @param int $hotel_id Hotel ID.
	 * @param int $video_id Internal video ID from video_metadata table.
	 * @return bool True on success.
	 */
	public function unassign( int $hotel_id, int $video_id ): bool {
		$assignment = $this->get_assignment( $hotel_id, $video_id );
		if ( ! $assignment ) {
			return false;
		}

		return $this->update_status( $assignment->id, 'inactive' );
	}

	/**
	 * Get assignment.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @param int $video_id Internal video ID from video_metadata table.
	 * @return object|null Assignment object or null.
	 */
	public function get_assignment( int $hotel_id, int $video_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		$assignment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE hotel_id = %d AND video_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hotel_id,
				$video_id
			)
		);

		return $assignment ? $assignment : null;
	}

	/**
	 * Get all videos assigned to a hotel.
	 *
	 * @param int   $hotel_id Hotel ID.
	 * @param array $args     Query arguments.
	 * @return array Array of assignment objects with video data.
	 */
	public function get_hotel_videos( int $hotel_id, array $args = array() ): array {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'status'  => 'active',
			'orderby' => 'assigned_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where        = array( 'hotel_id = %d' );
		$where_values = array( $hotel_id );

		if ( ! empty( $args['status'] ) ) {
			$where[]        = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], array( 'assigned_at', 'video_id' ), true ) ? $args['orderby'] : 'assigned_at';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// Now video_id references internal video_id from video_metadata table, not WordPress post ID.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT a.* 
			FROM {$table} a 
			WHERE {$where_clause} 
			ORDER BY a.{$orderby} {$order}";

		$assignments = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $query, ...$where_values )
		);

		return $assignments ? $assignments : array();
	}

	/**
	 * Get all hotels assigned to a video.
	 *
	 * @param int   $video_id Internal video ID from video_metadata table.
	 * @param array $args     Query arguments.
	 * @return array Array of assignment objects with hotel data.
	 */
	public function get_video_hotels( int $video_id, array $args = array() ): array {
		global $wpdb;
		$table        = $this->get_table_name();
		$hotels_table = Schema::get_table_name( 'hotels' );

		$defaults = array(
			'status'  => 'active',
			'orderby' => 'assigned_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where        = array( 'a.video_id = %d' );
		$where_values = array( $video_id );

		if ( ! empty( $args['status'] ) ) {
			$where[]        = 'a.status = %s';
			$where_values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], array( 'assigned_at', 'hotel_id' ), true ) ? $args['orderby'] : 'assigned_at';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT a.*, h.hotel_name, h.hotel_code, h.status as hotel_status 
			FROM {$table} a 
			LEFT JOIN {$hotels_table} h ON a.hotel_id = h.id 
			WHERE {$where_clause} 
			ORDER BY a.{$orderby} {$order}";

		$assignments = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $query, ...$where_values )
		);

		return $assignments ? $assignments : array();
	}

	/**
	 * Get assignment count for a video.
	 *
	 * @param int $video_id Internal video ID from video_metadata table.
	 * @return int Count.
	 */
	public function get_video_assignment_count( int $video_id ): int {
		global $wpdb;
		$table = $this->get_table_name();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE video_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$video_id
			)
		);

		return absint( $count );
	}

	/**
	 * Update assignment status.
	 *
	 * @param int    $assignment_id Assignment ID.
	 * @param string $status       New status.
	 * @return bool True on success.
	 */
	public function update_status( int $assignment_id, string $status ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$result = $wpdb->update(
			$table,
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'id' => $assignment_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete assignment.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return bool True on success.
	 */
	public function delete( int $assignment_id ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$result = $wpdb->delete(
			$table,
			array( 'id' => $assignment_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Create a video request (pending status).
	 *
	 * @param int $hotel_id    Hotel ID.
	 * @param int $video_id    Internal video ID.
	 * @param int $requested_by User ID who requested.
	 * @return int|false Assignment ID on success, false on failure.
	 */
	public function request( int $hotel_id, int $video_id, int $requested_by = 0 ) {
		global $wpdb;
		$table = $this->get_table_name();

		// Check if already exists.
		$existing = $this->get_assignment( $hotel_id, $video_id );
		if ( $existing ) {
			// Already assigned or pending.
			return $existing->id;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'hotel_id'    => absint( $hotel_id ),
				'video_id'    => absint( $video_id ),
				'assigned_by' => $requested_by ? absint( $requested_by ) : null,
				'status'      => 'pending',
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Approve a pending request (change to active).
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $approved_by   Admin user ID.
	 * @return bool True on success.
	 */
	public function approve( int $assignment_id, int $approved_by = 0 ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$data = array( 'status' => 'active' );
		if ( $approved_by ) {
			$data['assigned_by'] = $approved_by;
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $assignment_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Reject a pending request (delete or set inactive).
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return bool True on success.
	 */
	public function reject( int $assignment_id ): bool {
		return $this->delete( $assignment_id );
	}

	/**
	 * Get all pending requests for admin.
	 *
	 * @return array Array of pending request objects.
	 */
	public function get_pending_requests(): array {
		global $wpdb;
		$table        = $this->get_table_name();
		$hotels_table = Schema::get_table_name( 'hotels' );
		$videos_table = Schema::get_table_name( 'video_metadata' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT a.*, h.hotel_name, h.hotel_code, v.title as video_title
			FROM {$table} a
			LEFT JOIN {$hotels_table} h ON a.hotel_id = h.id
			LEFT JOIN {$videos_table} v ON a.video_id = v.video_id
			WHERE a.status = 'pending'
			ORDER BY a.assigned_at DESC";

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $results ? $results : array();
	}

	/**
	 * Get pending requests count.
	 *
	 * @return int Count.
	 */
	public function get_pending_requests_count(): int {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );

		return absint( $count );
	}

	/**
	 * Get hotel's pending requests.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @return array Array of pending request objects.
	 */
	public function get_hotel_pending_requests( int $hotel_id ): array {
		return $this->get_hotel_videos( $hotel_id, array( 'status' => 'pending' ) );
	}

	/**
	 * Check if hotel has pending request for video.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @param int $video_id Video ID.
	 * @return bool True if pending request exists.
	 */
	public function has_pending_request( int $hotel_id, int $video_id ): bool {
		$assignment = $this->get_assignment( $hotel_id, $video_id );
		return $assignment && 'pending' === $assignment->status;
	}

	/**
	 * Get assignment by ID.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return object|null Assignment object or null.
	 */
	public function get_by_id( int $assignment_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		$assignment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$assignment_id
			)
		);

		return $assignment ? $assignment : null;
	}

	/**
	 * Update hotel's status for a video (status_by_hotel).
	 *
	 * @param int    $hotel_id Hotel ID.
	 * @param int    $video_id Video ID.
	 * @param string $status   New status (active/inactive).
	 * @return bool True on success.
	 */
	public function update_hotel_status( int $hotel_id, int $video_id, string $status ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$result = $wpdb->update(
			$table,
			array( 'status_by_hotel' => sanitize_text_field( $status ) ),
			array(
				'hotel_id' => $hotel_id,
				'video_id' => $video_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get hotel's active videos (both admin approved and hotel active).
	 *
	 * @param int $hotel_id Hotel ID.
	 * @return array Array of assignment objects.
	 */
	public function get_hotel_active_videos( int $hotel_id ): array {
		global $wpdb;
		$table = $this->get_table_name();

		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE hotel_id = %d AND status = 'active' AND status_by_hotel = 'active' ORDER BY assigned_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hotel_id
			)
		);

		return $assignments ? $assignments : array();
	}
}
