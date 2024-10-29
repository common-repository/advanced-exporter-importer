<?php

/**
 * Plugin Name:  Advanced Exporter Importer [GrandPlugins]
 * Description:  This Plugin offers a way to export specific custom post types including their media with many filters in a single xml file, export and import media's files using a zip file, also export and import the menus in a clean way without duplicating any other posts, pages, etc...
 * Author:       GrandPlugins
 * Author URI:   https://profiles.wordpress.org/grandplugins/
 * Plugin URI:   https://grandplugins.com/product/advanced-exporter-importer-pro/
 * Text Domain:  gpls-cpt-exporter-importer
 * Domain Path:  /languages
 * Requires PHP: 5.6
 * Std Name:     gpls-cpt-exporter-importer
 * Version:      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GPLS_AEI_Exporter_Importer' ) ) :

	/**
	 * Exporter Main Class.
	 */
	class GPLS_AEI_Exporter_Importer {

		/**
		 * Single Instance
		 *
		 * @var object
		 */
		private static $instance;

		/**
		 * Plugin Info
		 *
		 * @var array
		 */
		private static $plugin_info;

		/**
		 * Debug Mode Status
		 *
		 * @var bool
		 */
		protected $debug;


		/**
		 * Errors Notices.
		 *
		 * @var array
		 */
		private $notices;

		/**
		 * Menus Notices.
		 *
		 * @var array
		 */
		private $menus_notices;

		/**
		 * Exporter Helper Object
		 *
		 * @var object
		 */
		private $exporter_helper;

		/**
		 * Importer Helper Object
		 *
		 * @var object
		 */
		private $importer_helper;

		/**
		 * Core Object
		 *
		 * @return object
		 */
		private static $core;

		/**
		 * Singular init Function.
		 *
		 * @return void
		 */
		public static function init() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Core Actions Hook.
		 *
		 * @return void
		 */
		public static function core_actions( $action_type ) {
			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'core/bootstrap.php';
			self::$core = new GPLSCore\GPLS_AEI\Core( self::$plugin_info );
			if ( 'activated' === $action_type ) {
				self::$core->plugin_activated();
			} elseif ( 'deactivated' === $action_type ) {
				self::$core->plugin_deactivated();
			} elseif ( 'uninstall' === $action_type ) {
				self::$core->plugin_uninstalled();
			}
		}

		/**
		 * Plugin Activated Hook.
		 *
		 * @return void
		 */
		public static function plugin_activated() {
			self::setup_plugin_info();
			if ( is_plugin_active( self::$plugin_info['name'] . '-pro/' . self::$plugin_info['name'] . '-pro.php' ) ) {
				deactivate_plugins( plugin_basename( self::$plugin_info['name'] . '-pro/' . self::$plugin_info['name'] . '-pro.php' ) );
			}
			self::core_actions( 'activated' );
		}

		/**
		 * Plugin Deactivated Hook.
		 *
		 * @return void
		 */
		public static function plugin_deactivated() {
			self::setup_plugin_info();
			self::core_actions( 'deactivated' );
		}

		/**
		 * Plugin Installed hook.
		 *
		 * @return void
		 */
		public static function plugin_uninstalled() {
			self::setup_plugin_info();
			self::core_actions( 'uninstall' );
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			self::setup_plugin_info();
			$this->load_languages();
			$this->setup();
			$this->includes();
			$this->hooks();
			self::$core            = new GPLSCore\GPLS_AEI\Core( self::$plugin_info );
			$this->exporter_helper = new GPLS_AEI_Exporter_Helper( self::$core, self::$plugin_info );
			$this->importer_helper = new GPLS_AEI_Importer_Helper( self::$core, self::$plugin_info );
		}

		/**
		 * Includes Files
		 *
		 * @return void
		 */
		public function includes() {
			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'core/bootstrap.php';
			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'libs/exporter-trait.php';
			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'libs/attachment-helper.php';
			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'libs/exporter-helper.php';
			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'libs/importer-helper.php';
		}

		/**
		 * Load languages Folder.
		 *
		 * @return void
		 */
		public function load_languages() {
			load_plugin_textdomain( self::$plugin_info['text_domain'], false, self::$plugin_info['path'] . 'languages/' );
		}

		/**
		 * Setup Function - Initialize Vars
		 *
		 * @return void
		 */
		public function setup() {
			$this->options_page_slug = self::$plugin_info['name'];
			$this->options_page_url  = admin_url( 'tools.php' ) . '?page=' . self::$plugin_info['name'];
			$this->debug             = true;
			$this->menus_notices     = array(
				1 => array(
					'notice' => __( 'Update the Menu Item Target ID from the new site or leave it empty if the post, page, term, etc.. that the menu item links to it has the same name on the new site', 'gpls-cpt-exporter-importer' ),
					'type'   => 'warning',
				),
			);
		}

		/**
		 * Set Plugin Info
		 *
		 * @return array
		 */
		public static function setup_plugin_info() {
			$plugin_data = get_file_data(
				__FILE__,
				array(
					'Version'     => 'Version',
					'Name'        => 'Plugin Name',
					'URI'         => 'Plugin URI',
					'SName'       => 'Std Name',
					'text_domain' => 'Text Domain',
				),
				false
			);

			self::$plugin_info = array(
				'id'           => 581,
				'basename'     => plugin_basename( __FILE__ ),
				'version'      => $plugin_data['Version'],
				'name'         => $plugin_data['SName'],
				'text_domain'  => $plugin_data['text_domain'],
				'file'         => __FILE__,
				'plugin_url'   => $plugin_data['URI'],
				'public_name'  => $plugin_data['Name'],
				'path'         => trailingslashit( plugin_dir_path( __FILE__ ) ),
				'url'          => trailingslashit( plugin_dir_url( __FILE__ ) ),
				'options_page' => $plugin_data['SName'],
				'localize_var' => str_replace( '-', '_', $plugin_data['SName'] ) . '_localize_data',
				'type'         => 'free',
			);
		}

		/**
		 * Define Constants
		 *
		 * @param string $key
		 * @param string $value
		 * @return void
		 */
		public function define( $key, $value ) {
			if ( ! defined( $key ) ) {
				define( $key, $value );
			}
		}

		/**
		 * Hooks Function
		 *
		 * @return void
		 */
		public function hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10 );
			add_action( 'admin_menu', array( $this, 'export_page' ) );
			add_action( self::$plugin_info['name'] . 'settings_content', array( $this, 'export_page_tabs_content' ) );
			add_filter( 'plugin_action_links_' . self::$plugin_info['basename'], array( $this, 'settings_link' ), 10, 1 );

			add_action( 'wp_ajax_' . self::$plugin_info['name'] . '-export_process', array( $this, 'export_cpt_submit' ) );
		}

		/**
		 * Plugin Settings Link
		 *
		 * @param array $links
		 * @return array
		 */
		public function settings_link( $links ) {
			$links[] = '<a href="' . admin_url( 'tools.php?page=' . $this->options_page_slug ) . '&tab=export" >' . __( 'Settings' ) . '</a>';
			$links[] = '<a target="_blank" href="' . self::$plugin_info['plugin_url'] . '" >' . __( 'Pro' ) . '</a>';
			return $links;
		}

		/**
		 * Enqueue and Localize Assets Function
		 *
		 * @return void
		 */
		public function admin_enqueue_scripts( $hook_suffix ) {
			if ( is_admin() && 'tools_page_' . $this->options_page_slug === $hook_suffix ) {
				wp_enqueue_style( self::$plugin_info['name'] . '-admin-bootstrap', self::$core->core_assets_lib( 'bootstrap', 'css' ), array(), 'all' );
				if ( ! wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}

				wp_enqueue_style( 'wp-jquery-ui-dialog' );
				wp_enqueue_script( 'jquery-ui-dialog' );

				wp_enqueue_script( self::$plugin_info['name'] . '-core-admin-bootstrap-js', self::$core->core_assets_lib( 'bootstrap.bundle', 'js' ), array( 'jquery' ), self::$plugin_info['version'], true );
				wp_enqueue_script( self::$plugin_info['name'] . '-admin-actions', self::$plugin_info['url'] . 'assets/dist/js/admin/scripts.min.js', array( 'jquery', self::$plugin_info['name'] . '-core-admin-bootstrap-js' ), self::$plugin_info['version'], true );

				wp_localize_script(
					self::$plugin_info['name'] . '-admin-actions',
					self::$plugin_info['localize_var'],
					array(
						'prefix'                 => self::$plugin_info['name'],
						'export_cpt_list_action' => self::$plugin_info['name'] . '-cpt-posts-list',
						'import_start_action'    => self::$plugin_info['name'] . '-import_process',
						'export_start_action'    => self::$plugin_info['name'] . '-export_process',
						'ajax_url'               => admin_url( 'admin-ajax.php' ),
						'nonce'                  => wp_create_nonce( self::$plugin_info['name'] . '-nonce' ),
						'cpt_posts_list'         => self::$plugin_info['name'] . '-cpt-posts-list',
						'export_finish_cookie'   => self::$plugin_info['name'] . '-export-finish',
						'cookie_path'            => ADMIN_COOKIE_PATH,
						'cookie_domain'          => COOKIE_DOMAIN,
						'cpt_export_url'         => admin_url( 'tools.php?page=' . self::$plugin_info['name'] ),
						'menu_export_url'        => admin_url( 'tools.php?page=' . self::$plugin_info['name'] . '&stab=menus' ),
					)
				);
			}
		}

		/**
		 * Resolve Errors to Admin Notices.
		 *
		 * @return void
		 */
		public function admin_notices( $type ) {
			$current_screen = get_current_screen();
			if ( ! empty( $_GET['page'] ) && self::$plugin_info['name'] === wp_unslash( $_GET['page'] ) && ! empty( $_GET['tab'] ) && 'menus' === wp_unslash( $_GET['tab'] ) ) {
				if ( 'menus' === $type ) {
					$this->exporter_helper->add_notice( $this->menus_notices[1]['notice'], $this->menus_notices[1]['type'] );
				} else {
					$this->exporter_helper->add_notice( 'Export and Import Process might take some time in large Database because of the many search queries while exporting and the remapping while importing!', 'warning' );
				}
			}
		}

		/**
		 * Register Export admin Page
		 *
		 * @return void
		 */
		public function export_page() {
			add_submenu_page( 'tools.php', 'CPT Exporter', 'Advanced Exporter Importer', 'manage_options', $this->options_page_slug, array( $this, 'export_page_content' ) );
		}

		/**
		 * Export Admin Page
		 *
		 * @return void
		 */
		public function export_page_content() {
			?>
			<nav class="nav-tab-wrapper nav-tab-wrapper wp-clearfix">
				<!-- Export Tab -->
				<a href="<?php echo admin_url( 'tools.php?page=' . self::$plugin_info['name'] . '&tab=export' ); ?>" class="nav-tab<?php echo ( empty( $_GET['tab'] ) || ( isset( $_GET['tab'] ) && 'export' == $_GET['tab'] ) ? ' nav-tab-active' : '' ); ?>"><?php _e( 'Export', 'gpls-cpt-exporter-importer' ); ?></a>

				<!-- Import Tab -->
				<a href="<?php echo admin_url( 'tools.php?page=' . self::$plugin_info['name'] . '&tab=import' ); ?>" class="nav-tab<?php echo ( isset( $_GET['tab'] ) && 'import' == $_GET['tab'] ? ' nav-tab-active' : '' ); ?>"><?php _e( 'Import', 'gpls-cpt-exporter-importer' ); ?></a>

				<!-- Pro Tab -->
				<?php self::$core->pro_tab( 'tools', true ); ?>
			</nav>
			<?php
			do_action( self::$plugin_info['name'] . 'settings_content' );
		}

		/**
		 * Tabs Content
		 *
		 * @return void
		 */
		public function export_page_tabs_content() {
			$cpts = $this->exporter_helper->get_cpts();

			set_query_var( 'core_obj', self::$core );
			set_query_var( 'plugin_info', self::$plugin_info );
			set_query_var( 'exporter_helper', $this->exporter_helper );
			set_query_var( 'cpts', $cpts );

			if ( empty( $_GET['tab'] ) || ( ! empty( $_GET['tab'] ) && 'pro' !== sanitize_text_field( $_GET['tab'] ) ) ) {
				$this->exporter_helper->add_notice( 'Export and Import Process might take some time in large Database because of the many search queries while exporting and remapping while importing!', 'warning' );
			}

			if ( empty( $_GET['tab'] ) || ( ! empty( $_GET['tab'] ) && 'export' === sanitize_text_field( $_GET['tab'] ) ) ) {
				$this->admin_notices( 'menus' );
				load_template( self::$plugin_info['path'] . '/views/export-page.php' );
			} elseif ( ! empty( $_GET['tab'] ) && 'import' === sanitize_text_field( $_GET['tab'] ) ) {
				set_query_var( 'importer_helper', $this->importer_helper );
				$this->admin_notices( 'cpts' );
				load_template( self::$plugin_info['path'] . '/views/import-page.php' );
			} elseif ( ! empty( $_GET['tab'] ) && 'pro' === sanitize_text_field( $_GET['tab'] ) ) {
				do_action( self::$plugin_info['name'] . '-pro-tab-content' );
			}



			self::$core->default_footer_section();

		}

		/**
		 * Handle Export Form Submit
		 *
		 * @return void
		 */
		public function export_cpt_submit() {
			if ( ! empty( $_POST[ self::$plugin_info['name'] . '-export-submit' ] ) && ! empty( $_POST[ self::$plugin_info['name'] . '-export-nonce' ] ) && check_admin_referer( self::$plugin_info['name'] . '-export-nonce', self::$plugin_info['name'] . '-export-nonce' ) ) {

				// CPT Export.
				if ( 'cpt' === sanitize_text_field( wp_unslash( $_POST['export_type'] ) ) ) {

					if ( ! empty( $_POST['cpt_name'] ) ) {
						$cpt_names            = array_map( 'sanitize_text_field', $_POST['cpt_name'] );
						$cpt_names            = array_intersect( $cpt_names, $this->exporter_helper->get_cpts() );
						$terms                = array();
						$filterable_cpt_names = array();
						$specific_cpt_names   = array();
						$direct_posts_ids     = array();

						if ( ! empty( $cpt_names ) ) {
							$filterable_cpt_names = $cpt_names;

							// CPT by Filters //
							if ( ! empty( $filterable_cpt_names ) ) {

								// CPT statuses.
								$statuses             = ! empty( $_POST['cpt_statuses'] ) ? wp_unslash( $_POST['cpt_statuses'] ) : array();
								$filterable_cpt_names = $this->exporter_helper->filter_cpt_statuses( $filterable_cpt_names, $statuses );

								// CPT Authors.
								$authors              = ! empty( $_POST['cpt_authors'] ) ? wp_unslash( $_POST['cpt_authors'] ) : array();
								$filterable_cpt_names = $this->exporter_helper->filter_cpt_authors( $filterable_cpt_names, $authors );

								// CPT Dates.
								$cpt_start_dates      = ! empty( $_POST['cpt_start_date'] ) ? wp_unslash( $_POST['cpt_start_date'] ) : array();
								$cpt_end_dates        = ! empty( $_POST['cpt_end_date'] ) ? wp_unslash( $_POST['cpt_end_date'] ) : array();
								$filterable_cpt_names = $this->exporter_helper->filter_cpt_dates( $filterable_cpt_names, $cpt_start_dates, $cpt_end_dates );
							}

							$step = ! empty( $_POST['step'] ) ? intval( wp_unslash( $_POST['step'] ) ) : 0;

							$this->exporter_helper->handle_export( $filterable_cpt_names, array(), array(), $cpt_names, $step );
							die();

						} else {
							// Invalid Custom Post Type
							wp_send_json_error( __( 'Invalid Post Type Name!', 'gpls-cpt-exporter-importer' ) );
						}
					} else {
						// Invalid Input
						wp_send_json_error( __( 'Select a Post Type!', 'gpls-cpt-exporter-importer' ) );
					}
				}
			}
		}
	}

	add_action( 'plugins_loaded', array( 'GPLS_AEI_Exporter_Importer', 'init' ), 10 );
	register_activation_hook( __FILE__, array( 'GPLS_AEI_Exporter_Importer', 'plugin_activated' ) );
	register_deactivation_hook( __FILE__, array( 'GPLS_AEI_Exporter_Importer', 'plugin_deactivated' ) );
	register_uninstall_hook( __FILE__, array( 'GPLS_AEI_Exporter_Importer', 'plugin_uninstalled' ) );
endif;
