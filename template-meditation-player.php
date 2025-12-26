<?php
/**
 * Meditation player template.
 *
 * @package HotelChain
 */

use HotelChain\Repositories\HotelRepository;
use HotelChain\Repositories\VideoRepository;
use HotelChain\Repositories\HotelVideoAssignmentRepository;

$hotel_user = get_query_var( 'hotel_user' );
$video_id   = get_query_var( 'meditation_video_id' );

if ( ! $hotel_user || ! $video_id ) {
	get_template_part( '404' );
	return;
}

// Get hotel data.
$hotel_repo = new HotelRepository();
$hotel      = $hotel_repo->get_by_user_id( $hotel_user->ID );

if ( ! $hotel ) {
	get_template_part( '404' );
	return;
}

// Get video data.
$video_repo = new VideoRepository();
$video      = $video_repo->get_by_video_id( $video_id );

if ( ! $video ) {
	get_template_part( '404' );
	return;
}

// Get video file URL.
$video_url = $video->video_file_id ? wp_get_attachment_url( $video->video_file_id ) : '';

// Get all fully active videos for "Continue Your Journey" section
// (admin approved AND hotel has this video set to active).
$assignment_repo = new HotelVideoAssignmentRepository();
$all_videos      = $assignment_repo->get_hotel_active_videos( $hotel->id );

// Find current video index and next videos.
$current_index = 0;
$total_videos  = count( $all_videos );
foreach ( $all_videos as $idx => $assignment ) {
	if ( $assignment->video_id === $video_id ) {
		$current_index = $idx;
		break;
	}
}

// Get next 2 videos for "Continue Your Journey".
$next_videos = array();
$max_index   = min( $current_index + 3, $total_videos );
for ( $i = $current_index + 1; $i < $max_index; $i++ ) {
	$next_video = $video_repo->get_by_video_id( $all_videos[ $i ]->video_id );
	if ( $next_video ) {
		$next_videos[] = array(
			'video' => $next_video,
			'index' => $i + 1,
		);
	}
}

// Current tab for navigation.
$current_tab = 'home';

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
	<title><?php echo esc_html( $video->title ); ?> - <?php echo esc_html( $hotel->hotel_name ); ?></title>
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
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Local variable for navigation tabs.
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
		</div>
	</header>

