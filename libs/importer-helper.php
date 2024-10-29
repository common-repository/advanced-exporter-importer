<?php defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require plugin_dir_path( __FILE__ ) . '/xml-parser.php';

/**
 * Menus Exporter Class
 */
class GPLS_AEI_Importer_Helper {

	/**
	 * Export Type.
	 *
	 * @var string
	 */
	public $export_type = 'cpt';

	/**
	 * Parser Object
	 *
	 * @var object
	 */
	public $parser;

	/**
	 * Max. supported WXR version
	 *
	 * @var float
	 */
	public $max_wxr_version = 1.2;

	/**
	 *  WXR attachment ID
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Imported Menu Items IDs Mapping [ old ID => new ID ]
	 *
	 * @var array
	 */
	public $processed_menu_items = array();

	/**
	 * Processed Nav Menus terms [ Old ID => new ID ]
	 *
	 * @var array
	 */
	public $processed_terms = array();

	/**
	 * Menu Items Children needs to be connected with It's parent [ Parent Old ID  => new Child ID ]
	 *
	 * @var array
	 */
	public $missing_menu_items_parent = array();

	/**
	 * Information to import from WXR file
	 *
	 * @var string
	 */
	public $version = '1.0';

	/**
	 * Plugin Info.
	 *
	 * @var object
	 */
	public $plugin_info;

	/**
	 * Core Object
	 *
	 * @var object
	 */
	public $core;

	/**
	 * Imported Terms
	 *
	 * @var array
	 */
	public $terms = array();

	/**
	 * Imported Authors
	 *
	 * @var array
	 */
	public $authors = array();

	/**
	 * Imported Attribute Taxonomies
	 *
	 * @var array
	 */
	public $attribute_taxonomies = array();

	/**
	 * Imported Base URL.
	 *
	 * @var string
	 */
	public $base_url = '';

	/**
	 * Current Site URL.
	 *
	 * @var string
	 */
	public $site_url = '';

	/**
	 * Processed Authors.
	 *
	 * @var array
	 */
	public $processed_authors = array();

	/**
	 * Imported Author Mapping
	 *
	 * @var array
	 */
	public $author_mapping = array();

	/**
	 * Processed Posts
	 *
	 * @var array
	 */
	public $processed_posts = array();

	/**
	 * Posts Exists Array.
	 *
	 * @var array
	 */
	public $posts_exists = array();

	/**
	 * Processed Attachments.
	 *
	 * @var array
	 */
	public $processed_attachments = array();

	/**
	 * Processed Missing Taxonomies names.
	 *
	 * @var array
	 */
	public $processed_missing_taxonomies_names = array();

	/**
	 * Processed Terms that have thumbnail_id termmeta.
	 *
	 * @var array
	 */
	public $processed_terms_have_thumbnail = array();

	/**
	 * Posts Orphans
	 *
	 * @var array
	 */
	public $post_orphans = array();

	/**
	 * Postmeta Remap
	 *
	 * @var array
	 */
	public $postmeta_remap = array();

	/**
	 * Featured Images.
	 *
	 * @var array
	 */
	public $featured_images = array();

	/**
	 * Current Uploads Dir Array.
	 *
	 * @var array
	 */
	public $uploads_dir = array();

	/**
	 * Authors Mapping Type (1 - 2)
	 *
	 * @var integer
	 */
	public $author_mapping_type = 2;

	/**
	 * Single Author to map all Posts to.
	 *
	 * @var int
	 */
	public $all_posts_single_author_mapping_id = 0;

	/**
	 * Tables that might be not exists.
	 *
	 * @var array
	 */
	public $tables_exists = array();

	/**
	 * Attachments needs to make custom size.
	 *
	 * @var array
	 */
	public $attachments_needs_custom_sizes = array();

	/**
	 * Errors While Importing.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Posts that have shortcodes to remap its target.
	 *
	 * @var array
	 */
	public $shortcodes_posts_to_remap = array();

	/**
	 * Total Items before Importing.
	 *
	 * @var integer
	 */
	public $total_items = 0;

	/**
	 * Links attr Names.
	 *
	 * @var array
	 */
	public $attachments_links_attr_names;

	/**
	 * Posts Names and Types Combination for duplication Check.
	 *
	 * @var array
	 */
	public $posts_names_types_comb = array();

	/**
	 * Empty Names Titles and Types combination for duplication Check.
	 *
	 * @var array
	 */
	public $empty_names_types_comb = array();

	/**
	 * Posts to Update GUID after each step.
	 *
	 * @var array
	 */
	public $posts_to_update_guid = array();

	/**
	 * Home URL.
	 *
	 * @var string
	 */
	public $home_url;

	/**
	 * Menu Items New Remappings.
	 *
	 * @var array
	 */
	public $menu_items_new_remapping = array();

	/**
	 * Constructor
	 */
	public function __construct( $core, $plugin_info ) {
		$this->core        = $core;
		$this->plugin_info = $plugin_info;
		$this->uploads_dir = wp_get_upload_dir();
		$this->home_url    = rtrim( home_url(), '/' );
		$this->hooks();
	}

	/**
	 * Menus Exporter / Importer Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( $this->plugin_info['name'] . 'import-upload-form-action', array( $this, 'xml_import_advanced_options' ), 100, 2 );
		add_action( 'wp_loaded', array( $this, 'submit_zip_import' ) );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'adjust_setup_menu_item_missing_targets' ), 100, 1 );
		add_action( 'wp_ajax_' . $this->plugin_info['name'] . '-import_process', array( $this, 'handle_import_ajax' ) );
	}


	/**
	 * Attachments Zip Import Function.
	 *
	 * @return void
	 */
	public function handle_zip_import() {
		global $pagenow;
		if ( ! is_admin() ) {
			return;
		}

		if ( 'tools.php' !== $pagenow ) {
			return;
		}

		if ( empty( $_GET['step'] ) && empty( $_GET['type'] ) ) {
			$this->import_upload_form( 'zip', 'tools.php?page=' . $this->plugin_info['name'] . '&amp;tab=import&amp;type=zip' );
		}
	}

	/**
	 * Submit Import Attachments Zip File.
	 *
	 * @return void
	 */
	public function submit_zip_import() {
		global $pagenow;

		if ( ! is_admin() ) {
			return;
		}

		if ( 'tools.php' !== $pagenow ) {
			return;
		}

		if ( ! empty( $_GET['type'] ) && 'zip' === sanitize_text_field( wp_unslash( $_GET['type'] ) ) && check_admin_referer( 'import-upload' ) ) {
			$file_path = $this->handle_zip_upload();
			if ( false !== $file_path ) {
				WP_Filesystem();
				$result = unzip_file( $file_path, $this->uploads_dir['basedir'] );
				if ( true === $result ) {
					wp_delete_attachment( $this->id, true );
					wp_safe_redirect( add_query_arg( array( 'tab' => 'import', 'saved' => true ), admin_url( 'tools.php' ) . '?page=' . $this->plugin_info['name'] ) );
					die();
				} elseif ( is_wp_error( $result ) ) {
					?>
					<div id="message">
						<?php
						foreach ( $result->get_error_messages() as $error_msg ) {
							echo '<p class="notice notice-error">' . $error_msg . '</p>';
						}
						?>
					</div>
					<?php
				}
			}
		}
	}

