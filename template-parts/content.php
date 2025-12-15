<?php
/**
 * Post content template.
 *
 * @package HotelChain
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'rounded-lg border border-slate-200 p-6 shadow-sm transition hover:shadow-md' ); ?>>
	<header class="mb-4">
		<?php if ( is_singular() ) : ?>
			<h1 class="text-2xl font-bold leading-tight">
				<a href="<?php the_permalink(); ?>" class="hover:text-brand-700">
					<?php the_title(); ?>
				</a>
			</h1>
		<?php else : ?>
			<h2 class="text-xl font-semibold leading-tight">
				<a href="<?php the_permalink(); ?>" class="hover:text-brand-700">
					<?php the_title(); ?>
				</a>
			</h2>
		<?php endif; ?>
		<p class="mt-2 text-sm text-slate-600">
			<?php esc_html_e( 'Posted on', 'hotel-chain' ); ?>
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php esc_html_e( 'by', 'hotel-chain' ); ?>
			<span class="font-medium"><?php the_author(); ?></span>
		</p>
	</header>

	<div class="prose max-w-none">
		<?php
		if ( is_singular() ) {
			the_content();
		} else {
			the_excerpt();
		}
		?>
	</div>

	<?php if ( is_singular() ) : ?>
		<footer class="mt-6 text-sm text-slate-600">
			<?php the_tags( '<div class="flex flex-wrap gap-2">', '', '</div>' ); ?>
		</footer>
	<?php endif; ?>
</article>
