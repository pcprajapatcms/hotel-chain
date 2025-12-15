<?php
/**
 * Theme footer.
 *
 * @package HotelChain
 */

?>
</main>
<footer class="border-t border-slate-200">
	<div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6 md:flex-row md:items-center md:justify-between">
		<div class="text-sm text-slate-600">
			<?php
			printf(
				/* translators: %s: Site name. */
				esc_html__( '© %1$s %2$s', 'hotel-chain' ),
				esc_html( gmdate( 'Y' ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</div>
		<nav aria-label="<?php esc_attr_e( 'Footer', 'hotel-chain' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'menu_class'     => 'flex gap-4 text-sm text-slate-600',
					'container'      => '',
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
