<?php
namespace GPLSCore\GPLS_AEI\Modules\Services;

use GPLSCore\GPLS_AEI\Modules\Services\Helpers;

defined( 'ABSPATH' ) || exit();

/**
 * Helpers Class
 */
class Pro_Tab {

	use Helpers;

	/**
	 * Core Object.
	 *
	 * @var object
	 */
	private $core;

	/**
	 * Plugin Basename
	 *
	 * @var string
	 */
	public static $plugin_info;

	/**
	 * Single Instance
	 *
	 * @var object
	 */
	private static $instance = null;

	/**
	 * Constructor
	 *
	 * @param array $plugin_info
	 * @param array $general_vars
	 */
	private function __construct( $plugin_info, $core ) {
		$this->core        = $core;
		self::$plugin_info = $plugin_info;

		$this->hooks();
	}

	/**
	 * Single Instance Initalization
	 *
	 * @param array $plugin_info
	 * @param array $general_vars
	 * @return object
	 */
	public static function init( $plugin_info, $core ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $plugin_info, $core );
		}
		return self::$instance;
	}

	/**
	 * Hooks Functions.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( self::$plugin_info['name'] . '-pro-tab-content', array( $this, 'get_pro_features' ) );
	}

	/**
	 * Additional Scripts.
	 *
	 * @return void
	 */
	public function scripts() {
		wp_enqueue_style( self::$plugin_info['name'] . '-magnif-popup-style', $this->core->core_assets_lib( 'magnific-popup', 'css' ), array(), 'all' );
		wp_enqueue_script( self::$plugin_info['name'] . '-magnif-popup-script', $this->core->core_assets_lib( 'jquery.magnific-popup', 'js' ), array( 'jquery' ), '1.0.0', false );
	}

	/**
	 * Pro Tab Link
	 *
	 * @param string  $main_page
	 * @param boolean $echo
	 * @return string
	 */
	public static function pro_tab( $main_page, $echo = false ) {
		$content = '';
		ob_start();
		?>
		<a href="<?php echo admin_url( $main_page . '.php?page=' . self::$plugin_info['options_page'] ) . '&tab=pro'; ?>" class="nav-tab<?php echo ( isset( $_GET['tab'] ) && 'pro' == $_GET['tab'] ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'Pro' ); ?></a>
		<?php
		$content = ob_get_clean();

		if ( $echo ) {
			echo $content;
		} else {
			return $content;
		}
	}

	/**
	 * Get Pro Features HTML.
	 *
	 * @param boolean $other_check
	 * @param int     $counter
	 *
	 * @return void
	 */
	public function get_pro_features( $counter = 0 ) {
		$product_id = self::$plugin_info['id'];
		$response   = wp_remote_post(
			$this->plugin_pro_features_route,
			array(
				'body' => array(
					'id' => $product_id,
				),
			)
		);
		$code       = wp_remote_retrieve_response_code( $response );
		$body       = (array) json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $body ) && ( $counter < 3 ) ) {
			$this->get_pro_features( ++$counter );
		}

		if ( ! empty( $body['content'] ) ) {
			echo $body['content'];
			echo $this->pro_features_popup_js();
		}
	}

	/**
	 * Pro Features Popup Js Code.
	 *
	 * @return void
	 */
	private function pro_features_popup_js() {
		$js_code = '';
		ob_start();
		?>
		<script type="text/javascript">
		jQuery('.pro-feature-img').magnificPopup(
			{
				type: 'image',
				removalDelay: 300,
				gallery: {
					enabled: true
				}
			}
		);
		</script>
		<?php
		$js_code = ob_get_clean();
		return $js_code;
	}
}
