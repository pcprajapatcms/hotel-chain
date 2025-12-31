<?php
/**
 * Style Settings service provider.
 *
 * @package HotelChain
 */

namespace HotelChain\Setup;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Support\StyleSettings;

/**
 * Style Settings service provider.
 */
class StyleSettingsService implements ServiceProviderInterface {
	/**
	 * Register service hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Enqueue Google Fonts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fonts' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_fonts' ), 5 );

		// Add Google Fonts link tags directly to head (as backup).
		add_action( 'wp_head', array( $this, 'add_font_links' ), 2 );
		add_action( 'admin_head', array( $this, 'add_font_links' ), 2 );

		// Inject custom CSS variables.
		add_action( 'wp_head', array( $this, 'inject_css_variables' ), 100 );
		add_action( 'admin_head', array( $this, 'inject_css_variables' ), 100 );

		// Add favicon.
		add_action( 'wp_head', array( $this, 'add_favicon' ), 1 );
		add_action( 'admin_head', array( $this, 'add_favicon' ), 1 );

		// Clear cache when settings are saved.
		add_action( 'admin_post_hotel_chain_save_system_settings', array( $this, 'clear_cache' ), 999 );
		add_action( 'admin_post_hotel_chain_reset_system_settings', array( $this, 'clear_cache' ), 999 );
	}

	/**
	 * Enqueue Google Fonts.
	 *
	 * @return void
	 */
	public function enqueue_fonts(): void {
		$elements       = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' );
		$enqueued_fonts = array();

		foreach ( $elements as $element ) {
			$font_url = StyleSettings::get_typography_font_url( $element );

			// If URL is empty, try to generate it from font name.
			if ( empty( $font_url ) ) {
				$font_name = StyleSettings::get_typography_font( $element );
				$font_url  = $this->generate_google_font_url( $font_name );
			}

			if ( ! empty( $font_url ) && ! in_array( $font_url, $enqueued_fonts, true ) ) {
				wp_enqueue_style(
					'hotel-chain-typography-' . $element,
					$font_url,
					array(),
					filemtime( get_template_directory() . '/assets/css/main.css' ) // Use theme version for cache busting.
				);
				$enqueued_fonts[] = $font_url;
			}
		}
	}

	/**
	 * Add Google Fonts link tags directly to head.
	 *
	 * @return void
	 */
	public function add_font_links(): void {
		$elements       = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' );
		$enqueued_fonts = array();

		foreach ( $elements as $element ) {
			$font_url = StyleSettings::get_typography_font_url( $element );

			// If URL is empty, try to generate it from font name.
			if ( empty( $font_url ) ) {
				$font_name = StyleSettings::get_typography_font( $element );
				$font_url  = $this->generate_google_font_url( $font_name );
			}

			if ( ! empty( $font_url ) && ! in_array( $font_url, $enqueued_fonts, true ) ) {
				// Enqueue Google Fonts via wp_enqueue_style instead of direct link tag.
				wp_enqueue_style(
					'hotel-chain-google-font-' . md5( $font_url ),
					$font_url,
					array(),
					'1.0.0'
				);
				$enqueued_fonts[] = $font_url;
			}
		}
	}

	/**
	 * Generate Google Fonts URL from font name.
	 *
	 * @param string $font_name Font name.
	 * @return string Font URL or empty string if not found.
	 */
	private function generate_google_font_url( string $font_name ): string {
		// Map of font names to their Google Fonts URLs.
		$font_map = array(
			'Inter'            => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
			'Playfair Display' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap',
			'Roboto'           => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
			'Open Sans'        => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap',
			'Lato'             => 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
			'Montserrat'       => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap',
			'Poppins'          => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
			'Raleway'          => 'https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700&display=swap',
			'Oswald'           => 'https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&display=swap',
			'Roboto Condensed' => 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;500;600;700&display=swap',
			'Ubuntu'           => 'https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;600;700&display=swap',
			'Lobster'          => 'https://fonts.googleapis.com/css2?family=Lobster&display=swap',
			'Pacifico'         => 'https://fonts.googleapis.com/css2?family=Pacifico&display=swap',
			'Anton'            => 'https://fonts.googleapis.com/css2?family=Anton&display=swap',
			'Exo 2'            => 'https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700&display=swap',
			'Bebas Neue'       => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap',
			'Bitter'           => 'https://fonts.googleapis.com/css2?family=Bitter:wght@300;400;500;600;700&display=swap',
			'Fira Sans'        => 'https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&display=swap',
			'Jura'             => 'https://fonts.googleapis.com/css2?family=Jura:wght@300;400;500;600;700&display=swap',
			'Kanit'            => 'https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap',
			'Kaushan Script'   => 'https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap',
			'Lobster Two'      => 'https://fonts.googleapis.com/css2?family=Lobster+Two:wght@300;400;700&display=swap',
			'Noto Serif'       => 'https://fonts.googleapis.com/css2?family=Noto+Serif:wght@300;400;500;600;700&display=swap',
			'Merriweather'     => 'https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&display=swap',
			'Source Sans Pro'  => 'https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap',
		);

		return $font_map[ $font_name ] ?? '';
	}

