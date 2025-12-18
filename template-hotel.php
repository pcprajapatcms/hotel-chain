<?php
/**
 * Hotel profile template.
 *
 * @package HotelChain
 */

use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\GuestRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;

$hotel_user = get_query_var( 'hotel_user' );

if ( ! $hotel_user ) {
	get_template_part( '404' );
	return;
}

// Get hotel data from custom table.
$hotel_repo = new HotelRepository();
$hotel      = $hotel_repo->get_by_user_id( $hotel_user->ID );

if ( ! $hotel ) {
	get_template_part( '404' );
	return;
}

// Check if user is logged in and is a guest for this hotel.
$is_guest        = false;
$guest           = null;
$current_user_id = get_current_user_id();

if ( $current_user_id ) {
	$guest_repo = new GuestRepository();
	$guest      = $guest_repo->get_by_hotel_and_user( $hotel->id, $current_user_id );
	$is_guest   = $guest && 'active' === $guest->status;
}

// Get current page/tab.
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'home'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Get assigned videos count.
$assignment_repo = new HotelVideoAssignmentRepository();
$videos          = $assignment_repo->get_hotel_videos( $hotel->id, array( 'status' => 'active' ) );
$video_count     = count( $videos );

// Calculate overall progress for the current user.
$completed_count    = 0;
$total_progress_pct = 0;
if ( $current_user_id && $video_count > 0 ) {
	global $wpdb;
	$video_views_table = $wpdb->prefix . 'hotel_chain_video_views';
	foreach ( $videos as $v ) {
		$view = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT completion_percentage, completed FROM {$video_views_table} WHERE video_id = %d AND hotel_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$v->video_id,
				$hotel->id,
				$current_user_id
			)
		);
		if ( $view ) {
			if ( $view->completed ) {
				$completed_count++;
			}
			$total_progress_pct += (int) $view->completion_percentage;
		}
	}
	$total_progress_pct = round( $total_progress_pct / $video_count );
}

// Get welcome section data.
$welcome_section = ! empty( $hotel->welcome_section ) ? json_decode( $hotel->welcome_section, true ) : array();
$welcome_heading     = ! empty( $welcome_section['welcome_heading'] ) ? $welcome_section['welcome_heading'] : __( 'WELCOME TO YOUR SANCTUARY', 'hotel-chain' );
$welcome_subheading  = ! empty( $welcome_section['welcome_subheading'] ) ? $welcome_section['welcome_subheading'] : __( 'The Inner Peace Series', 'hotel-chain' );
$welcome_description = ! empty( $welcome_section['welcome_description'] ) ? $welcome_section['welcome_description'] : __( 'Watch this brief introduction to learn how to get the most from your meditation practice', 'hotel-chain' );
$welcome_video_id    = ! empty( $welcome_section['welcome_video_id'] ) ? absint( $welcome_section['welcome_video_id'] ) : 0;
$welcome_thumbnail_id = ! empty( $welcome_section['welcome_thumbnail_id'] ) ? absint( $welcome_section['welcome_thumbnail_id'] ) : 0;
$welcome_steps       = ! empty( $welcome_section['steps'] ) ? $welcome_section['steps'] : array(
	array( 'heading' => __( 'Practice in Order', 'hotel-chain' ), 'description' => __( 'Each meditation builds on the previous one for maximum benefit', 'hotel-chain' ) ),
	array( 'heading' => __( 'Find Your Space', 'hotel-chain' ), 'description' => __( "Choose a quiet place where you won't be disturbed", 'hotel-chain' ) ),
	array( 'heading' => __( 'No Pressure', 'hotel-chain' ), 'description' => __( "There's no wrong way. Simply show up and be present", 'hotel-chain' ) ),
);

// Get video and thumbnail URLs.
$welcome_video_url     = $welcome_video_id ? wp_get_attachment_url( $welcome_video_id ) : '';
$welcome_thumbnail_url = $welcome_thumbnail_id ? wp_get_attachment_url( $welcome_thumbnail_id ) : '';

