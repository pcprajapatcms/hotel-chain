<?php
/**
 * Guest Repository.
 *
 * @package HotelChain
 */

namespace HotelChain\Repositories;

use HotelChain\Database\Schema;

/**
 * Guest data access.
 */
class GuestRepository {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->table = Schema::get_table_name( 'guests' );
	}

	/**
	 * Create a new guest.
	 *
	 * @param array $data Guest data.
	 * @return int|false Guest ID or false on failure.
	 */
	public function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'hotel_id'           => 0,
			'user_id'            => null,
			'guest_code'         => null,
			'first_name'         => null,
			'last_name'          => null,
			'email'              => null,
			'phone'              => null,
			'registration_code'  => null,
			'verification_token' => null,
			'email_verified_at'  => null,
			'access_start'       => current_time( 'mysql' ),
			'access_end'         => null,
			'status'             => 'pending',
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( $this->table, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get guest by verification token.
	 *
	 * @param string $token Verification token.
	 * @return object|null
	 */
	public function get_by_token( string $token ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE verification_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);
	}

	/**
	 * Verify guest email.
	 *
	 * @param int $guest_id Guest ID.
	 * @return bool
	 */
	public function verify_email( int $guest_id ): bool {
		global $wpdb;
		return (bool) $wpdb->update(
			$this->table,
			array(
				'email_verified_at'  => current_time( 'mysql' ),
				'verification_token' => null,
				'status'             => 'active',
			),
			array( 'id' => $guest_id )
		);
	}

	/**
	 * Get guest by email and hotel.
	 *
	 * @param string $email    Email address.
	 * @param int    $hotel_id Hotel ID.
	 * @return object|null
	 */
	public function get_by_email_and_hotel( string $email, int $hotel_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s AND hotel_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				$hotel_id
			)
		);
	}

	/**
	 * Get guest by ID.
	 *
	 * @param int $id Guest ID.
	 * @return object|null
	 */
	public function get_by_id( int $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get guest by user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public function get_by_user_id( int $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get guest by hotel and user.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @param int $user_id  User ID.
	 * @return object|null
	 */
	public function get_by_hotel_and_user( int $hotel_id, int $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE hotel_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hotel_id,
				$user_id
			)
		);
	}

	/**
	 * Get all guests for a hotel.
	 *
	 * @param int   $hotel_id Hotel ID.
	 * @param array $args     Optional arguments.
	 * @return array
	 */
	public function get_hotel_guests( int $hotel_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'  => null,
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$sanitized_orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$orderby           = $sanitized_orderby ? $sanitized_orderby : 'created_at DESC';

		// Handle -1 limit as "no limit".
		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit_clause = sprintf( 'LIMIT %d OFFSET %d', absint( $args['limit'] ), absint( $args['offset'] ) );
		}

		if ( $args['status'] ) {
			if ( $limit_clause ) {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE hotel_id = %d AND status = %s ORDER BY {$orderby} {$limit_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel_id,
					$args['status']
				);
			} else {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE hotel_id = %d AND status = %s ORDER BY {$orderby}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel_id,
					$args['status']
				);
			}
		} else {
			if ( $limit_clause ) {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE hotel_id = %d ORDER BY {$orderby} {$limit_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel_id
				);
			} else {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE hotel_id = %d ORDER BY {$orderby}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel_id
				);
			}
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count guests for a hotel.
	 *
	 * @param int    $hotel_id Hotel ID.
	 * @param string $status   Optional status filter.
	 * @return int
	 */
	public function count_hotel_guests( int $hotel_id, string $status = '' ): int {
		global $wpdb;

		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE hotel_id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$hotel_id,
					$status
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE hotel_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hotel_id
			)
		);
	}

	/**
	 * Update guest.
	 *
	 * @param int   $id   Guest ID.
	 * @param array $data Data to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	/**
	 * Delete guest.
	 *
	 * @param int $id Guest ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}
}
