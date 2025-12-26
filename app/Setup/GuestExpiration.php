<?php
/**
 * Guest Expiration service provider.
 *
 * Handles automatic expiration of guest accounts and access enforcement.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Database\Schema;

/**
 * Guest Expiration service provider.
 */
class GuestExpiration implements ServiceProviderInterface {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Schedule daily cron to check and expire guests.
		add_action( 'hotel_chain_check_guest_expiration', array( $this, 'check_and_expire_guests' ) );

		// Register cron schedule if not already scheduled.
		if ( ! wp_next_scheduled( 'hotel_chain_check_guest_expiration' ) ) {
			wp_schedule_event( time(), 'daily', 'hotel_chain_check_guest_expiration' );
		}

		// Also check on admin init (for immediate updates when admin visits).
		add_action( 'admin_init', array( $this, 'check_and_expire_guests' ) );

		// Check expiration on template load (real-time enforcement).
		add_action( 'template_redirect', array( $this, 'check_guest_access' ), 1 );
	}

	/**
	 * Check and expire guests whose access_end has passed.
	 *
	 * @return void
	 */
	public function check_and_expire_guests(): void {
		global $wpdb;
		$guests_table = Schema::get_table_name( 'guests' );

		// Update guests whose access_end has passed to 'expired' status.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"UPDATE {$guests_table} 
			SET status = 'expired' 
			WHERE status = 'active' 
			AND access_end IS NOT NULL 
			AND access_end < NOW()"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Check guest access in real-time and update status if needed.
	 *
	 * @return void
	 */
	public function check_guest_access(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'guest', $current_user->roles, true ) ) {
			return;
		}

		$guest_repo = new GuestRepository();
		$guest      = $guest_repo->get_by_user_id( $current_user->ID );

		if ( ! $guest ) {
			return;
		}

		// If guest is active but access_end has passed, update to expired.
		if ( 'active' === $guest->status && ! empty( $guest->access_end ) ) {
			$access_end_timestamp = strtotime( $guest->access_end );
			$current_timestamp    = time();

			if ( $access_end_timestamp < $current_timestamp ) {
				$guest_repo->update( $guest->id, array( 'status' => 'expired' ) );
			}
		}
	}

	/**
	 * Check if a guest has valid access.
	 *
	 * @param object $guest Guest object.
	 * @return bool True if guest has valid access, false otherwise.
	 */
	public static function is_guest_access_valid( $guest ): bool {
		if ( ! $guest ) {
			return false;
		}

		// Guest must be active.
		if ( 'active' !== $guest->status ) {
			return false;
		}

		// If access_end is set, check if it hasn't passed.
		if ( ! empty( $guest->access_end ) ) {
			$access_end_timestamp = strtotime( $guest->access_end );
			$current_timestamp    = time();

			if ( $access_end_timestamp < $current_timestamp ) {
				return false;
			}
		}

		return true;
	}
}