<div class="flex-1 overflow-auto p-4 lg:p-8" style="background-color: rgb(240, 231, 215); min-height: calc(100vh - 64px);">
	<div class="max-w-7xl mx-auto">
		<div class="space-y-6">
			<!-- Back Button -->
			<a href="<?php echo esc_url( home_url( '/hotel/' . $hotel->hotel_slug . '/' ) ); ?>" class="inline-flex items-center gap-2 px-6 py-3 rounded transition-all hover:opacity-90 no-underline" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); border: none;">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
				<?php esc_html_e( 'Back to Meditation Library', 'hotel-chain' ); ?>
			</a>

			<!-- Video Player -->
			<?php
			$thumbnail_url = $video->thumbnail_url ? $video->thumbnail_url : ( $video->thumbnail_id ? wp_get_attachment_url( $video->thumbnail_id ) : '' );
			?>
			<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
				<div class="aspect-video rounded-lg relative overflow-hidden" style="background-color: rgb(0, 0, 0);">
					<?php if ( $video_url ) : ?>
						<!-- Thumbnail with play button -->
						<div id="video-poster-container" class="absolute inset-0 cursor-pointer" onclick="playMeditationVideo()">
							<?php if ( $thumbnail_url ) : ?>
								<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $video->title ); ?>" class="w-full h-full object-cover">
							<?php endif; ?>
							<div class="absolute inset-0 flex items-center justify-center" style="background-color: <?php echo $thumbnail_url ? 'rgba(0,0,0,0.4)' : 'transparent'; ?>;">
								<div class="text-center">
									<div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4 cursor-pointer transition-all hover:scale-110" style="background-color: rgb(240, 231, 215);">
										<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1" style="color: rgb(61, 61, 68);">
											<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
										</svg>
									</div>
									<p class="text-lg mb-2" style="color: rgb(240, 231, 215); font-family: var(--font-serif); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html( $video->title ); ?></p>
									<?php if ( $video->duration_label ) : ?>
										<p class="text-sm" style="color: rgb(196, 196, 196);"><?php esc_html_e( 'Duration:', 'hotel-chain' ); ?> <?php echo esc_html( $video->duration_label ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<!-- Video element (hidden initially) -->
						<video id="meditation-video" class="w-full h-full object-contain hidden" controls>
							<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4">
						</video>
					<?php else : ?>
						<div class="absolute inset-0 flex items-center justify-center">
							<div class="text-center">
								<div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgb(240, 231, 215);">
									<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1" style="color: rgb(61, 61, 68);">
										<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
									</svg>
								</div>
								<p class="text-lg mb-2" style="color: rgb(240, 231, 215); font-family: var(--font-serif); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html( $video->title ); ?></p>
								<?php if ( $video->duration_label ) : ?>
									<p class="text-sm" style="color: rgb(196, 196, 196);"><?php esc_html_e( 'Duration:', 'hotel-chain' ); ?> <?php echo esc_html( $video->duration_label ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<script>
			function playMeditationVideo() {
				const poster = document.getElementById('video-poster-container');
				const video = document.getElementById('meditation-video');
				if (poster) poster.classList.add('hidden');
				if (video) {
					video.classList.remove('hidden');
					video.play();
				}
			}

			document.addEventListener('DOMContentLoaded', function() {
				const video = document.getElementById('meditation-video');
				const poster = document.getElementById('video-poster-container');
				if (!video || !poster) return;

				video.addEventListener('pause', function() {
					if (!video.ended) {
						poster.classList.remove('hidden');
						video.classList.add('hidden');
					}
				});

				video.addEventListener('ended', function() {
					poster.classList.remove('hidden');
					video.classList.add('hidden');
				});
			});
			</script>

			<!-- Content Grid -->
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
				<!-- Main Content -->
				<div class="lg:col-span-2">
					<!-- Video Details -->
					<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
						<h3 class="mb-3 pb-3 border-b-2" style="border-color: rgb(196, 196, 196); font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 1.5rem; letter-spacing: 0.02em;"><?php echo esc_html( $video->title ); ?></h3>
						
						<div class="mb-4 flex gap-3">
							<?php if ( $video->duration_label ) : ?>
								<div class="px-4 py-2 rounded" style="background-color: rgb(240, 231, 215); font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem;">
									<?php echo esc_html( $video->duration_label ); ?> <?php esc_html_e( 'minutes', 'hotel-chain' ); ?>
								</div>
							<?php endif; ?>
							<?php if ( $video->category ) : ?>
								<div class="px-4 py-2 rounded" style="background-color: rgb(240, 231, 215); font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem;">
									<?php echo esc_html( $video->category ); ?>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( $video->description ) : ?>
							<div class="mb-6" style="font-family: var(--font-sans); color: rgb(61, 61, 68); line-height: 1.7;">
								<?php echo wp_kses_post( wpautop( $video->description ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $video->practice_tip ) ) : ?>
							<div class="p-6 rounded" style="background-color: rgb(240, 231, 215); border: 2px solid rgb(196, 196, 196);">
								<div class="mb-2" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600;"><?php esc_html_e( 'Practice Tip', 'hotel-chain' ); ?></div>
								<div style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; line-height: 1.6;"><?php echo wp_kses_post( $video->practice_tip ); ?></div>
							</div>
						<?php endif; ?>
					</div>

					<!-- Feedback Section -->
					<div class="bg-white rounded p-4 mt-6" style="border: 2px solid rgb(196, 196, 196);">
						<h3 class="mb-3 pb-3 border-b-2" style="border-color: rgb(196, 196, 196); font-family: var(--font-serif); color: rgb(61, 61, 68); font-size: 1.25rem; letter-spacing: 0.02em;"><?php esc_html_e( 'Share Your Feedback', 'hotel-chain' ); ?></h3>
						<div>
							<p class="mb-4" style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.875rem; line-height: 1.6;"><?php esc_html_e( 'Your feedback helps us improve the meditation experience', 'hotel-chain' ); ?></p>
							<button class="px-6 py-3 rounded-lg transition-all hover:opacity-90 flex items-center gap-2" style="background-color: rgb(61, 61, 68); color: rgb(240, 231, 215); font-family: var(--font-sans); font-weight: 600; border: none;">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" aria-hidden="true"><path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"></path></svg>
								<?php esc_html_e( 'Leave Feedback', 'hotel-chain' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Sidebar -->
				<div>
					<!-- Progress Section -->
					<div class="bg-white rounded p-4" style="border: 2px solid rgb(196, 196, 196);">
						<h3 class="mb-3 pb-3 border-b-2" style="border-color: rgb(196, 196, 196); font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600; font-size: 1rem;"><?php esc_html_e( 'Your Progress', 'hotel-chain' ); ?></h3>
						<div class="space-y-3 mb-4">
							<div class="flex justify-between">
								<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Completed:', 'hotel-chain' ); ?></span>
								<span id="progress-time" style="color: rgb(61, 61, 68); font-family: var(--font-sans);">0:00 / <?php echo esc_html( $video->duration_label ? $video->duration_label : '0:00' ); ?></span>
							</div>
							<div class="flex justify-between">
								<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Percentage:', 'hotel-chain' ); ?></span>
								<span id="progress-percent" style="color: rgb(61, 61, 68); font-family: var(--font-sans); font-weight: 600;">0%</span>
							</div>
							<div class="flex justify-between">
								<span style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( 'Series Progress:', 'hotel-chain' ); ?></span>
								<span style="color: rgb(61, 61, 68); font-family: var(--font-sans);"><?php echo esc_html( $current_index + 1 ); ?> / <?php echo esc_html( $total_videos ); ?></span>
							</div>
						</div>
						<div class="w-full h-3 rounded mb-2 overflow-hidden" style="background-color: rgb(229, 229, 229);">
							<div id="progress-bar" class="h-full rounded-l transition-all" style="background-color: rgb(61, 61, 68); width: 0%;"></div>
						</div>
						<div id="progress-remaining" class="text-center" style="color: rgb(122, 122, 122); font-family: var(--font-sans); font-size: 0.875rem;"><?php esc_html_e( '100% remaining', 'hotel-chain' ); ?></div>
					</div>

					<!-- Continue Your Journey -->
					<?php if ( ! empty( $next_videos ) ) : ?>
					<div class="bg-white rounded p-4 mt-4" style="border: 2px solid rgb(196, 196, 196);">
						<h3 class="mb-3 pb-3 border-b-2" style="border-color: rgb(196, 196, 196); font-family: var(--font-sans); color: rgb(61, 61, 68); font-weight: 600; font-size: 1rem;"><?php esc_html_e( 'Continue Your Journey', 'hotel-chain' ); ?></h3>
						<div class="space-y-3">
							<?php foreach ( $next_videos as $next ) : ?>
								<a href="<?php echo esc_url( home_url( '/hotel/' . $hotel->hotel_slug . '/meditation/' . $next['video']->video_id . '/' ) ); ?>" class="border-2 rounded p-3 cursor-pointer transition-all hover:shadow-md block no-underline" style="border-color: rgb(196, 196, 196);">
									<div class="flex gap-3">
										<div class="w-16 h-16 rounded flex items-center justify-center flex-shrink-0" style="background-color: rgb(240, 231, 215);">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8" aria-hidden="true" style="color: rgb(61, 61, 68);">
												<path d="M12 5a3 3 0 1 1 3 3m-3-3a3 3 0 1 0-3 3m3-3v1M9 8a3 3 0 1 0 3 3M9 8h1m5 0a3 3 0 1 1-3 3m3-3h-1m-2 3v-1"></path>
												<circle cx="12" cy="8" r="2"></circle>
												<path d="M12 10v12"></path>
												<path d="M12 22c4.2 0 7-1.667 7-5-4.2 0-7 1.667-7 5Z"></path>
												<path d="M12 22c-4.2 0-7-1.667-7-5 4.2 0 7 1.667 7 5Z"></path>
											</svg>
										</div>
										<div class="flex-1">
											<div class="mb-1" style="font-family: var(--font-sans); color: rgb(61, 61, 68); font-size: 0.875rem; font-weight: 600;"><?php echo esc_html( $next['video']->title ); ?></div>
											<div style="font-family: var(--font-sans); color: rgb(122, 122, 122); font-size: 0.75rem;">
												<?php
												/* translators: %d: Meditation number. */
												printf( esc_html__( 'Meditation %d', 'hotel-chain' ), esc_html( (string) $next['index'] ) );
												?>
												<?php if ( $next['video']->duration_label ) : ?>
													· <?php echo esc_html( $next['video']->duration_label ); ?>
												<?php endif; ?>
											</div>
										</div>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const video = document.getElementById('meditation-video');
	if (!video) return;

	const progressBar = document.getElementById('progress-bar');
	const progressTime = document.getElementById('progress-time');
	const progressPercent = document.getElementById('progress-percent');
	const progressRemaining = document.getElementById('progress-remaining');

	const videoId = <?php echo absint( $video_id ); ?>;
	const hotelId = <?php echo absint( $hotel->id ); ?>;
	const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

	let savedPercentage = 0;

	function formatTime(seconds) {
		const mins = Math.floor(seconds / 60);
		const secs = Math.floor(seconds % 60);
		return mins + ':' + (secs < 10 ? '0' : '') + secs;
	}

	function updateProgressUI(percent) {
		if (progressBar) progressBar.style.width = percent + '%';
		if (progressPercent) progressPercent.textContent = percent + '%';
		if (progressRemaining) progressRemaining.textContent = (100 - percent) + '% remaining';
	}

	// Load saved progress on page load.
	function loadProgress() {
		const formData = new FormData();
		formData.append('action', 'get_video_progress');
		formData.append('video_id', videoId);
		formData.append('hotel_id', hotelId);

		fetch(ajaxUrl, { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => {
				if (data.success && data.data) {
					savedPercentage = data.data.percentage || 0;
					updateProgressUI(Math.round(savedPercentage));

					// Seek video to saved position when metadata is loaded.
					if (savedPercentage > 0 && savedPercentage < 100) {
						const seekToSaved = function() {
							const duration = video.duration;
							if (duration > 0) {
								video.currentTime = (savedPercentage / 100) * duration;
							}
						};
						if (video.readyState >= 1) {
							seekToSaved();
						} else {
							video.addEventListener('loadedmetadata', seekToSaved, { once: true });
						}
					}
				}
			})
			.catch(err => console.log('Error loading progress:', err));
	}

	// Save progress to database.
	function saveProgress() {
		const current = video.currentTime;
		const duration = video.duration || 1;
		const percent = Math.round((current / duration) * 100);
		const completed = percent >= 95 ? 1 : 0;

		// Only save if percentage is higher than saved.
		if (percent <= savedPercentage) return;

		savedPercentage = percent;

		const formData = new FormData();
		formData.append('action', 'save_video_progress');
		formData.append('video_id', videoId);
		formData.append('hotel_id', hotelId);
		formData.append('duration', Math.round(current));
		formData.append('percentage', percent);
		formData.append('completed', completed);

		fetch(ajaxUrl, { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					updateProgressUI(percent);
				}
			})
			.catch(err => console.log('Error saving progress:', err));
	}

	// Update time display during playback.
	video.addEventListener('timeupdate', function() {
		const current = video.currentTime;
		const duration = video.duration || 1;
		if (progressTime) progressTime.textContent = formatTime(current) + ' / ' + formatTime(duration);
	});

	// Save progress on pause.
	video.addEventListener('pause', function() {
		saveProgress();
	});

	// Save progress on video end.
	video.addEventListener('ended', function() {
		saveProgress();
	});

	// Load saved progress on page load.
	loadProgress();
});
</script>

<?php wp_footer(); ?>
</body>
</html>
