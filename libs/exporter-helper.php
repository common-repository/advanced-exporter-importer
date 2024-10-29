<?php defined( 'ABSPATH' ) || exit();

class GPLS_AEI_Exporter_Helper {

	use GPLS_AEI_Exporter_Trait;

	/**
	 * Core Object
	 *
	 * @var object
	 */
	public $core;

	/**
	 * Plugin Info
	 *
	 * @var object
	 */
	public $plugin_info;

	/**
	 * Distinct Date Options For All CPTs.  [ cpt_slug ] => array( array( month => , year => , post_type => ) )
	 *
	 * @var array
	 */
	protected $cpts_date_options = array();

	/**
	 * Distinct Date Options For All CPTs.  [ cpt_slug ] => array( array( author_obj => , post_type => ) )
	 *
	 * @var array
	 */
	protected $cpts_author_options = array();

	/**
	 * CPTs Taxonomies and Terms Array Mapping.  [ cpt_slug ] => array of taxonomies names => array of terms objects
	 *
	 * @var array
	 */
	public $cpts_taxonomies_terms = array();

	/**
	 * Attachments Only IDs Array.
	 *
	 * @var array
	 */
	public $attachments_only_ids = array();

	/**
	 * Additional Users IDs.
	 *
	 * @var array
	 */
	public $additional_users_ids = array();

	/**
	 * Exporting Woo Products.
	 *
	 * @var boolean
	 */
	public $is_including_products = false;

	/**
	 * ACF Data Remapping.  array( post_id => array( 'type' => , 'key' => ) ) )
	 *
	 * @var array
	 */
	public $acf_remapping = array();


	/**
	 * Related ACF Terms.
	 *
	 * @var array
	 */
	public $related_acf_terms = array();


	/**
	 * Uploads Dir Details.
	 *
	 * @var array
	 */
	public $uploads_dir;

	/**
	 * Exported File name and Path.
	 *
	 * @var string
	 */
	public $export_file_name;

	/**
	 * Cycle Count Posts for Export.
	 *
	 * @var integer
	 */
	public $cycle_count = 50;

	/**
	 * Inner Steps For Export.
	 *
	 * @var integer
	 */
	public $inner_export_steps = 5;

	/**
	 * List of Posts IDs that have shortcdes to remap.
	 *
	 * @var array
	 */
	public $shortcodes_need_remapping = array();

	/**
	 * URls in Post Content needs to remap array( post_id => serialized array of URLs )
	 *
	 * @var array
	 */
	public $post_content_urls_remap = array();

	/**
	 * URLs in Postmeta to remap array ( post_id => array of meta_keys and urls to remap )
	 *
	 * @var array
	 */
	public $postmeta_urls_remap = array();

	/**
	 * Attachemnts attribute names for links and shortcodes.
	 *
	 * @var array
	 */
	public $attachments_links_attr_names = array();

	/**
	 * Related Links Basenames.
	 *
	 * @var array
	 */
	public $related_links_basenames = array();

	/**
	 * Constructor.
	 *
	 * @param object $core
	 * @param object $plugin_info
	 */
	public function __construct( $core, $plugin_info ) {
		$this->core        = $core;
		$this->plugin_info = $plugin_info;
		$this->uploads_dir = wp_get_upload_dir();
		$this->hooks();
	}

