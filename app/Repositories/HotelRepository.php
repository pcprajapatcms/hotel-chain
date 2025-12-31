<?php
/**
 * Hotel repository for database operations.
 *
 * @package HotelChain
 */

namespace HotelChain\Repositories;

use HotelChain\Database\Schema;
use WP_User;

/**
 * Hotel repository.
 */
class HotelRepository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function get_table_name(): string {
		return Schema::get_table_name( 'hotels' );
	}

	/**
	 * Create a new hotel record.
	 *
	 * @param array $data Hotel data.
	 * @return int|false Hotel ID on success, false on failure.
	 */
	public function create( array $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'user_id'          => 0,
			'hotel_code'       => '',
			'hotel_name'       => '',
			'hotel_slug'       => '',
			'contact_email'    => '',
			'contact_phone'    => '',
			'address'          => '',
			'city'             => '',
			'country'          => '',
			'access_duration'  => 0,
			'license_start'    => null,
			'license_end'      => null,
			'registration_url' => '',
			'landing_url'      => '',
			'status'           => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		// Calculate license dates if access_duration is set.
		if ( $data['access_duration'] > 0 && ! $data['license_start'] ) {
			$data['license_start'] = current_time( 'mysql' );
			$data['license_end']   = gmdate( 'Y-m-d H:i:s', strtotime( $data['license_start'] ) + ( $data['access_duration'] * DAY_IN_SECONDS ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'          => absint( $data['user_id'] ),
				'hotel_code'       => $this->sanitize_hotel_code( $data['hotel_code'] ),
				'hotel_name'       => sanitize_text_field( $data['hotel_name'] ),
				'hotel_slug'       => sanitize_title( $data['hotel_slug'] ),
				'contact_email'    => sanitize_email( $data['contact_email'] ),
				'contact_phone'    => sanitize_text_field( $data['contact_phone'] ),
				'address'          => sanitize_text_field( $data['address'] ),
				'city'             => sanitize_text_field( $data['city'] ),
				'country'          => sanitize_text_field( $data['country'] ),
				'access_duration'  => absint( $data['access_duration'] ),
				'license_start'    => $data['license_start'] ? sanitize_text_field( $data['license_start'] ) : null,
				'license_end'      => $data['license_end'] ? sanitize_text_field( $data['license_end'] ) : null,
				'registration_url' => esc_url_raw( $data['registration_url'] ),
				'landing_url'      => esc_url_raw( $data['landing_url'] ),
				'status'           => sanitize_text_field( $data['status'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update hotel record.
	 *
	 * @param int   $hotel_id Hotel ID.
	 * @param array $data     Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $hotel_id, array $data ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$update_data = array();
		$format      = array();

		if ( isset( $data['hotel_name'] ) ) {
			$update_data['hotel_name'] = sanitize_text_field( $data['hotel_name'] );
			$format[]                  = '%s';
		}

		if ( isset( $data['hotel_code'] ) ) {
			$update_data['hotel_code'] = $this->sanitize_hotel_code( $data['hotel_code'] );
			$format[]                  = '%s';
		}

		if ( isset( $data['contact_email'] ) ) {
			$update_data['contact_email'] = sanitize_email( $data['contact_email'] );
			$format[]                     = '%s';
		}

		if ( isset( $data['contact_phone'] ) ) {
			$update_data['contact_phone'] = sanitize_text_field( $data['contact_phone'] );
			$format[]                     = '%s';
		}

		if ( isset( $data['address'] ) ) {
			$update_data['address'] = sanitize_text_field( $data['address'] );
			$format[]               = '%s';
		}

		if ( isset( $data['city'] ) ) {
			$update_data['city'] = sanitize_text_field( $data['city'] );
			$format[]            = '%s';
		}

		if ( isset( $data['country'] ) ) {
			$update_data['country'] = sanitize_text_field( $data['country'] );
			$format[]               = '%s';
		}

		if ( isset( $data['website'] ) ) {
			$update_data['website'] = esc_url_raw( $data['website'] );
			$format[]               = '%s';
		}

		if ( isset( $data['welcome_section'] ) ) {
			$update_data['welcome_section'] = $data['welcome_section']; // Already JSON encoded.
			$format[]                       = '%s';
		}

		if ( isset( $data['logo_id'] ) ) {
			$update_data['logo_id'] = absint( $data['logo_id'] );
			$format[]               = '%d';
		}

		if ( isset( $data['favicon_id'] ) ) {
			$update_data['favicon_id'] = absint( $data['favicon_id'] );
			$format[]                  = '%d';
		}

		if ( isset( $data['access_duration'] ) ) {
			$update_data['access_duration'] = absint( $data['access_duration'] );
			$format[]                       = '%d';

			// Recalculate license dates.
			if ( $update_data['access_duration'] > 0 ) {
				$hotel = $this->get_by_id( $hotel_id );
				if ( $hotel ) {
					$start                        = $hotel->license_start ? strtotime( $hotel->license_start ) : time();
					$update_data['license_start'] = gmdate( 'Y-m-d H:i:s', $start );
					$update_data['license_end']   = gmdate( 'Y-m-d H:i:s', $start + ( $update_data['access_duration'] * DAY_IN_SECONDS ) );
					$format[]                     = '%s';
					$format[]                     = '%s';
				}
			}
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
			$format[]              = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $hotel_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get hotel by ID.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @return object|null Hotel object or null.
	 */
	public function get_by_id( int $hotel_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hotel = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $hotel_id ) );

		return $hotel ? $hotel : null;
	}

	/**
	 * Get hotel by user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null Hotel object or null.
	 */
	public function get_by_user_id( int $user_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hotel = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );

		return $hotel ? $hotel : null;
	}

	/**
	 * Get hotel by slug.
	 *
	 * @param string $slug Hotel slug.
	 * @return object|null Hotel object or null.
	 */
	public function get_by_slug( string $slug ) {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hotel = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE hotel_slug = %s", $slug ) );

		return $hotel ? $hotel : null;
	}

	/**
	 * Get hotel by code.
	 *
	 * @param string $code Hotel code.
	 * @return object|null Hotel object or null.
	 */
	public function get_by_code( string $code ) {
		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hotel = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE hotel_code = %s", $code ) );

		return $hotel ? $hotel : null;
	}

	/**
	 * Get all hotels with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of hotel objects.
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'status'  => '',
			'search'  => '',
			'orderby' => 'id',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where        = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]        = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$search         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]        = '(hotel_name LIKE %s OR hotel_code LIKE %s OR contact_email LIKE %s OR city LIKE %s OR country LIKE %s)';
			$where_values[] = $search;
			$where_values[] = $search;
			$where_values[] = $search;
			$where_values[] = $search;
			$where_values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], array( 'id', 'hotel_name', 'created_at', 'city', 'country' ), true ) ? $args['orderby'] : 'id';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// Handle -1 limit as "no limit".
		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit        = absint( $args['limit'] );
			$offset       = absint( $args['offset'] );
			$limit_clause = "LIMIT {$limit} OFFSET {$offset}";
		}

		if ( ! empty( $args['status'] ) && ! empty( $args['search'] ) ) {
			// Both status and search filters.
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$query  = "SELECT * FROM {$table} WHERE status = %s AND (hotel_name LIKE %s OR hotel_code LIKE %s OR contact_email LIKE %s OR city LIKE %s OR country LIKE %s) ORDER BY {$orderby} {$order}";
			if ( $limit_clause ) {
				$query .= ' ' . $limit_clause;
			}
			$results = $wpdb->get_results(
				$wpdb->prepare(
					$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$args['status'],
					$search,
					$search,
					$search,
					$search,
					$search
				)
			);
		} elseif ( ! empty( $args['status'] ) ) {
			// Only status filter.
			$query = "SELECT * FROM {$table} WHERE status = %s ORDER BY {$orderby} {$order}";
			if ( $limit_clause ) {
				$query .= ' ' . $limit_clause;
			}
			$results = $wpdb->get_results(
				$wpdb->prepare(
					$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$args['status']
				)
			);
		} elseif ( ! empty( $args['search'] ) ) {
			// Only search filter.
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$query  = "SELECT * FROM {$table} WHERE (hotel_name LIKE %s OR hotel_code LIKE %s OR contact_email LIKE %s OR city LIKE %s OR country LIKE %s) ORDER BY {$orderby} {$order}";
			if ( $limit_clause ) {
				$query .= ' ' . $limit_clause;
			}
			$results = $wpdb->get_results(
				$wpdb->prepare(
					$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$search,
					$search,
					$search,
					$search,
					$search
				)
			);
		} else {
			// No filters.
			$query = "SELECT * FROM {$table} ORDER BY {$orderby} {$order}";
			if ( $limit_clause ) {
				$query .= ' ' . $limit_clause;
			}
			$results = $wpdb->get_results(
				$query // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}

		return $results ? $results : array();
	}

	/**
	 * Get hotel count.
	 *
	 * @param array $args Query arguments.
	 * @return int Count.
	 */
	public function count( array $args = array() ): int {
		global $wpdb;
		$table = $this->get_table_name();

		$where        = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]        = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$search         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]        = '(hotel_name LIKE %s OR hotel_code LIKE %s OR contact_email LIKE %s OR city LIKE %s OR country LIKE %s)';
			$where_values[] = $search;
			$where_values[] = $search;
			$where_values[] = $search;
			$where_values[] = $search;
			$where_values[] = $search;
		}

		if ( ! empty( $args['status'] ) && ! empty( $args['search'] ) ) {
			// Both status and search filters.
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$count  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s AND (hotel_name LIKE %s OR hotel_code LIKE %s OR contact_email LIKE %s OR city LIKE %s OR country LIKE %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$args['status'],
					$search,
					$search,
					$search,
					$search,
					$search
				)
			);
		} elseif ( ! empty( $args['status'] ) ) {
			// Only status filter.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$args['status']
				)
			);
		} elseif ( ! empty( $args['search'] ) ) {
			// Only search filter.
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$count  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE (hotel_name LIKE %s OR hotel_code LIKE %s OR contact_email LIKE %s OR city LIKE %s OR country LIKE %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$search,
					$search,
					$search,
					$search,
					$search
				)
			);
		} else {
			// No filters.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return absint( $count );
	}

	/**
	 * Delete hotel and all related data.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $hotel_id ): bool {
		global $wpdb;

		// Get hotel data before deletion to access user_id.
		$hotel = $this->get_by_id( $hotel_id );
		if ( ! $hotel ) {
			return false;
		}

		$user_id = (int) $hotel->user_id;

		// Delete related data from custom tables.
		$assignments_table = Schema::get_table_name( 'hotel_video_assignments' );
		$guests_table      = Schema::get_table_name( 'guests' );
		$video_views_table = Schema::get_table_name( 'video_views' );

		// Delete hotel video assignments.
		$wpdb->delete(
			$assignments_table,
			array( 'hotel_id' => $hotel_id ),
			array( '%d' )
		);

		// Get all guests for this hotel before deletion.
		$guests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id FROM {$guests_table} WHERE hotel_id = %d AND user_id IS NOT NULL AND user_id > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hotel_id
			)
		);

		// Delete WordPress users associated with guests.
		if ( ! empty( $guests ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $guests as $guest ) {
				$guest_user_id = (int) $guest->user_id;
				if ( $guest_user_id > 0 ) {
					$guest_user = get_user_by( 'id', $guest_user_id );
					if ( $guest_user ) {
						wp_delete_user( $guest_user_id );
					}
				}
			}
		}

		// Delete guests (this will delete all guests for this hotel, including those without user accounts).
		$wpdb->delete(
			$guests_table,
			array( 'hotel_id' => $hotel_id ),
			array( '%d' )
		);

		// Delete video views.
		$wpdb->delete(
			$video_views_table,
			array( 'hotel_id' => $hotel_id ),
			array( '%d' )
		);

		// Delete WordPress user if exists.
		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				// Use wp_delete_user which handles user meta cleanup.
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user_id );
			}
		}

		// Delete hotel record.
		$table  = $this->get_table_name();
		$result = $wpdb->delete(
			$table,
			array( 'id' => $hotel_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get hotel with WordPress user data.
	 *
	 * @param int $hotel_id Hotel ID.
	 * @return object|null Hotel object with user property or null.
	 */
	public function get_with_user( int $hotel_id ) {
		$hotel = $this->get_by_id( $hotel_id );

		if ( ! $hotel ) {
			return null;
		}

		$hotel->user = get_user_by( 'id', $hotel->user_id );

		return $hotel;
	}

	/**
	 * Calculate days to renewal.
	 *
	 * @param object $hotel Hotel object.
	 * @return int|null Days to renewal or null if not applicable.
	 */
	public function get_days_to_renewal( $hotel ): ?int {
		if ( ! $hotel->license_end ) {
			return null;
		}

		$end_timestamp = strtotime( $hotel->license_end );
		$now_timestamp = time();
		$days_diff     = (int) ceil( ( $end_timestamp - $now_timestamp ) / DAY_IN_SECONDS );

		return $days_diff;
	}

	/**
	 * Sanitize hotel code to only allow alphanumeric characters and single dash before year.
	 * Format: {INITIALS}-{YEAR} or {INITIALS}-{YEAR}-{SUFFIX}
	 *
	 * @param string $code Hotel code to sanitize.
	 * @return string Sanitized hotel code.
	 */
	private function sanitize_hotel_code( string $code ): string {
		// Remove all special characters except alphanumeric and dashes.
		$code = preg_replace( '/[^A-Za-z0-9\-]/', '', $code );

		// Remove multiple consecutive dashes.
		$code = preg_replace( '/-+/', '-', $code );

		// Remove leading/trailing dashes.
		$code = trim( $code, '-' );

		// Try to extract and validate format: {INITIALS}-{YEAR} or {INITIALS}-{YEAR}-{SUFFIX}.
		$parts = explode( '-', $code );

		if ( count( $parts ) >= 2 ) {
			$initials = preg_replace( '/[^A-Za-z0-9]/', '', $parts[0] );
			$year     = preg_replace( '/[^0-9]/', '', $parts[1] );

			// Ensure initials are uppercase and at least 2 characters.
			$initials = strtoupper( $initials );
			if ( strlen( $initials ) < 2 ) {
				$initials = 'HTL';
			}
			if ( strlen( $initials ) > 10 ) {
				$initials = substr( $initials, 0, 10 );
			}

			// Validate year (should be 4 digits).
			if ( strlen( $year ) === 4 && is_numeric( $year ) ) {
				$sanitized = $initials . '-' . $year;

				// Add suffix if present (for duplicates).
				if ( count( $parts ) > 2 ) {
					$suffix = preg_replace( '/[^0-9]/', '', $parts[2] );
					if ( ! empty( $suffix ) ) {
						$sanitized .= '-' . $suffix;
					}
				}

				return $sanitized;
			}
		}

		// If format is invalid, generate a new code from initials.
		$initials = preg_replace( '/[^A-Za-z0-9]/', '', $code );
		$initials = strtoupper( $initials );
		if ( strlen( $initials ) < 2 ) {
			$initials = 'HTL';
		}
		if ( strlen( $initials ) > 10 ) {
			$initials = substr( $initials, 0, 10 );
		}

		$year = gmdate( 'Y' );
		return $initials . '-' . $year;
	}
}
