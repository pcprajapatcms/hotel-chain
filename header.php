<?php
/**
 * Theme header.
 *
 * @package HotelChain
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-white text-slate-900 min-h-screen' ); ?>>
<?php wp_body_open(); ?>
<header class="border-b border-slate-200">
	<div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
		<div class="flex items-center gap-3">
			<?php if ( has_custom_logo() ) : ?>
				<div class="shrink-0">
					<?php the_custom_logo(); ?>
				</div>
			<?php else : ?>
				<a class="text-xl font-bold tracking-tight" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<nav class="hidden items-center gap-6 text-sm font-medium md:flex" aria-label="<?php esc_attr_e( 'Primary', 'hotel-chain' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_class'     => 'flex gap-6',
					'container'      => '',
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
		<button class="md:hidden inline-flex items-center justify-center rounded-md border border-slate-200 px-3 py-2 text-sm" type="button" aria-expanded="false" data-nav-toggle>
			<span class="sr-only"><?php esc_html_e( 'Toggle navigation', 'hotel-chain' ); ?></span>
			<span class="font-semibold"><?php esc_html_e( 'Menu', 'hotel-chain' ); ?></span>
		</button>
	</div>
	<div class="md:hidden border-t border-slate-200 px-4 py-3 hidden" data-nav-menu>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'menu_class'     => 'flex flex-col gap-3',
				'container'      => '',
				'fallback_cb'    => false,
			)
		);
		?>
	</div>
</header>
<main class="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-8">
