<?php
/**
 * Sidebar template.
 *
 * @package HotelChain
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="w-full md:w-72 md:shrink-0">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
