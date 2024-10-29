<?php
namespace GPLSCore\GPLS_AEI\Modules\Services;

/**
 * Helpers Class
 *
 */
trait Helpers {

	/**
	 * All Plugins Main Menu Slug.
	 *
	 * @var string
	 */
	public $plugins_main_menu_slug = 'gpls-main-plugins-menu';

	/**
	 * License Endpoint
	 *
	 * @var string
	 */
	public $plugin_license_route = 'https://grandplugins.com/wp-json/gpls/v1/plugins/licenses/activate';

	/**
	 * Contact Us Link
	 *
	 * @var string
	 */
	public $contact_us_link = 'https://grandplugins.com/contact-us';

	/**
	 * My Account Link
	 *
	 * @var string
	 */
	public $my_account_link = 'https://grandplugins.com/my-account';

	/**
	 * Downloads Link
	 *
	 * @var string
	 */
	public $downloads_link = 'https://grandplugins.com/my-account/downloads/';

	/**
	 * Plugin Update Check Route
	 *
	 * @var string
	 */
	public $plugin_update_route = 'https://grandplugins.com/wp-json/gpls/v1/plugins/update';

	/**
	 * Pro Features for a product Route.
	 *
	 * @var string
	 */
	public $plugin_pro_features_route = 'https://grandplugins.com/wp-json/gpls/v1/general/pro/features';

	/**
	 * Ajax Response
	 *
	 * @param string $error
	 * @param array  $result
	 *
	 * @return void
	 */
	private function ajax_response( $error, $result ) {
		wp_send_json(
			array(
				'status' => ( ! empty( $error ) ) ? false : true,
				'result' => ( ! empty( $error ) ) ? $error : $result,
			)
		);
	}
}
