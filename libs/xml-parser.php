<?php defined( 'ABSPATH' ) || exit();

class GPLS_AEI_XML_Parser {

	/**
	 * Exoprt Type.
	 *
	 * @var string
	 */
	private $export_type;

	/**
	 * All Processed IDs.
	 *
	 * @var array
	 */
	private $processed_ids = array();

	/**
	 * Importer Helper Object.
	 *
	 * @var object
	 */
	private $importer_helper;

	/**
	 * Importing File Path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Import Cycle Count.
	 *
	 * @var integer
	 */
	public $cycle_count = 50;

	/**
	 * Total Items before Importing.
	 *
	 * @var integer
	 */
	public $total_items = 0;

	/**
	 * Constructor.
	 *
	 * @param importer_helper $importer_helper_obj
	 */
	public function __construct( $importer_helper_obj ) {
		$this->importer_helper = $importer_helper_obj;

		if ( ! defined( 'IMPORT_DEBUG' ) ) {
			define( 'IMPORT_DEBUG', true );
		}
	}

	/**
	 * Check if Steps Process needs to be applied.
	 *
	 * @return void
	 */
	public function apply_steps() {
		return ( $this->cycle_count < $this->total_items );
	}

	/**
	 * Parse Authors First For Author Mapping.
	 *
	 * @param string $file
	 * @return array
	 */
	public function parse_export_type_and_authors_first( $file, $needs_usermeta = false, $step = 0 ) {
		$result = array(
			'authors'     => array(),
			'export_type' => 'cpt',
			'base_url'    => '',
		);
		$reader = new XMLReader();
		$dom    = new DomDocument( '1.0', 'UTF-8' );
		$reader->open( $file );

		$start_parsing_authors     = false;
		$shortcode_remap_next_node = 'author';

		while ( @$reader->read() && 'base_url' !== $reader->name ) {
		}

		while ( $reader->nodeType === XMLReader::ELEMENT ) {

			// Base URL //
			if ( $reader->nodeType === XMLReader::ELEMENT && 'base_url' === $reader->name ) {
				$base_url           = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
				$result['base_url'] = (string) $base_url;
				$reader->next( 'export_type' );

				// Export Type //
			} elseif ( $reader->nodeType === XMLReader::ELEMENT && 'export_type' === $reader->name ) {
				$export_type           = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
				$result['export_type'] = (string) $export_type;

				if ( 'menu' === $result['export_type'] ) {
					$shortcode_remap_next_node = 'menu_items_depths';
				}

				$reader->next( 'total_items' );
			} elseif ( $reader->nodeType === XMLReader::ELEMENT && 'total_items' === $reader->name ) {
					$total_items           = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
					$this->total_items     = intval( $total_items );
					$result['total_items'] = intval( $total_items );

					$reader->next( 'shortcode_remap' );

					// Shortcodes Remap //
			} elseif ( $reader->nodeType === XMLReader::ELEMENT && 'shortcode_remap' === $reader->name ) {
				$shortcodes_posts_ids       = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
				$result['shortcodes_remap'] = (string) $shortcodes_posts_ids;
				$reader->next( $shortcode_remap_next_node );

				// Authors - Menus Items Remap //
			} elseif ( $reader->nodeType === XMLReader::ELEMENT && 'menu_items_depths' === $reader->name ) {
				$menu_items_depths           = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
				$result['menu_items_depths'] = (array) json_decode( (string) $menu_items_depths, true );
				$reader->next( 'author' );
					// Authors //
			} elseif ( $reader->nodeType === XMLReader::ELEMENT && 'author' === $reader->name ) {
				$start_parsing_authors = true;
				$author_node           = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
				$ssl_meta              =
				$author_item           = array(
					'usermeta' => array(
						(object) array(
							'key'   => 'use_ssl',
							'value' => 0,
						),
					),
				);
				$attr                  = $author_node->attributes();

				foreach ( $attr as $key => $value ) {
					$author_item[ $key ] = (string) $value;
				}

				if ( $needs_usermeta && isset( $author_node->usermeta ) ) {
					// Usermeta //
					foreach ( $author_node->usermeta as $meta ) {
						$metas_arr = (array) json_decode( (string) $meta );
						foreach ( $metas_arr as $meta_arr ) {
							$author_item['usermeta'][] = $meta_arr;
						}
					}
				}

				$result['authors'][ (string) $author_item['user_login'] ] = $author_item;
				$reader->next();
				$reader->next();
			} else {
				break;
			}
		}

		$reader->close();
		return $result;
	}