	/**
	 * Actions and Filters Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_loaded', array( $this, 'download_export_file' ) );
	}


	/**
	 * Add Admin Notice
	 *
	 * @param string $notice
	 * @param string $type  ( error - success - info - warning )
	 * @return void
	 */
	public function add_notice( $notice = '', $type = 'error', $is_dismissible = false ) {
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> <?php echo( $is_dismissible ? 'is-dismissible' : '' ); ?>">
			<p><?php _e( $notice, 'gpls-cpt-exporter-importer' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Get Custom Post Types
	 *
	 * @return Array array of cpt_slugs
	 */
	public function get_cpts() {
		$pypass_cpts = array( 'acf-field-group', 'acf-field', 'attachment', 'nav_menu_item', 'custom_css', 'product_variation', 'shop_order', 'shop_order_refund', 'shop_coupon' );
		return array_filter(
			get_post_types(
				array(
					'can_export' => true,
				)
			),
			function( $cpt_slug ) use ( $pypass_cpts ) {
				return ! in_array( $cpt_slug, $pypass_cpts );
			}
		);
	}

	/**
	 * Get custom Post Types statuses
	 *
	 * @param string $cpt_slug
	 * @return array
	 */
	public function get_cpt_statuses( $cpt_slug ) {
		global $wpdb;
		$statuses         = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_status FROM {$wpdb->prefix}posts WHERE post_type = '%s'", $cpt_slug ) );
		$auto_draft_index = array_search( 'auto-draft', $statuses, true );
		if ( false !== $auto_draft_index ) {
			unset( $statuses[ $auto_draft_index ] );
		}
		return $statuses;
	}

	/**
	 * Get Custom Post Types Posts'count.
	 *
	 * @param string $cpt_slug
	 * @return void
	 */
	public function get_cpt_count( $cpt_slug ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = '%s'", $cpt_slug ) );
	}

	/**
	 * Check if table exists.
	 *
	 * @param string $table_name
	 * @return boolean
	 */
	public function check_table_exists( $table_name ) {
		global $wpdb;
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
		if ( $wpdb->get_var( $query ) == $table_name ) {
			return true;
		}
		return false;
	}


	/**
	 * Start CPT Export
	 *
	 * @param array $cpt_names
	 * @param array $terms
	 * @param array $direct_posts_ids
	 *
	 * @return array
	 */
	public function export_query( $cpt_names, $terms, $direct_posts_ids, $all_cpt_names ) {
		$cache_key = $this->plugin_info['name'] . '-cpt-export-results-' . md5( json_encode( $cpt_names ) . json_encode( $terms ) . json_encode( $direct_posts_ids ) );
		$result    = json_decode( get_site_transient( $cache_key ), true );
		if ( ! $result ) {
			$result = array();
			if (  in_array( 'product', $all_cpt_names ) ) {
				$this->is_including_products = true;
			}

			foreach ( $cpt_names as $cpt_name => $cpt_options_arr ) :
				if ( ! empty( $terms[ $cpt_name ] ) ) :
					$result = array_merge( $result, $this->export_cpt_for_terms( $cpt_name, $cpt_options_arr, $terms ) );
				else :
					$result = array_merge( $result, $this->export_cpt( $cpt_name, $cpt_options_arr ) );
				endif;
			endforeach;

			// process the specific cpt posts IDs.
			if ( ! empty( $direct_posts_ids ) ) {
				$result = array_merge( $result, $this->export_cpts_direct( $direct_posts_ids ) );
			}

			$result         = $this->get_posts_other_related( $result );
			$result         = array_unique( array_merge( $result, $this->attachments_only_ids ) );
			$transient_data = json_encode(
				array(
					'attachments_ids'           => array_unique( $this->attachments_only_ids ),
					'acf_remapping'             => $this->acf_remapping,
					'post_content_urls_remap'   => $this->post_content_urls_remap,
					'postmeta_urls_remap'       => $this->postmeta_urls_remap,
					'related_acf_terms'         => $this->related_acf_terms,
					'related_links_basenames'   => $this->related_links_basenames,
					'additional_users_ids'      => $this->additional_users_ids,
					'shortcodes_need_remapping' => $this->shortcodes_need_remapping,
					'is_including_products'     => $this->is_including_products,
					'posts_ids'                 => $result,
				)
			);

			set_site_transient( $cache_key, $transient_data, 1800 );

		} else {
			$this->attachments_only_ids      = $result['attachments_ids'];
			$this->acf_remapping             = $result['acf_remapping'];
			$this->post_content_urls_remap   = $result['post_content_urls_remap'];
			$this->postmeta_urls_remap       = $result['postmeta_urls_remap'];
			$this->related_acf_terms         = $result['related_acf_terms'];
			$this->related_links_basenames   = $result['related_links_basenames'];
			$this->additional_users_ids      = $result['additional_users_ids'];
			$this->shortcodes_need_remapping = $result['shortcodes_need_remapping'];
			$this->is_including_products     = $result['is_including_products'];
			$result                          = $result['posts_ids'];
		}
		return $result;
	}

	/**
	 * Get all possible related Posts / Pages / Content from Posts IDs.
	 *
	 * @param array $result
	 * @return array
	 */
	public function get_posts_other_related( $result, $include_products = false, $additional_terms = array() ) {

		// Get any related Posts.
		$result = $this->get_more_connected_posts( $result, $include_products );

		// Get ACF Related Terms.
		$this->export_cpt_related_acf_terms( $result );

		// Get Posts Related Attachments.
		$this->export_cpt_related_attachments( $result, $include_products );

		// Get ACF Related Links.
		$this->export_related_acf_links( $result );

		// Get Related Posts By Links basename.
		$result = array_merge( $result, $this->export_links_posts() );

		// Get ACF Attachments.
		$this->export_related_acf_attachments( $result );

		// GET ACF galleries.
		$this->export_related_acf_galleries( $result );

		// Get ACF Users Related Fields.
		$this->export_related_acf_users( $result );

		// Get Terms Attachments for All CPTs IDs.
		$this->export_terms_thumbnails( $result, $additional_terms );

		return $result;
	}

	/**
	 * Get Any Connected Posts ( Woo Children - ACF Relations - LInks to other Posts in Content ).
	 *
	 * @param array $result
	 * @param boolean $include_products
	 * @return void
	 */
	private function get_more_connected_posts( $result, $include_products ) {
		$more_posts_ids = array();
		// Get Variables Products and Groupd children Products for WooCommerce.
		if ( $this->is_including_products || $include_products ) {
			$more_posts_ids = array_merge( $more_posts_ids, $this->product_grouped_children( $result ) );
			$more_posts_ids = array_merge( $more_posts_ids, $this->product_variations_query( $result ) );
		}

		// Get ACF Related Posts ( Post Object, Page Link, Relationships ).
		$more_posts_ids = array_merge( $more_posts_ids, $this->export_cpt_related_acf_posts( $result ) );

		// Get Posts Related Attachments in Posts content.
		$more_posts_ids = array_merge( $more_posts_ids, $this->export_cpt_related_attachments_and_posts_in_content( $result ) );
		$more_posts_ids = array_merge( $more_posts_ids, $this->export_cpt_related_acf_wysiwyg_attachments_and_posts_in_content( $result ) );
		$result         = array_unique( array_merge( $more_posts_ids, $result ) );

		if ( empty( $more_posts_ids ) ) {
			return $result;
		}

		do {
			$temp_more_ids = array();
			if ( $this->is_including_products || $include_products ) {
				$temp_more_ids = array_merge( $temp_more_ids, $this->product_grouped_children( $more_posts_ids ) );
				$temp_more_ids = array_merge( $temp_more_ids, $this->product_variations_query( $more_posts_ids ) );
			}
			$temp_more_ids = array_merge( $temp_more_ids, $this->export_cpt_related_acf_posts( $more_posts_ids ) );
			$temp_more_ids = array_merge( $temp_more_ids, $this->export_cpt_related_attachments_and_posts_in_content( $more_posts_ids ) );
			$temp_more_ids = array_merge( $temp_more_ids, $this->export_cpt_related_acf_wysiwyg_attachments_and_posts_in_content( $more_posts_ids ) );

			if ( ! empty( $temp_more_ids ) ) {
				$result         = array_unique( array_merge( $temp_more_ids, $result ) );
				$more_posts_ids = $temp_more_ids;
			}
		} while ( ! empty( $temp_more_ids ) );

		return $result;
	}

	/**
	 * Product Variations IDs.
	 *
	 * @param array $cpts_ids
	 * @return array
	 */
	public function product_variations_query( $cpts_ids ) {
		global $wpdb;
		$ids   = array();
		$query =
			"SELECT
				pp.ID
			FROM
				{$wpdb->prefix}posts p
			LEFT JOIN
				{$wpdb->prefix}posts pp
			ON
				p.ID = pp.post_parent
			WHERE
				p.ID IN ('" . implode( "','", $cpts_ids ) . "')
			AND
				pp.post_type = 'product_variation'
		";

		$ids = $wpdb->get_col( $query );
		return $ids;
	}

	/**
	 * Grouped Products Children
	 *
	 * @param array $cpts_ids
	 * @return array
	 */
	public function product_grouped_children( $cpts_ids ) {
		global $wpdb;
		$ids   = array();
		$query =
			"SELECT
				pm.meta_value
			FROM
				{$wpdb->prefix}posts p
			LEFT JOIN
				{$wpdb->prefix}postmeta pm
			ON
				p.ID = pm.post_id
			WHERE
				p.ID IN ('" . implode( "','", $cpts_ids ) . "')
			AND
				p.post_type = 'product'
			AND
				pm.meta_key = '_children'
		";

		$grouped_children_ids = $wpdb->get_col( $query );
		if ( ! empty( $grouped_children_ids ) ) {
			$ids = wp_parse_id_list( array_merge( ...array_map( 'unserialize', $grouped_children_ids ) ) );
		}

		return $ids;
	}

	/**
	 * Direct CPT IDs Query
	 *
	 * @param string $cpt_name
	 * @param array  $cpt_options_arr
	 * @return string
	 */
	public function plain_cpt_query( $cpt_name, $cpt_options_arr ) {
		global $wpdb;
		$cpts_ids = array();
		$query    =
			"SELECT
				p.ID
			FROM
				{$wpdb->prefix}posts p
			WHERE
				p.post_type = '{$cpt_name}'
		";

		if ( ! empty( $cpt_options_arr['statuses'] ) ) {
			$posts_statuses = $cpt_options_arr['statuses'];
			$query         .= " AND p.post_status IN ('" . implode( "','", $posts_statuses ) . "') ";
		} else {
			$query .= " AND p.post_status != 'auto-draft'";
		}

		if ( ! empty( $cpt_options_arr['authors'] ) ) {
			$posts_authors = $cpt_options_arr['authors'];
			$query        .= " AND p.post_author IN ('" . implode( "','", $posts_authors ) . "') ";
		}

		if ( ! empty( $cpt_options_arr['start_date'] ) ) {
			$posts_start_date = $cpt_options_arr['start_date'];
			$query           .= $wpdb->prepare( ' AND p.post_date >= %s', gmdate( 'Y-m-d', strtotime( $posts_start_date ) ) );
		}

		if ( ! empty( $cpt_options_arr['end_date'] ) ) {
			$post_end_date = $cpt_options_arr['end_date'];
			$query        .= $wpdb->prepare( ' AND p.post_date < %s', gmdate( 'Y-m-d', strtotime( '+1 month', strtotime( $post_end_date ) ) ) );
		}

		$cpts_ids = $wpdb->get_col( $query );
		return $cpts_ids;
	}

	/**
	 * Export CPTs Posts Directly By Ids.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function export_cpts_direct( $posts_ids ) {
		$related_children_ids   = $this->product_grouped_children( $posts_ids );
		$related_variations_ids = $this->product_variations_query( $posts_ids );
		return array_unique( array_merge( $posts_ids, $related_children_ids, $related_variations_ids ) );
	}

	/**
	 * Export CPT Query.
	 *
	 * @param String $cpt_name
	 * @param array  $cpt_options_arr
	 * @return Array
	 */
	public function export_cpt( $cpt_name, $cpt_options_arr ) {
		global $wpdb;

		$cpts_ids = $this->plain_cpt_query( $cpt_name, $cpt_options_arr );

		return $cpts_ids;
	}

	/**
	 * Conditional Query for CPTs with Terms.
	 *
	 * @param array  $cpt_ids
	 * @param string $cpt_name
	 * @param array  $terms
	 * @return array
	 */
	public function dynamic_cpt_by_terms( $cpt_ids, $cpt_name, $terms ) {
		global $wpdb;
		if ( empty( $cpt_ids ) ) {
			return array();
		}

		foreach ( $terms[ $cpt_name ] as $taxonomy_name => $taxonomy_terms_arr ) :
			$cpt_ids = $wpdb->get_col(
				"SELECT
					p.id
				FROM
					$wpdb->posts p
				INNER JOIN
					$wpdb->term_relationships tr
				ON
					p.ID = tr.object_id
				INNER JOIN
					$wpdb->term_taxonomy tt
				ON
					tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE
					p.ID IN ('" . implode( "','", $cpt_ids ) . "')
				AND
					tt.term_id IN ('" . implode( "','", $taxonomy_terms_arr ) . "')
				"
			);
		endforeach;

		return $cpt_ids;
	}

	/**
	 * Export CPT for specific Terms
	 *
	 * @param String $cpt_name
	 * @param Array  $cpt_options_arr
	 * @param Array  $terms
	 * @return Array
	 */
	public function export_cpt_for_terms( $cpt_name, $cpt_options_arr, $terms = array() ) {
		global $wpdb;

		$cpts_ids = $this->plain_cpt_query( $cpt_name, $cpt_options_arr );
		$cpts_ids = $this->dynamic_cpt_by_terms( $cpts_ids, $cpt_name, $terms );

		return $cpts_ids;
	}

	/**
	 * Get Realated Post in ACF Fields.
	 *
	 * @param array $cpts_ids
	 * @return array
	 */
	public function export_cpt_related_acf_posts( $posts_ids ) {
		global $wpdb;
		$ids = array();
		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:12:\"relationship\"|s:11:\"post_object\|s:9:\"page_link\")'" );

		if ( ! $field_type_exists ) {
			return $ids;
		}

		$related_posts_serialized = array();
		$query                    = "SELECT
			p.ID as post_id, pm2.meta_key, pm2.meta_value
		FROM
			{$wpdb->posts} AS p
		INNER JOIN
			{$wpdb->postmeta} AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			{$wpdb->prefix}posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			{$wpdb->postmeta} pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:12:\"relationship\"|s:11:\"post_object\|s:9:\"page_link\")'
		AND
			pm2.meta_value <> ''
		";

		$related_posts_serialized = $wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $related_posts_serialized ) ) {

			foreach ( $related_posts_serialized as $arr ) {

				if ( ! isset( $this->acf_remapping[ $arr['post_id'] ] ) ) {
					$this->acf_remapping[ $arr['post_id'] ] = array();
				}

				$this->acf_remapping[ $arr['post_id'] ][] = array(
					'type'    => 'serialized_field',
					'subtype' => 'post_page_linking',
					'key'     => $arr['meta_key'],
				);
			}

			$flatten_arr = array_merge( ...array_values( array_map( array( $this, 'maybe_unserialize_arr' ), array_column( $related_posts_serialized, 'meta_value' ) ) ) );

			$related_posts_serialized = array_unique(
				array_filter(
					$flatten_arr,
					function( $arr ) {
						return is_numeric( $arr );
					}
				)
			);

			$related_pages_links = array_unique(
				array_filter(
					$flatten_arr,
					function( $arr ) {
						return ! is_numeric( $arr );
					}
				)
			);

			unset( $flatten_arr );

			$related_pages_basenames = array();

			if ( ! empty( $related_pages_links ) ) {
				foreach ( $related_pages_links as $related_page_link ) {
					if ( ! empty( $related_page_link ) ) {
						$related_pages_basenames[] = wp_basename( $related_page_link );
					}
				}
				$this->related_links_basenames = array_merge( $this->related_links_basenames, $related_pages_basenames );
			}

			if ( ! empty( $related_posts_serialized ) ) {
				$ids = $related_posts_serialized;
			}

		}

