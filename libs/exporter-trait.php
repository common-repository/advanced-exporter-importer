<?php defined( 'ABSPATH' ) || exit();

trait GPLS_AEI_Exporter_Trait {

	/**
	 * Wrap given string in XML CDATA tag.
	 *
	 * @since 2.1.0
	 *
	 * @param string $str String to wrap in XML CDATA tag.
	 * @return string
	 */
	function wxr_cdata( $str ) {
		if ( ! seems_utf8( $str ) ) {
			$str = utf8_encode( $str );
		}
		// $str = ent2ncr(esc_html($str));
		$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

		return $str;
	}

	/**
	 * Return the URL of the site
	 *
	 * @since 2.5.0
	 *
	 * @return string Site URL.
	 */
	function wxr_site_url() {
		if ( is_multisite() ) {
			// Multisite: the base URL.
			return network_home_url();
		} else {
			// WordPress (single site): the blog URL.
			return get_bloginfo_rss( 'url' );
		}
	}

	function get_site_url() {
		if ( is_multisite() ) {
			// Multisite: the base URL.
			return network_home_url();
		} else {
			// WordPress (single site): the blog URL.
			return get_home_url();
		}
	}

	/**
	 * Output Authors to DomDocument.
	 *
	 * @param DomDocument $dom
	 * @param array       $post_ids
	 * @return void
	 */
	public function xml_authors_list( $dom, $post_ids ) {
		global $wpdb;

		$paypass_author_metas = array( 'use_ssl', 'session_tokens', 'wp_user-settings-time', 'wp_user-settings' );
		if ( ! empty( $post_ids ) ) {
			$post_ids = array_map( 'absint', $post_ids );
			$and      = 'AND ID IN ( ' . implode( ', ', $post_ids ) . ')';
		} else {
			$and = '';
		}

		$authors     = array();
		$authors_ids = $wpdb->get_col( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE post_status != 'auto-draft' $and" );
		$authors_ids = array_unique( array_merge( $authors_ids, $this->additional_users_ids ) );
		foreach ( $authors_ids as $author_id ) {
			$authors[] = get_userdata( $author_id );
		}

		$authors = array_filter( $authors );

		foreach ( $authors as $author ) {

			$dom->startElement( 'author' );

				$dom->writeAttribute( 'ID', intval( $author->ID ) );
				$dom->writeAttribute( 'user_login', $author->user_login );
				$dom->startAttribute( 'user_pass' );
					$dom->writeRaw( $author->user_pass );
				$dom->endAttribute();
				$dom->writeAttribute( 'user_nicename', $author->user_nicename );
				$dom->writeAttribute( 'user_email', $author->user_email );
				$dom->startAttribute( 'user_url' );
					$dom->writeRaw( $author->user_url );
				$dom->endAttribute();
				$dom->writeAttribute( 'user_registered', $author->user_registered );
				$dom->writeAttribute( 'user_activation_key', $author->user_activation_key );
				$dom->writeAttribute( 'user_status', $author->user_status );
				$dom->writeAttribute( 'display_name', $author->display_name );

			// Author metadata //
			$usermeta   = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $wpdb->usermeta WHERE user_id = %d", $author->ID ) );
			$metas_step = array();
			$counter    = 0;

			foreach ( $usermeta as $meta ) :

				if ( in_array( $meta->meta_key, $paypass_author_metas ) ) {
					continue;
				}

				$metas_step[] = array(
					'key'   => $meta->meta_key,
					'value' => $meta->meta_value,
				);

				if ( $counter >= 10 ) {
					$dom->startElement( 'usermeta' );
						$dom->writeCdata( json_encode( $metas_step ) );
					$dom->endElement();

					$metas_step = array();
					$counter    = 0;
				}

				$counter += 1;

			endforeach;

			if ( ! empty( $metas_step ) ) {
				$dom->startElement( 'usermeta' );
					$dom->writeCdata( json_encode( $metas_step ) );
				$dom->endElement();
			}

			$dom->endElement();
		}

	}

	/**
	 * Get Terms Metas.
	 *
	 * @param array $terms
	 * @return array
	 */
	function query_terms_metas( $terms ) {
		global $wpdb;

		$terms_ids = wp_list_pluck( $terms, 'term_id' );
		$query     =
		"SELECT
			term_id, meta_key, meta_value
		FROM
			$wpdb->termmeta
		WHERE
			term_id IN ('" . implode( "','", $terms_ids ) . "')
		";

		$terms_metas = $wpdb->get_results( $query );
		$sorted      = array();
		foreach ( $terms_metas as $term_meta ) {
			if ( ! isset( $sorted[ $term_meta->term_id ] ) ) {
				$sorted[ $term_meta->term_id ] = array();
			}

			$sorted[ $term_meta->term_id ][] = array(
				'key'   => $term_meta->meta_key,
				'value' => $term_meta->meta_value,
			);
		}

		return $sorted;
	}

	/**
	 * Query Posts Terms.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	function query_posts_terms( $posts_ids ) {
		global $wpdb;

		$query =
		"SELECT
			tr.object_id, tt.taxonomy, t.name, t.slug, t.term_id
		FROM
			{$wpdb->term_taxonomy} tt
		INNER JOIN
			{$wpdb->term_relationships} tr
		ON
			tt.term_taxonomy_id = tr.term_taxonomy_id
		INNER JOIN
			{$wpdb->terms} t
		ON
			t.term_id = tt.term_id
		WHERE
			tr.object_id IN ('" . implode( "','", $posts_ids ) . "')
		";

		$result = $wpdb->get_results( $query );

		$sorted    = array();
		$terms_ids = array();
		foreach ( $result as $row ) {

			if ( ! isset( $sorted[ $row->object_id ] ) ) {
				$sorted[ $row->object_id ] = array();
			}

			$sorted[ $row->object_id ][] = $row->term_id;
			$terms_ids[]                 = $row->term_id;
		}

		return array(
			'sorted'    => $sorted,
			'terms_ids' => array_unique( $terms_ids ),
		);
	}

	/**
	 * Query Posts Postmeta.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	function query_posts_postmeta( $posts_ids ) {
		global $wpdb;

		$query =
		"SELECT
			pm.post_id, pm.meta_key, pm.meta_value
		FROM
			{$wpdb->postmeta} pm
		INNER JOIN
			{$wpdb->posts} p
		ON
			p.ID = pm.post_id
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		";

		$result = $wpdb->get_results( $query );

		$sorted = array();
		foreach ( $result as $row ) {
			if ( isset( $sorted[ $row->post_id ] ) ) {
				$sorted[ $row->post_id ][] = $row;
			} else {
				$sorted[ $row->post_id ]   = array();
				$sorted[ $row->post_id ][] = $row;
			}
		}

		return $sorted;
	}

	/**
	 * Query Posts Comments.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function query_posts_comments( $posts_ids ) {
		global $wpdb;

		$query =
		"SELECT
			c.*
		FROM
			{$wpdb->comments} c
		INNER JOIN
			{$wpdb->posts} p
		ON
			p.ID = c.comment_post_ID
		WHERE
			p.ID IN ('" . implode( "','", $posts_ids ) . "')
		";

		$result = $wpdb->get_results( $query );

		$sorted = array();
		foreach ( $result as $row ) {
			if ( isset( $sorted[ $row->comment_post_ID ] ) ) {
				$sorted[ $row->comment_post_ID ][] = $row;
			} else {
				$sorted[ $row->comment_post_ID ]   = array();
				$sorted[ $row->comment_post_ID ][] = $row;
			}
		}

		return $sorted;
	}

	/**
	 * Add List of taxonomy Terms of A post to Dom Document.
	 *
	 * @param DomDocument $dom
	 * @param object      $post
	 * @param array       $general_terms
	 * @return void
	 */
	function xml_post_terms( $dom, $terms, $post_id ) {
		if ( isset( $terms[ $post_id ] ) ) {
			$terms_arr = $terms[ $post_id ];

			$dom->startElement( 'category' );
				$dom->writeCdata( json_encode( $terms_arr ) );
			$dom->endElement();
		}
	}

	/**
	 * Output Postmeta to DomDocument
	 *
	 * @param DomDocument $dom
	 * @param int         $post_id
	 *
	 * @return void
	 */
	function xml_post_postmeta( $dom, $postmeta, $post_id, $export_type, $post_type ) {

		if ( isset( $postmeta[ $post_id ] ) ) {
			$postmeta   = $postmeta[ $post_id ];
			$metas_step = array();
			$counter    = 0;
			if ( 'menu' === $export_type && 'nav_menu_item' === $post_type ) {
				$postmeta = apply_filters( $this->plugin_info['name'] . '-export-post-postmeta', $postmeta, $post_id );
			}
			foreach ( $postmeta as $meta ) :
				if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) ) {
					continue;
				}

				$metas_step[] = array(
					'key'   => $meta->meta_key,
					'value' => $meta->meta_value,
				);

				if ( $counter >= 10 ) {

					$dom->startElement( 'postmeta' );
						$dom->writeCdata( json_encode( $metas_step ) );
					$dom->endElement();

					$metas_step = array();
					$counter    = 0;
				}

				$counter += 1;
			endforeach;

			if ( ! empty( $metas_step ) ) {
				$dom->startElement( 'postmeta' );
					$dom->writeCdata( json_encode( $metas_step ) );
				$dom->endElement();
			}
		}
	}


	/**
	 * Output Postmeta to DomDocument
	 *
	 * @param DomDocument $dom
	 * @param int         $post_id
	 *
	 * @return void
	 */
	function xml_post_comments( $dom, $comments, $post_id ) {
		global $wpdb;

		if ( ! empty( $comments[ $post_id ] ) ) {
			$comments           = $comments[ $post_id ];
			$comments_ids       = wp_list_pluck( $comments, 'comment_ID' );
			$comments_metas_arr = array();
			$comments_metas     = $wpdb->get_results( "SELECT * FROM $wpdb->commentmeta WHERE comment_id IN ('" . implode( "','", $comments_ids ) . "')" );

			foreach ( $comments_metas as $comment_meta ) {
				if ( ! isset( $comments_metas_arr[ $comment_meta->comment_id ] ) ) {
					$comments_metas_arr[ $comment_meta->comment_id ] = array();
				}

				$comments_metas_arr[ $comment_meta->comment_id ][] = array(
					'key'   => $comment_meta->meta_key,
					'value' => $comment_meta->meta_value,
				);
			}

			foreach ( $comments as $c ) :

				$dom->startElement( 'comment' );
					$dom->writeAttribute( 'comment_ID', intval( $c->comment_ID ) );
					$dom->writeAttribute( 'comment_author', $c->comment_author );
					$dom->writeAttribute( 'comment_author_email', $c->comment_author_email );
					$dom->writeAttribute( 'comment_author_url', esc_url_raw( $c->comment_author_url ) );
					$dom->writeAttribute( 'comment_author_IP', $c->comment_author_IP );
					$dom->writeAttribute( 'comment_date', $c->comment_date );
					$dom->writeAttribute( 'comment_date_gmt', $c->comment_date_gmt );
					$dom->writeAttribute( 'comment_approved', intval( $c->comment_approved ) );
					$dom->writeAttribute( 'comment_type', $c->comment_type );
					$dom->writeAttribute( 'comment_parent', intval( $c->comment_parent ) );
					$dom->writeAttribute( 'user_id', intval( $c->user_id ) );
					$dom->startAttribute( 'comment_agent' );
						$dom->writeRaw( $c->comment_agent );
					$dom->endAttribute();
					$dom->writeAttribute( 'comment_karma', intval( $c->comment_karma ) );
					$dom->startElement( 'comment_content' );
						$dom->writeRaw( $this->wxr_cdata( $c->comment_content ) );
					$dom->endElement();

					if ( ! empty( $comments_metas_arr[ $c->comment_ID ] ) ) {
						$dom->startElement( 'commentmeta' );
							$dom->writeCdata( json_encode( $comments_metas_arr[ $c->comment_ID ] ) );
						$dom->endElement();
					}

				$dom->endElement();

			endforeach;
		}
	}

	/**
	 * Output Terms to DomDocument Dom
	 *
	 * @param DomDocument $dom
	 *
	 * @return void
	 */
	public function xml_terms( $dom, $general_terms ) {
		global $wpdb;
		$custom_terms = array();

		if ( ! empty( $general_terms ) ) {
			// Add General Terms at last.
			$custom_terms = $wpdb->get_results(
				"SELECT
					t.term_id, t.name, t.slug, t.term_group, tt.taxonomy, tt.description, tt.parent
				FROM
					$wpdb->terms t
				INNER JOIN
					$wpdb->term_taxonomy tt
				ON
					t.term_id = tt.term_id
				WHERE
					t.term_id IN ('" . implode( "','", $general_terms ) . "')"
			);
		}

		$general_terms    = array();
		$custom_terms_ids = wp_list_pluck( $custom_terms, 'term_id' );

		// Put terms in order with no child going before its parent.
		while ( $t = array_shift( $custom_terms ) ) {
			$general_terms_ids = array_keys( $general_terms );
			if ( 0 == $t->parent || in_array( $t->parent, $general_terms_ids ) ) {
				$general_terms[ $t->term_id ] = $t;
			} else {
				if ( ! in_array( $t->parent, $custom_terms_ids ) ) {
					$missing_parents_tree                = array();
					$missing_parents_tree[ $t->term_id ] = $t;
					$missing_parent_term                 = get_term( $t->parent );
					if ( ! is_null( $missing_parent_term ) && ! is_wp_error( $missing_parent_term ) ) {
						while ( ! is_null( $missing_parent_term ) && ! is_wp_error( $missing_parent_term ) ) {
							if ( in_array( $missing_parent_term->term_id, array_keys( $general_terms ) ) ) {
								break;
							} elseif ( ! in_array( $missing_parent_term->term_id, $custom_terms_ids ) ) {
								$missing_parents_tree[ $missing_parent_term->term_id ] = $missing_parent_term;
								$missing_parent_term                                   = get_term( $missing_parent_term->parent );
							} else {
								$custom_terms[] = $t;
								break;
							}
						}
					}
					$general_terms = $general_terms + array_reverse( $missing_parents_tree, true );
				} else {
					$custom_terms[] = $t;
				}
			}
		}

		$unique_general_terms_ids = array_unique( array_keys( $general_terms ) );
		$general_terms            = array_filter(
			$general_terms,
			function( $key ) use ( $unique_general_terms_ids ) {
				return in_array( $key, $unique_general_terms_ids );
			},
			ARRAY_FILTER_USE_KEY
		);

		$counter = 0;
		while ( $export_terms = array_slice( $general_terms, $counter, 30, true ) ) :

			$terms_metas      = $this->query_terms_metas( $export_terms );
			$free_terms       = array();
			$terms_with_metas = array();

			foreach ( $export_terms as $export_term ) {
				if ( ! empty( $terms_metas[ $export_term->term_id ] ) ) {
					$terms_with_metas[] = $export_term;
				} else {
					$free_terms[] = $export_term;
				}
			}

			// Free Terms.
			$dom->startElement( 'term' );
				$dom->writeCdata( json_encode( $free_terms ) );
			$dom->endElement();

			// Terms WIth Metas.
			foreach ( $terms_with_metas as $t ) :
				$dom->startElement( 'term' );

					$dom->writeAttribute( 'term_id', intval( $t->term_id ) );
					$dom->writeAttribute( 'taxonomy', $t->taxonomy );
					$dom->writeAttribute( 'slug', $t->slug );
					$dom->writeAttribute( 'term_group', intval( $t->term_group ) );

					$dom->writeAttribute( 'parent', intval( $t->parent ) );

					$dom->writeAttribute( 'name', $t->name );
					$dom->startElement( 'description' );
						$dom->writeCdata( $t->description );
					$dom->endElement();

					$dom->startElement( 'termeta' );
						$dom->writeCdata( json_encode( $terms_metas[ $t->term_id ] ) );
					$dom->endElement();

				$dom->endElement();
			endforeach;
			$counter += 30;
		endwhile;
	}

	/**
	 * Get WooCommerce Attributes Taxonomies.
	 *
	 * @return void
	 */
	public function woo_attribute_taxonomies() {
		global $wpdb;
		$attribute_taxonomies = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name != '' ORDER BY attribute_name ASC;" );
		return $attribute_taxonomies;
	}

	/**
	 * Output The Attribute Taxonomies for WooCommerce.
	 *
	 * @param DomDocument $dom
	 * @return void
	 */
	public function xml_attribute_taxonomies( $dom ) {
		$attribute_taxonomies = $this->woo_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) :

			foreach ( $attribute_taxonomies as $attribute_taxonomy ) :

				$dom->startElement( 'wc_attribute_taxonomies' );

					$dom->startAttribute( 'attribute_name' );
						$dom->writeRaw( $attribute_taxonomy->attribute_name );
					$dom->endAttribute();

					$dom->startAttribute( 'attribute_label' );
						$dom->writeRaw( $attribute_taxonomy->attribute_label );
					$dom->endAttribute();

					$dom->startAttribute( 'attribute_type' );
						$dom->writeRaw( $attribute_taxonomy->attribute_type );
					$dom->endAttribute();

					$dom->startAttribute( 'attribute_orderby' );
						$dom->writeRaw( $attribute_taxonomy->attribute_orderby );
					$dom->endAttribute();

					$dom->startAttribute( 'attribute_public' );
						$dom->writeRaw( $attribute_taxonomy->attribute_public );
					$dom->endAttribute();

				$dom->fullEndElement();

			endforeach;

		endif;
	}

	/**
	 * Output specific navigation menu terms in DomDocument.
	 */
	public function xml_nav_specific_menu_terms( $dom, $nav_menus ) {
		if ( empty( $nav_menus ) || ! is_array( $nav_menus ) ) {
			return;
		}

		foreach ( $nav_menus as $t ) {

			$dom->startElement( 'term' );

				$dom->writeAttribute( 'term_id', $t->term_id );
				$dom->writeAttribute( 'term_taxonomy', $t->taxonomy );
				$dom->writeAttribute( 'term_slug', $t->slug );
				$dom->writeAttribute( 'term_parent', intval( $t->parent ) );
				$dom->writeAttribute( 'term_name', $t->name );
				$dom->startElement( 'term_description' );
					$dom->writeCdata( $t->description );
				$dom->endElement();

			$dom->endElement();

		}
	}

	/**
	 * Posts ACF Fields.
	 *
	 * @param object $dom
	 * @return void
	 */
	public function xml_acf_remapping( $dom ) {
		if ( ! empty( $this->acf_remapping ) && is_array( $this->acf_remapping ) ) {
			foreach ( $this->acf_remapping as $post_id => $post_acf_fields ) {
				$dom->startElement( 'acf_remapping' );
					$dom->writeAttribute( 'post_id', intval( $post_id ) );
					$dom->writeRaw( json_encode( $post_acf_fields ) );
				$dom->endElement();
			}
		}
	}

	/**
	 * Is Export Needs Steps.
	 *
	 * @param array $post_ids
	 * @return void
	 */
	public function apply_steps( $post_ids ) {
		return ( count( $post_ids ) > ( $this->cycle_count * $this->inner_export_steps ) );
	}

	/**
	 * Unserialize or return array.
	 *
	 * @param array|int|string $original
	 * @return array
	 */
	public function maybe_unserialize_arr( $original ) {
		if ( is_serialized( $original ) ) {
			return @unserialize( $original );
		}
		return (array) $original;
	}

	/**
	 * @param bool   $return_me
	 * @param string $meta_key
	 * @return bool
	 */
	function wxr_filter_postmeta( $return_me, $meta_key ) {
		if ( '_edit_lock' == $meta_key ) {
			$return_me = true;
		}
		return $return_me;
	}

	/**
	 * Loader HTML Code.
	 *
	 * @return void
	 */
	function loader_html() {
		?>
		<div class="loader w-100 h-100 position-absolute">
			<div class="text-white wrapper text-center position-absolute d-block w-100 " style="top: 125px;z-index:1000;">
				<img src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"  />
			</div>
			<div class="overlay position-absolute d-block w-100 h-100" style="opacity:0.7;background:#FFF;z-index:100;"></div>
		</div>
		<?php
	}


	/**
	 * Filter the Posts if cached or not by IDs.
	 *
	 * @param array $posts_ids
	 * @return array
	 */
	public function filter_cached_posts( $posts_ids ) {
		$cached_posts = array();
		$non_cached   = array();
		foreach ( $posts_ids as $post_id ) {
			$cached_post = wp_cache_get( $post_id, 'posts' );
			if ( ! $cached_post ) {
				$non_cached[] = $post_id;
			} else {
				$cached_posts[] = $cached_post;
			}
		}

		return array(
			'cached_posts' => $cached_posts,
			'non_cached'   => $non_cached,
		);
	}

	/**
	 * Filter The Terms objects to get IDs only.
	 *
	 * @param array $list
	 * @return array
	 */
	public function filter_terms_items( $list ) {
		$new_list = array();

		foreach ( $list as $key => $value ) {
			$new_list = array_merge( $new_list, wp_list_pluck( $value, 'term_id', 'no' ) );
		}

		return $new_list;
	}

	/**
	 * Check if Post Content has any of the target Shortcodes.
	 *
	 * @param string $content
	 * @return array
	 */
	function has_target_shortcodes( $content ) {
		preg_match_all(
			'/' . get_shortcode_regex() . '/',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		$shortcodes = array();

		foreach ( $matches as $shortcode ) {
			$shortcodes[] = $shortcode[0];
		}

		return $shortcodes;
	}

	/**
	 * Get IDs from Shortcodes.
	 *
	 * @param array   $shortcodes
	 * @param integer $post_id
	 * @return array
	 */
	public function extract_ids_from_shortcodes( $shortcodes, $post_id ) {
		$posts_ids       = array();
		$attachments_ids = array();
		foreach ( $shortcodes as $shortcode ) {
			$atts = shortcode_parse_atts( trim( $shortcode, '[]' ) );
			if ( ! empty( $atts['id'] ) && is_numeric( $atts['id'] ) ) {
				$posts_ids[] = intval( $atts['id'] );
			} elseif ( ! empty( $atts['ids'] ) ) {
				$attachments_ids = array_merge( $attachments_ids, array_map( 'intval', explode( ',', $atts['ids'] ) ) );
			}
		}

		if ( ! empty( $attachments_ids ) ) {
			$this->attachments_only_ids = array_merge( $this->attachments_only_ids, $attachments_ids );
		}

		if ( ! empty( $posts_ids ) || ! empty( $attachments_ids ) ) {
			$this->shortcodes_need_remapping[] = $post_id;
		}

		return $posts_ids;
	}

	/**
	 * Validate URL.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function validate_url($url) {
		$path = parse_url($url, PHP_URL_PATH);
		$encoded_path = array_map('urlencode', explode('/', $path));
		$url = str_replace($path, implode('/', $encoded_path), $url);

		return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
	}

	/**
	 * Check if Link Belongs to this site.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function is_link_internal( $url ) {
		$url_host      = str_replace( 'www.', '', parse_url( $url, PHP_URL_HOST ) );
		$home_url_host = str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );

		if ( $url_host && $url_host !== $home_url_host ) {
			return false;
		}

		return true;
	}

	/**
	 * Filter Attachments URLs in post content.
	 *
	 * @param string $attachment_url
	 * @param string $attachment_type
	 * @return string|false
	 */
	public function filter_attachment_url( $attachment_url, $attachment_type ) {
		$attachment_url_details = wp_parse_url( $attachment_url );
		$attachment_info        = pathinfo( $attachment_url );
		$attachment_dirname     = substr( $attachment_url_details['path'], strpos( $attachment_url_details['path'], '/wp-content/uploads/' ) + 20 );

		if ( 0 === strpos( $attachment_type, 'image/' ) ) {
			if ( preg_match( ( ! empty( $attachment_info['filename'] ) ? '/-([0-9]+x[0-9]+)$/' : '/-([0-9]+x[0-9]+).\w+$/' ), ( ! empty( $attachment_info['filename'] ) ? $attachment_info['filename'] : $attachment_url_details['path'] ), $matches ) ) {
				if ( $matches && ! empty( $matches[1] ) ) {
					$attachment_dirname = str_replace( $matches[1], '', $attachment_dirname );
				}
			}
		}

		return $attachment_dirname;
	}

	/**
	 * Update ACF and Mapping Fields.
	 *
	 * @return void
	 */
	public function update_remapping_fields() {

		if ( ! empty( $this->related_acf_terms ) ) {
			$old_data = get_site_option( $this->plugin_info['name'] . '-exported-acf-terms', array() );
			update_site_option( $this->plugin_info['name'] . '-exported-acf-terms', ( $old_data + $this->related_acf_terms ) );
		}

		if ( ! empty( $this->acf_remapping ) ) {
			$old_data = (array) json_decode( get_site_option( $this->plugin_info['name'] . '-exported-acf-remapping', '' ), true );
			$new_data = array();

			if ( ! empty( $old_data ) ) {
				$new_data = $old_data + $this->acf_remapping;
			} else {
				$new_data = $this->acf_remapping;
			}

			update_site_option( $this->plugin_info['name'] . '-exported-acf-remapping', json_encode( $new_data ) );
		}

	}

}