	/**
	 * Extract and Import Terms Only first from the File.
	 *
	 * @param string $file The file path.
	 * @return void
	 */
	public function import_terms( $reader, $dom, $step ) {
		$start_parsing_terms = false;

		$reader->open( $this->file );

		while ( $reader->read() && 'term' !== $reader->name ) {
		}

		while ( 'term' === $reader->name && $reader->nodeType === XMLReader::ELEMENT ) {
			$start_parsing_terms = true;
			$term_node           = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
			$term_item           = array();

			if ( isset( $term_node->termeta ) ) {
				foreach ( $term_node->attributes() as $key => $value ) {
					$term_item[ (string) $key ] = (string) $value;
				}
				$term_item['description'] = (string) $term_node->description;
				$term_item['termmeta']    = (array) json_decode( (string) $term_node->termeta );

				// Insert Term.
				$this->importer_helper->process_xml_term( $term_item );
				$reader->next( 'term' );
			} else {
				$term_items = (array) json_decode( (string) $term_node, true );
				foreach ( $term_items as $term_item ) {
					// Insert Term.
					$this->importer_helper->process_xml_term( $term_item );
				}
				$reader->next( 'term' );
			}
		}

		if ( $this->apply_steps() && 0 == $step ) {
			update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-terms', json_encode( $this->importer_helper->processed_terms ), '', false );
			update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-missing-taxonomies-names', $this->importer_helper->processed_missing_taxonomies_names, '', false );
			update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-terms-have-thumbnail', $this->importer_helper->processed_terms_have_thumbnail, '', false );
		}

		$reader->close();
	}

	/**
	 * Import Posts Items
	 *
	 * @param object $reader
	 * @param object $dom
	 * @param int    $step
	 * @return void
	 */
	public function import_items( $reader, $dom, $step ) {
		$offset      = $this->cycle_count * $step;
		$counter     = 0;
		$total_steps = ceil( $this->total_items / $this->cycle_count );

		if ( 0 == $step ) {
			$this->importer_helper->get_posts_names_type_comb();
		} else {
			$this->importer_helper->posts_names_types_comb = (array) json_decode( get_site_option( $this->importer_helper->plugin_info['name'] . '-processed-posts-names-types-combination', '' ), true );
			$this->importer_helper->empty_names_types_comb = (array) json_decode( get_site_option( $this->importer_helper->plugin_info['name'] . '-processed-empty-names-types-combination', '' ), true );
		}

		if ( $step > $total_steps ) {
			return;
		}

		$reader->open( $this->file );

		while ( $reader->read() && 'item' !== $reader->name ) {
		}

		while ( 'item' === $reader->name && $reader->nodeType === XMLReader::ELEMENT ) {
			$counter += 1;

			if ( $this->apply_steps() ) {

				if ( $counter > $offset && $counter > ( $offset + $this->cycle_count ) ) {
					// Step is over, Send Progress Update.
					$reader->close();

					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-errors', json_encode( $this->importer_helper->errors ), '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-posts-names-types-combination', json_encode( $this->importer_helper->posts_names_types_comb ), '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-empty-names-types-combination', json_encode( $this->importer_helper->empty_names_types_comb ), '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-posts', $this->importer_helper->processed_posts, '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-posts-exists', $this->importer_helper->posts_exists, '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-posts-orphans', $this->importer_helper->post_orphans, '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-attachments', $this->importer_helper->processed_attachments, '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-featured-images', $this->importer_helper->featured_images, '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-postmeta-remap', json_encode( $this->importer_helper->postmeta_remap ), '', false );
					update_site_option( $this->importer_helper->plugin_info['name'] . '-attachments-needs-custom-sizes', json_encode( $this->importer_helper->attachments_needs_custom_sizes ), '', false );

					// Update Inserted Posts GUID.
					$this->importer_helper->update_posts_guid();

					wp_send_json_success(
						array(
							'progress' => $step,
						)
					);
				} elseif ( $counter <= $offset ) {
					// Pointer Still in the Past Steps offset, Keep moving.
					$reader->next( 'item' );
					continue;
				}
			}

			$item = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );

			$post_item = array(
				'post_content'          => (string) $item->content,
				'post_title'            => (string) $item->title,
				'post_excerpt'          => (string) $item->excerpt,
				'post_password'         => (string) $item->post_password,
				'to_ping'               => (string) $item->to_ping,
				'pinged'                => (string) $item->pinged,
				'post_content_filtered' => (string) $item->content_filtered,
				'guid'                  => (string) $item->guid,
				'attachment_url'        => ! empty( $item->attachment_url ) ? (string) $item->attachment_url : '',
				'postmeta'              => array(),
				'comments'              => array(),
				'terms'                 => array(),
			);

			foreach ( $item->attributes() as $key => $value ) {
				$post_item[ $key ] = (string) $value;
			}

			if ( isset( $item->attachment_url ) ) {
				$post_item['attachment_url'] = (string) $item->attachment_url;
			}

			if ( isset( $item->urls_remap ) ) {
				$post_item['urls_remap'] = (array) json_decode( (string) $item->urls_remap, true );
			}

			if ( isset( $item->postmeta_urls_remap ) ) {
				$post_item['postmeta_urls_remap'] = (array) json_decode( (string) $item->postmeta_urls_remap, true );
			} else {
				$post_item['postmeta_urls_remap'] = array();
			}

			foreach ( $item->category as $c ) {
				$terms_arr          = (array) json_decode( (string) $c, true );
				$post_item['terms'] = array_merge( $post_item['terms'], $terms_arr );
			}

			// Postmeta //
			foreach ( $item->postmeta as $meta ) {
				$post_item['postmeta'] = array_merge( $post_item['postmeta'], (array) json_decode( (string) $meta, true ) );
			}

			foreach ( $item->comment as $comment ) {

				$attr                                 = $comment->attributes();
				$comment_id                           = (int) $attr['comment_ID'];
				$post_item['comments'][ $comment_id ] = array();

				foreach ( $attr as $key => $value ) {
					$post_item['comments'][ $comment_id ][ (string) $key ] = (string) $value;
				}

				$post_item['comments'][ $comment_id ]['comment_content'] = (string) $comment->comment_content;

				if ( isset( $this->importer_helper->processed_authors[ (int) $post_item['comments'][ $comment_id ]['user_id'] ] ) ) {
					$post_item['comments'][ $comment_id ]['user_id'] = $this->importer_helper->processed_authors[ $post_item['comments'][ $comment_id ]['user_id'] ];
				}

				if ( isset( $comment->commentmeta ) ) {
					$post_item['comments'][ $comment_id ]['comment_meta'] = (array) json_decode( (string) $comment->commentmeta, true );
				}
			}

			// Insert Post.
			$this->importer_helper->process_xml_post( $post_item, $step );

			$test = $reader->next( 'item' );
		}