	/**
	 * Inject CSS variables for style settings.
	 *
	 * @return void
	 */
	public function inject_css_variables(): void {
		$elements         = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' );
		$typography_fonts = array();
		foreach ( $elements as $element ) {
			$typography_fonts[ $element ] = StyleSettings::get_typography_font( $element );
		}

		$button_colors = array(
			'primary'   => StyleSettings::get_button_color( 'primary' ),
			'secondary' => StyleSettings::get_button_color( 'secondary' ),
			'success'   => StyleSettings::get_button_color( 'success' ),
			'info'      => StyleSettings::get_button_color( 'info' ),
			'warning'   => StyleSettings::get_button_color( 'warning' ),
			'danger'    => StyleSettings::get_button_color( 'danger' ),
		);

		?>
		<style id="hotel-chain-style-settings">
		:root {
			/* Typography Fonts */
			--font-h1: '<?php echo esc_html( $typography_fonts['h1'] ); ?>', serif;
			--font-h2: '<?php echo esc_html( $typography_fonts['h2'] ); ?>', serif;
			--font-h3: '<?php echo esc_html( $typography_fonts['h3'] ); ?>', serif;
			--font-h4: '<?php echo esc_html( $typography_fonts['h4'] ); ?>', serif;
			--font-h5: '<?php echo esc_html( $typography_fonts['h5'] ); ?>', serif;
			--font-h6: '<?php echo esc_html( $typography_fonts['h6'] ); ?>', serif;
			--font-p: '<?php echo esc_html( $typography_fonts['p'] ); ?>', sans-serif;

			/* Button Colors */
			--button-primary-color: <?php echo esc_attr( $button_colors['primary'] ); ?>;
			--button-secondary-color: <?php echo esc_attr( $button_colors['secondary'] ); ?>;
			--button-success-color: <?php echo esc_attr( $button_colors['success'] ); ?>;
			--button-info-color: <?php echo esc_attr( $button_colors['info'] ); ?>;
			--button-warning-color: <?php echo esc_attr( $button_colors['warning'] ); ?>;
			--button-danger-color: <?php echo esc_attr( $button_colors['danger'] ); ?>;
		}

		/* Apply typography fonts, weights, and responsive sizes - Mobile First */
		body, p {
			font-family: var(--font-p);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'p', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'p' ) ); ?>;
		}

		h1 { 
			font-family: var(--font-h1);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h1', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'h1' ) ); ?>;
		}
		h2 { 
			font-family: var(--font-h2);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h2', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'h2' ) ); ?>;
		}
		h3 { 
			font-family: var(--font-h3);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h3', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'h3' ) ); ?>;
		}
		h4 { 
			font-family: var(--font-h4);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h4', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'h4' ) ); ?>;
		}
		h5 { 
			font-family: var(--font-h5);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h5', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'h5' ) ); ?>;
		}
		h6 { 
			font-family: var(--font-h6);
			font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h6', 'mobile' ) ); ?>px;
			font-weight: <?php echo esc_attr( StyleSettings::get_typography_font_weight( 'h6' ) ); ?>;
		}

		/* Tablet styles (768px and up) */
		@media (min-width: 768px) {
			body, p {
				font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'p', 'tablet' ) ); ?>px;
			}
			h1 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h1', 'tablet' ) ); ?>px; }
			h2 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h2', 'tablet' ) ); ?>px; }
			h3 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h3', 'tablet' ) ); ?>px; }
			h4 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h4', 'tablet' ) ); ?>px; }
			h5 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h5', 'tablet' ) ); ?>px; }
			h6 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h6', 'tablet' ) ); ?>px; }
		}

		/* Desktop styles (1024px and up) */
		@media (min-width: 1024px) {
			body, p {
				font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'p', 'desktop' ) ); ?>px;
			}
			h1 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h1', 'desktop' ) ); ?>px; }
			h2 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h2', 'desktop' ) ); ?>px; }
			h3 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h3', 'desktop' ) ); ?>px; }
			h4 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h4', 'desktop' ) ); ?>px; }
			h5 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h5', 'desktop' ) ); ?>px; }
			h6 { font-size: <?php echo esc_attr( (string) StyleSettings::get_responsive_font_size( 'h6', 'desktop' ) ); ?>px; }
		}

		/* Button color classes */
		.btn-primary { background-color: var(--button-primary-color); }
		.btn-secondary { background-color: var(--button-secondary-color); }
		.btn-success { background-color: var(--button-success-color); }
		.btn-info { background-color: var(--button-info-color); }
		.btn-warning { background-color: var(--button-warning-color); }
		.btn-danger { background-color: var(--button-danger-color); }
		</style>
		<?php
	}

	/**
	 * Add favicon to head.
	 *
	 * @return void
	 */
	public function add_favicon(): void {
		$favicon_url = StyleSettings::get_favicon_url();
		if ( ! empty( $favicon_url ) ) {
			echo '<link rel="icon" type="image/png" href="' . esc_url( $favicon_url ) . '" />' . "\n";
		}
	}

	/**
	 * Clear style settings cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		StyleSettings::clear_cache();
	}
}

