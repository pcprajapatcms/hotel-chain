<?php
/**
 * Main theme class.
 *
 * @package HotelChain
 */

namespace HotelChain;

use HotelChain\Contracts\ServiceProviderInterface;
use HotelChain\Database\Migration;
use HotelChain\Setup\Assets;
use HotelChain\Setup\CustomLogin;
use HotelChain\Setup\GuestExpiration;
use HotelChain\Setup\HotelRoutes;
use HotelChain\Setup\MenuVisibility;
use HotelChain\Setup\Roles;
use HotelChain\Setup\Sidebars;
use HotelChain\Setup\StyleSettingsService;
use HotelChain\Setup\ThemeSupport;
use HotelChain\Setup\Videos;
use HotelChain\Support\AssetResolver;
use HotelChain\Admin\HotelsPage;
use HotelChain\Admin\HotelView;
use HotelChain\Admin\HotelEdit;
use HotelChain\Admin\VideosPage;
use HotelChain\Admin\VideoLibraryPage;
use HotelChain\Admin\VideoTaxonomyPage;
use HotelChain\Admin\VideoRequestsPage;
use HotelChain\Admin\DatabaseToolsPage;
use HotelChain\Admin\AdminDashboardPage;
use HotelChain\Admin\SystemAnalyticsPage;
use HotelChain\Admin\SystemSettingsPage;
use HotelChain\Frontend\HotelDashboard;
use HotelChain\Frontend\HotelProfilePage;
use HotelChain\Frontend\GuestRegistration;

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
			new Migration(),
			new ThemeSupport(),
			new CustomLogin(),
			new GuestExpiration(),
			new HotelRoutes(),
			new HotelsPage(),
			new HotelView(),
			new HotelEdit(),
			new VideosPage(),
			new VideoLibraryPage(),
			new VideoTaxonomyPage(),
			new VideoRequestsPage(),
			new DatabaseToolsPage(),
			new AdminDashboardPage(),
			new SystemAnalyticsPage(),
			new SystemSettingsPage(),
			new HotelDashboard(),
			new HotelProfilePage(),
			new GuestRegistration(),
			new \HotelChain\Frontend\HotelGuestManagementPage(),
			new Assets( $assets ),
			new Videos(),
			new Roles(),
			new Sidebars(),
			new MenuVisibility(),
			new StyleSettingsService(),
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
