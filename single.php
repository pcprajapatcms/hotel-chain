<?php
/**
 * Single post template.
 *
 * @package HotelChain
 */

get_header();
?>
<div class="flex flex-col gap-10 md:flex-row">
	<div class="flex-1 space-y-8">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content' );
			the_post_navigation(
				array(
					'prev_text' => __( 'Previous: %title', 'hotel-chain' ),
					'next_text' => __( 'Next: %title', 'hotel-chain' ),
				)
			);

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</div>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
