<?php

/**
 * Inverted Index Builder.
 *
 * Create index for posts, taxonomies, author, comments, etc.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Index_Builder {

	/**
	 * Stores plugin index options.
	 *
	 * @since 5.0
	 * @var IS_Index_Option
	 */
	protected $index_opt;

	/**
	 * IS Index Helper object.
	 *
	 * @since 5.0
	 * @var IS_Index_Helper
	 */
	protected $helper;

	/**
	 * IS Index Model object.
	 *
	 * @since 5.0
	 * @var IS_Index_Model
	 */
	protected $model;

	/**
	 * IS Index Models array.
	 *
	 * @since 5.0
	 * @var IS_Index_Model[]
	 */
	protected $indexes;

	/**
	 * The current indexing post.
	 *
	 * @since 5.0
	 * @var WP_Post
	 */
	protected $post;

	/**
	 * Initializes this class.
	 *
	 * @since 5.0
	 * @param IS_Index_Options $index_opt The plugin index options.
	 * @param IS_Index_Helper  $helper The index helper object.
	 * @param IS_Index_Model   $model The index model object.
	 */
	public function __construct( $index_opt, $helper, $model ) {
		$this->index_opt = $index_opt;
		$this->helper    = $helper;
		$this->model     = $model;
	}

	/**
	 * Index post.
	 *
	 * Uses index options.
	 *
	 * @since 5.0
	 * @param int|WP_Post $post The post to index.
	 * @param bool        $remove_first If true, remove the post index before indexing.
	 * @return int The number of terms/rows/indexes saved.
	 */
	public function index_post( $post, $remove_first = false ) {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post );
		}

		if ( ! $post instanceof WP_Post ) {
			throw new Exception( __( 'Post not found', 'add-search-to-menu' ) );
		}

		$rows = 0;

		$this->post    = $post;
		$this->indexes = array();

		if ( $remove_first ) {
			$this->delete_post_index( $this->post->ID );
		}

		if ( ! $this->is_indexable_post() ) {
			return 0;
		}

		wp_suspend_cache_addition( true );

		$this->index_post_title();

		$this->index_post_content();

		$this->index_attachment();

		$this->index_post_excerpt();

		$this->index_post_meta();

		$this->index_taxonomies();

		$this->index_comments();

		$this->index_author();

		$this->calc_score();

		$rows = $this->save_indexes();

		wp_suspend_cache_addition( false );

		return $rows;
	}

	/**
	 * Save all indexes.
	 *
	 * @since 5.0
	 * @return int The number of indexes saved.
	 */
	protected function save_indexes() {
		/**
		 * Save indexes filter hook.
		 *
		 * @since 5.0
		 * @param IS_Index_Model[] The indexes to save.
		 * @param self $this The reference to this object.
		 */
		$this->indexes = apply_filters( 'is_save_indexes', $this->indexes, $this );

		return $this->model->save_indexes( $this->indexes );
	}

	/**
	 * Verify if this post is indexable.
	 *
	 * @since 5.0
	 * @return bool True if this post is indexable, false otherwise.
	 */
	protected function is_indexable_post() {
		$no_index_reason = '';
		$indexable       = true;
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || 'auto-draft' === $this->post->post_status ) {
			$indexable       = false;
			$no_index_reason = 'Auto save or auto draft post';
		} else {
			$post_type  = $this->get_post_type();
			$post_types = $this->index_opt->post_types;

			if ( empty( $post_type ) || ! in_array( $post_type, $post_types ) ) {
				$indexable       = false;
				$no_index_reason = __( 'Post is not in selected post types', 'add-search-to-menu' );
			}

			// Check if this is a Jetpack Contact Form entry.
			if ( isset( $_REQUEST['contact-form-id'] ) ) {
				$indexable       = false;
				$no_index_reason = __( 'Post is a Jetpack contact form', 'add-search-to-menu' );
			}

			// Posts marked as no index in Yoast metabox.
			if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
				$yoast_opt = $this->index_opt->yoast_no_index;
				$noindex   = get_post_meta( $this->post->ID, '_yoast_wpseo_meta-robots-noindex', true );
				if ( $indexable && ( !empty( $noindex ) && 1 == $noindex && empty( $yoast_opt) ) ) {
					$indexable       = false;
					$no_index_reason = __( 'Post marked as Yoast no index', 'add-search-to-menu' );
				}
			}
		}

		/**
		 * Filter indexable for a post.
		 *
		 * @since 5.0
		 * @param bool The indexable flag.
		 * @param WP_Post The post to verify.
		 */
		return apply_filters(
			'is_indexable_post',
			$indexable,
			$this->post
		);
	}

	/**
	 * Get the current post type.
	 *
	 * @since 5.0
	 * @return string The post type.
	 */
	protected function get_post_type() {
		$post      = $this->post;
		$post_type = $post->post_type ? $post->post_type : '';

		if ( 'revision' == $post_type && ! empty( $post->post_parent ) ) {
			$parent    = get_post( $post->post_parent );
			$post_type = $parent->post_type ? $parent->post_type : '';
		}

		return $post_type;
	}

	/**
	 * Create index info.
	 *
	 * @since 5.0
	 * @param string $field The indexed field.
	 * @param string $to_index The string to index.
	 * @param bool   $expand_content The flag to expand shortcodes and blocks.
	 */
	protected function create_index( $field, $to_index, $expand_content = false ) {

		if ( ! empty( $to_index ) ) {
			$min_len = $this->index_opt->min_word_length;
			$tokens  = $this->helper->tokenize_string( $to_index, $min_len, $expand_content );

			foreach ( $tokens as $token => $qty ) {
				$index = $this->get_index( $token );

				$index->$field = $qty;
			}
		}
	}

	/**
	 * Index post title.
	 *
	 * @since 5.0
	 */
	protected function index_post_title() {
		$indexable = $this->index_opt->index_title;
		$field     = 'title';

		$to_index = $this->post->post_title;

		if ( ! empty( $to_index ) ) {
			$to_index = apply_filters( 'the_title', $this->post->post_title, $this->post->ID );
		}

		if ( $to_index && $indexable ) {
			$this->create_index( $field, $to_index );
		}
	}

	/**
	 * Index post content.
	 *
	 * @since 5.0
	 */
	protected function index_post_content() {
		$indexable = $this->index_opt->index_content;

		$field    = 'content';
		$to_index = $this->post->post_content;
		/**
		 * Index Post Content filter hook.
		 *
		 * @since 5.0
		 * @param string $to_index The content to index.
		 * @param WP_Post $post The post to index.
		 */
		$to_index = apply_filters( 'is_index_post_content', $to_index, $this->post );

		// backup global var to handle do_shortcode
		global $post;
		$post = $this->post;

		if ( $to_index && $indexable ) {
			$this->create_index( $field, $to_index, true );
		}
		// restore global post
		$post = $this->post;
	}

	/**
	 * Index post excerpt.
	 *
	 * @since 5.0
	 */
	protected function index_post_excerpt() {
		$indexable = $this->index_opt->index_excerpt;
		$indexable = $indexable || 'attachment' == $this->post->post_type;
		$to_index  = $this->post->post_excerpt;
		$field     = 'excerpt';

		// backup global var to handle do_shortcode
		global $post;
		$post = $this->post;

		if ( $to_index && $indexable ) {
			$this->create_index( $field, $to_index, true );
		}

		// restore global post
		$post = $this->post;
	}

	/**
	 * Index post meta fields.
	 *
	 * @since 5.0
	 */
	protected function index_post_meta() {

		$to_index = $this->get_meta_fields();

		if ( ! empty( $to_index ) && is_array( $to_index ) ) {

			foreach ( $to_index as $meta_key => $val ) {
				$val    = maybe_unserialize( $val );
				$tokens = $this->helper->tokenize_string( $val );

				foreach ( $tokens as $token => $qty ) {
					$index = $this->get_index( $token );
					$index->add_customfield_qty( $meta_key, $qty );
				}
			}
		}
	}

	/**
	 * Get post meta fields to index.
	 *
	 * Filter fields using index options.
	 *
	 * @since 5.0
	 * @return array The post meta fields.
	 */
	protected function get_meta_fields() {
		$opt            = $this->index_opt;
		$meta_field_opt = $opt->meta_fields_opt;

		$meta_fields  = get_post_meta( $this->post->ID );
		$allowed_keys = $opt->get_meta_keys( $meta_field_opt );

		$to_index = array();

		foreach ( $meta_fields as $key => $val ) {
			if ( ! empty( $val ) && is_array( $val ) ) {
				$to_index[ $key ] = implode( ' ', $val );
			}

			if ( is_array( $allowed_keys ) && ! in_array( $key, $allowed_keys ) ) {
				unset( $to_index[ $key ] );
			}
		}

		/**
		 * Get post terms to index filter hook.
		 *
		 * @since 5.0
		 * @param array The array of custom meta fields values.
		 * @param WP_Post The post to get the terms for.
		 */
		return apply_filters( 'is_index_get_meta_fields', $to_index, $this->post );
	}

	/**
	 * Index post taxonomies.
	 *
	 * @since 5.0
	 */
	protected function index_taxonomies() {

		$terms = $this->get_terms();
		$index_tax_title = $this->index_opt->index_tax_title;
		$index_tax_desp  = $this->index_opt->index_tax_desp;
		$ignore          = $this->index_opt->get_ignore_taxonomies();

		if ( ! empty( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->taxonomy, $ignore ) ) {

					$to_index = '';
					if ( $index_tax_title ) {
						$to_index = $term->name;
					}
					if ( $index_tax_desp ) {
						$to_index .= ' ' . $term->description;
					}
					$tokens = $this->helper->tokenize_string( $to_index );

					foreach ( $tokens as $token => $qty ) {
						$index = $this->get_index( $token );
						$index->add_taxonomy_qty( $term->taxonomy, $qty );
					}
				}
			}
		}
	}

	/**
	 * Get post taxonomies terms to index.
	 *
	 * Filter taxonomies using index options.
	 *
	 * @since 5.0
	 * @return WP_Term[] The array of terms to index.
	 */
	protected function get_terms() {
		$terms = array();

		$opt = $this->index_opt;

		switch ( $opt->tax_index_opt ) {
			case IS_Index_Options::TAX_OPT_ALL:
				$query = new WP_Term_Query(
					array(
						'object_ids' => $this->post->ID,
					)
				);
				$terms = $query->get_terms();
				break;

			case IS_Index_Options::TAX_OPT_SELECT:
				$tax_selected = $opt->tax_selected;

				$terms = wp_get_post_terms( $this->post->ID, $tax_selected );
				break;
		}

		if ( $terms instanceof WP_Error ) {
			$terms = array();
		}

		/**
		 * Get post terms to index filter hook.
		 *
		 * @since 5.0
		 * @param WP_Term[] The array of terms.
		 * @param WP_Post The post to get the terms for.
		 */
		return apply_filters( 'is_index_get_terms', $terms, $this->post );
	}

	/**
	 * Index post author info.
	 *
	 * @since 5.0
	 */
	protected function index_author() {
		$indexable = $this->index_opt->index_author_info;
		$to_index  = get_the_author_meta( 'display_name', $this->post->post_author );
		$field     = 'author';

		if ( $to_index && $indexable ) {
			$this->create_index( $field, $to_index );
		}
	}

	/**
	 * Index post comments.
	 *
	 * @since 5.0
	 */
	protected function index_comments() {

		$indexable = $this->index_opt->index_comments;
		$to_index  = $this->get_comments();
		$field     = 'comment';

		if ( $to_index && $indexable ) {
			$this->create_index( $field, $to_index );
		}
	}

	/**
	 * Get post comments to index.
	 *
	 * @since 5.0
	 * @param optional array $args The args to query comments.
	 * @return string The concatenated comments fot the post.
	 */
	protected function get_comments( $args = array() ) {
		$defaults = array(
			'paged'  => 1,
			'number' => 20,
			'type'   => array( 'comment' ),
		);
		$args     = wp_parse_args( $args, $defaults );

		$comments = null;
		$to_index = array();
		do {
			$comments = get_approved_comments( $this->post->ID, $args );

			foreach ( $comments as $comment ) {
				if ( $this->index_opt->index_author_info ) {
					$to_index [ $comment->comment_ID ] = $comment->comment_author
						. ' ' . $comment->comment_content;
				} else {
					$to_index [ $comment->comment_ID ] = $comment->comment_content;
				}
			}
			$args['paged']++;
		} while ( ! empty( $comments ) );

		/**
		 * Index comment filter hook.
		 *
		 * @since 5.0
		 * @param array The post comments to index.
		 * @param WP_Post The post to index.
		 */
		$to_index = apply_filters( 'is_index_get_comments', $to_index, $this->post );
		$to_index = implode( ' ', $to_index );
		return $to_index;
	}

	/**
	 * Index post custom attachments.
	 *
	 * @since 5.0
	 */
	protected function index_attachment() {
		if ( 'attachment' == $this->get_post_type() ) {
			$attachment = wp_get_attachment_metadata( $this->post->ID );

			$mime_type = ! empty( $this->post->post_mime_type )
					? $this->post->post_mime_type
					: null;

			/**
			 * Index attachment action hook.
			 *
			 * @since 5.0
			 * @param array $attachment The attachment metadata.
			 * @param null|string $mime_type The attachment mime type if exists.
			 * @param self This object.
			 */
			do_action( 'is_index_attachment', $attachment, $mime_type, $this );

			switch ( $mime_type ) {
				case 'application/pdf':
					// TODO
					break;
				default:
					break;
			}
		}

	}

	/**
	 * Calculates index scores.
	 *
	 * @since 5.0
	 */
	protected function calc_score() {
		foreach ( $this->indexes as $index ) {
			$index->calc_score();
		}
	}

	/**
	 * Get index info for a token/term.
	 *
	 * If not found, create a new one.
	 *
	 * @since 5.0
	 * @param string $token The term to get index info.
	 * @return IS_Index_Model The token index object.
	 */
	protected function get_index( $token ) {
		if ( ! empty( $this->indexes[ $token ] ) ) {
			$index = $this->indexes[ $token ];
		} else {
			if ( function_exists( 'pll_get_post_language' ) ) {
				$lang = pll_get_post_language( $this->post->ID );
			}
			if ( empty( $lang ) ) {
				$lang = get_locale();
			}

			$index = $this->model->create();

			$index->post_id = $this->post->ID;
			$index->term    = $token;
			$index->lang    = $lang;
			$index->type    = $this->get_post_type();

			/**
			 * Get index for a token filter hook.
			 *
			 * @since 5.0
			 * @param IS_Index_Model $index The index for a token.
			 * @param string $token The token.
			 * @param WP_Post $post The respective post.
			 */
			$index                   = apply_filters( 'is_get_index', $index, $token, $this->post );
			$this->indexes[ $token ] = $index;
		}
		return $index;
	}

	/**
	 * Delete post index.
	 *
	 * @since 5.0
	 * @param int $post_id The post ID to delete index.
	 */
	public function delete_post_index( $post_id ) {
		$this->model->delete_post_index( $post_id );
	}

	/**
	 * Get existing properties values.
	 *
	 * @since 5.0
	 * @param string $property The name of a property.
	 * @return mixed Returns mixed value of a property or NULL if a property doesn't exist.
	 */
	public function __get( $property ) {
		if ( property_exists( $this, $property ) ) {
			return $this->$property;
		}
	}

	/**
	 * Magic method to set protected properties.
	 * Sanitize fields before set.
	 *
	 * @since 5.0
	 * @param string $property The name of a property to associate.
	 * @param mixed  $value The value of a property.
	 */
	public function __set( $property, $value ) {
		if ( property_exists( $this, $property ) ) {
			switch ( $property ) {
				default:
					$this->$property = $value;
					break;
			}
		}
	}
}
