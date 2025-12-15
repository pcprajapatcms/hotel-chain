<?php
/**
 * Archive template.
 *
 * @package HotelChain
 */

get_header();
?>
<div class="flex flex-col gap-10 md:flex-row">
	<div class="flex-1 space-y-8">
		<header class="border-b border-slate-200 pb-4">
			<h1 class="text-3xl font-bold"><?php the_archive_title(); ?></h1>
			<?php if ( get_the_archive_description() ) : ?>
				<p class="mt-2 text-slate-600"><?php echo wp_kses_post( get_the_archive_description() ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content' );
			endwhile;
			?>

			<div class="flex items-center justify-between text-sm">
				<div><?php previous_posts_link( __( 'Newer posts', 'hotel-chain' ) ); ?></div>
				<div><?php next_posts_link( __( 'Older posts', 'hotel-chain' ) ); ?></div>
			</div>
		<?php else : ?>
			<p class="text-slate-700"><?php esc_html_e( 'No content found.', 'hotel-chain' ); ?></p>
		<?php endif; ?>
	</div>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