		return $ids;
	}

	/**
	 * Get Related Terms in ACF.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function export_cpt_related_acf_terms( $posts_ids ) {
		global $wpdb;

		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:8:\"taxonomy\")'" );

		if ( ! $field_type_exists ) {
			return $posts_ids;
		}

		$related_terms_serialized = array();
		$query                    = "SELECT
			p.ID as post_id, pm2.meta_key, pm2.meta_value
		FROM
			{$wpdb->prefix}posts AS p
		INNER JOIN
			{$wpdb->prefix}postmeta AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			{$wpdb->prefix}posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			{$wpdb->prefix}postmeta pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:8:\"taxonomy\")'
		AND
			pm2.meta_value <> ''
		";

		$related_terms_serialized = $wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $related_terms_serialized ) ) {

			foreach ( $related_terms_serialized as $arr ) {

				if ( ! isset( $this->acf_remapping[ $arr['post_id'] ] ) ) {
					$this->acf_remapping[ $arr['post_id'] ] = array();
				}

				$this->acf_remapping[ $arr['post_id'] ][] = array(
					'type' => 'serialized_terms_field',
					'key'  => $arr['meta_key'],
				);
			}

			$related_terms_serialized = array_merge( ...array_values( array_map( 'unserialize', array_filter( array_column( $related_terms_serialized, 'meta_value' ) ) ) ) );
			$this->related_acf_terms  = $related_terms_serialized;
		}

	}

	/**
	 * Check if Attachments and Posts in ACF wysiwyg content.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function export_cpt_related_acf_wysiwyg_attachments_and_posts_in_content( $posts_ids ) {
		global $wpdb;
		$attachments_urls                   = array();
		$attachments_ids                    = array();
		$related_posts_ids                  = array();
		$this->attachments_links_attr_names = implode( '|', array_merge( wp_get_audio_extensions(), wp_get_video_extensions(), array( 'src', 'href' ) ) );

		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:7:\"wysiwyg\")'" );

		if ( ! $field_type_exists ) {
			return $related_posts_ids;
		}

		$query = "SELECT
			p.ID as post_id, pm2.meta_key, pm2.meta_value
		FROM
			{$wpdb->prefix}posts AS p
		INNER JOIN
			{$wpdb->prefix}postmeta AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			{$wpdb->prefix}posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			{$wpdb->prefix}postmeta pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:7:\"wysiwyg\")'
		AND
			pm2.meta_value <> ''
		";

		$wysiwyg_content = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $wysiwyg_content as $wysiwyg_content_row ) {
			// Attachments and Posts Links.
			$found_matches = preg_match_all( '/(' . $this->attachments_links_attr_names . ')=[\'"](.*?)[\'"]/i', $wysiwyg_content_row['meta_value'], $matches );
			// $matches[1] => array( 'src', 'href', 'src', '..' );
			// $matches[2] => array( links of images and posts links );

			if ( $found_matches && ! empty( $matches[1] ) && ! empty( $matches[2] ) ) {
				foreach ( $matches[2] as $index => $_link ) {
					if ( $this->is_link_internal( $_link ) ) {
						if ( empty( $this->postmeta_urls_remap[ $wysiwyg_content_row['post_id'] ] ) ) {
							$this->postmeta_urls_remap[ $wysiwyg_content_row['post_id'] ] = array();
						}

						if ( empty( $this->postmeta_urls_remap[ $wysiwyg_content_row['post_id'] ][ $wysiwyg_content_row['meta_key'] ] ) ) {
							$this->postmeta_urls_remap[ $wysiwyg_content_row['post_id'] ][ $wysiwyg_content_row['meta_key'] ] = array();
						}

						$attachment_type_and_ext = wp_check_filetype( $_link );

						if ( empty( $attachment_type_and_ext['type'] ) ) {
							$link_post_id = url_to_postid( $_link );
							if ( $link_post_id ) {
								$related_posts_ids[]                                                                                = $link_post_id;
								$this->postmeta_urls_remap[ $wysiwyg_content_row['post_id'] ][ $wysiwyg_content_row['meta_key'] ][] = $_link;
							}
						} else {
							$filtered_attachment_link = $this->filter_attachment_url( $_link, $attachment_type_and_ext['type'] );
							if ( $filtered_attachment_link ) {
								$attachments_urls[]                                                                                 = $filtered_attachment_link;
								$this->postmeta_urls_remap[ $wysiwyg_content_row['post_id'] ][ $wysiwyg_content_row['meta_key'] ][] = $_link;
							}
						}
					}
				}
			}

			// Shortcodes Digging Part.
			$found_shortcodes = $this->has_target_shortcodes( $wysiwyg_content_row['meta_value'] );

			if ( ! empty( $found_shortcodes ) ) {
				$more_posts_ids    = $this->extract_ids_from_shortcodes( $found_shortcodes, $wysiwyg_content_row['post_id'] );
				$related_posts_ids = array_merge( $related_posts_ids, $more_posts_ids );
			}

		}

		$query =
		"SELECT
			post_id
		FROM
			{$wpdb->postmeta}
		WHERE
			meta_value IN ('" . implode( "','", $attachments_urls ) . "')
		AND
			meta_key = '_wp_attached_file'
		";

		$result = $wpdb->get_col( $query );

		if ( ! empty( $result ) ) {
			$this->attachments_only_ids = array_merge( $this->attachments_only_ids, $result );
		}

		return $related_posts_ids;
	}


	/**
	 * Get Attachments from Posts Content.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function export_cpt_related_attachments_and_posts_in_content( $posts_ids ) {
		global $wpdb;
		$attachments_urls                   = array();
		$attachments_ids                    = array();
		$related_posts_ids                  = array();
		$this->attachments_links_attr_names = implode( '|', array_merge( wp_get_audio_extensions(), wp_get_video_extensions(), array( 'src', 'href' ) ) );

		while ( $next_posts = array_splice( $posts_ids, 0, 200 ) ) {

			$query = new WP_Query(
				array(
					'post__in'  => $next_posts,
					'post_type' => 'any',
				)
			);

			$posts = $query->posts;

			foreach ( $posts as $post ) {
				// Attachments and Posts Links.
				$found_matches = preg_match_all( '/(' . $this->attachments_links_attr_names . ')=[\'"](.*?)[\'"]/i', $post->post_content, $matches );
				// $matches[1] => array( 'src', 'href', 'src', '..' );
				// $matches[2] => array( links of images and posts links );

				if ( $found_matches && ! empty( $matches[1] ) && ! empty( $matches[2] ) ) {
					$this->post_content_urls_remap[ $post->ID ] = array();
					foreach ( $matches[2] as $index => $_link ) {
						if ( $this->is_link_internal( $_link ) ) {
							$attachment_type_and_ext = wp_check_filetype( $_link );
							if ( empty( $attachment_type_and_ext['type'] ) ) {
								$link_post_id = url_to_postid( $_link );
								if ( $link_post_id ) {
									$related_posts_ids[]                          = $link_post_id;
									$this->post_content_urls_remap[ $post->ID ][] = $_link;
								}
							} else {
								$filtered_attachment_link = $this->filter_attachment_url( $_link, $attachment_type_and_ext['type'] );
								if ( $filtered_attachment_link ) {
									$attachments_urls[]                           = $filtered_attachment_link;
									$this->post_content_urls_remap[ $post->ID ][] = $_link;
								}
							}
						}
					}
				}

				// Shortcodes Digging Part.
				$found_shortcodes = $this->has_target_shortcodes( $post->post_content );

				if ( ! empty( $found_shortcodes ) ) {
					$more_posts_ids    = $this->extract_ids_from_shortcodes( $found_shortcodes, $post->ID );
					$related_posts_ids = array_merge( $related_posts_ids, $more_posts_ids );
				}

			}
		}

		$query =
		"SELECT
			post_id
		FROM
			{$wpdb->postmeta}
		WHERE
			meta_value IN ('" . implode( "','", $attachments_urls ) . "')
		AND
			meta_key = '_wp_attached_file'
		";

		$result = $wpdb->get_col( $query );

		if ( ! empty( $result ) ) {
			$this->attachments_only_ids = array_merge( $this->attachments_only_ids, $result );
		}

		return $related_posts_ids;
	}


	/**
	 * Export Posts IDs Related Attachments.
	 *
	 * @param array $cpts_ids
	 * @return array
	 */
	public function export_cpt_related_attachments( $cpts_ids, $include_products = false ) {
		global $wpdb;

		// Direct inherited Attachments
		$query = "SELECT
				p.ID
			FROM
				{$wpdb->prefix}posts AS p
			INNER JOIN
				{$wpdb->prefix}posts AS pp
			ON
				p.post_parent = pp.id
			WHERE
				pp.id IN ('" . implode( "','", $cpts_ids ) . "')
			AND
				p.post_type = 'attachment'
			AND
				p.post_status = 'inherit'";

		$query .= ' UNION ALL ';

			// attachment added as a custom meta by ID ( thumbnails )

		$query .= "SELECT
				p.ID
			FROM
				{$wpdb->prefix}posts AS p			# attachment
			INNER JOIN
				{$wpdb->prefix}postmeta AS pm		# target CPT's postmeta
			ON
				p.ID = pm.meta_value
			INNER JOIN
				{$wpdb->prefix}posts AS pp			# target CPT
			ON
				pp.ID = pm.post_id
			WHERE
				pp.id IN ('" . implode( "','", $cpts_ids ) . "')
			AND
				p.post_type = 'attachment'
			AND
				pm.meta_key = '_thumbnail_id'";

		if ( $this->is_including_products || $include_products ) {

			$query .= ' UNION ALL ';

			// Product Gallery for WooCommerce.

			$query .= " SELECT
				p.ID
			FROM
				{$wpdb->prefix}posts AS p			# attachment
			INNER JOIN
				{$wpdb->prefix}postmeta AS pm		# target CPT's postmeta
			ON
				FIND_IN_SET( p.ID, pm.meta_value )
			INNER JOIN
				{$wpdb->prefix}posts AS pp			# Target CPT
			ON
				pp.ID = pm.post_id
			WHERE
				pp.id IN ('" . implode( "','", $cpts_ids ) . "')
			AND
				p.post_type = 'attachment'
			AND
				pm.meta_key = '_product_image_gallery'";
		}

		$result                     = $wpdb->get_col( $query );
		$this->attachments_only_ids = array_merge( $this->attachments_only_ids, $result );

		return $result;
	}


	/**
	 * Get Posts Related ACF attachemnts ( file - image )
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function export_related_acf_attachments( $posts_ids ) {
		global $wpdb;

		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:5:\"image\"|s:4:\"file\")'" );

		if ( ! $field_type_exists ) {
			return $posts_ids;
		}

		$query = "SELECT DISTINCT
			p.ID as post_id, ppp.ID as attachment_id, pm2.meta_key
		FROM
			$wpdb->posts AS p
		INNER JOIN
			$wpdb->postmeta AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			$wpdb->posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			$wpdb->postmeta pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		INNER JOIN
			$wpdb->posts ppp
		ON
			ppp.ID = pm2.meta_value
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:5:\"image\"|s:4:\"file\")'
		AND
			pm2.meta_value <> ''
		";

		$result = $wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $result ) ) {

			foreach ( $result as $arr ) {

				if ( ! isset( $this->acf_remapping[ $arr['post_id'] ] ) ) {
					$this->acf_remapping[ $arr['post_id'] ] = array();
				}

				$this->acf_remapping[ $arr['post_id'] ][] = array(
					'type'    => 'single_field',
					'subtype' => 'attachment',
					'key'     => $arr['meta_key'],
				);
			}

			$this->attachments_only_ids = array_merge( $this->attachments_only_ids, wp_list_pluck( $result, 'attachment_id' ) );
		}

		return $result;
	}

	/**
	 * Get Realated Galleries in ACF Fields.
	 *
	 * @param array $cpts_ids
	 * @return array
	 */
	public function export_related_acf_galleries( $posts_ids ) {
		global $wpdb;

		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:7:\"gallery\")'" );

		if ( ! $field_type_exists ) {
			return $posts_ids;
		}

		$related_galleries_serialized = array();
		$query                        = "SELECT
			p.ID as post_id, pm2.meta_key, pm2.meta_value
		FROM
			$wpdb->posts AS p
		INNER JOIN
			$wpdb->postmeta AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			$wpdb->posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			$wpdb->postmeta pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:7:\"gallery\")'
		AND
			pm2.meta_value <> ''
		";

		$related_galleries_serialized = $wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $related_galleries_serialized ) ) {

			foreach ( $related_galleries_serialized as $arr ) {

				if ( ! isset( $this->acf_remapping[ $arr['post_id'] ] ) ) {
					$this->acf_remapping[ $arr['post_id'] ] = array();
				}

				$this->acf_remapping[ $arr['post_id'] ][] = array(
					'type'    => 'serialized_field',
					'subtype' => 'gallery',
					'key'     => $arr['meta_key'],
				);
			}

			$related_galleries_serialized = array_merge(
				...array_values(
					array_map( function( $col ) {
						if ( ! empty( $col ) ) {
							return maybe_unserialize( $col );
						} else {
							return array();
						}
					},
						array_column( $related_galleries_serialized, 'meta_value' )
					)
				)
			);
			$this->attachments_only_ids   = array_merge( $this->attachments_only_ids, $related_galleries_serialized );

		}

		return $related_galleries_serialized;
	}

	/**
	 * Export Related ACF Users.
	 *
	 * @param array $posts_ids
	 * @return void
	 */
	public function export_related_acf_users( $posts_ids ) {
		global $wpdb;
		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:4:\"user\")'" );

		if ( ! $field_type_exists ) {
			return $posts_ids;
		}

		$related_links  = array();
		$query          = "SELECT
			p.ID as post_id, pm2.meta_key, pm2.meta_value
		FROM
			$wpdb->posts AS p
		INNER JOIN
			$wpdb->postmeta AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			$wpdb->posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			$wpdb->postmeta pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:4:\"user\")'
		AND
			pm2.meta_value <> ''
		";

		$result = $wpdb->get_results( $query );

		if ( ! empty( $result ) ) {

			foreach ( $result as $row_obj ) {

				if ( ! isset( $this->acf_remapping[ $row_obj->post_id ] ) ) {
					$this->acf_remapping[ $row_obj->post_id ] = array();
				}

				if ( is_numeric( $row_obj->meta_value ) ) {
					$status = 'single';
				} else {
					$status = 'serialized';
				}

				$this->acf_remapping[ $row_obj->post_id ][] = array(
					'type'    => 'author_field',
					'status'  => $status,
					'subtype' => 'user',
					'key'     => $row_obj->meta_key,
				);

				if ( is_serialized( $row_obj->meta_value ) ) {
					$this->additional_users_ids = array_merge( $this->additional_users_ids, unserialize( $row_obj->meta_value ) );
				} elseif ( is_numeric( $row_obj->meta_value ) ) {
					$this->additional_users_ids[] = absint( $row_obj->meta_value );
				}
			}
		}
	}

	/**
	 * Export Related Posts Links Fields.
	 *
	 * @param array $posts_ids
	 * @return void
	 */
	public function export_related_acf_links( $posts_ids ) {
		global $wpdb;
		// Check if there is an acf-field post with this type.
		$field_type_exists = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'acf-field' AND post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:4:\"link\")'" );

		if ( ! $field_type_exists ) {
			return $posts_ids;
		}

		$related_links  = array();
		$query          = "SELECT
			p.ID as post_id, pm2.meta_key, pm2.meta_value
		FROM
			$wpdb->posts AS p
		INNER JOIN
			$wpdb->postmeta AS pm
		ON
			p.ID = pm.post_id
		INNER JOIN
			$wpdb->posts AS pp
		ON
			pp.post_name = pm.meta_value
		INNER JOIN
			$wpdb->postmeta pm2
		ON
			SUBSTRING( pm.meta_key, 2 ) = pm2.meta_key AND pm2.post_id = p.ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		AND
			pp.post_type = 'acf-field'
		AND
			pp.post_content REGEXP '^a:[0-9]+:[{]s:4:\"type\";(s:4:\"link\")'
		AND
			pm2.meta_value <> ''
		";

		$result              = $wpdb->get_results( $query );
		$related_links_names = array();
		if ( ! empty( $result ) ) {
			foreach ( $result as $row_obj ) {
				$link_arr = unserialize( $row_obj->meta_value );
				if ( ! empty( $link_arr ) && ! empty( $link_arr['url'] ) && $this->is_link_internal( $link_arr['url'] ) ) {
					if ( ! isset( $this->acf_remapping[ $row_obj->post_id ] ) ) {
						$this->acf_remapping[ $row_obj->post_id ] = array();
					}
					$this->acf_remapping[ $row_obj->post_id ][] = array(
						'type'    => 'link_field',
						'subtype' => 'link',
						'key'     => $row_obj->meta_key,
					);
					$related_links_names[] = wp_basename( $link_arr['url'] );
				}
			}

			if ( ! empty( $related_links_names ) ) {
				$this->related_links_basenames = array_merge( $this->related_links_basenames, $related_links_names );
			}
		}

		return $result;
	}


	/**
	 * Get the Thumbnails IDs for the CPT's Terms.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function export_terms_thumbnails( $posts_ids, $additional_terms = array() ) {
		global $wpdb;

		$additional_terms = array_merge( $this->related_acf_terms, $additional_terms );

		$query = "SELECT
				p.ID
			FROM
				{$wpdb->prefix}posts p
			INNER JOIN
				{$wpdb->prefix}termmeta tm
			ON
				p.ID = tm.meta_value
			INNER JOIN
				{$wpdb->prefix}terms t
			ON
				t.term_id = tm.term_id
			INNER JOIN
				{$wpdb->prefix}term_taxonomy tt
			ON
				t.term_id = tt.term_id
			INNER JOIN
				{$wpdb->prefix}term_relationships tr
			ON
				tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN
				{$wpdb->prefix}posts pp
			ON
				pp.ID = tr.object_id
			WHERE
				tm.meta_key = 'thumbnail_id'
			AND
				pp.ID IN ('" . implode( "','", $posts_ids ) . "')
			AND
				p.post_type = 'attachment' ";

		if ( ! empty( $additional_terms ) ) {
			$query .=
			" UNION
				SELECT
					p.ID
				FROM
					{$wpdb->posts} p
				INNER JOIN
					{$wpdb->termmeta} tm
				ON
					p.ID = tm.meta_value
				WHERE
					tm.term_id IN ('" . implode( "','", $additional_terms ) . "')
				AND
					tm.meta_key = 'thumbnail_id'
				AND
					p.post_type = 'attachment'";
		}

		$result = $wpdb->get_col( $query );

		if ( ! empty( $result ) ) {
			$this->attachments_only_ids = array_merge( $this->attachments_only_ids, $result );
		}

		return $result;
	}


	/**
	 * Export Posts By Links Basenames.
	 *
	 * @return void
	 */
	public function export_links_posts() {
		global $wpdb;
		$attachments_ids = array();
		$posts_ids       = array();

		if ( ! empty( $this->related_links_basenames ) ) {
			$result           = $wpdb->get_results(
				"SELECT
					ID, post_type
				FROM
					$wpdb->posts
				WHERE
					post_name IN ('" . implode( "','", $this->related_links_basenames ) . "')"
			);

			if ( ! empty( $result ) ) {
				foreach ( $result as $row ) {
					if ( 'attachment' === $row->post_type ) {
						$attachments_ids[] = $row->ID;
					} else {
						$posts_ids[] = $row->ID;
					}
				}

				if ( ! empty( $attachments_ids ) ) {
					$this->attachments_only_ids = array_merge( $this->attachments_only_ids, $attachments_ids );
				}
			}
		}
		return $posts_ids;
	}

	/**
	 * Date Start - End Options For CPT
	 *
	 * @param string $post_type
	 * @return void
	 */
	public function export_date_options( $post_type = 'post' ) {
		global $wpdb, $wp_locale;
		$post_type  = sanitize_text_field( $post_type );
		$cpts_types = $this->get_cpts();
		if ( empty( $this->cpts_date_options ) ) {
			$query = "SELECT DISTINCT
					YEAR( post_date ) AS year, MONTH( post_date ) AS month, post_type
				FROM
					$wpdb->posts
				WHERE
					post_type IN ('" . implode( "','", $cpts_types ) . "')
				AND
					post_status != 'auto-draft'
				ORDER BY
					post_date DESC";

			$months          = $wpdb->get_results( $query, ARRAY_A );
			$filtered_months = array();
			foreach ( $months as $month_arr ) {
				$filtered_months[ $month_arr['post_type'] ][] = $month_arr;
			}
			$this->cpts_date_options = $filtered_months;
		}

		$months      = ! empty( $this->cpts_date_options[ $post_type ] ) ? $this->cpts_date_options[ $post_type ] : array();
		$month_count = ! empty( $months ) ? count( $months ) : 0;
		if ( ! $month_count || ( 1 == $month_count && 0 == $months[0]['month'] ) ) {
			return;
		}

		foreach ( $months as $date ) {
			if ( 0 == $date['year'] ) {
				continue;
			}
			$month = zeroise( $date['month'], 2 );
			echo '<option value="' . $date['year'] . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date['year'] . '</option>';
		}
	}

	/**
	 * Authors Options For CPT.
	 *
	 * @param string $post_type
	 * @return void
	 */
	public function export_authors_options( $post_type = 'post' ) {
		global $wpdb;
		if ( empty( $this->cpts_author_options ) ) {
			$cpts_types = $this->get_cpts();
			$query      =
				"SELECT
					u.ID, u.user_login, u.display_name, p.post_type
				FROM
					$wpdb->posts p
				INNER JOIN
					$wpdb->users u
				ON
					p.post_author = u.ID
				WHERE
					p.post_type IN ('" . implode( "','", $cpts_types ) . "')
				AND
					p.post_status != 'auto-draft'
				GROUP BY
					p.post_type, u.ID
				ORDER BY
					p.post_date DESC";

			$cpts_authors = $wpdb->get_results( $query, ARRAY_A );
			foreach ( $cpts_authors as $author ) {
				$this->cpts_author_options[ $author['post_type'] ][] = $author;
			}
		}
		?>
		<select class="cpt-authors-select" data-cpt_type="<?php echo $post_type; ?>" name="cpt_authors[<?php echo $post_type; ?>][]" multiple>
			<option value=""><?php _e( '&mdash; Select &mdash;' ); ?></option>
			<?php
			foreach ( (array) $this->cpts_author_options[ $post_type ] as $user ) :
				$display = sprintf( _x( '%1$s (%2$s)', 'user dropdown' ), $user['display_name'], $user['user_login'] );
				?>
				<option value="<?php echo $user['ID']; ?>"><?php echo $display; ?></option>
				<?php
			endforeach;
			?>
		</select>
		<?php
	}


	/**
	 * CPTs Taxonomies and Terms for Export.
	 *
	 * @param string $post_type
	 * @return void
	 */
	public function export_taxonomies_and_terms_options() {
		global $wpdb;
		$cpts                        = $this->get_cpts();
		$cpts_taxonomies             = get_object_taxonomies( $cpts, 'objects' );
		$cpts_taxonomies_names       = array_keys( $cpts_taxonomies );
		$this->cpts_taxonomies_terms = array();

		$query =
		"SELECT
			p.post_type, tt.taxonomy, tt.count, t.term_id, t.name
		FROM
			{$wpdb->prefix}posts p
		INNER JOIN
			{$wpdb->prefix}term_relationships tr
		ON
			p.ID = tr.object_id
		INNER JOIN
			{$wpdb->prefix}term_taxonomy tt
		ON
			tr.term_taxonomy_id = tt.term_taxonomy_id
		INNER JOIN
			{$wpdb->prefix}terms t
		ON
			t.term_id = tt.term_id
		WHERE
			tt.taxonomy IN ('" . implode( "','", $cpts_taxonomies_names ) . "')
		AND
			tt.count > 0
		GROUP BY
			p.post_type, tt.taxonomy, t.term_id
		ORDER BY
			t.name
		";

		$result                      = $wpdb->get_results( $query, ARRAY_A );
		$this->cpts_taxonomies_terms = array();

		foreach ( $result as $taxonomy_term ) {
			$this->cpts_taxonomies_terms[ $taxonomy_term['post_type'] ][ $taxonomy_term['taxonomy'] ][] = $taxonomy_term;
		}
	}

	/**
	 * Check which Export Type
	 *
	 * @param array $cpt_name
	 * @param array $terms
	 * @param array $direct_posts_ids
	 *
	 * @return void
	 */
	public function handle_export( $cpt_names, $terms = array(), $direct_posts_ids, $all_cpt_names, $step = 0 ) {
		set_time_limit( 0 );

		$total_posts = 0;

		if ( 0 == $step ) {
			$post_ids    = $this->export_query( $cpt_names, $terms, $direct_posts_ids, $all_cpt_names );
			$total_posts = count( $post_ids );
		} else {
			$saved_post_ids = get_site_option( $this->plugin_info['name'] . '-export-post-ids-result', array() );
			$post_ids       = $saved_post_ids['post_ids'];
			$total_posts    = $saved_post_ids['total_posts'];
		}

		if ( empty( $post_ids ) ) {
			wp_send_json_error( __( 'No Posts Found', 'gpls-cpt-exporter-importer' ) );
		}

		if ( ! empty( $_POST['submit'] ) && 'export_media_zip' === sanitize_text_field( wp_unslash( $_POST['submit'] ) ) ) {

			if ( empty( $this->attachments_only_ids ) ) {
				wp_send_json_error( __( 'No Attachments was found for the exported data', 'gpls-cpt-exporter-importer' ) );
			}

			$sitename               = sanitize_key( get_bloginfo( 'name' ) );
			$date                   = gmdate( 'Y-m-d' );
			$filename               = $sitename . '-advanced-cpt-export-' . $date . '.zip';
			$attachment_obj         = new GPLS_AEI_Attachment_Helper( $filename );
			$zip_path               = $attachment_obj->prepare_attachments_zip_file( $this->attachments_only_ids );
			$this->export_file_name = $zip_path;
			if ( ! file_exists( $zip_path ) ) {
				wp_send_json_error( __( 'Attachments files are missing from uploads folder!', 'gpls-cpt-exporter-importer' ) );
			}
			$download_url = $this->prepare_download_link( $filename, 'zip' );

			wp_send_json_success(
				array(
					'result'        => 'success',
					'progress'      => 'end',
					'download_link' => $download_url,
				)
			);

		} else {

			$this->export_wp( $post_ids, $step, $total_posts );

		}
	}


	/**
	 * Export WP using DomDocument.
	 *
	 * @param array $post_ids
	 * @param int   $step
	 * @param int   $total_posts
	 * @return void
	 */
	public function export_wp( $post_ids, $step, $total_posts ) {
		global $wpdb, $post;

		$sitename               = sanitize_key( get_bloginfo( 'name' ) );
		$date                   = gmdate( 'Y-m-d' );
		$filename               = $sitename . '-advanced-cpt-export-' . $date . '.xml';
		$this->export_file_name = trailingslashit( $this->uploads_dir['basedir'] ) . $filename;

		add_filter( 'wxr_export_skip_postmeta', array( $this, 'wxr_filter_postmeta' ), 10, 2 );

		$this->export_xml( $post_ids, $step, $total_posts );

		$download_url = $this->prepare_download_link( $filename );

		wp_send_json_success(
			array(
				'result'        => 'success',
				'progress'      => 'end',
				'download_link' => $download_url,
			)
		);
	}

	/**
	 * Prepare Download Link for exported File.
	 *
	 * @return string
	 */
	public function prepare_download_link( $file_name, $type = 'xml' ) {
		$download_key = md5( uniqid( rand(), true ) );
		$download_url = admin_url( 'tools.php' ) . '?page=' . $this->plugin_info['name'] . '&key=' . $download_key . '&nonce=' . wp_create_nonce( $this->plugin_info['name'] . '-download-exported-file' );
		$data         = array(
			'type'      => $type,
			'key'       => $download_key,
			'path'      => $this->export_file_name,
			'file_name' => $file_name,
		);
		set_site_transient( $this->plugin_info['name'] . '-download-exported-file-details', $data );

		return $download_url;
	}

	/**
	 * Download Exported XML File.
	 *
	 * @return void
	 */
	public function download_export_file() {

		if ( ! empty( $_GET['page'] ) && ! empty( $_GET['key'] ) && ! empty( $_GET['nonce'] ) && ( $this->plugin_info['name'] === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) && current_user_can( 'manage_options') ) {

			check_admin_referer( $this->plugin_info['name'] . '-download-exported-file', 'nonce' );

			$data = get_site_transient( $this->plugin_info['name'] . '-download-exported-file-details' );

			if ( ! $data ) {
				return false;
			}

			$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

			if ( $data['key'] !== $key ) {
				return false;
			}

			if ( 'zip' === $data['type'] ) {

				if ( file_exists( $data['path'] ) ) {

					header( 'Set-Cookie: ' . $this->plugin_info['name'] . '-export-finish' . '=' . $data['type'] );
					header( 'Content-Description: File Transfer' );
					header( 'Content-Disposition: attachment; filename=' . $data['file_name'] );
					header( 'Content-Type: application/zip; charset=' . get_site_option( 'blog_charset' ), true );

					$this->readfile_chunked( $data['path'] );

				} else {
					return false;
				}
			} else {

				header( 'Set-Cookie: ' . $this->plugin_info['name'] . '-export-finish' . '=cpt' );
				header( 'Content-Description: File Transfer' );
				header( 'Content-Disposition: attachment; filename=' . $data['file_name'] );
				header( 'Content-Type: text/xml; charset=' . get_site_option( 'blog_charset' ), true );

				$this->readfile_chunked( $data['path'] );
			}

			@unlink( $data['path'] );
			delete_site_transient( $this->plugin_info['name'] . '-download-exported-file-details' );
			die();
		}
	}


	/**
	 * Export XML using DOMDocument.
	 *
	 * @param array $post_ids
	 * @return void
	 */
	public function export_xml( $post_ids, $step, $total_posts, $export_type = 'cpt', $additional_terms_ids = array() ) {
		global $wpdb;

		$general_terms = array();
		$file_pointer  = false;

		$dom = new XMLWriter();
		$dom->openMemory();
		$dom->setIndent( true );

		if ( 0 == $step ) {
			$dom->startDocument( '1.0', 'UTF-8' );
			$dom->startElement( 'channel' );
			$dom->writeElement( 'base_url', rtrim( home_url(), '/' ) );
			$dom->writeElement( 'export_type', 'cpt' );
			$dom->writeELement( 'total_items', intval( count( $post_ids ) ) );
			$dom->writeElement( 'shortcode_remap', implode( ',', $this->shortcodes_need_remapping ) );

			if ( $this->apply_steps( $post_ids ) ) {
				update_site_option( $this->plugin_info['name'] . '-exported-urls-remap', json_encode( $this->post_content_urls_remap ) );
				update_site_option( $this->plugin_info['name'] . '-exported-postmeta-urls-remap', json_encode( $this->postmeta_urls_remap ) );
				update_site_option( $this->plugin_info['name'] . '-is-include-products', $this->is_including_products );
			}

			// Authors //
			$this->xml_authors_list( $dom, $post_ids );

			$file_pointer = fopen( $this->export_file_name, 'w' );
			fwrite( $file_pointer, $dom->flush( true ) );

		} else {
			$dom->startElement( 'schannel' );
			$this->post_content_urls_remap = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-exported-urls-remap', '' ), true );
			$this->postmeta_urls_remap     = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-exported-postmeta-urls-remap', '' ), true );
			$this->is_including_products   = get_site_option( $this->plugin_info['name'] . '-is-include-products' );
		}

		$next_posts = array();
		$counter    = 0;

		// Fetch 50 posts at a time rather than loading the entire table into memory.
		while ( $next_posts = array_splice( $post_ids, 0, $this->cycle_count ) ) {
			$posts                 = array();
			$cached_and_non_cached = $this->filter_cached_posts( $next_posts );

			if ( ! empty( $cached_and_non_cached['non_cached'] ) ) {
				$non_cached_ids = $cached_and_non_cached['non_cached'];
				$posts          = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}posts WHERE ID IN ('" . implode( "','", $non_cached_ids ) . "')"
				);
			}

			// Posts.
			$posts = array_merge( $posts, $cached_and_non_cached['cached_posts'] );

			// Terms.
			$resulted_terms  = $this->query_posts_terms( $next_posts );
			$posts_terms     = $resulted_terms['sorted'];
			$posts_terms_ids = $resulted_terms['terms_ids'];
			$general_terms   = array_unique( array_merge( $general_terms, $posts_terms_ids ) );

			// Postsmeta.
			$posts_meta = $this->query_posts_postmeta( $next_posts );

			// Posts Comments.
			$posts_comments = $this->query_posts_comments( $next_posts );

			// Begin Loop.
			foreach ( $posts as $post ) {
				$post = (object) $post;
				$dom->startElement( 'item' );

					$dom->startAttribute( 'post_author' );
						$dom->writeRaw( get_the_author_meta( 'login', $post->post_author ) );
					$dom->endAttribute();

					$dom->startAttribute( 'import_id' );
						$dom->writeRaw( $post->ID );
					$dom->endAttribute();

					$dom->startAttribute( 'post_date' );
						$dom->writeRaw( $post->post_date );
					$dom->endAttribute();

					$dom->startAttribute( 'post_date_gmt' );
						$dom->writeRaw( $post->post_date_gmt );
					$dom->endAttribute();

					$dom->startAttribute( 'post_status' );
						$dom->writeRaw( $post->post_status );
					$dom->endAttribute();

					$dom->startAttribute( 'comment_status' );
						$dom->writeRaw( $post->comment_status );
					$dom->endAttribute();

					$dom->startAttribute( 'ping_status' );
						$dom->writeRaw( $post->ping_status );
					$dom->endAttribute();

					$dom->startAttribute( 'post_name' );
						$dom->writeRaw( $post->post_name );
					$dom->endAttribute();

					$dom->startAttribute( 'post_modified' );
						$dom->writeRaw( $post->post_modified );
					$dom->endAttribute();

					$dom->startAttribute( 'post_modified_gmt' );
						$dom->writeRaw( $post->post_modified_gmt );
					$dom->endAttribute();

					$dom->startAttribute( 'menu_order' );
						$dom->writeRaw( $post->menu_order );
					$dom->endAttribute();

					$dom->startAttribute( 'post_type' );
						$dom->writeRaw( $post->post_type );
					$dom->endAttribute();

					$dom->startAttribute( 'post_mime_type' );
						$dom->writeRaw( $post->post_mime_type );
					$dom->endAttribute();

					$dom->startAttribute( 'comment_count' );
						$dom->writeRaw( $post->comment_count );
					$dom->endAttribute();

					$dom->startAttribute( 'is_sticky' );
						$dom->writeRaw( intval( is_sticky( $post->ID ) ? 1 : 0 ) );
					$dom->endAttribute();

					$dom->startAttribute( 'post_parent' );
						$dom->writeRaw( intval( $post->post_parent ) );
					$dom->endAttribute();

					$dom->startElement( 'content' );
						$dom->writeRaw( $this->wxr_cdata( $post->post_content ) );
					$dom->endElement();
					$dom->startElement( 'title' );
						$dom->writeCdata( $post->post_title );
					$dom->endElement();
					$dom->startElement( 'excerpt' );
						$dom->writeRaw( $this->wxr_cdata( $post->post_excerpt ) );
					$dom->endElement();
					$dom->startElement( 'post_password' );
						$dom->writeCdata( $post->post_password );
					$dom->endElement();
					$dom->writeElement( 'to_ping', $post->to_ping );
					$dom->writeElement( 'pinged', $post->pinged );

					$dom->startElement( 'content_filtered' );
						$dom->writeRaw( $this->wxr_cdata( $post->post_content_filtered ) );
					$dom->endElement();
					$dom->startElement( 'guid' );
						$dom->writeCdata( $post->guid );
					$dom->endElement();

					if ( 'attachment' === $post->post_type ) {
						$dom->startElement( 'attachment_url' );
							$dom->writeCdata( wp_get_attachment_url( $post->ID ) );
						$dom->endElement();
					}

					if ( ! empty( $this->post_content_urls_remap[ $post->ID ] ) ) {
						$dom->startElement( 'urls_remap' );
							$dom->writeCdata( json_encode( $this->post_content_urls_remap[ $post->ID ] ) );
						$dom->endElement();
						unset( $this->post_content_urls_remap[ $post->ID ] );
					}

					if ( ! empty( $this->postmeta_urls_remap[ $post->ID ] ) ) {
						$dom->startElement( 'postmeta_urls_remap' );
							$dom->writeCdata( json_encode( $this->postmeta_urls_remap[ $post->ID ] ) );
						$dom->endElement();
						unset( $this->postmeta_urls_remap[ $post->ID ] );
					}

					// Post Taxonomy Terms //
					$this->xml_post_terms( $dom, $posts_terms, $post->ID );

					// Post Metas //
					$this->xml_post_postmeta( $dom, $posts_meta, $post->ID, $export_type, $post->post_type );

					// Post Comments //
					$this->xml_post_comments( $dom, $posts_comments, $post->ID );

				$dom->endElement();
			}

			if ( false === $file_pointer ) {
				$file_pointer = fopen( $this->export_file_name, 'a' );
			}

			$dox = $dom->flush( true );

			if ( $step > 0 ) {
				$dox = preg_replace( '/<schannel>/', '', $dox, 1 );
			}

			fwrite( $file_pointer, $dox );

			unset( $posts, $posts_meta, $posts_comments, $dox );

			if ( ! empty( $post_ids ) && $counter >= $this->inner_export_steps && ( 'cpt' === $export_type ) ) {

				update_site_option(
					$this->plugin_info['name'] . '-export-post-ids-result',
					array(
						'post_ids'    => $post_ids,
						'total_posts' => $total_posts,
					)
				);

				$this->update_remapping_fields();

				if ( ! empty( $general_terms ) ) {
					$old_terms = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-exported-posts-terms', '' ), true );
					if ( $old_terms ) {
						$general_terms = array_merge( $general_terms, $old_terms );
					}
					update_site_option( $this->plugin_info['name'] . '-exported-posts-terms', json_encode( $general_terms ) );
				}

				wp_send_json_success(
					array(
						'result'      => 'success',
						'progress'    => ( ( ( $total_posts - count( $post_ids ) ) / $total_posts ) * 100 ),
						'total_posts' => $total_posts,
					)
				);
			}

			$counter += 1;
		}

		if ( $step > 0 ) {
			$this->related_acf_terms = get_site_option( $this->plugin_info['name'] . '-exported-acf-terms', array() );
			$this->acf_remapping     = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-exported-acf-remapping', '' ), true );
			$general_terms           = array_merge( $general_terms, (array) json_decode( get_site_option( $this->plugin_info['name'] . '-exported-posts-terms', '' ), true ) );
		}

		// All Terms //
		$general_terms = array_unique( array_merge( $this->related_acf_terms, $general_terms, $additional_terms_ids ) );
		$this->xml_terms( $dom, $general_terms );

		// WooCommerce Attributes Taxonomies //
		if ( $this->is_including_products ) {
			$this->xml_attribute_taxonomies( $dom );
		}

		// ACF Remap Fields.
		$this->xml_acf_remapping( $dom );

		$dox = $dom->flush( true );

		if ( $step > 0 ) {
			$dox = preg_replace( '/<schannel>/', '', $dox, 1 );
		}

		if ( false === $file_pointer ) {
			$file_pointer = fopen( $this->export_file_name, 'a' );
		}

		fwrite( $file_pointer, $dox. '</channel>' );
		fclose( $file_pointer );

		delete_site_option( $this->plugin_info['name'] . '-exported-acf-remapping' );
		delete_site_option( $this->plugin_info['name'] . '-exported-acf-terms' );
		delete_site_option( $this->plugin_info['name'] . '-exported-posts-terms' );
		delete_site_option( $this->plugin_info['name'] . '-export-post-ids-result' );
		delete_site_option( $this->plugin_info['name'] . '-exported-urls-remap' );
		delete_site_option( $this->plugin_info['name'] . '-exported-postmeta-urls-remap' );
		delete_site_option( $this->plugin_info['name'] . '-is-include-products' );
	}

	/**
	 * Read File Chunked.
	 *
	 * @param string $file
	 * @return void
	 */
	private function readfile_chunked( $file ) {
		$length     = 0;
		$start      = 0;
		$chunk_size = 1024 * 1024;
		$handle     = @fopen( $file, 'r' );

		if ( false === $handle ) {
			return false;
		}

		if ( ! $length ) {
			$length = @filesize( $file );
		}

		$read_length = (int) $chunk_size;

		if ( $length ) {
			$end = $start + $length - 1;

			@fseek( $handle, $start );
			$p = @ftell( $handle );

			while ( ! @feof( $handle ) && $p <= $end ) {
				if ( $p + $read_length > $end ) {
					$read_length = $end - $p + 1;
				}

				echo @fread( $handle, $read_length );
				$p = @ftell( $handle );

				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		} else {
			while ( ! @feof( $handle ) ) {
				echo @fread( $handle, $read_length );
				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		}

		return @fclose( $handle );
	}


	/**
	 * Filter Submitted Terms IDs.
	 *
	 * @param array $terms
	 * @return Array
	 */
	public function filter_terms_ids( $terms = array(), $cpt_names ) {
		$filtered_terms = array();
		foreach ( $terms as $cpt_name => $cpt_terms ) {
			if ( ! in_array( $cpt_name, $cpt_names ) ) {
				continue;
			}
			foreach ( $cpt_terms as $taxonomy_name => $taxonomy_terms ) {
				if ( empty( $taxonomy_terms ) ) {
					continue;
				}
				$taxonomy_terms = array_filter( $taxonomy_terms );
				if ( empty( $taxonomy_terms ) ) {
					continue;
				}
				$filtered_terms[ $cpt_name ][ $taxonomy_name ] = array_filter( array_unique( array_map( 'absint', $taxonomy_terms ) ) );
			}
		}

		return $filtered_terms;
	}

	/**
	 * Filtered Submitted CPT statuses
	 *
	 * @param array $cpt_names
	 * @param array $cpts_statuses
	 * @return array
	 */
	public function filter_cpt_statuses( $cpt_names, $cpts_statuses ) {
		$new_cpt_names = array();
		foreach ( $cpt_names as $cpt_name ) :
			if ( ! empty( $cpts_statuses[ $cpt_name ] ) ) {
				$new_cpt_names[ $cpt_name ]['statuses'] = array_filter( array_map( 'sanitize_text_field', $cpts_statuses[ $cpt_name ] ) );
			} else {
				$new_cpt_names[ $cpt_name ]['statuses'] = array();
			}
		endforeach;

		return $new_cpt_names;
	}


	/**
	 * Filtered Submitted CPT Dates
	 *
	 * @param array $cpt_names
	 * @param array $cpts_start_dates
	 * @param array $cpts_startcpts_end_dates_dates
	 * @return array
	 */
	public function filter_cpt_dates( $cpt_names, $cpts_start_dates, $cpts_end_dates ) {
		foreach ( $cpt_names as $cpt_name => $cpt_options_arr ) :
			if ( ! empty( $cpts_start_dates[ $cpt_name ] ) ) {
				$cpt_names[ $cpt_name ]['start_date'] = sanitize_text_field( $cpts_start_dates[ $cpt_name ] );
			} else {
				$cpt_names[ $cpt_name ]['start_date'] = '';
			}
		endforeach;

		foreach ( $cpt_names as $cpt_name => $cpt_options_arr ) :
			if ( ! empty( $cpts_end_dates[ $cpt_name ] ) ) {
				$cpt_names[ $cpt_name ]['end_date'] = sanitize_text_field( $cpts_end_dates[ $cpt_name ] );
			} else {
				$cpt_names[ $cpt_name ]['end_date'] = '';
			}
		endforeach;

		return $cpt_names;
	}


	/**
	 * Filtered Submitted CPT statuses
	 *
	 * @param array $cpt_names
	 * @param array $cpts_authors
	 * @return array
	 */
	public function filter_cpt_authors( $cpt_names, $cpts_authors ) {
		foreach ( $cpt_names as $cpt_name => $cpt_options_arr ) :
			if ( ! empty( $cpts_authors[ $cpt_name ] ) ) {
				$cpt_names[ $cpt_name ]['authors'] = array_filter( array_map( 'absint', array_filter( $cpts_authors[ $cpt_name ] ) ) );
			} else {
				$cpt_names[ $cpt_name ]['authors'] = array();
			}
		endforeach;

		return $cpt_names;
	}


	/**
	 * Get CPT posts List
	 *
	 * @param string  $cpt
	 * @param integer $paged
	 * @return array
	 */
	private function get_cpt_posts( $cpt, $paged = 1 ) {
		$args = array(
			'post_type'                => $cpt,
			'posts_per_page'           => 20,
			'paged'                    => $paged,
			'no_found_rows'            => true,
			'update_post_meta_cache'   => false,
			'update_object_term_cache' => false,
			'order'                    => 'DESC',
		);

		$posts        = new WP_Query( $args );
		$posts_result = array();

		if ( $posts->have_posts() ) {
			while ( $posts->have_posts() ) :
				$posts->the_post();
				$posts_result[ get_the_ID() ] = array(
					'title' => get_the_title(),
					'date'  => get_the_date( 'Y/m/d' ),
				);
			endwhile;
		}
		return $posts_result;
	}
}