// Enqueue CSS.
wp_enqueue_style(
	'hotel-chain-main',
	get_template_directory_uri() . '/assets/css/main.css',
	array(),
	filemtime( get_template_directory() . '/assets/css/main.css' )
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $hotel->hotel_name ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="bg-gray-100 m-0 p-0">

	<!-- Header -->
	<header class="bg-white border-b border-solid border-gray-200 sticky top-0 z-50">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<div class="flex items-center justify-between h-16">
				<!-- Logo / Hotel Name -->
				<div class="flex items-center gap-4">
					<div class="w-10 h-10 bg-gray-200 border border-solid border-gray-300 rounded-lg flex items-center justify-center">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M10 12h4"></path>
							<path d="M10 8h4"></path>
							<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
							<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
							<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
						</svg>
					</div>
					<div>
						<h1 class="text-lg font-semibold text-gray-900"><?php echo esc_html( $hotel->hotel_name ); ?></h1>
						<p class="text-xs text-gray-500"><?php esc_html_e( 'Meditation Series', 'hotel-chain' ); ?></p>
					</div>
				</div>

				<!-- Navigation -->
				<nav class="hidden md:flex items-center gap-1">
					<?php
					$tabs = array(
						'home'      => __( 'Home', 'hotel-chain' ),
						'videos'    => __( 'Videos', 'hotel-chain' ),
						'analytics' => __( 'Analytics', 'hotel-chain' ),
						'account'   => __( 'Account', 'hotel-chain' ),
					);
					foreach ( $tabs as $tab_key => $tab_label ) :
						$is_active = ( $current_tab === $tab_key );
						$tab_url   = add_query_arg( 'tab', $tab_key, home_url( '/hotel/' . $hotel->hotel_slug . '/' ) );
						if ( 'home' === $tab_key ) {
							$tab_url = home_url( '/hotel/' . $hotel->hotel_slug . '/' );
						}
						?>
						<a href="<?php echo esc_url( $tab_url ); ?>" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $is_active ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
							<?php echo esc_html( $tab_label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<!-- User Menu -->
				<div class="flex items-center gap-3">
					<?php if ( is_user_logged_in() ) : ?>
						<div class="flex items-center gap-2">
							<div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
								<span class="text-blue-700 text-sm font-medium"><?php echo esc_html( strtoupper( substr( wp_get_current_user()->display_name, 0, 1 ) ) ); ?></span>
							</div>
							<span class="hidden sm:block text-sm text-gray-700"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
						</div>
						<a href="<?php echo esc_url( wp_logout_url( home_url( '/hotel/' . $hotel->hotel_slug . '/' ) ) ); ?>" class="text-sm text-gray-500 hover:text-gray-700">
							<?php esc_html_e( 'Logout', 'hotel-chain' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( wp_login_url( home_url( '/hotel/' . $hotel->hotel_slug . '/' ) ) ); ?>" class="px-4 py-2 bg-blue-200 border border-solid border-blue-400 rounded-lg text-blue-900 text-sm font-medium hover:bg-blue-300">
							<?php esc_html_e( 'Sign In', 'hotel-chain' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Mobile Navigation -->
			<nav class="md:hidden flex items-center gap-1 pb-3 overflow-x-auto">
				<?php
				foreach ( $tabs as $tab_key => $tab_label ) :
					$is_active = ( $current_tab === $tab_key );
					$tab_url   = add_query_arg( 'tab', $tab_key, home_url( '/hotel/' . $hotel->hotel_slug . '/' ) );
					if ( 'home' === $tab_key ) {
						$tab_url = home_url( '/hotel/' . $hotel->hotel_slug . '/' );
					}
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?php echo $is_active ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100'; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
	</header>

	<?php if ( 'home' === $current_tab ) : ?>
	<!-- Hero Welcome Section -->
	<div class="py-20 px-8" style="background-color: rgb(61, 61, 68);">
		<div class="max-w-5xl mx-auto">
			<div class="text-center mb-12">
				<h1 class="mb-6 font-serif text-[#f0e7d7] text-3xl md:text-4xl lg:text-5xl tracking-wider uppercase"><?php echo esc_html( $welcome_heading ); ?></h1>
				<p class="text-[#f0e7d7] text-2xl md:text-3xl mb-6" style="font-family: 'Brush Script MT', cursive;"><?php echo esc_html( $welcome_subheading ); ?></p>
				<p class="max-w-3xl mx-auto text-[#c4c4c4] text-lg leading-relaxed"><?php echo esc_html( $welcome_description ); ?></p>
			</div>
			<div class="relative aspect-video rounded-lg overflow-hidden shadow-2xl mb-8 bg-black">
				<?php if ( $welcome_video_url ) : ?>
					<?php if ( $welcome_thumbnail_url ) : ?>
						<img id="welcome-video-poster" src="<?php echo esc_url( $welcome_thumbnail_url ); ?>" alt="" class="absolute inset-0 w-full h-full object-cover cursor-pointer" onclick="playWelcomeVideo()">
					<?php endif; ?>
					<video id="welcome-video-player" class="w-full h-full object-cover <?php echo $welcome_thumbnail_url ? 'hidden' : ''; ?>" controls <?php echo ! $welcome_thumbnail_url ? 'autoplay' : ''; ?>>
						<source src="<?php echo esc_url( $welcome_video_url ); ?>" type="video/mp4">
					</video>
					<?php if ( $welcome_thumbnail_url ) : ?>
						<div id="welcome-play-overlay" class="absolute inset-0 flex items-center justify-center cursor-pointer" onclick="playWelcomeVideo()">
							<div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto cursor-pointer transition-all hover:scale-110 bg-[#f0e7d7]">
								<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1" style="color: rgb(61, 61, 68);">
									<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
								</svg>
							</div>
						</div>
					<?php endif; ?>
					<script>
					function playWelcomeVideo() {
						const poster = document.getElementById('welcome-video-poster');
						const video = document.getElementById('welcome-video-player');
						const overlay = document.getElementById('welcome-play-overlay');
						if (poster) poster.classList.add('hidden');
						if (overlay) overlay.classList.add('hidden');
						if (video) {
							video.classList.remove('hidden');
							video.play();
						}
					}
					document.addEventListener('DOMContentLoaded', function() {
						const video = document.getElementById('welcome-video-player');
						const poster = document.getElementById('welcome-video-poster');
						const overlay = document.getElementById('welcome-play-overlay');
						if (!video) return;

						video.addEventListener('pause', function() {
							if (!video.ended) {
								video.classList.add('hidden');
								if (poster) poster.classList.remove('hidden');
								if (overlay) overlay.classList.remove('hidden');
							}
						});

						video.addEventListener('ended', function() {
							video.classList.add('hidden');
							if (poster) poster.classList.remove('hidden');
							if (overlay) overlay.classList.remove('hidden');
						});
					});
					</script>
				<?php else : ?>
					<div class="absolute inset-0 flex items-center justify-center">
						<div class="text-center">
							<div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4 cursor-pointer transition-all hover:scale-110 bg-[#f0e7d7]">
								<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1" style="color: rgb(61, 61, 68);">
									<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
								</svg>
							</div>
							<p class="text-[#f0e7d7] text-lg"><?php esc_html_e( 'How to Engage with Your Meditation Series', 'hotel-chain' ); ?></p>
							<p class="text-gray-500 text-sm mt-2"><?php esc_html_e( 'Duration: 4:20', 'hotel-chain' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $welcome_steps ) ) : ?>
			<div class="grid grid-cols-1 md:grid-cols-<?php echo count( $welcome_steps ) <= 3 ? count( $welcome_steps ) : 3; ?> gap-6">
				<?php foreach ( $welcome_steps as $step_index => $step ) : ?>
				<div class="text-center p-6 rounded-lg" style="background-color: rgba(240, 231, 215, 0.1);">
					<div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center bg-[#f0e7d7]">
						<span class="font-serif text-2xl" style="color: rgb(61, 61, 68);"><?php echo esc_html( $step_index + 1 ); ?></span>
					</div>
					<h3 class="mb-2 text-[#f0e7d7] font-semibold"><?php echo esc_html( $step['heading'] ); ?></h3>
					<p class="text-[#c4c4c4] text-sm leading-relaxed"><?php echo esc_html( $step['description'] ); ?></p>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Meditation Series Section -->
	<div class="py-20 px-8 bg-[#f0e7d7]">
		<div class="max-w-4xl mx-auto">
			<div class="text-center mb-12">
				<h2 class="mb-4 font-serif text-2xl md:text-3xl lg:text-4xl tracking-wider uppercase" style="color: rgb(61, 61, 68);"><?php esc_html_e( 'YOUR MEDITATION SERIES', 'hotel-chain' ); ?></h2>
				<p class="text-xl md:text-2xl mb-4" style="font-family: 'Brush Script MT', cursive; color: rgb(61, 61, 68);"><?php esc_html_e( 'Seven guided journeys to inner peace', 'hotel-chain' ); ?></p>
				<p class="text-gray-500"><?php esc_html_e( 'Total Duration: 1 hour 55 minutes', 'hotel-chain' ); ?></p>
			</div>

			<!-- Progress Bar -->
			<div class="mb-12 p-6 rounded-lg bg-white border-2 border-solid border-gray-300">
				<div class="flex items-center justify-between mb-3">
					<span class="font-semibold" style="color: rgb(61, 61, 68);"><?php esc_html_e( 'Your Progress', 'hotel-chain' ); ?></span>
					<span class="text-gray-500 text-sm"><?php echo esc_html( $completed_count ); ?> of <?php echo esc_html( $video_count ); ?> <?php esc_html_e( 'completed', 'hotel-chain' ); ?></span>
				</div>
				<div class="w-full h-3 rounded-full overflow-hidden bg-gray-200">
					<div class="h-full transition-all duration-500" style="background-color: rgb(61, 61, 68); width: <?php echo esc_attr( $total_progress_pct ); ?>%;"></div>
				</div>
			</div>

			<!-- Video List -->
			<div class="space-y-4" id="video-accordion">
				<?php
				global $wpdb;
				$video_views_table = $wpdb->prefix . 'hotel_chain_video_views';
				$video_index       = 1;
				foreach ( $videos as $assignment ) :
					$video_repo = new \HotelChain\Repositories\VideoRepository();
					$video      = $video_repo->get_by_video_id( $assignment->video_id );
					if ( ! $video ) {
						continue;
					}

					// Get user's progress for this video.
					$video_progress = null;
					$is_completed   = false;
					$progress_pct   = 0;
					if ( $current_user_id ) {
						$video_progress = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT completion_percentage, completed FROM {$video_views_table} WHERE video_id = %d AND hotel_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$video->video_id,
								$hotel->id,
								$current_user_id
							)
						);
						if ( $video_progress ) {
							$is_completed = (bool) $video_progress->completed;
							$progress_pct = (int) $video_progress->completion_percentage;
						}
					}

					$tags = ! empty( $video->tags ) ? array_map( 'trim', explode( ',', $video->tags ) ) : array();
					?>
					<div class="accordion-item rounded-lg overflow-hidden transition-all bg-white border-2 border-solid <?php echo $is_completed ? 'border-green-500' : 'border-gray-300'; ?>" style="box-shadow: none;">
						<button type="button" class="accordion-trigger w-full p-6 flex items-center gap-4 hover:bg-gray-50 transition-colors text-left">
							<div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center <?php echo $is_completed ? 'bg-green-500' : ''; ?>" style="<?php echo ! $is_completed ? 'background-color: rgb(61, 61, 68);' : ''; ?> color: rgb(240, 231, 215);">
								<?php if ( $is_completed ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<circle cx="12" cy="12" r="10"></circle>
										<path d="m9 12 2 2 4-4"></path>
									</svg>
								<?php else : ?>
									<span class="font-serif text-xl"><?php echo esc_html( $video_index ); ?></span>
								<?php endif; ?>
							</div>
							<div class="flex-1">
								<h3 class="mb-1 text-lg font-semibold" style="color: rgb(61, 61, 68);"><?php echo esc_html( $video->title ); ?></h3>
								<div class="flex items-center gap-3">
									<div class="flex items-center gap-1">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7a7a7a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M12 6v6l4 2"></path>
											<circle cx="12" cy="12" r="10"></circle>
										</svg>
										<span class="text-gray-500 text-sm"><?php echo esc_html( $video->duration_label ?: '00:00' ); ?></span>
									</div>
									<?php if ( $is_completed ) : ?>
										<span class="text-green-500 text-xs font-semibold uppercase tracking-wider"><?php esc_html_e( 'Completed', 'hotel-chain' ); ?></span>
									<?php elseif ( $progress_pct > 0 ) : ?>
										<span class="text-blue-500 text-xs font-semibold"><?php echo esc_html( $progress_pct ); ?>%</span>
									<?php endif; ?>
								</div>
							</div>
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7a7a7a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="accordion-icon flex-shrink-0 transition-transform duration-300">
								<path d="m6 9 6 6 6-6"></path>
							</svg>
						</button>
						<div class="accordion-content hidden px-6 pb-6 pt-2 border-t-2" style="border-color: rgb(229, 229, 229);">
							<?php if ( ! empty( $video->description ) ) : ?>
								<p class="mb-4" style="color: rgb(61, 61, 68); font-size: 1rem; line-height: 1.7;"><?php echo esc_html( $video->description ); ?></p>
							<?php endif; ?>
							
							<?php if ( ! empty( $video->category ) ) : ?>
								<div class="mb-4">
									<span class="block mb-2 text-gray-500 text-xs font-semibold uppercase tracking-wider"><?php esc_html_e( 'Category', 'hotel-chain' ); ?></span>
									<span class="px-3 py-1 rounded-full text-sm" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215);"><?php echo esc_html( $video->category ); ?></span>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $tags ) ) : ?>
								<div class="mb-6">
									<span class="block mb-2 text-gray-500 text-xs font-semibold uppercase tracking-wider"><?php esc_html_e( 'Themes', 'hotel-chain' ); ?></span>
									<div class="flex flex-wrap gap-2">
										<?php foreach ( $tags as $tag ) : ?>
											<span class="px-3 py-1 rounded-full text-sm" style="background-color: rgb(240, 231, 215); color: rgb(61, 61, 68);"><?php echo esc_html( $tag ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<div class="flex flex-col sm:flex-row gap-3">
								<a href="<?php echo esc_url( home_url( '/hotel/' . $hotel->hotel_slug . '/meditation/' . $video->video_id . '/' ) ); ?>" class="flex-1 px-6 py-3 rounded-lg flex items-center justify-center gap-2 transition-all hover:opacity-90 font-semibold no-underline" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215);">
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
									</svg>
									<?php esc_html_e( 'Start Meditation', 'hotel-chain' ); ?>
								</a>
							</div>
						</div>
					</div>
					<?php
					$video_index++;
				endforeach;
				?>
			</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const accordionItems = document.querySelectorAll('#video-accordion .accordion-item');
				
				accordionItems.forEach(function(item) {
					const trigger = item.querySelector('.accordion-trigger');
					const content = item.querySelector('.accordion-content');
					const icon = item.querySelector('.accordion-icon');
					
					trigger.addEventListener('click', function() {
						const isOpen = !content.classList.contains('hidden');
						
						// Close all other accordions
						accordionItems.forEach(function(otherItem) {
							const otherContent = otherItem.querySelector('.accordion-content');
							const otherIcon = otherItem.querySelector('.accordion-icon');
							otherContent.classList.add('hidden');
							otherIcon.style.transform = 'rotate(0deg)';
							otherItem.style.boxShadow = 'none';
						});
						
						// Toggle current accordion
						if (!isOpen) {
							content.classList.remove('hidden');
							icon.style.transform = 'rotate(180deg)';
							item.style.boxShadow = 'rgba(0, 0, 0, 0.1) 0px 4px 12px';
						}
					});
				});
			});
			</script>

			<!-- Take Your Time Box -->
			<div class="mt-12 p-8 rounded-lg text-center" style="background-color: rgb(61, 61, 68);">
				<p class="mb-2 text-[#f0e7d7] text-3xl" style="font-family: 'Brush Script MT', cursive;"><?php esc_html_e( 'Take your time', 'hotel-chain' ); ?></p>
				<p class="text-[#c4c4c4] leading-relaxed"><?php esc_html_e( "You have one year of access from your registration date. There's no rush—move through the series at your own pace.", 'hotel-chain' ); ?></p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Main Content -->
	<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

		<?php if ( ! $is_guest && is_user_logged_in() ) : ?>
			<!-- Not Registered as Guest Banner -->
			<div class="bg-yellow-50 border border-solid border-yellow-300 rounded-lg p-4 mb-6">
				<div class="flex items-center gap-3">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
						<line x1="12" y1="9" x2="12" y2="13"></line>
						<line x1="12" y1="17" x2="12.01" y2="17"></line>
					</svg>
					<div>
						<p class="text-yellow-800 font-medium"><?php esc_html_e( 'You are not registered as a guest at this hotel.', 'hotel-chain' ); ?></p>
						<a href="<?php echo esc_url( add_query_arg( 'hotel', $hotel->hotel_code, home_url( '/register' ) ) ); ?>" class="text-yellow-700 underline text-sm">
							<?php esc_html_e( 'Register now to access the meditation series', 'hotel-chain' ); ?>
						</a>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'videos' === $current_tab ) : ?>
			<!-- Videos Tab -->
			<h2 class="text-2xl font-semibold mb-6"><?php esc_html_e( 'Meditation Library', 'hotel-chain' ); ?></h2>
			
			<?php if ( $video_count > 0 ) : ?>
				<div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
					<?php
					foreach ( $videos as $assignment ) :
						$video_repo = new \HotelChain\Repositories\VideoRepository();
						$video      = $video_repo->get_by_video_id( $assignment->video_id );
						if ( ! $video ) {
							continue;
						}
						?>
						<div class="bg-white rounded-lg border border-solid border-gray-200 overflow-hidden hover:shadow-md transition-shadow cursor-pointer">
							<div class="aspect-video bg-gray-200 flex items-center justify-center relative">
								<?php if ( $video->thumbnail_url ) : ?>
									<img src="<?php echo esc_url( $video->thumbnail_url ); ?>" alt="<?php echo esc_attr( $video->title ); ?>" class="w-full h-full object-cover">
								<?php else : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
										<polygon points="5 3 19 12 5 21 5 3"></polygon>
									</svg>
								<?php endif; ?>
								<div class="absolute inset-0 bg-black/30 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center">
									<div class="w-14 h-14 bg-white/90 rounded-full flex items-center justify-center">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#1e3a8a" stroke="none">
											<polygon points="5 3 19 12 5 21 5 3"></polygon>
										</svg>
									</div>
								</div>
							</div>
							<div class="p-4">
								<h4 class="font-medium text-gray-900 mb-1"><?php echo esc_html( $video->title ); ?></h4>
								<div class="flex items-center justify-between">
									<?php if ( $video->duration_label ) : ?>
										<p class="text-sm text-gray-500"><?php echo esc_html( $video->duration_label ); ?></p>
									<?php endif; ?>
									<?php if ( $video->category ) : ?>
										<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded"><?php echo esc_html( $video->category ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-12 text-center">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4">
						<path d="m22 8-6 4 6 4V8Z"></path>
						<rect width="14" height="12" x="2" y="6" rx="2" ry="2"></rect>
					</svg>
					<h3 class="text-lg font-medium text-gray-900 mb-2"><?php esc_html_e( 'No videos available yet', 'hotel-chain' ); ?></h3>
					<p class="text-gray-500"><?php esc_html_e( 'Videos will appear here once they are assigned to this hotel.', 'hotel-chain' ); ?></p>
				</div>
			<?php endif; ?>

		<?php elseif ( 'analytics' === $current_tab ) : ?>
			<!-- Analytics Tab -->
			<h2 class="text-2xl font-semibold mb-6"><?php esc_html_e( 'Your Analytics', 'hotel-chain' ); ?></h2>
			
			<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-6">
					<p class="text-sm text-gray-500 mb-1"><?php esc_html_e( 'Total Watch Time', 'hotel-chain' ); ?></p>
					<p class="text-3xl font-bold text-gray-900">0<span class="text-lg font-normal text-gray-500"> min</span></p>
				</div>
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-6">
					<p class="text-sm text-gray-500 mb-1"><?php esc_html_e( 'Videos Watched', 'hotel-chain' ); ?></p>
					<p class="text-3xl font-bold text-gray-900">0</p>
				</div>
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-6">
					<p class="text-sm text-gray-500 mb-1"><?php esc_html_e( 'Completed', 'hotel-chain' ); ?></p>
					<p class="text-3xl font-bold text-green-600">0</p>
				</div>
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-6">
					<p class="text-sm text-gray-500 mb-1"><?php esc_html_e( 'Streak', 'hotel-chain' ); ?></p>
					<p class="text-3xl font-bold text-orange-500">0<span class="text-lg font-normal text-gray-500"> days</span></p>
				</div>
			</div>

			<div class="bg-white rounded-lg border border-solid border-gray-200 p-6">
				<h3 class="text-lg font-semibold mb-4"><?php esc_html_e( 'Recent Activity', 'hotel-chain' ); ?></h3>
				<div class="text-center py-8 text-gray-500">
					<p><?php esc_html_e( 'No activity yet. Start watching videos to see your progress here.', 'hotel-chain' ); ?></p>
				</div>
			</div>

		<?php elseif ( 'account' === $current_tab ) : ?>
			<!-- Account Tab -->
			<h2 class="text-2xl font-semibold mb-6"><?php esc_html_e( 'Your Account', 'hotel-chain' ); ?></h2>
			
			<?php if ( is_user_logged_in() ) : ?>
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-6 max-w-2xl">
					<div class="flex items-center gap-4 mb-6 pb-6 border-b border-solid border-gray-200">
						<div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
							<span class="text-blue-700 text-2xl font-medium"><?php echo esc_html( strtoupper( substr( wp_get_current_user()->display_name, 0, 1 ) ) ); ?></span>
						</div>
						<div>
							<h3 class="text-xl font-semibold"><?php echo esc_html( wp_get_current_user()->display_name ); ?></h3>
							<p class="text-gray-500"><?php echo esc_html( wp_get_current_user()->user_email ); ?></p>
						</div>
					</div>

					<?php if ( $is_guest ) : ?>
						<div class="space-y-4">
							<div class="flex items-center justify-between py-3 border-b border-solid border-gray-100">
								<span class="text-gray-600"><?php esc_html_e( 'Guest Code', 'hotel-chain' ); ?></span>
								<span class="font-medium"><?php echo esc_html( $guest->guest_code ); ?></span>
							</div>
							<div class="flex items-center justify-between py-3 border-b border-solid border-gray-100">
								<span class="text-gray-600"><?php esc_html_e( 'Status', 'hotel-chain' ); ?></span>
								<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium"><?php esc_html_e( 'Active', 'hotel-chain' ); ?></span>
							</div>
							<div class="flex items-center justify-between py-3 border-b border-solid border-gray-100">
								<span class="text-gray-600"><?php esc_html_e( 'Registered', 'hotel-chain' ); ?></span>
								<span class="font-medium"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $guest->created_at ) ) ); ?></span>
							</div>
							<?php if ( $guest->access_end ) : ?>
								<div class="flex items-center justify-between py-3">
									<span class="text-gray-600"><?php esc_html_e( 'Access Expires', 'hotel-chain' ); ?></span>
									<span class="font-medium"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $guest->access_end ) ) ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="bg-white rounded-lg border border-solid border-gray-200 p-12 text-center max-w-lg mx-auto">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4">
						<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
						<circle cx="12" cy="7" r="4"></circle>
					</svg>
					<h3 class="text-lg font-medium text-gray-900 mb-2"><?php esc_html_e( 'Sign in to view your account', 'hotel-chain' ); ?></h3>
					<p class="text-gray-500 mb-6"><?php esc_html_e( 'Access your profile and track your meditation progress.', 'hotel-chain' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( home_url( '/hotel/' . $hotel->hotel_slug . '/?tab=account' ) ) ); ?>" class="inline-block px-6 py-3 bg-blue-200 border-2 border-solid border-blue-400 rounded-lg text-blue-900 font-medium hover:bg-blue-300">
						<?php esc_html_e( 'Sign In', 'hotel-chain' ); ?>
					</a>
				</div>
			<?php endif; ?>

		<?php endif; ?>

	</main>

	<!-- Footer -->
	<footer class="bg-white border-t border-solid border-gray-200 mt-12">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<div class="flex flex-col md:flex-row items-center justify-between gap-4">
				<p class="text-sm text-gray-500">
					&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $hotel->hotel_name ); ?>. <?php esc_html_e( 'All rights reserved.', 'hotel-chain' ); ?>
				</p>
				<div class="flex items-center gap-4 text-sm text-gray-500">
					<a href="#" class="hover:text-gray-700"><?php esc_html_e( 'Privacy Policy', 'hotel-chain' ); ?></a>
					<a href="#" class="hover:text-gray-700"><?php esc_html_e( 'Terms of Service', 'hotel-chain' ); ?></a>
					<a href="#" class="hover:text-gray-700"><?php esc_html_e( 'Contact', 'hotel-chain' ); ?></a>
				</div>
			</div>
		</div>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>