	/**
	 * Handle ZIP File Upload.
	 *
	 * @return string
	 */
	public function handle_zip_upload() {
		$file = $this->zip_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'gpls-cpt-exporter-importer' ) . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} elseif ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'gpls-cpt-exporter-importer' ) . '</strong><br />';
			printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'gpls-cpt-exporter-importer' ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}

		$this->id = (int) $file['id'];
		return $file['file'];
	}

	/**
	 * CPTs and Menus Import Functions.
	 *
	 * @return void
	 */
	public function handle_cpts_and_menus_import() {
		global $pagenow;
		if ( ! is_admin() ) {
			return;
		}

		if ( 'tools.php' !== $pagenow ) {
			return;
		}

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];

		if ( ! defined( 'WXR_VERSION' ) ) {
			define( 'WXR_VERSION', '1.2' );
		}

		switch ( $step ) {
			case 0:
				$this->import_upload_form( 'xml', 'tools.php?page=' . $this->plugin_info['name'] . '&amp;tab=import&amp;step=1&amp;saved=true' . ( ! empty( $_GET['disabled'] ) ? '&disabled=true' : '' ) );
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				if ( $this->handle_upload() ) {
					$this->author_mapping_options();
				}
				break;
		}
	}

	/**
	 * Start Import thorugh Ajax.
	 *
	 * @return void
	 */
	public function handle_import_ajax() {
		if ( ! empty( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], $this->plugin_info['name'] . '-import-nonce' ) ) {
			$this->id = (int) sanitize_text_field( $_POST['import_id'] );
			$file     = get_attached_file( $this->id );

			$import_type = ! empty( $_POST['import_type'] ) ? sanitize_text_field( $_POST['import_type'] ) : 'cpt';
			$step        = ! empty( $_POST['step'] ) ? intval( sanitize_text_field( $_POST['step'] ) ) : 0;

			if ( ! empty( $import_type ) ) {
				$this->process_import( $file, $import_type, $step );
				$errors = $this->errors;
				wp_send_json_success(
					array(
						'progress' => 'end',
						'errors'   => $this->errors,
					)
				);
				die();
			}
		} else {
			wp_send_json_error( 'invalid_nonce' );
		}

	}

	/**
	 * Handle Import File Upload
	 *
	 * @return void
	 */
	public function handle_upload() {
		if ( ! empty( $_FILES['import'] && ( 'xml' !== pathinfo( $_FILES['import']['name'], PATHINFO_EXTENSION ) ) ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'gpls-cpt-exporter-importer' ) . '</strong><br />';
			echo __( 'Invalid File Type, Upload the exported CPTs XML File!', 'gpls-cpt-exporter-importer' ) . '</p>';
			return false;
		}

		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'gpls-cpt-exporter-importer' ) . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} elseif ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'gpls-cpt-exporter-importer' ) . '</strong><br />';
			printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'gpls-cpt-exporter-importer' ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}

		$this->id          = (int) $file['id'];
		$this->parser      = new GPLS_AEI_XML_Parser( $this );
		$result            = $this->parser->parse_export_type_and_authors_first( $file['file'] );
		$this->authors     = $result['authors'];
		$this->export_type = $result['export_type'];
		$this->total_items = $result['total_items'];

		return true;
	}

	/**
	 * Mapping Authors before import.
	 *
	 */
	public function author_mapping_options() {
		$j = 0;
		?>
		<style>
			.main-loader .loader-text { position: fixed; left: 50%; top: 38%; transform: translate(-50%, -50%); color: #000; }
			.main-loader .loader-small-text { position: fixed; left: 50%; top: 44%; transform: translate(-50%, -50%); color: #000; }
			.main-loader .loader-icon { position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%); }
			.main-loader .loader-progress-num { position: fixed; color: #000; left: 50%; top: 60%; transform: translate(-50%, -50%); }
			.main-loader .loader-progress { position: fixed; left: 50%; top: 65%; transform: translate(-50%, -50%); }
		</style>
		<form class="last-step-import-form position-relative" action="<?php echo admin_url( 'tools.php?page=' . $this->plugin_info['name'] . '&amp;tab=import&amp;step=2&amp;updated=true' ); ?>" method="post">
			<div class="main-loader loader w-100 h-100 position-absolute">
				<div class="text-white wrapper text-center position-absolute d-block w-100 " style="top: 125px;z-index:1000;">
					<h4 class="d-none loader-text mb-4"><?php _e( 'Importing the content..., It may take several minutes for big content', 'gpls-cpt-exporter-importer' ); ?></h4>
					<h6 class="loader-small-text first-step-import-xml d-none mb-2"><?php _e( 'Importing Authors and Metas...', 'gpls-cpt-exporter-importer' ); ?></h6>
					<h6 class="loader-small-text rest-step-import-xml d-none mb-2"><?php _e( 'Importing Posts...', 'gpls-cpt-exporter-importer' ); ?></h6>
					<img class="loader-icon" src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"  />
					<div class="d-none loader-progress-num">0%</div>
					<progress class="d-none loader-progress" value="0" max="100" ></progress>
				</div>
				<div class="overlay position-absolute d-block w-100 h-100" style="opacity:0.7;background:#FFF;z-index:100;"></div>
			</div>

			<input type="hidden" class="import-nonce" value="<?php echo wp_create_nonce( $this->plugin_info['name'] . '-import-nonce' ); ?>">
			<input type="hidden" class="import-file-id" name="import_id" value="<?php echo $this->id; ?>" />

			<input type="hidden" class="import-type-val" name="import_type" value="<?php echo $this->export_type; ?>" >
			<input type="hidden" class="import-total-items" name="total_items" value="<?php echo $this->total_items; ?>" >
			<input type="hidden" class="import-total-steps" name="total_steps" value="<?php echo ceil( $this->total_items / $this->parser->cycle_count ); ?>" >
			<div class="accordion">
				<div class="card w-100" style="max-width: 650px;">
					<div class="mb-3 custom-radio custom-control" id="user-mapping-single-author-wrapper">
						<input id="user-mapping-single-author" class="user-mapping-type custom-control-input" type="radio" name="user_mapping_type" value="1" data-toggle="collapse" data-target="#user-mapping-single-author-select-wrapper" aria-expanded="true" aria-controls="user-mapping-single-author-select-wrapper" required>
						<label class="custom-control-label" for="user-mapping-single-author"><?php _e( 'Assign All posts to a single Author', 'gpls-cpt-exporter-importer' ); ?></label>
					</div>
					<div id="user-mapping-single-author-select-wrapper" class="collapse" aria-labelledby="user-mapping-single-author-wrapper">
						<label for="user-mapping-single-author-select"><?php _e( 'All imported posts will be assigned to one existing author', 'gpls-cpt-exporter-importer' ); ?></label>
						<?php
							wp_dropdown_users(
								array(
									'name'              => 'user_mapping_single_author_select',
									'id'                => 'user-mapping-single-author-select',
									'multi'             => false,
									'option_none_value' => '',
									'show'              => 'display_name_with_login',
									'show_option_none'  => __( '- Select -', 'gpls-cpt-exporter-importer' ),
									'option_none_value' => -1,
									'selected'          => -1,
									'echo'              => 1,
								)
							);
						?>
					</div>
				</div>

				<div class="card w-100" style="max-width: 650px;">
					<div class="mb-3 custom-control custom-radio" id="user-mapping-authors-wrapper">
						<input id="user-mapping-authors" class="user-mapping-type custom-control-input" type="radio" name="user_mapping_type" value="2" data-toggle="collapse" data-target="#assign-author-wrapper" aria-expanded="false" aria-controls="assign-author-wrapper" checked required>
						<label class="custom-control-label" for="user-mapping-authors"><?php _e( 'Assign Authors', 'gpls-cpt-exporter-importer' ); ?></label>
					</div>

					<div id="assign-author-wrapper" class="collapse show" aria-labelledby="user-mapping-authors-wrapper">
					<?php if ( ! empty( $this->authors ) ) : ?>
							<p><?php _e( 'Reassign the author of the imported posts to an existing author of this site or leave it to import the post\'s author', 'gpls-cpt-exporter-importer' ); ?></p>
								<ol class="ml-0 authors-list">
							<?php foreach ( $this->authors as $author ) : ?>
									<li><?php $this->author_select( $j++, $author ); ?></li>
							<?php endforeach; ?>
								</ol>
					<?php else : ?>
						<h3><?php _e( 'No Authors to import!', 'gpls-cpt-exporter-importer' ); ?></h3>
					<?php endif; ?>
					</div>
				</div>

			</div>
			<p class="submit"><input type="submit" class="button button-primary last-step-submit" value="<?php esc_attr_e( 'Start Importing', 'gpls-cpt-exporter-importer' ); ?>" /></p>
		</form>
		<?php
	}

	/**
	 * Display import options for an individual author. That is, either create
	 * a new user based on import info or map to an existing user
	 *
	 * @param int $n Index for each author in the form
	 * @param array $author Author information, e.g. login, display name, email
	 */
	public function author_select( $n, $author ) {
		_e( 'Import author:', 'gpls-cpt-exporter-importer' );
		echo ' <strong>' . esc_html( $author['display_name'] );
		echo '</strong><br />';

		echo '<div class="ml-3">';

		echo '<div class="mb-2"><label for="imported_authors_'. $n . '">';
		_e( 'OR assign posts to an existing user:', 'gpls-cpt-exporter-importer' );

		echo '</label>';

		echo ' ' . wp_dropdown_users(
			array(
				'name'              => "user_map[$n]",
				'id'                => 'imported_authors_' . $n,
				'class'             => 'user-map-select',
				'multi'             => true,
				'show'              => 'display_name_with_login',
				'echo'              => 0,
				'show_option_none'  => __( '- Select -', 'gpls-cpt-exporter-importer' ),
				'option_none_value' => -1,
				'selected'          => -1,
			)
		);

		echo '<input class="imported-authors-select" type="hidden" name="imported_authors[' . $n . ']" value="' . esc_attr( $author['user_login'] ) . '" /></div>';

		echo '</div>';
	}

	/**
	 * Import Start Process
	 *
	 * @param string $file File Path.
	 * @return void
	 */
	public function import_start( $file, $step ) {
		if ( ! is_file( $file ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'gpls-cpt-exporter-importer' ) . '</strong><br />';
			echo __( 'The file does not exist, please try again.', 'gpls-cpt-exporter-importer' ) . '</p>';
			die();
		}

		$this->parser = new GPLS_AEI_XML_Parser( $this );

		if ( $step > 0 ) {
			$this->processed_posts                    = get_site_option( $this->plugin_info['name'] . '-processed-posts', array() );
			$this->posts_exists                       = get_site_option( $this->plugin_info['name'] . '-processed-posts-exists', array() );
			$this->post_orphans                       = get_site_option( $this->plugin_info['name'] . '-processed-posts-orphans', array() );
			$this->processed_terms                    = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-processed-terms', '' ), true );
			$this->processed_authors                  = get_site_option( $this->plugin_info['name'] . '-processed-authors', array() );
			$this->processed_attachments              = get_site_option( $this->plugin_info['name'] . '-processed-attachments', array() );
			$this->processed_missing_taxonomies_names = get_site_option( $this->plugin_info['name'] . '-processed-missing-taxonomies-names', array() );
			$this->processed_terms_have_thumbnail     = get_site_option( $this->plugin_info['name'] . '-processed-terms-have-thumbnail', array() );
			$this->featured_images                    = get_site_option( $this->plugin_info['name'] . '-processed-featured-images', array() );
			$this->author_mapping_type                = get_site_option( $this->plugin_info['name'] . '-author-mapping-type', array() );
			$this->all_posts_single_author_mapping_id = get_site_option( $this->plugin_info['name'] . '-single-author-mapping', '' );
			$this->author_mapping                     = get_site_option( $this->plugin_info['name'] . '-author-mapping', array() );
			$this->attachments_needs_custom_sizes     = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-attachments-needs-custom-sizes', '' ), true );
			$this->postmeta_remap                     = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-postmeta-remap', '' ), true );
			$this->errors                             = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-processed-errors', '' ), true );
		}

		// Pypass the empty content import check.
		add_filter( 'wp_insert_post_empty_content', '__return_false', 1000 );

		$this->process_tables_exists();

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
	}

	/**
	 * Process Import
	 *
	 * @return void
	 */
	public function process_import( $file, $type, $step ) {
		set_time_limit( 0 );

		$this->import_start( $file, $step );
		wp_suspend_cache_invalidation( true );

		if ( $step > 0 ) {
			$this->set_missing_taxonomies();
		}

		$this->parser->parse( $file, $step );


		$this->backfill_parents();
		$this->remap_featured_images();
		$this->remap_terms_thumbnail();
		$this->backfill_postmeta();
		$this->remap_shortcodes( $step );

		wp_suspend_cache_invalidation( false );


		$this->import_end( $type, $step );
	}

	/**
	 * Import End Function
	 *
	 * @param string $type
	 * @return void
	 */
	public function import_end( $type, $step ) {

		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		$this->delete_transients();

		wp_defer_comment_counting( false );

		$this->update_terms_count();
		$this->clean_registered_taxonomies();

		if ( $step > 0 ) {
			$this->errors = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-processed-errors' ), true );
		}

		$this->clean_stored_processed();

		wp_delete_attachment( $this->id, true );
	}

	/**
	 * Remove Stored Processed Data.
	 *
	 * @return void
	 */
	public function clean_stored_processed() {
		delete_site_option( $this->plugin_info['name'] . '-processed-posts' );
		delete_site_option( $this->plugin_info['name'] . '-processed-posts-exists' );
		delete_site_option( $this->plugin_info['name'] . '-processed-posts-orphans' );
		delete_site_option( $this->plugin_info['name'] . '-processed-authors' );
		delete_site_option( $this->plugin_info['name'] . '-single-author-mapping' );
		delete_site_option( $this->plugin_info['name'] . '-author-mapping-type' );
		delete_site_option( $this->plugin_info['name'] . '-author-mapping' );
		delete_site_option( $this->plugin_info['name'] . '-processed-terms' );
		delete_site_option( $this->plugin_info['name'] . '-processed-authors' );
		delete_site_option( $this->plugin_info['name'] . '-processed-attachments' );
		delete_site_option( $this->plugin_info['name'] . '-processed-missing-taxonomies-names' );
		delete_site_option( $this->plugin_info['name'] . '-processed-terms-have-thumbnail' );
		delete_site_option( $this->plugin_info['name'] . '-processed-initials' );
		delete_site_option( $this->plugin_info['name'] . '-processed-featured-images' );
		delete_site_option( $this->plugin_info['name'] . '-shortcodes-posts-remap' );
		delete_site_option( $this->plugin_info['name'] . '-processed-errors' );
		delete_site_option( $this->plugin_info['name'] . '-processed-posts-names-types-combination' );
		delete_site_option( $this->plugin_info['name'] . '-processed-empty-names-types-combination' );
		delete_site_option( $this->plugin_info['name'] . '-attachments-needs-custom-sizes' );
		delete_site_option( $this->plugin_info['name'] . '-menu-items-new-remapping' );
		delete_site_option( $this->plugin_info['name'] . '-postmeta-remap' );
	}

	/**
	 * Remap Posts Shortcodes Targets remap.
	 *
	 * @return void
	 */
	public function remap_shortcodes( $step ) {
		if ( $step > 0 ) {
			$this->shortcodes_posts_to_remap = explode( ',', get_site_option( $this->plugin_info['name'] . '-shortcodes-posts-remap', array() ) );
		}

		foreach ( $this->shortcodes_posts_to_remap as $post_id ) {
			if ( isset( $this->processed_posts[ intval( $post_id ) ] ) ) {
				$this->update_post_shortcodes_targets( $this->processed_posts[ intval( $post_id ) ] );
			}
		}
	}

	/**
	 * Update Post Content Shortcode Targets.
	 *
	 * @param array $post_id
	 * @return void
	 */
	public function update_post_shortcodes_targets( $post_id ) {
		global $wpdb;

		$is_post_cached = wp_cache_get( $post_id, 'posts' );
		if ( ! $is_post_cached ) {
			$post_content = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID = $post_id" );
		} else {
			$post_content = $is_post_cached->post_content;
		}

		if ( empty( $post_content ) ) {
			return;
		}

		$shortcodes = $this->has_target_shortcodes( $post_content );
		$this->update_ids_from_shortcodes( $shortcodes, $post_content, $post_id );
	}

	/**
	 * Display Import Error.
	 *
	 * @return void
	 */
	public function display_errors() {
		foreach ( $this->errors as $error ) {
			echo $error . '<br/>';
		}
	}

	/**
	 * Process XML WooCommerce Attribute Taxonomy.
	 *
	 * @param array $wc_attribute_item
	 * @return void
	 */
	public function process_xml_wc_attribute_taxonomy( $wc_attribute_item ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
		if ( in_array( $table_name, array_keys( $this->tables_exists ) ) ) {
			$attributes_names = $wpdb->get_col(
				"SELECT
					attribute_name
				FROM
					{$table_name}"
			);

			if ( ! in_array( $wc_attribute_item['attribute_name'], $attributes_names ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table_name}
							( attribute_name, attribute_label, attribute_type, attribute_orderby, attribute_public )
						VALUES
							( %s, %s, %s, %s, %d )
						",
						array(
							$wc_attribute_item['attribute_name'],
							$wc_attribute_item['attribute_label'],
							$wc_attribute_item['attribute_type'],
							$wc_attribute_item['attribute_orderby'],
							$wc_attribute_item['attribute_public'],
						)
					)
				);
			}
		}
	}

	/**
	 * Delete Needed transients after importing.
	 *
	 * @return void
	 */
	public function delete_transients() {

		// Woo Attributes.
		delete_transient( 'wc_attribute_taxonomies' );

		// Woo Featured Attributes.
		delete_transient( 'wc_featured_products' );

		// Woo Terms Counts.
		delete_transient( 'wc_term_counts' );

	}

	/**
	 * Update Terms Count in DB.
	 *
	 * @return void
	 */
	public function update_terms_count() {
		global $wpdb, $wp_taxonomies;
		foreach ( $this->processed_terms as $old_term_id => $term_new_id ) {
			$term_taxonomy_id = intval( $term_new_id['term_taxonomy_id'] );
			$count            = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = $term_taxonomy_id" );
			$wpdb->update(
				$wpdb->term_taxonomy,
				array(
					'count' => $count,
				),
				array(
					'term_taxonomy_id' => intval( $term_new_id['term_taxonomy_id'] ),
				)
			);
		}
	}

	/**
	 * Set Missing Taxonomies.
	 *
	 * @return void
	 */
	public function set_missing_taxonomies() {
		global $wp_taxonomies;

		if ( ! empty( $this->processed_missing_taxonomies_names ) ) {
			foreach ( $this->processed_missing_taxonomies_names as $term_taxonomy ) {
				$wp_taxonomies[ $term_taxonomy ] = new WP_Taxonomy( $term_taxonomy, array() );
			}
		}
	}

	/**
	 * Process XML Term.
	 *
	 * @param array $term
	 * @return void
	 */
	public function process_xml_term( $term ) {
		global $wp_taxonomies;

		// pypass Registering The Txonomy to avoid failing importing the terms.
		if ( ! isset( $wp_taxonomies[ $term['taxonomy'] ] ) ) {
			$this->processed_missing_taxonomies_names[] = $term['taxonomy'];
			$wp_taxonomies[ $term['taxonomy'] ]         = new WP_Taxonomy( $term['taxonomy'], array() );
		}

		if ( empty( $term['parent'] ) || 0 == $term['parent'] ) {
			$parent         = 0;
			$term['parent'] = 0;
		} else {
			if ( isset( $this->processed_terms[ intval( $term['parent'] ) ] ) ) {
				$parent = $this->processed_terms[ intval( $term['parent'] ) ]['term_id'];
			}
			if ( ! $parent ) {
				$parent = term_exists( $term['parent'], $term['taxonomy'] );
				if ( is_array( $parent ) ) {
					$parent = $parent['term_id'];
				}
			}
			$term['parent'] = $parent;
		}

		// if the term already exists in the correct taxonomy leave it alone.
		$term_id = term_exists( $term['slug'], $term['taxonomy'], $parent );
		if ( $term_id ) {
			if ( isset( $term_id['term_id'] ) ) {
				$this->processed_terms[ intval( $term['term_id'] ) ] = array(
					'term_id'          => (int) $term_id['term_id'],
					'term_taxonomy_id' => (int) $term_id['term_taxonomy_id'],
					'term_taxonomy'    => $term['taxonomy'],
				);
			}
			return;
		}

		$description = isset( $term['description'] ) ? $term['description'] : '';
		$args        = array(
			'slug'        => $term['slug'],
			'description' => $description,
			'parent'      => (int) $parent,
		);

		$term_id = $this->xml_insert_term( $term );

		if ( ! is_wp_error( $term_id ) ) {
			$this->processed_terms[ intval( $term['term_id'] ) ] = array(
				'term_id'          => $term_id['term_id'],
				'term_taxonomy_id' => $term_id['term_taxonomy_id'],
				'term_taxonomy'    => $term['taxonomy'],
			);
		} else {
			$this->errors[] = sprintf( __( 'Failed to import %1$s %2$s', 'gpls-cpt-exporter-importer' ), esc_html( $term['taxonomy'] ), esc_html( $term['name'] ) );
			if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
				error_log( print_r( $term_id->get_error_message(), true ) );
			}
			return;
		}

		if ( ! empty( $term['termmeta'] ) ) {
			$this->process_termmeta( $term['termmeta'], $term_id['term_id'] );
		}

	}

	/**
	 * Add metadata to imported term.
	 *
	 * @since 0.6.2
	 *
	 * @param array $term    Term data from WXR import.
	 * @param int   $term_id ID of the newly created term.
	 */
	protected function process_termmeta( $term_meta, $term_id ) {
		global $wpdb;
		$table = $wpdb->termmeta;

		foreach ( $term_meta as $meta ) {
			$wpdb->insert(
				$table,
				array(
					'term_id'    => $term_id,
					'meta_key'   => $meta->key,
					'meta_value' => $meta->value,
				)
			);

			// Check if the term has thumbnail_id termmeta and store it in the processed_meta var  as [ $new_term_id ] => old_attachment_id.
			if ( ( 'thumbnail_id' === $meta->key ) && ( 0 != $meta->value ) ) {
				$this->processed_terms_have_thumbnail[ intval( $term_id ) ] = $meta->value;
			}
		}
	}

	/**
	 * process XML Author Import.
	 *
	 * @param array $authordata
	 * @return int
	 */
	public function process_xml_author( $author_data ) {
		global $wpdb;

		if ( empty( $author_data ) ) {
			return false;
		}

		$user_email = $author_data['user_email'];

		if ( email_exists( $user_email ) ) {
			$already_user = get_user_by( 'email', $user_email );
			if ( $already_user ) {
				return $already_user->ID;
			}
		}

		$user_login = $author_data['user_login'];

		if ( empty( $author_data['user_nicename'] ) ) {
			$user_nicename = mb_substr( $user_login, 0, 50 );
		} else {
			$user_nicename = $author_data['user_nicename'];
		}

		$user_nicename = apply_filters( 'pre_user_nicename', $user_nicename );

		$user_nicename_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1", $user_nicename, $user_login ) );

		if ( $user_nicename_check ) {
			$suffix = 2;
			while ( $user_nicename_check ) {
				// user_nicename allows 50 chars. Subtract one for a hyphen, plus the length of the suffix.
				$base_length         = 49 - mb_strlen( $suffix );
				$alt_user_nicename   = mb_substr( $user_nicename, 0, $base_length ) . "-$suffix";
				$user_nicename_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1", $alt_user_nicename, $user_login ) );
				$suffix++;
			}
			$user_nicename = $alt_user_nicename;
		}

		$data = array(
			'user_login'          => $author_data['user_login'],
			'user_pass'           => $author_data['user_pass'],
			'user_nicename'       => $author_data['user_nicename'],
			'user_email'          => $author_data['user_email'],
			'user_url'            => $author_data['user_url'],
			'user_registered'     => $author_data['user_registered'],
			'user_activation_key' => $author_data['user_activation_key'],
			'user_status'         => $author_data['user_status'],
			'display_name'        => $author_data['display_name'],
		);

		$wpdb->insert( $wpdb->users, $data );
		$user_id = (int) $wpdb->insert_id;

		foreach ( $author_data['usermeta'] as $meta ) {
			$result = $wpdb->insert(
				$wpdb->usermeta,
				array(
					'user_id'    => $user_id,
					'meta_key'   => $meta->key,
					'meta_value' => $meta->value,
				)
			);
		}

		return $user_id;

	}

	/**
	 * Process XML Post
	 *
	 * @param array $post
	 * @return void
	 */
	public function process_xml_post( $postdata, $step ) {
		global $wpdb;

		if ( 'auto-draft' === $postdata['post_status'] ) {
			return;
		}

		if ( 'nav_menu_item' === $postdata['post_type'] ) {
			return;
		}

		$post_exists = $this->is_post_exists( $postdata['post_name'], $postdata['post_title'], $postdata['post_type'], $step );

		if ( $post_exists ) {
			$this->errors[]                                            = sprintf( __( '%1$s &#8220;%2$s&#8221; already exists.', 'gpls-cpt-exporter-importer' ), $postdata['post_type'], esc_html( $postdata['post_title'] ) );
			$this->posts_exists[ intval( $postdata['import_id'] ) ]    = $post_exists;
			$this->processed_posts[ intval( $postdata['import_id'] ) ] = $post_exists;
			if ( 'attachment' === $postdata['post_type'] ) {
				$this->processed_attachments[ intval( $postdata['import_id'] ) ] = $post_exists;
			}
			return;
		} else {
			$post_parent = (int) $postdata['post_parent'];
			if ( $post_parent ) {
				// if we already know the parent, map it to the new local ID
				if ( isset( $this->processed_posts[ intval( $post_parent ) ] ) ) {
					$postdata['post_parent'] = $this->processed_posts[ intval( $post_parent ) ];
				// otherwise record the parent for later
				} else {
					$this->post_orphans[ intval( $postdata['import_id'] ) ] = intval( $post_parent );
					$postdata['post_parent']                                = 0;
				}
			}

			// map the post author
			$author = sanitize_user( $postdata['post_author'], true );

			// Map All posts to single Author.
			if ( 1 == $this->author_mapping_type && 0 !== $this->all_posts_single_author_mapping_id ) {
				$author = $this->all_posts_single_author_mapping_id;
			} elseif ( isset( $this->author_mapping[ $author ] ) ) {
			// Map All Author.
				$author = $this->author_mapping[ $author ];
			} else {
			// Fallback to current user.
				$author = (int) get_current_user_id();
			}

			$postdata['post_author'] = $author;
			$original_post_ID        = intval( $postdata['import_id'] );

			if ( 'attachment' == $postdata['post_type'] ) {
				$remote_url                 = ! empty( $postdata['attachment_url'] ) ? $postdata['attachment_url'] : $postdata['guid'];
				$comment_post_ID = $post_id = $this->process_attachment_v2( $postdata, $remote_url );

				if ( false === $post_id ) {
					return;
				}

			} else {
				$postdata                   = $this->update_post_content_attachments_and_urls( $postdata );
				$comment_post_ID = $post_id = $this->xml_insert_post( $postdata, true );
			}

			if ( is_wp_error( $post_id ) ) {
				$this->errors[] = sprintf( __( 'Failed to import %1$s &#8220;%2$s&#8221;', 'gpls-cpt-exporter-importer' ), $postdata['post_type'], esc_html( $postdata['post_title'] ) );
				if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
					error_log( print_r( $post_id->get_error_message(), true ) );
				}
				return;
			}

			if ( ! empty( $postdata['is_sticky'] ) && $postdata['is_sticky'] == 1 ) {
				stick_post( $post_id );
			}
		}

		// map pre-import ID to local ID
		$this->processed_posts[ intval( $original_post_ID ) ] = (int) $post_id;

		// add categories, tags and other terms.
		if ( ! empty( $postdata['terms'] ) ) {
			$terms_to_set       = array();
			$terms_placeholders = array();
			$term_order_counter = array();
			foreach ( $postdata['terms'] as $term_id ) {
				if ( isset( $this->processed_terms[ intval( $term_id ) ] ) ) {
					$term_taxonomy_id     = $this->processed_terms[ intval( $term_id ) ]['term_taxonomy_id'];
					$term_taxonomy        = $this->processed_terms[ intval( $term_id ) ]['term_taxonomy'];
					$term_order           = 0;
					$terms_placeholders[] = '( %d, %d, %d )';
					$term_taxonomy_obj    = get_taxonomy( $term_taxonomy );
					if ( $term_taxonomy_obj && isset( $term_taxonomy_obj->sort ) && $term_taxonomy_obj->sort ) {
						if ( isset( $term_order_counter[ $term_taxonomy ] ) ) {
							$term_order                           = $term_order_counter[ $term_taxonomy ];
							$term_order_counter[ $term_taxonomy ] = $term_order + 1;
							$term_order++;
						} else {
							$term_order_counter[ $term_taxonomy ] = 0;
						}
					}
					array_push( $terms_to_set, $post_id, $term_taxonomy_id, $term_order );
				}
			}

			if ( ! empty( $terms_to_set ) ) {
				$relation_query =
				"INSERT INTO {$wpdb->term_relationships}
					( `object_id`, `term_taxonomy_id`, `term_order` )
				VALUES
				";

				$relation_query .= implode( ', ', $terms_placeholders );
				$wpdb->query( $wpdb->prepare( $relation_query, $terms_to_set ) );
			}
			unset( $postdata['terms'], $terms_to_set );
		}

		// add/update comments.
		if ( ! empty( $postdata['comments'] ) ) {
			$inserted_comments = array();

			ksort( $postdata['comments'] );

			foreach ( $postdata['comments'] as $comment_id => $comment ) {

				if ( isset( $inserted_comments[ $comment['comment_parent'] ] ) ) {
					$comment['comment_parent'] = $inserted_comments[ $comment['comment_parent'] ];
				}

				$comment_meta               = ! empty( $comment['comment_meta'] ) ? $comment['comment_meta'] : array();
				$comment['comment_post_ID'] = $comment_post_ID;

				unset( $comment['comment_meta'] );
				unset( $comment['comment_ID'] );

				$wpdb->insert(
					$wpdb->comments,
					$comment
				);

				$new_comment_id                   = (int) $wpdb->insert_id;
				$inserted_comments[ $comment_id ] = $new_comment_id;

				foreach ( $comment_meta as $meta ) {
					$wpdb->insert(
						$wpdb->commentmeta,
						array(
							'comment_id' => $new_comment_id,
							'meta_key'   => $meta['key'],
							'meta_value' => $meta['value'],
						)
					);
				}
			}

			unset( $comment_meta, $inserted_comments, $postdata['comments'] );
		}

		// add/update post meta.
		if ( ! empty( $postdata['postmeta'] ) ) {
			$postmeta_counter       = 1;
			$postmetas_to_set       = array();
			$postmetas_placeholders = array();

			foreach ( $postdata['postmeta'] as $meta_arr ) {
				$key   = $meta_arr['key'];
				$value = $meta_arr['value'];

				if ( '_edit_last' == $key ) {
					if ( isset( $this->processed_authors[ intval( $value ) ] ) ) {
						$value = $this->processed_authors[ intval( $value ) ];
					} else {
						$key = false;
					}
				} elseif ( '_product_image_gallery' == $key ) {
					if ( isset( $this->postmeta_remap[ $post_id ] ) ) {
						$this->postmeta_remap[ $post_id ][] = '_product_image_gallery';
					} else {
						$this->postmeta_remap[ $post_id ] = array();
						$this->postmeta_remap[ $post_id ][] = '_product_image_gallery';
					}
				} elseif ( '_children' == $key ) {
					if ( isset( $this->postmeta_remap[ $post_id ] ) ) {
						$this->postmeta_remap[ $post_id ][] = '_children';
					} else {
						$this->postmeta_remap[ $post_id ] = array();
						$this->postmeta_remap[ $post_id ][] = '_children';
					}
				} elseif ( '_thumbnail_id' == $key ) {
					$this->featured_images[ $post_id ] = (int) $value;
				} elseif ( ! empty( $postdata['postmeta_urls_remap'] ) && array_key_exists( $key, $postdata['postmeta_urls_remap'] ) ) {
					foreach ( $postdata['postmeta_urls_remap'][ $key ] as $_postmeta_link_to_remap ) {
						$url_to_remap = str_replace( $this->base_url, $this->home_url, $_postmeta_link_to_remap );
						$value        = str_replace( $_postmeta_link_to_remap, $url_to_remap, $value );

						// Check if URL is an image and create missing subsize.
						if ( $this->is_image_url( $url_to_remap ) && $this->is_local_image( $url_to_remap, $this->base_url ) ) {
							$current_upload_dir     = wp_get_upload_dir();
							$current_domain_details = wp_parse_url( $this->home_url );
							$image_url_details      = wp_parse_url( $url_to_remap );
							$image_info             = pathinfo( $url_to_remap );
							$image_dirname          = substr( $image_url_details['path'], strpos( $image_url_details['path'], '/wp-content/uploads/' ) + 20 );

							// Check if the image URL targets subsize.
							$is_image_subsize = $this->is_image_subsize( $image_info, $image_url_details );

							if ( $is_image_subsize && is_array( $is_image_subsize ) && ! empty( $is_image_subsize[1] ) ) {

								// Image Path including the subsize extension name.
								$image_url_to_path = trailingslashit( $current_upload_dir['basedir'] ) . $image_dirname;
								$image_size        = explode( 'x', $is_image_subsize[1] );

								// Check if the subsize is registered.
								if ( ! file_exists( $image_url_to_path ) && ! $this->is_image_subsize_exists( $image_size[0], $image_size[1] ) ) {

									// Image path without the subsize extension name.
									$image_url_to_path = str_replace( '-' . $is_image_subsize[1], '', $image_url_to_path );

									// Check if the file exists.
									if ( file_exists( $image_url_to_path ) ) {
										$image_name    = strstr( basename( $image_url_details['path'] ), $is_image_subsize[0], true );
										$image_details = array(
											'src'        => $url_to_remap,
											'width'      => $image_size[0],
											'height'     => $image_size[1],
											'image_name' => $image_name,
										);
										$this->attachments_needs_custom_sizes[ $image_name ] = $image_details;
									}
								}
							}
						}
					}

				}

				$value_format             = ( is_string( $value ) ? '%s' : ( is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' ) ) );
				$postmetas_placeholders[] = "( '%d', '%s', '" . $value_format . "' )";
				array_push( $postmetas_to_set, $post_id, $key, $value );

				if ( $postmeta_counter >= 20 ) {
					$postmeta_query =
					"INSERT INTO {$wpdb->postmeta}
						( `post_id`, `meta_key`, `meta_value` )
					VALUES
					";

					$postmeta_query .= implode( ', ', $postmetas_placeholders );
					$wpdb->query( $wpdb->prepare( $postmeta_query, $postmetas_to_set ) );
					$postmetas_placeholders = array();
					$postmetas_to_set       = array();
					$postmeta_counter       = 0;
				}

				$postmeta_counter += 1;
			}

			if ( ! empty( $postmetas_placeholders ) ) {
				$postmeta_query =
				"INSERT INTO {$wpdb->postmeta}
					( `post_id`, `meta_key`, `meta_value` )
				VALUES
				";

				$postmeta_query .= implode( ', ', $postmetas_placeholders );

				$wpdb->query( $wpdb->prepare( $postmeta_query, $postmetas_to_set ) );
				$postmetas_placeholders = array();
				$postmetas_to_set       = array();
			}
		}
	}

	/**
	 * Check if URL is for an image.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function is_image_url( $url ) {
		$wp_filetype = wp_check_filetype( $url );
		if( $wp_filetype['type'] && 0 === strpos( $wp_filetype['type'], 'image/' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	public function process_attachment_v2( $post, $url ) {
		$import_id             = intval( $post['import_id'] );
		$attachment_upload_pos = strpos( $url, '/wp-content/uploads/' );

		if ( false !== $attachment_upload_pos ) {
			$attachment_full_name = substr( $url, $attachment_upload_pos + 20 ); // Extract the path after wp-content/uploads/
			$attachment_path      = $this->uploads_dir['basedir'] . '/' . $attachment_full_name;

			// 1) Check if the Attachment exists.
			if ( file_exists( $attachment_path ) ) {
				$image_pathinfo    = pathinfo( $url );
				$image_url_details = wp_parse_url( $url );
				$attachment_name   = basename( parse_url( $url, PHP_URL_PATH ) );

					$wp_filetype = wp_check_filetype_and_ext( $attachment_path, $attachment_name );
					$upload      = array(
						'file'  => $attachment_path,
						'url'   => $this->uploads_dir['baseurl'] . '/' . $attachment_full_name,
						'type'  => $wp_filetype['type'],
						'error' => false,
					);

					$post['guid']           = $upload['url'];
					$post['file']           = $upload['file'];
					$post['post_mime_type'] = ( ! empty( $wp_filetype['type'] ) && false !== $wp_filetype['type'] ) ? $wp_filetype['type'] : $post['post_mime_type'];
					$post_id                = $this->xml_insert_post( $post );

					if ( is_wp_error( $post_id ) ) {
						return false;
					}

					$image_meta = wp_generate_attachment_metadata( $post_id, $upload['file'] );

					if ( preg_match( '!^image/!', $wp_filetype['type'] ) ) {

						// check if needs create a missing subsize image.
						$image_exact_name = $image_pathinfo['filename'];

						if ( ! empty( $this->attachments_needs_custom_sizes[ $image_exact_name ] ) ) {

							$new_size = array(
								'file'      => $image_pathinfo['basename'],
								'width'     => $this->attachments_needs_custom_sizes[ $image_exact_name ]['width'],
								'height'    => $this->attachments_needs_custom_sizes[ $image_exact_name ]['height'],
								'mime-type' => $wp_filetype['type'],
							);

							// Create the missing subsize.
							$this->make_image_subsize( $upload['file'], $post_id, $image_meta, $new_size );

							if ( empty( $image_meta['sizes'] ) ) {
								$image_meta['sizes'] = array();
							}

							$image_meta['sizes'][ $new_size['width'] . 'x' . $new_size['height'] ] = $new_size;
						}

					}

					wp_update_attachment_metadata( $post_id, $image_meta );

					$this->processed_attachments[ intval( $import_id ) ] = $post_id;

					return $post_id;

			} else {
				$this->errors[] = sprintf( __('Attachment &#8220;%s&#8221; is missing', 'gpls-cpt-exporter-importer'), esc_html( $post['post_title'] ) );
			}
		}

		// Missing Local Attachment. //
		if ( 0 === strpos( $this->base_url, $url ) ) {
			$url = str_replace( $this->base_url, $this->home_url, $url );
		}

		// 2) Remote Attachment.
		$post['guid']                                        = $url;
		$post_id                                             = $this->xml_insert_post( $post );
		$this->processed_attachments[ intval( $import_id ) ] = $post_id;

		return $post_id;
	}

	/**
	 * Insert POST From XML into DB.
	 *
	 * @param array $postarr
	 * @return integer
	 */
	public function xml_insert_post( $postarr ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$postarr               = sanitize_post( $postarr, 'db' );
		$post_ID               = 0;
		$post_author           = isset( $postarr['post_author'] ) ? $postarr['post_author'] : $user_id;
		$post_date             = $postarr['post_date'];
		$post_date_gmt         = $postarr['post_date_gmt'];
		$post_content          = $postarr['post_content'];
		$post_title            = ! empty( $postarr['post_title'] ) ? $postarr['post_title'] : '';
		$post_excerpt          = $postarr['post_excerpt'];
		$post_status           = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];
		$comment_status        = ! empty( $postarr['comment_status'] ) ? $postarr['comment_status'] : 'closed';
		$ping_status           = empty( $postarr['ping_status'] ) ? get_default_comment_status( $post_type, 'pingback' ) : $postarr['ping_status'];
		$post_password         = isset( $postarr['post_password'] ) ? $postarr['post_password'] : '';
		$post_name             = ! empty( $postarr['post_name'] ) ? $postarr['post_name'] : '';
		$to_ping               = isset( $postarr['to_ping'] ) ? sanitize_trackback_urls( $postarr['to_ping'] ) : '';
		$pinged                = isset( $postarr['pinged'] ) ? $postarr['pinged'] : '';
		$post_modified         = $postarr['post_modified'];
		$post_modified_gmt     = $postarr['post_modified_gmt'];
		$post_content_filtered = ! empty( $postarr['post_content_filtered'] ) ? $postarr['post_content_filtered'] : '';
		$post_parent           = $postarr['post_parent'];
		$guid                  = '';
		$menu_order            = $postarr['menu_order'];
		$post_type             = $postarr['post_type'];
		$post_mime_type        = $postarr['post_mime_type'];
		$comment_count         = $postarr['comment_count'];
		$import_id             = isset( $postarr['import_id'] ) ? intval( $postarr['import_id'] ) : 0;

		$data = compact( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'post_mime_type', 'guid' );
		$data = wp_unslash( $data );

		if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'db_insert_error', __( 'Could not insert post into the database' ), $wpdb->last_error );
			} else {
				return 0;
			}
		}

		$post_ID                                = (int) $wpdb->insert_id;
		$where                                  = array( 'ID' => $post_ID );
		$this->posts_to_update_guid[ $post_ID ] = $this->home_url . '/?p=' . $post_ID;

		// Attachment attached file field.
		if ( 'attachment' === $postarr['post_type'] ) {

			if ( ! empty( $postarr['file'] ) ) {
				update_attached_file( $post_ID, $postarr['file'] );
			}

			if ( ! empty( $postarr['context'] ) ) {
				add_post_meta( $post_ID, '_wp_attachment_context', $postarr['context'], true );
			}
		}

		return $post_ID;
	}

	/**
	 * Update Posts GUID.
	 *
	 * @return void
	 */
	public function update_posts_guid() {
		global $wpdb;

		if ( empty( $this->posts_to_update_guid ) ) {
			return;
		}

		$posts_ids = array_keys( $this->posts_to_update_guid );

		$query =
		"UPDATE
			$wpdb->posts
		SET
			`guid` =
		CASE ";
		foreach ( $this->posts_to_update_guid as $post_id => $post_guid ) {
			$query .= ' WHEN ID = ' . $post_id . ' THEN ' . "'" . $post_guid . "'";
		}
		$query .= ' END';
		$query .= " WHERE ID IN ('" . implode( "','", $posts_ids ) . "')";
		$wpdb->query( $query );

		$this->posts_to_update_guid = array();
	}
	/**
	 * Insert Term into DB.
	 *
	 * @param array $term
	 * @return void
	 */
	public function xml_insert_term( $term ) {
		global $wpdb;

		$data = array(
			'name'       => $term['name'],
			'slug'       => $term['slug'],
			'term_group' => $term['term_group'],
		);

		$wpdb->insert(
			$wpdb->terms,
			$data
		);

		$term_id = (int) $wpdb->insert_id;

		if ( false === $term_id ) {
			return new WP_Error( 'db_insert_error', __( 'Could not insert term into the database.', 'gpls-cpt-exporter-importer' ), $wpdb->last_error );
		}

		$wpdb->insert(
			$wpdb->term_taxonomy,
			array(
				'term_id'     => $term_id,
				'taxonomy'    => $term['taxonomy'],
				'description' => $term['description'],
				'parent'      => $term['parent'],
				'count'       => 0,
			)
		);

		$term_taxonomy_id = (int) $wpdb->insert_id;

		if ( false === $term_taxonomy_id ) {
			return new WP_Error( 'db_insert_error', __( 'Could not insert term taxonomy into the database.' ), $wpdb->last_error );
		}

		return array( 'term_id' => $term_id, 'term_taxonomy_id' => $term_taxonomy_id );
	}

	/**
	 * Get Site URL.
	 *
	 * @return void
	 */
	public function get_site_url() {
		if ( is_multisite() ) {
			// Multisite: the base URL.
			return network_home_url();
		} else {
			// WordPress (single site): the blog URL.
			return get_bloginfo_rss( 'url' );
		}
	}

	/**
	 * Backfill Postmeta targetting other IDs.
	 *
	 * @return void
	 */
	public function backfill_postmeta() {
		global $wpdb;
		foreach ( $this->postmeta_remap as $post_id => $post_keys ) {
			foreach ( $post_keys as $post_key ) {
				if ( '_product_image_gallery' == $post_key ) {
					$value   = get_post_meta( $post_id, $post_key, true );
					$value   = explode( ',', $value );
					$changed = false;
					foreach ( $value as &$id ) {
						if ( ! empty( $this->processed_posts[ $id ] ) && $id != $this->processed_posts[ $id ] ) {
							$id      = $this->processed_posts[ $id ];
							$changed = true;
						}
					}
					if ( $changed ) {
						$wpdb->update(
							$wpdb->postmeta,
							array(
								'meta_value' => implode( ',', $value ),
							),
							array(
								'post_id'  => $post_id,
								'meta_key' => $post_key,
							),
							array( '%s' ),
							array( '%d', '%s' )
						);
					}
				} elseif ( '_children' == $post_key ) {
					$value   = get_post_meta( $post_id, $post_key, true );
					$changed = false;
					foreach ( $value as &$id ) {
						if ( ! empty( $this->processed_posts[ $id ] ) && $id != $this->processed_posts[ $id ] ) {
							$id      = $this->processed_posts[ $id ];
							$changed = true;
						}
					}
					if ( $changed ) {
						$wpdb->update(
							$wpdb->postmeta,
							array(
								'meta_value' => serialize( $value ),
							),
							array(
								'post_id'  => $post_id,
								'meta_key' => $post_key,
							),
							array( '%s' ),
							array( '%d', '%s' )
						);
					}
				}
			}
		}
	}

	/**
	 * Update Menu Items Parents After Finish importing menu items.
	 *
	 * @return void
	 */
	public function backfill_parents() {
		global $wpdb;

		// find parents for post orphans
		foreach ( $this->post_orphans as $child_id => $parent_id ) {
			$local_child_id = $local_parent_id = false;
			if ( isset( $this->processed_posts[ intval( $child_id ) ] ) ) {
				$local_child_id = $this->processed_posts[ intval( $child_id ) ];
			}

			if ( isset( $this->processed_posts[ $parent_id ] ) ) {
				$local_parent_id = $this->processed_posts[ intval( $parent_id ) ];
			}

			if ( $local_child_id && $local_parent_id ) {
				$wpdb->update(
					$wpdb->posts,
					array(
						'post_parent' => $local_parent_id
					),
					array( 'ID' => $local_child_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		// find parents for menu item orphans
		foreach ( $this->missing_menu_items_parent as $parent_old_id => $child_new_ids_arr ) :
			$new_parent_id = 0;
			if ( array_key_exists( $parent_old_id, $this->processed_menu_items ) ) :
				foreach ( $child_new_ids_arr as $child_new_id ) :
					$new_parent_id = $this->processed_menu_items[ $parent_old_id ];
					$wpdb->update(
						$wpdb->postmeta,
						array(
							'meta_value' => (int) $new_parent_id,
						),
						array(
							'post_id'  => $child_new_id,
							'meta_key' => '_menu_item_menu_item_parent',
						),
						array( '%d' ),
						array( '%d', '%s' )
					);
				endforeach;
			else :
				// Find nearset Parent.
				if ( array_key_exists( $parent_old_id, $this->menu_items_new_remapping ) ) {
					while ( ! array_key_exists( $parent_old_id, $this->processed_menu_items ) && ( 0 != $this->menu_items_new_remapping[ $parent_old_id ]['parent'] ) ) {
						$parent_old_id = (int) $this->menu_items_new_remapping[ $parent_old_id ]['parent'];
					}
					if ( array_key_exists( $parent_old_id, $this->processed_menu_items ) ) {
						$parent_old_id = (int) $this->processed_menu_items[ $parent_old_id ];
					} else {
						$parent_old_id = 0;
					}
				}
				foreach ( $child_new_ids_arr as $child_new_id ) :
					$wpdb->update(
						$wpdb->postmeta,
						array(
							'meta_value' => $parent_old_id,
						),
						array(
							'post_id'  => $child_new_id,
							'meta_key' => '_menu_item_menu_item_parent',
						),
						array( '%d' ),
						array( '%d', '%s' )
					);
				endforeach;
			endif;
		endforeach;
	}

	/**
	 * Decide if the given meta key maps to information we will want to import
	 *
	 * @param string $key The meta key to check
	 * @return string|bool The key if we do want to import, false if not
	 */
	function is_valid_meta_key( $key ) {
		// skip attachment metadata since we'll regenerate it from scratch
		// skip _edit_lock as not relevant for import
		if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ) ) ) {
			return false;
		}
		return $key;
	}

	/**
	 * Decide whether or not the importer is allowed to create users.
	 *
	 * @return bool True if creating users is allowed
	 */
	function allow_create_users() {
		return true;
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 *
	 * @return int Maximum attachment file size to import
	 */
	function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 * @return int 60
	 */
	function bump_request_timeout( $val ) {
		return 60;
	}

	// return the difference in length between two strings
	function cmpr_strlen( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}

	/**
	 * Author Mapping.
	 *
	 */
	function get_author_mapping() {

		$this->author_mapping_type = ( ! empty( $_POST['user_mapping_type'] ) ? intval( $_POST['user_mapping_type'] ) : 2 );

		// Assign All posts to single Author. (1)
		if ( 1 == $this->author_mapping_type ) {
			if ( ! empty( $_POST['user_mapping_single_author_select'] ) && $_POST['user_mapping_single_author_select'] > 0 ) {
				$single_author = (int) sanitize_text_field( $_POST['user_mapping_single_author_select'] );
			} else {
				$single_author = get_current_user_id();
			}

			$this->all_posts_single_author_mapping_id = $single_author;

		} elseif ( 2 == $this->author_mapping_type ) {
			// Mapping Every Author. (2)

			if ( ! isset( $_POST['imported_authors'] ) ) {
				return;
			}

			foreach ( (array) $_POST['imported_authors'] as $i => $old_login ) {
				// Multisite adds strtolower to sanitize_user. Need to sanitize here to stop breakage in process_posts.
				$santized_old_login = sanitize_text_field( $old_login );
				$old_id             = isset( $this->authors[ $santized_old_login ]['ID'] ) ? intval( $this->authors[ $santized_old_login ]['ID'] ) : false;

				if ( ! empty( $_POST['user_map'] ) && ! empty( $_POST['user_map'][ $i ] ) && ( 0 < $_POST['user_map'][ $i ] ) ) {
					$user = get_userdata( intval( $_POST['user_map'][ $i ] ) );
					if ( isset( $user->ID ) ) {
						if ( $old_id ) {
							$this->processed_authors[ $old_id ] = $user->ID;
						}
						$this->author_mapping[ $santized_old_login ] = $user->ID;
					}
				} else {
					$user_id = username_exists( $santized_old_login );

					if ( ! $user_id ) {
						$user_id = $this->process_xml_author( $this->authors[ $santized_old_login ] );
					}

					if ( ( false !== $user_id ) && ( ! is_wp_error( $user_id ) ) ) {
						if ( $old_id ) {
							$this->processed_authors[ $old_id ] = $user_id;
						}
						$this->author_mapping[ $santized_old_login ] = $user_id;
					} else {
						$this->errors[] = sprintf( __( 'Failed to create new user for %s Their posts will be attributed to the current user.', 'gpls-cpt-exporter-importer' ), esc_html($this->authors[$old_login]['author_display_name'] ) );
						if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
							error_log( print_r( $user_id->get_error_message(), true ) );
						}
					}
				}

				// failsafe: if the user_id was invalid, default to the current user
				if ( ! isset( $this->author_mapping[ $santized_old_login ] ) ) {
					if ( $old_id ) {
						$this->processed_authors[ $old_id ] = (int) get_current_user_id();
					}
					$this->author_mapping[ $santized_old_login ] = (int) get_current_user_id();
				}
			}
		}

		update_site_option( $this->plugin_info['name'] . '-single-author-mapping', $this->all_posts_single_author_mapping_id, '', false );
		update_site_option( $this->plugin_info['name'] . '-processed-authors', $this->processed_authors, '', false );
		update_site_option( $this->plugin_info['name'] . '-author-mapping-type', $this->author_mapping_type, '', false );
		update_site_option( $this->plugin_info['name'] . '-author-mapping', $this->author_mapping, '', false );
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 */
	function remap_featured_images() {
		global $wpdb;
		// cycle through posts that have a featured image
		foreach ( $this->featured_images as $post_id => $value ) {
			if ( isset( $this->processed_attachments[ intval( $value ) ] ) ) {
				$new_id = $this->processed_attachments[ intval( $value ) ];
				// only update if there's a difference
				if ( $new_id != $value ) {
					$wpdb->update(
						$wpdb->postmeta,
						array(
							'meta_value' => $new_id,
						),
						array(
							'post_id'  => $post_id,
							'meta_key' => '_thumbnail_id',
						),
						array( '%d' ),
						array( '%d', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 */
	function remap_terms_thumbnail() {
		global $wpdb;
		// Loop over terms that have thumbnails and update the termmeta with the new thumbnail id.
		foreach ( $this->processed_terms_have_thumbnail as $new_term_id => $old_thumbnail_id ) {
			if ( isset( $this->processed_attachments[ intval( $old_thumbnail_id ) ] ) ) {
				$new_thumbnail_id = $this->processed_attachments[ intval( $old_thumbnail_id ) ];
				$wpdb->update(
					$wpdb->termmeta,
					array(
						'meta_value' => (int) $new_thumbnail_id,
					),
					array(
						'term_id'  => $new_term_id,
						'meta_key' => 'thumbnail_id',
					),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Clear the Missing Taxonomies from global taxonomies at the end
	 *
	 * @return void
	 */
	function clean_registered_taxonomies() {
		global $wp_taxonomies;
		foreach ( $this->processed_missing_taxonomies_names as $tax_name => $tax_obj ) {
			unset( $wp_taxonomies[ $tax_name ] );
		}
	}

	/**
	 * Check if tables exist before import.
	 *
	 * @return void
	 */
	public function process_tables_exists() {
		global $wpdb;

		$tables = array(
			$table_name = $wpdb->prefix . 'woocommerce_attribute_taxonomies',
		);

		foreach ( $tables as $table ) {
			if ( $this->check_table_exists( $table ) ) {
				$this->tables_exists[ $table ] = true;
			} else {
				// $this->errors[] = sprintf( __( 'Failed to import WooCommerce Attributes, %s table does\'nt exist', 'gpls-cpt-exporter-importer' ), $table_name );
			}
		}
	}

	/**
	 * Check if table exists.
	 *
	 * @param string $table_name
	 * @return boolean
	 */
	public function check_table_exists( $table_name ) {
		global $wpdb;
		$query = $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) );
		if ( $wpdb->get_var( $query ) == $table_name ) {
			return true;
		}
		return false;
	}

	/**
	 * Handle importer uploading and add attachment.
	 *
	 * @since 2.0.0
	 *
	 * @return array Uploaded file's details on success, error message on failure
	 */
	function zip_import_handle_upload() {
		if ( ! isset( $_FILES['import'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'tab' => 'import', 'error' => 1 ), admin_url( 'tools.php' ) . '?page=' . $this->plugin_info['name'] ) );
			die();
		}

		if ( 'zip' !== pathinfo( $_FILES['import']['name'], PATHINFO_EXTENSION ) ) {
			wp_safe_redirect( add_query_arg( array( 'tab' => 'import', 'error' => 2 ), admin_url( 'tools.php' ) . '?page=' . $this->plugin_info['name'] ) );
			die();
		}

		$overrides = array(
			'test_form' => false,
			'test_type' => false,
		);

		$upload = wp_handle_upload( $_FILES['import'], $overrides );

		if ( isset( $upload['error'] ) ) {
			return $upload;
		}

		// Construct the object array.
		$object = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => $upload['url'],
			'post_mime_type' => $upload['type'],
			'guid'           => $upload['url'],
			'context'        => 'import',
			'post_status'    => 'private',
		);

		// Save the data.
		$id = wp_insert_attachment( $object, $upload['file'] );
		return array(
			'file' => $upload['file'],
			'id'   => $id,
		);
	}

	/**
	 * Outputs the form used by the importers to accept the data to be imported.
	 *
	 * @param string $file_type File Type to upload.
	 * @param string $action The action attribute for the form.
	 */
	public function import_upload_form( $file_type, $action ) {
		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) :
			?>
			<div class="error"><p><?php _e( 'Before you can upload your import file, you will need to fix the following error:' ); ?></p>
			<p><strong><?php echo $upload_dir['error']; ?></strong></p></div>
			<?php
		else :

			if ( ! empty( $_GET['error'] ) ) :
				if ( 1 == sanitize_text_field( $_GET['error'] ) ) :
					?>
					<div class="notice notice-error is-dismissible">
						<p><?php echo sprintf( __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.' ), 'php.ini', 'post_max_size', 'upload_max_filesize' ); ?></p>
					</div>
					<?php
				elseif ( 2 == sanitize_text_field( $_GET['error'] ) ) :
					?>
					<div class="notice notice-error is-dismissible">
						<p><?php _e( 'Invalid File Type, Upload the exported Attachments Zip File!', 'gpls-cpt-exporter-importer' ); ?></p>
					</div>
					<?php
				endif;
			endif;
			?>
			<form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form" action="<?php echo esc_url( wp_nonce_url( $action, 'import-upload' ) ); ?>">
				<p>
					<?php
					printf(
						'<label for="upload">%s</label> (%s)',
						__( 'Choose the exported ' . $file_type . ' file from your computer:' ),
						sprintf( __( 'Maximum size: %s' ), $size )
					);
					?>
					<input type="file" id="upload" name="import" size="25" />
					<input type="hidden" name="action" value="save" />
					<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
				</p>

				<?php do_action( $this->plugin_info['name'] . 'import-upload-form-action', $file_type, $action ); ?>
				<?php
				if ( 'zip' === $file_type ) :
					submit_button( __( 'Upload and import' ), 'primary import-attachment-zip', 'submit', true );
				elseif ( 'xml' === $file_type ) :
					submit_button( __( 'Upload' ), 'primary upload-xml-file', 'submit', true );
				endif;
				?>
			</form>
			<?php
		endif;
	}

	/**
	 * Advanced XML Import Options
	 *
	 * @param string $file_type
	 * @param string $action
	 * @return void
	 */
	public function xml_import_advanced_options( $file_type, $action ) {
		if ( 'xml' === $file_type ) :
		?>

		<?php
		endif;
	}

	/**
	 * Adjust the Menu Item Object in case the Menu Item Target is missing.
	 *
	 * @param object $menu_item
	 * @return object
	 */
	public function adjust_setup_menu_item_missing_targets( $menu_item ) {
		if ( 'post_missing' === $menu_item->type || 'post_type_missing' === $menu_item->type || 'taxonomy_missing' === $menu_item->type || 'post_type_archive_missing' === $menu_item->type ) {
			$menu_item->_invalid   = true;
			$menu_item->url        = '';
			$menu_item->title      = $menu_item->post_title;
			$menu_item->type_label = $menu_item->object;

			if ( 'post_type_archive_missing' === $menu_item->type ) {
				$menu_item->type_label = __( 'Post Type Archive' ) . ' ( ' . $menu_item->object . ' ) ';
			}
		}

		return $menu_item;
	}

	/**
	 * Extract Images and its sources from Post Content.
	 *
	 * @param string $post_content
	 * @return array
	 */
	private function parse_post_content_images( $post_content ) {
		if ( empty( trim( $post_content ) ) ) {
			return;
		}
		$images_srcs = array();
		$doc         = new DOMDocument();
		@$doc->loadHTML( $post_content );
		$img_tags = $doc->getElementsByTagName( 'img' );
		foreach ( $img_tags as $tag ) {
			$images_srcs[] = $tag->getAttribute( 'src' );
		}
		return $images_srcs;
	}

	/**
	 * Check if Image URL target to subsize.
	 *
	 * @param array $image_info
	 * @param array $image_url_details
	 *
	 * @return array|boolean
	 */
	function is_image_subsize( $image_info, $image_url_details ) {
		if ( preg_match( ( ! empty( $image_info['filename'] ) ? '/-([0-9]+x[0-9]+)$/' : '/-([0-9]+x[0-9]+).\w+$/' ), ( ! empty( $image_info['filename'] ) ? $image_info['filename'] : $image_url_details['path'] ), $matches ) ) {
			return $matches;
		} else {
			return false;
		}
	}

	/**
	 * Check if attachment URL subsize exists in the current site registered sizes or not.
	 *
	 * @param int $width
	 * @param int $height
	 * @return boolean
	 */
	public function is_image_subsize_exists( $width, $height ) {
		$images_subsizes = wp_get_registered_image_subsizes();
		if ( ! empty( $images_subsizes[ $width . 'x' . $height ] ) ) {
			return true;
		}

		foreach ( $images_subsizes as $subsize_name => $subsize_arr ) {
			if ( $width == $subsize_arr['width'] && $height == $subsize_arr['height'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the image is local or remote
	 *
	 * @param string $image_url
	 * @param string $base_url
	 * @return boolean
	 */
	private function is_local_image( $image_url, $base_url ) {
		return ( ( false !== strpos( $image_url, $base_url ) ) && ( false !== strpos( $image_url, 'wp-content/uploads' ) ) );
	}

	/**
	 * Get the best close subsize for the unregistered images sizes.
	 *
	 * @param int $width
	 * @param int $height
	 * @return array
	 */
	private function find_best_subsize_match( $width, $height ) {
		$images_subsizes  = wp_get_registered_image_subsizes();
		$size_differrence = 100000;
		$best_size        = ( ! empty( $images_subsizes['medium'] ) ? $images_subsizes['medium'] : $images_subsizes['thumbnail'] );

		foreach ( $images_subsizes as $subsize_name => $subsize_arr ) {
			$diff = abs( $subsize_arr['width'] - $width ) + abs( $subsize_arr['height'] - $height );
			if ( $diff < $size_differrence ) {
				$size_differrence = $diff;
				$best_size        = $images_subsizes[ $subsize_name ];
			}
		}

		return $best_size;
	}

	/**
	 * Update the Post content attachments URLs and images subsizes.
	 *
	 * @param array $post_data
	 * @return array
	 */
	public function update_post_content_attachments_and_urls( $post_data ) {
		$post_content = $post_data['post_content'];
		if ( empty( trim( $post_content ) ) ) {
			return $post_data;
		}

		$current_upload_dir     = wp_get_upload_dir();
		$current_domain_details = wp_parse_url( $this->home_url );

		// 1 ) Extract post content images.
		$post_content_images = $this->parse_post_content_images( $post_content );

		if ( empty( $post_content_images ) ) {
			return $post_data;
		}

		// 2 ) Loop over the images.
		foreach ( $post_content_images as $image_src ) {

			// Check if <img tag and its src attribute and if It's local image.
			if ( ! empty( $image_src ) ) {

				$image_url_details = wp_parse_url( $image_src );
				$image_info        = pathinfo( $image_src );
				$image_dirname     = substr( $image_url_details['path'], strpos( $image_url_details['path'], '/wp-content/uploads/' ) + 20 );

				// Check if the image URL targets subsize.
				$is_image_subsize = $this->is_image_subsize( $image_info, $image_url_details );

				if ( $is_image_subsize && is_array( $is_image_subsize ) && ! empty( $is_image_subsize[1] ) ) {

					// Image Path including the subsize extension name.
					$image_url_to_path = trailingslashit( $current_upload_dir['basedir'] ) . $image_dirname;
					$image_size        = explode( 'x', $is_image_subsize[1] );

					// Check if the subsize is registered.
					if ( ! file_exists( $image_url_to_path ) && ! $this->is_image_subsize_exists( $image_size[0], $image_size[1] ) ) {

						// Image path without the subsize extension name.
						$image_url_to_path = str_replace( '-' . $is_image_subsize[1], '', $image_url_to_path );

						// Check if the file exists.
						if ( file_exists( $image_url_to_path ) ) {
							$image_name    = strstr( basename( $image_url_details['path'] ), $is_image_subsize[0], true );
							$image_details = array(
								'src'        => $image_src,
								'width'      => $image_size[0],
								'height'     => $image_size[1],
								'image_name' => $image_name,
							);
							$this->attachments_needs_custom_sizes[ $image_name ] = $image_details;
						}
					}
				}

				// Update the image src to the current domain.
				$new_image_src = esc_url( trailingslashit( $current_upload_dir['baseurl'] ) . $image_dirname );
				$post_content  = str_replace( $image_src, $new_image_src, $post_content );
			}
		}

		if ( ! empty( $post_data['urls_remap'] ) ) {
			foreach ( $post_data['urls_remap'] as $_url ) {
				$url_to_remap = str_replace( $this->base_url, $this->home_url, $_url );
				$post_content = str_replace( $_url, $url_to_remap, $post_content );
			}
		}

		$post_data['post_content'] = $post_content;
		return $post_data;
	}

	/**
	 * Create Image missing registered subsizes.
	 *
	 * @param string $file
	 * @param int    $attachment_id
	 * @param array  $image_meta
	 * @param array  $new_size
	 * @return array
	 */
	private function make_image_subsize( $file, $attachment_id, $image_meta, $new_size ) {
		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$new_size_meta = $editor->make_subsize( $new_size );
		if ( is_wp_error( $new_size_meta ) ) {
			return false;
		} else {
			$new_size_name                         = $new_size['width'] . 'x' . $new_size['height'];
			$image_meta['sizes'][ $new_size_name ] = $new_size_meta;
			wp_update_attachment_metadata( $attachment_id, $image_meta );
		}

		return $image_meta;
	}


	/**
	 * Check if post exists.
	 *
	 * @param string $post_name
	 * @param string $post_type
	 * @return false|int
	 */
	private function is_post_exists( $post_name, $post_title, $post_type, $step ) {
		$post_exists = false;
		if ( ! empty( $post_name ) ) {
			if ( 0 == $step ) {
				$post_exists = isset( $this->posts_names_types_comb[ $post_name . '-' . $post_type ] ) ? ( $this->posts_names_types_comb[ $post_name . '-' . $post_type ] )->ID : false;
			} else {
				$post_exists = isset( $this->posts_names_types_comb[ $post_name . '-' . $post_type ] ) ? ( $this->posts_names_types_comb[ $post_name . '-' . $post_type ] )['ID'] : false;
			}
		} else {
			if ( 0 == $step ) {
				$post_exists = isset( $this->empty_names_types_comb[ $post_title . '-' . $post_type ] ) ? ( $this->empty_names_types_comb[ $post_title . '-' . $post_type ] )->ID : false;
			} else {
				$post_exists = isset( $this->empty_names_types_comb[ $post_title . '-' . $post_type ] ) ? ( $this->empty_names_types_comb[ $post_title . '-' . $post_type ] )['ID'] : false;
			}
		}
		return $post_exists;
	}

	/**
	 * Import ACF serialized related Content. [ Relationship / Post Object / Page Link / Gallery ]
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 * @return void
	 */
	public function import_acf_serialized_data( $post_id, $meta_key ) {
		global $wpdb;
		$new_id          = (int) $this->processed_posts[ intval( $post_id ) ];
		$serialized_data = maybe_unserialize( get_post_meta( $new_id, $meta_key, true ) );
		$changed         = false;
		if ( ! empty( $serialized_data ) ) {

			if ( is_array( $serialized_data ) ) {

				foreach ( $serialized_data as &$target_id ) {
					if ( is_numeric( $target_id ) ) {
						if ( isset( $this->processed_posts[ intval( $target_id ) ] ) && ( $target_id != $this->processed_posts[ intval( $target_id ) ] ) ) {
							$target_id = $this->processed_posts[ intval( $target_id ) ];
							$changed   = true;
						}
					} elseif ( is_string( $target_id ) ) {
						$target_id = str_replace( $this->base_url, $this->home_url, $target_id );
						$changed   = true;
					}
				}

			} elseif ( is_numeric( $serialized_data ) ) {

				if ( isset( $this->processed_posts[ intval( $serialized_data ) ] ) && ( $serialized_data != $this->processed_posts[ intval( $serialized_data ) ] ) ) {
					$serialized_data = $this->processed_posts[ intval( $serialized_data ) ];
					$changed         = true;
				}

			} elseif ( is_string( $serialized_data ) && filter_var( $serialized_data, FILTER_VALIDATE_URL ) ) {

				$serialized_data = str_replace( $this->base_url, $this->home_url, $serialized_data );
				$changed         = true;

			}

		}

		if ( $changed ) {

			$wpdb->update(
				$wpdb->postmeta,
				array(
					'meta_value' => maybe_serialize( $serialized_data ),
				),
				array(
					'post_id'  => $new_id,
					'meta_key' => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);

		}
	}
	/**
	 * Import ACF Single Fields. [ Image/ File ]
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 * @return void
	 */
	public function import_acf_single_data( $post_id, $meta_key ) {
		global $wpdb;
		if ( isset( $this->processed_posts[ intval( $post_id ) ] ) ) {
			$new_id      = $this->processed_posts[ intval( $post_id ) ];
			$single_data = get_post_meta( $new_id, $meta_key, true );
			if ( ! empty( $single_data ) && isset( $this->processed_posts[ (int) $single_data ] ) ) {
				$target_id = $this->processed_posts[ (int) $single_data ];
				if ( $target_id != $single_data ) {
					$wpdb->update(
						$wpdb->postmeta,
						array(
							'meta_value' => $target_id,
						),
						array(
							'post_id'  => $new_id,
							'meta_key' => $meta_key,
						),
						array( '%d' ),
						array( '%d', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Import ACF User Field. [ User ]
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param string $status
	 *
	 * @return void
	 */
	public function import_acf_users_data( $post_id, $meta_key, $status ) {
		global $wpdb;
		if ( isset( $this->processed_posts[ intval( $post_id ) ] ) ) {
			$new_id  = $this->processed_posts[ intval( $post_id ) ];
			$changed = false;

			if ( 'single' === $status ) {
				$target_authors_ids = get_post_meta( $new_id, $meta_key, true );
				if ( ! empty( $this->processed_authors[ (int) $target_authors_ids ] ) ) {
					$target_authors_ids = $this->processed_authors[ (int) $target_authors_ids ];
				}
			} elseif ( 'serialized' === $status ) {
				$target_authors_ids = maybe_unserialize( get_post_meta( $new_id, $meta_key, true ) );
				foreach ( $target_authors_ids as &$target_author_id ) {
					if ( ! empty( $this->processed_authors[ (int) $target_author_id ] ) ) {
						$target_author_id = $this->processed_authors[ (int) $target_author_id ];
						$changed          = true;
					}
				}
			}

			$placeholder = ( 'single' === $status ) ? '%d' : '%s';

			if ( $changed ) {
				$wpdb->update(
					$wpdb->postmeta,
					array(
						'meta_value' => maybe_serialize( $target_authors_ids ),
					),
					array(
						'post_id'  => $new_id,
						'meta_key' => $meta_key,
					),
					array( $placeholder ),
					array( '%d', '%s' )
				);
			}
		}
	}

	/**
	 * Import ACF User Field. [ Link ]
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 * @return void
	 */
	public function import_acf_links_data( $post_id, $meta_key ) {
		global $wpdb;
		if ( isset( $this->processed_posts[ intval( $post_id ) ] ) ) {
			$new_id   = $this->processed_posts[ intval( $post_id ) ];
			$link_arr = get_post_meta( $new_id, $meta_key, true );
			if ( ! empty( $link_arr ) ) {
				$url = str_replace( $this->base_url, $this->home_url, $link_arr['url'] );
				if ( $url != $link_arr['url'] ) {
					$link_arr['url'] = $url;
					$wpdb->update(
						$wpdb->postmeta,
						array(
							'meta_value' => maybe_serialize( $link_arr ),
						),
						array(
							'post_id'  => $new_id,
							'meta_key' => $meta_key,
						),
						array( '%s' ),
						array( '%d', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Import ACF Terms serialized related Content. [ Txonomy ]
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 * @return void
	 */
	public function import_acf_terms_serialized_data( $post_id, $meta_key ) {
		global $wpdb;
		$new_id          = (int) $this->processed_posts[ intval( $post_id ) ];
		$serialized_data = maybe_unserialize( get_post_meta( $new_id, $meta_key, true ) );
		$changed         = false;
		if ( is_array( $serialized_data ) ) {
			foreach ( $serialized_data as &$target_id ) {
				if ( isset( $this->processed_terms[ intval( $target_id ) ] ) && ( $target_id != $this->processed_terms[ intval( $target_id ) ]['term_id'] ) ) {
					$target_id = $this->processed_terms[ intval( $target_id ) ]['term_id'];
					$changed   = true;
				}
			}
			if ( $changed ) {
				$changed = false;
				$wpdb->update(
					$wpdb->postmeta,
					array(
						'meta_value' => maybe_serialize( $serialized_data ),
					),
					array(
						'post_id'  => $new_id,
						'meta_key' => $meta_key,
					),
					array( '%s' ),
					array( '%d', '%s' )
				);
			}
		}
	}

	/**
	 * Check if Post Content has any of the target Shortcodes.
	 *
	 * @param string $content
	 * @return array
	 */
	public function has_target_shortcodes( $content ) {
		preg_match_all(
			'/' . get_shortcode_regex() . '/',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		$shortcodes = array();

		if ( ! empty( $matches ) ) {
			foreach( $matches as $shortcode ) {
				$shortcodes[] = $shortcode[0];
			}
		}
		return $shortcodes;
	}

	/**
	 * Get IDs from Shortcodes.
	 *
	 * @param array   $shortcodes
	 * @param string  $post_content
	 * @param integer $post_id
	 * @return array
	 */
	public function update_ids_from_shortcodes( $shortcodes, $post_content, $post_id ) {
		global $wpdb;

		$posts_ids       = array();
		$attachments_ids = array();
		$needs_update    = false;
		foreach ( $shortcodes as $shortcode ) {
			$atts = shortcode_parse_atts( trim( $shortcode, '[]' ) );

			if ( ! empty( $atts['id'] ) ) {

				$target_id = intval( $atts['id'] );
				if ( ! empty( $this->processed_posts[ intval( $target_id ) ] ) ) {
					$needs_update = true;
					$post_content = str_replace( 'id="' . $target_id . '"', 'id="' . $this->processed_posts[ intval( $target_id ) ] . '"', $post_content );
				}
			} elseif ( ! empty( $atts['ids'] ) ) {
				$target_ids = explode( ',', $atts['ids'] );
				if ( ! empty( $target_ids ) ) {
					foreach ( $target_ids as &$attach_target_id ) {
						if ( ! empty( $this->processed_posts[ intval( $attach_target_id ) ] ) ) {
							$needs_update     = true;
							$attach_target_id = $this->processed_posts[ intval( $attach_target_id ) ];
						}
					}
					if ( $needs_update ) {
						$target_ids   = implode( ',', $target_ids );
						$post_content = str_replace( 'ids="' . $atts['ids'] . '"', 'ids="' . $target_ids . '"', $post_content );
					}
				}
			}
		}

		if ( $needs_update ) {
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_content' => $post_content,
				),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}


	/**
	 * Get Alreadey Posts ( name + type ) To check if duplications.
	 *
	 * @return void
	 */
	public function get_posts_names_type_comb() {
		global $wpdb;
		$this->posts_names_types_comb = $wpdb->get_results( "SELECT CONCAT( post_name, '-', post_type ), ID FROM $wpdb->posts WHERE post_name <> ''", OBJECT_K );
		$this->empty_names_types_comb = $wpdb->get_results( "SELECT CONCAT( post_title, '-', post_type ), ID FROM $wpdb->posts WHERE post_name = ''", OBJECT_K );
	}

}
