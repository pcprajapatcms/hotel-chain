<?php
/**
 * Hotel profile template.
 *
 * @package HotelChain
 */

$hotel_user = get_query_var( 'hotel_user' );

if ( ! $hotel_user ) {
	get_template_part( '404' );
	return;
}

$hotel_name = get_user_meta( $hotel_user->ID, 'hotel_name', true );
$hotel_code = get_user_meta( $hotel_user->ID, 'hotel_code', true );
$email      = $hotel_user->user_email;
$phone      = get_user_meta( $hotel_user->ID, 'contact_phone', true );
$address    = get_user_meta( $hotel_user->ID, 'address', true );
$city       = get_user_meta( $hotel_user->ID, 'city', true );
$country    = get_user_meta( $hotel_user->ID, 'country', true );

get_header();
?>
<article class="rounded-lg border border-slate-200 p-8 shadow-sm">
	<header class="mb-6">
		<h1 class="text-3xl font-bold"><?php echo esc_html( $hotel_name ? $hotel_name : $hotel_user->display_name ); ?></h1>
		<?php if ( $city || $country ) : ?>
			<p class="text-slate-600 mt-1">
				<?php
				echo esc_html( trim( ( $address ? $address . ', ' : '' ) . $city . ' ' . $country ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $hotel_code ) : ?>
			<p class="mt-2 text-sm font-medium text-brand-700">
				<?php
				printf(
					/* translators: %s: hotel code */
					esc_html__( 'Hotel Code: %s', 'hotel-chain' ),
					esc_html( $hotel_code )
				);
				?>
			</p>
		<?php endif; ?>
	</header>

	<div class="prose max-w-none">
		<p><?php echo esc_html( get_user_meta( $hotel_user->ID, 'description', true ) ); ?></p>
	</div>

	<div class="mt-6 flex flex-col gap-2 text-sm text-slate-700">
		<?php if ( $email ) : ?>
			<div>
				<?php esc_html_e( 'Contact Email:', 'hotel-chain' ); ?>
				<a class="text-brand-700" href="mailto:<?php echo esc_attr( $email ); ?>">
					<?php echo esc_html( $email ); ?>
				</a>
			</div>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<div>
				<?php esc_html_e( 'Contact Phone:', 'hotel-chain' ); ?>
				<?php echo esc_html( $phone ); ?>
			</div>
		<?php endif; ?>
	</div>
</article>
<?php
get_footer();