		$reader->close();
	}


	/**
	 * Import Menu Items.
	 *
	 * @param object $reader
	 * @param object $dom
	 * @param int $step
	 * @return void
	 */
	public function import_menu_items( $reader, $dom, $step ) {
		$reader->open( $this->file );

		while ( $reader->read() && 'item_menu' !== $reader->name );

		while ( 'item_menu' === $reader->name && $reader->nodeType === XMLReader::ELEMENT ) {

			$item      = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
			$post_item = array(
				'post_content'          => (string) $item->content,
				'post_title'            => (string) $item->title,
				'post_excerpt'          => (string) $item->excerpt,
				'post_password'         => (string) $item->post_password,
				'to_ping'               => (string) $item->to_ping,
				'pinged'                => (string) $item->pinged,
				'post_content_filtered' => (string) $item->content_filtered,
				'guid'                  => (string) $item->guid,
				'attachment_url'        => ! empty( $item->attachment_url ) ? (string) $item->attachment_url : '',
				'postmeta'              => array(),
				'comments'              => array(),
				'terms'                 => array(),
			);

			if ( ! empty( $post_item['guid'] ) ) {
				$post_item['guid'] = str_replace( $this->importer_helper->base_url, $this->importer_helper->home_url, $post_item['guid'] );
			}

			foreach ( $item->attributes() as $key => $value ) {
				$post_item[ $key ] = (string) $value;
			}

			foreach ( $item->category as $c ) {
				$terms_arr          = (array) json_decode( (string) $c, true );
				$post_item['terms'] = array_merge( $post_item['terms'], $terms_arr );
			}

			// Postmeta //
			foreach ( $item->postmeta as $meta ) {
				$post_item['postmeta'] = array_merge( $post_item['postmeta'], (array) json_decode( (string) $meta, true ) );
			}

			$this->importer_helper->process_menu_item( $post_item );

			$reader->next( 'item_menu' );
		}
		$reader->close();
	}

	/**
	 * Import WooCommerce Attribute Taxonomies.
	 *
	 * @param object $reader
	 * @param object $dom
	 * @return void
	 */
	public function import_wc_attribute_taxonomies( $reader, $dom ) {

		$reader->open( $this->file );

		while ( $reader->read() && 'wc_attribute_taxonomies' !== $reader->name ) {
		}

		while ( 'wc_attribute_taxonomies' === $reader->name && $reader->nodeType === XMLReader::ELEMENT ) {

			$wc_attribute_node = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
			$wc_attribute_item = array();

			foreach ( $wc_attribute_node->attributes() as $key => $value ) {
				$wc_attribute_item[ $key ] = (string) $value;
			}

			// Insert Attribute Taxonomy.
			$this->importer_helper->process_xml_wc_attribute_taxonomy( $wc_attribute_item );

			$reader->next( 'wc_attribute_taxonomies' );

		}
	}

	/**
	 * Import ACF Remapping Fields.
	 *
	 * @param object $reader
	 * @param object $dom
	 * @return void
	 */
	public function import_acf_remapping( $reader, $dom ) {
		$reader->open( $this->file );

		while ( $reader->read() && 'acf_remapping' !== $reader->name ) {
		}

		while ( 'acf_remapping' === $reader->name && $reader->nodeType === XMLReader::ELEMENT ) {

			$item     = simplexml_import_dom( $dom->importNode( $reader->expand(), true ) );
			$attr     = $item->attributes();
			$_post_id = (int) $attr['post_id'];

			if ( ! isset( $this->importer_helper->posts_exists[ $_post_id ] ) ) {

				$fields   = (array) json_decode( (string) $item );

				foreach ( $fields as $field ) {
					if ( 'serialized_field' === $field->type ) {
						$this->importer_helper->import_acf_serialized_data( $_post_id, $field->key );
					} elseif ( 'serialized_terms_field' === $field->type ) {
						$this->importer_helper->import_acf_terms_serialized_data( $_post_id, $field->key );
					} elseif ( 'single_field' === $field->type ) {
						$this->importer_helper->import_acf_single_data( $_post_id, $field->key );
					} elseif ( 'author_field' === $field->type ) {
						$this->importer_helper->import_acf_users_data( $_post_id, $field->key, $field->status );
					} elseif ( 'link_field' === $field->type ) {
						$this->importer_helper->import_acf_links_data( $_post_id, $field->key );
					}
				}
			}

			$reader->next( 'acf_remapping' );
		}

	}

	/**
	 * Start Parsing and Importing the XML File.
	 *
	 * @param string $file
	 * @return void
	 */
	public function parse( $file, $step ) {
		$this->file = $file;
		if ( 0 == $step ) {
			$initials                                         = $this->parse_export_type_and_authors_first( $file, true, $step );
			$this->importer_helper->authors                   = $initials['authors'];
			$this->importer_helper->shortcodes_posts_to_remap = explode( ',', $initials['shortcodes_remap'] );
		} else {
			$initials = get_site_option( $this->importer_helper->plugin_info['name'] . '-processed-initials', array() );
		}

		$this->importer_helper->base_url = $initials['base_url'];
		$this->export_type               = $initials['export_type'];
		$this->total_items               = $initials['total_items'];
		if ( 'menu' == $this->export_type ) {
			$this->importer_helper->menu_items_new_remapping = $initials['menu_items_depths'];
		}

		if ( $this->apply_steps() && ( 0 == $step ) ) {
			update_site_option( $this->importer_helper->plugin_info['name'] . '-processed-initials', $initials, '', false );
			update_site_option( $this->importer_helper->plugin_info['name'] . '-shortcodes-posts-remap', (string) $initials['shortcodes_remap'] );

			if ( 'menu' == $this->export_type ) {
				update_site_option( $this->importer_helper->plugin_info['name'] . '-menu-items-new-remapping', json_encode( $this->importer_helper->menu_items_new_remapping ) );
			}
		} elseif ( $this->apply_steps() && 'menu' == $this->export_type && 0 < $step ) {
			$this->importer_helper->menu_items_new_remapping = (array) json_decode( get_site_option( $this->importer_helper->plugin_info['name'] . '-menu-items-new-remapping', '' ), true );
		}

		if ( 0 == $step ) {
			// Authors Mapping //
			$this->importer_helper->get_author_mapping();
		}

		$reader = new XMLReader();
		$dom    = new DomDocument( '1.0', 'UTF-8' );

		if ( 0 == $step ) {
			// Import Terms First //
			$this->import_terms( $reader, $dom, $step );
		}

		// Import Items //
		$this->import_items( $reader, $dom, $step );

		// Update Inserted Posts GUID.
		$this->importer_helper->update_posts_guid();

		// Import WC Attribute Taxonomies //
		$this->import_wc_attribute_taxonomies( $reader, $dom );

		// Import ACF Remapping FIelds. //
		$this->import_acf_remapping( $reader, $dom );

		$reader->close();

	}

}
