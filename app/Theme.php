<?php
/**
 * Main theme class.
 *
 * @package HotelChain
 */

namespace HotelChain;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Setup\Assets;
use HotelChain\Setup\HotelRoutes;
use HotelChain\Setup\Roles;
use HotelChain\Setup\Sidebars;
use HotelChain\Setup\ThemeSupport;
use HotelChain\Setup\Videos;
use HotelChain\Support\AssetResolver;
use HotelChain\Admin\HotelsPage;
use HotelChain\Admin\HotelView;
use HotelChain\Admin\VideosPage;
use HotelChain\Admin\VideoLibraryPage;

/**
 * Main theme class.
 */
class Theme {
	/**
	 * Service providers.
	 *
	 * @var ServiceProviderInterface[]
	 */
	private array $providers = array();

	/**
	 * Initialize the theme.
	 *
	 * @return void
	 */
	public static function init(): void {
		( new self() )->boot();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$assets = new AssetResolver();

		$this->providers = array(
			new ThemeSupport(),
			new HotelRoutes(),
			new HotelsPage(),
			new HotelView(),
			new VideosPage(),
			new VideoLibraryPage(),
			new Assets( $assets ),
			new Videos(),
			new Roles(),
			new Sidebars(),
		);
	}

	/**
	 * Boot all service providers.
	 *
	 * @return void
	 */
	private function boot(): void {
		foreach ( $this->providers as $provider ) {
			$provider->register();
		}
	}
}
