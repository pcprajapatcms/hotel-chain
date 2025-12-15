<?php
/**
 * 404 template.
 *
 * @package HotelChain
 */

get_header();
?>
<div class="flex flex-col gap-6 text-center">
	<h1 class="text-5xl font-bold text-brand-700"><?php esc_html_e( '404', 'hotel-chain' ); ?></h1>
	<p class="text-lg text-slate-700"><?php esc_html_e( 'Sorry, the page you are looking for could not be found.', 'hotel-chain' ); ?></p>
	<div class="mt-4">
		<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to home', 'hotel-chain' ); ?>
		</a>
	</div>
</div>
<?php
get_footer();
