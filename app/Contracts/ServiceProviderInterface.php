<?php
/**
 * Service provider interface.
 *
 * @package HotelChain
 */

namespace HotelChain\Contracts;

/**
 * Service provider interface.
 */
interface ServiceProviderInterface {
	/**
	 * Register service hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void;
}
