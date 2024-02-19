<?php

/**
 * Inverted Index Search.
 *
 * @package IS
 * @subpackage IS/public
 * @since 5.0
 */
class IS_Index_Search {

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
	 * The search form.
	 *
	 * @since 5.0
	 * @var IS_Search_Form
	 */
	protected $search_form;

	/**
	 * The Index table rows count.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected static $index_size;

	/**
	 * Singleton class.
	 *
	 * @var self
	 * @since 5.0
	 */
	protected static $_instance;

	/**
	 * Gets the instance of this class.
	 *
	 * @return self
	 */
	public static function getInstance() {

		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Initializes this class.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		$index_opt       = IS_Index_Options::getInstance();
		$this->index_opt = $index_opt;

		$this->helper = new IS_Index_Helper( $index_opt );
		$this->model  = new IS_Index_Model();
	}

	public function init_hooks() {
		add_filter( 'posts_pre_query', array( $this, 'posts_pre_query' ), 99, 2 );
	}

	/**
	 * Filter hook before query is run.
	 *
	 * @since 5.0
	 *
	 * @param null     $posts The posts to deliver.
	 * @param WP_Query $wp_query The intercepted query.
	 * @return null|WP_POST[] The posts to deliver.
	 */
	public function posts_pre_query( $posts, $wp_query ) {
		
		if ( $wp_query->is_search()
			&& ( $wp_query->is_main_query() || check_ajax_referer( 'is_ajax_nonce', 'security', false ) )
			&& ! empty( $s = $wp_query->query_vars['s'] )
		) {
			wp_suspend_cache_addition( true );

			$s = array_keys( $this->helper->tokenize_string( $s ) );
	
			$post_ids = $this->search( $s );

			if ( ! empty( $post_ids ) ) {

				$posts = $this->get_posts( $post_ids, $s );
				$found = is_array( $posts ) ? count( $posts ) : 0;

				$wp_query->found_posts = $found;
				if ( empty( $wp_query->query_vars['posts_per_page'] ) ) {
					$wp_query->query_vars['posts_per_page'] = -1;
				}
				if ( -1 === $wp_query->query_vars['posts_per_page'] ) {
					$wp_query->max_num_pages = 1;
				} else {
					$wp_query->max_num_pages = ceil( $found / $wp_query->query_vars['posts_per_page'] );
				}
				list( $search_low_boundary, $search_high_boundary ) = $this->get_boundaries( $wp_query );

				$posts = array_slice( $posts, $search_low_boundary, $search_high_boundary - $search_low_boundary + 1 );

				$wp_query->posts      = $posts;
				$wp_query->post_count = $found;
			}

			wp_suspend_cache_addition( false );
		}

		return $posts;
	}

	/**
	 * Get posts.
	 *
	 * Use IS_Public's pre_get_posts method to restrict the query.
	 *
	 * @since 5.0
	 *
	 * @param array $posts_ids The post ids to fetch posts.
	 * @param string $s The search string.
	 * @return array The retrieved posts.
	 */
	protected function get_posts( $post_ids, $s ) {
		$posts          = array();
		$max_slice_size = 10000;

		if ( ! empty( $post_ids ) ) {
			$search_form = $this->load_search_form();
			$orderby     = $search_form->group_prop( '_is_settings', 'orderby' );
			$order       = $search_form->group_prop( '_is_settings', 'order' );

			$ignore_sticky_posts = $search_form->group_prop( '_is_excludes', 'ignore_sticky_posts' );
			$move_sticky_posts   = $search_form->group_prop( '_is_settings', 'move_sticky_posts' );

			$sticky = get_option( 'sticky_posts' );
			if ( $ignore_sticky_posts ) {
				$post_ids = array_diff( $post_ids, $sticky );
			}
			elseif( $move_sticky_posts ) {
				$intersect = array_intersect(  $post_ids, $sticky );
				$diff = array_diff( $post_ids, $intersect );
				$post_ids = array_merge( $intersect, $diff );
			}

			do {
				$slice_ids = array_splice( $post_ids, 0, $max_slice_size );
				if ( ! empty( $slice_ids ) ) {

					$args = array(
						'post__in'  => $slice_ids,
						'post_type' => 'any',
						'nopaging'  => true,
					);

					$query = new WP_Query( $args );
					
					if ( is_array( $s ) ) {
						$s = implode( ' ', $s );
					}
					$query->query_vars['s'] = $s;

					// Use existing query restrictions.
					$is_public = IS_Public::getInstance();
					$query     = $is_public->pre_get_posts( $query, true );

					$query->query_vars['post__in'] = $slice_ids;

					$query->set( 'no_found_rows', true );
					$query->set( 'update_post_meta_cache', false );
					$query->set( 'update_post_term_cache', false );
					$query->set( 'ignore_sticky_posts', true );

					if ( $orderby ) {
						$query->query_vars['orderby'] = $orderby;

						if ( 'relevance' == $orderby ) {
							$query->query_vars['orderby'] = 'post__in';
						} elseif ( $order ) {
							$query->query_vars['order'] = $order;
						}
					}

					$posts = $query->get_posts();
					if ( ! empty( $posts ) ) {
						$query->set( 'found_posts', count( $posts ) );
					}
				}
			} while ( $post_ids );
		}

		return $posts;
	}

	/**
	 *
	 * Figures out the low and high boundaries for the search query.
	 *
	 * The low boundary defaults to 0. If the search is paged, the low boundary is
	 * calculated from the page number and posts_per_page value.
	 *
	 * The high boundary defaults to the low boundary + post_per_page, but if no
	 * posts_per_page is set or it's -1, the high boundary is the number of posts
	 * found. Also if the high boundary is higher than the number of posts found,
	 * it's set there.
	 *
	 * If an offset is defined, both boundaries are offset with the value.
	 *
	 * @since 5.0
	 * @param WP_Query $query The WP Query object.
	 * @return array An array with the low boundary first, the high boundary second.
	 */
	protected function get_boundaries( $query ) {
		$hits_count = $query->found_posts;

		if ( isset( $query->query_vars['paged'] ) && $query->query_vars['paged'] > 0 ) {
			$search_low_boundary = ( $query->query_vars['paged'] - 1 ) * $query->query_vars['posts_per_page'];
		} else {
			$search_low_boundary = 0;
		}

		if ( ! isset( $query->query_vars['posts_per_page'] ) || -1 === $query->query_vars['posts_per_page'] ) {
			$search_high_boundary = $hits_count;
		} else {
			$search_high_boundary = $search_low_boundary + $query->query_vars['posts_per_page'] - 1;
		}

		if ( isset( $query->query_vars['offset'] ) && $query->query_vars['offset'] > 0 ) {
			$search_high_boundary += $query->query_vars['offset'];
			$search_low_boundary  += $query->query_vars['offset'];
		}

		if ( $search_high_boundary > $hits_count ) {
			$search_high_boundary = $hits_count;
		}

		return array( $search_low_boundary, $search_high_boundary );
	}

	/**
	 * Request DB for post_ids for search terms.
	 *
	 * The results are ranked.
	 *
	 * @todo include product variation if enabled in search form.
	 *
	 * @since 5.0
	 * @param string[] $s The array of search terms.
	 * @return array The ranked post_ids found for the search.
	 */
	public function search( $s ) {
		// Load current IS Search Form Options.
		$search_form = $this->load_search_form();

		if ( empty( $s ) || empty( $search_form ) || ! $search_form->is_index_search() ) {
			return;
		}

		$post_ids = array();

		// keep original search terms
		$orig_search_terms = $s;
		$search_terms      = $this->add_stemms( $s );
		$search_terms      = $this->add_synonyms( $search_terms );

		if ( ! empty( $search_terms ) ) {

			$post_types    = $this->index_opt->post_types;
			$post_types    = $search_form->group_prop( '_is_includes', 'post_type', $post_types );
			$woo_variation = $search_form->group_prop( '_is_includes', 'woo' );
			if ( $woo_variation ) {
				$post_types[] = 'product_variation';
			}

			$fuzzy_match = $search_form->group_prop( '_is_settings', 'fuzzy_match' );
			$post__in    = $search_form->group_prop( '_is_includes', 'post__in' );

			$post__not_in = $search_form->group_prop( '_is_excludes', 'post__not_in', array() );
			if ( $search_form->group_prop( '_is_excludes', 'ignore_sticky_posts' ) ) {
				$sticky = get_option( 'sticky_posts' );
				if ( is_array( $sticky ) ) {
					$post__not_in = array_merge( $sticky, $post__not_in );
				}
			}

			$limit = $this->index_opt->throttle_searches ? 500 : -1;

			$all_matches = new IS_Index_Matches( $search_form );

			// Find index rows for each term
			foreach ( $search_terms as $term ) {
				$args = array(
					'term'         => $term,
					'type'         => $post_types,
					'post__in'     => $post__in,
					'post__not_in' => $post__not_in,
					'fuzzy_match'  => $fuzzy_match,
					'limit'        => $limit,
				);

				$all_matches->add_matches( $this->model->search( $args ) );
			}

			$rank = $this->rank( $search_terms, $orig_search_terms, $all_matches );
		}

		if ( ! empty( $rank ) ) {
			$post_ids = array_keys( $rank );
		} else {
			$post_ids = array( 0 );
		}

		return apply_filters( 'is_index_search_results', $post_ids, $search_terms, $all_matches );
	}

	/**
	 * Rank matches for searched terms.
	 *
	 * Handles AND | OR search term relationships.
	 *
	 * @since 5.0
	 * @param array            $search_terms The search terms with synonyms and stemms.
	 * @param array            $orig_search_terms The original search terms.
	 * @param array            $match_pts The search terms match points $term => $points.
	 * @param IS_Index_Matches $all_matches The object containing all matches found.
	 * @return array The ranked post_ids found for the search.
	 */
	public function rank( $search_terms, $orig_search_terms, $all_matches ) {

		$rank = array();

		$match_pts   = $all_matches->calc_match_points( $orig_search_terms, $this->get_index_size() );
		$search_rel  = $this->search_form->group_prop( '_is_settings', 'term_rel', 'AND' );
		$fuzzy_match = $this->search_form->group_prop( '_is_settings', 'fuzzy_match', 2 );

		// calc score giving points for every token match
		foreach ( $all_matches->matches as $post_id => $match ) {
			$score = 0;
			foreach ( $search_terms as $term ) {
				if ( $match->has_term( $term, $fuzzy_match ) ) {

					$term_score = $match->get_score( $term );

					if ( $term_score && ! empty( $match_pts[ $term ] ) ) {
						$score += $term_score * $match_pts[ $term ];
					}
				} elseif ( 'AND' == $search_rel && in_array( $term, $orig_search_terms ) ) {
					// not in original search terms, find in synonyms
					$synonyms = $this->get_synonym( $term );
					if ( ! $match->has_synonyms( $synonyms ) ) {
						// Not found even in synonyms|stemms, Skip post_id score for AND relationship.
						$score = 0;
						continue 2;
					}
				}
			}

			// calculate score for partial matches
			if ( is_array( $match->get_terms() ) && is_array( $search_terms ) ) {
				$diff = array_diff( $match->get_terms(), $search_terms );
				if ( is_array( $diff ) && ! empty( $diff ) ) {
					foreach ( $diff as $term ) {
						$term_score = $match->get_score( $term );
						if ( $term_score && ! empty( $match_pts[ $term ] ) ) {
							$score += $term_score * $match_pts[ $term ];
						}
					}
				}
			}

			// Score for post_id
			$score = intval( $score );
			if ( $score ) {
				$rank[ $post_id ] = $score;
			}
		}

		arsort( $rank, SORT_NUMERIC );

		return apply_filters(
			'is_index_rank_results',
			$rank,
			$search_terms,
			$orig_search_terms,
			$all_matches
		);
	}

	/**
	 * Add stems into search terms.
	 *
	 * Only for pro_plus or using $force param.
	 *
	 * @since 5.0
	 * @param array $search_terms The original search terms.
	 * @param bool  $force The flag to override the pro_plus checking.
	 * @return array The addded stemms search terms.
	 */
	public function add_stemms( $search_terms, $force = false ) {
		if ( is_fs()->is_plan_or_trial__premium_only( 'pro_plus' ) || $force ) {
			if ( class_exists( 'IS_Stemmer' ) ) {
				$keyword_stem = $this->search_form->group_prop( '_is_settings', 'keyword_stem' );
				if ( ! empty( $keyword_stem ) ) {
					foreach ( $search_terms as $term ) {
						$stem = IS_Stemmer::Stem( $term );
						if ( $term !== $stem && ! empty( $stem ) ) {
							array_push( $search_terms, $stem );
						}
					}
				}
			}
		}

		return $search_terms;
	}

	/**
	 * Add synonyms into search terms.
	 *
	 * Add the key and/or value for the synonym.
	 *
	 * @since 5.0
	 * @param array $search_terms The original search terms.
	 * @return array The synonyms added search terms.
	 */
	public function add_synonyms( $search_terms ) {
		$synonyms = $this->get_synonyms();

		if ( ! empty( $synonyms ) ) {
			foreach ( $synonyms as $key => $values ) {
				if ( in_array( $key, $search_terms ) ) {
					foreach ( $values as $value ) {
						if ( ! in_array( $value, $search_terms ) ) {
							array_push( $search_terms, $value );
						}
					}
				}
				foreach ( $values as $value ) {
					if ( in_array( $value, $search_terms ) && ! in_array( $key, $search_terms ) ) {
						array_push( $search_terms, $key );
					}
				}
			}
		}

		return $search_terms;
	}

	/**
	 * Get synonyms from IS Options.
	 *
	 * @since 5.0
	 * @return array {
	 *      @type string $key The synonym key.
	 *      @type string[] $values The synonyms array.
	 * }
	 */
	public function get_synonyms() {
		static $synonyms;

		if ( ! isset( $synonyms ) ) {
			$synonyms     = array();
			$synonyms_opt = $this->index_opt->get_is_option( 'synonyms' );

			if ( ! empty( $synonyms_opt ) ) {
				$pairs = preg_split( '/\r\n|\r|\n/', $synonyms_opt );
				foreach ( $pairs as $pair ) {
					if ( ! empty( $pair ) ) {
						$parts = explode( '=', $pair );
						$key   = strval( trim( $parts[0] ) );
						$value = trim( $parts[1] );
						if ( ! empty( $key ) && ! empty( $value ) ) {
							if ( empty( $synonyms[ $key ] ) || ! in_array( $value, $synonyms[ $key ] ) ) {
								$synonyms[ $key ][] = sanitize_text_field( $value );
							}
						}
					}
				}
			}
		}

		return $synonyms;
	}

	/**
	 * Get synonyms from IS Options.
	 *
	 * @since 5.0
	 * @param string $term The term to find synonyms.
	 * @return string[] $values The synonyms array.
	 */
	public function get_synonym( $term ) {
		$synonym  = null;
		$synonyms = $this->get_synonyms();

		if ( ! empty( $synonyms[ $term ] ) ) {
			$synonym = $synonyms[ $term ];
		}

		return $synonym;
	}

	/**
	 * Load IS Search Form.
	 *
	 * Option objects are singletons.
	 *
	 * @since 5.0
	 *
	 * @return IS_Search_Form The retrieved object.
	 */
	public function load_search_form() {

		$this->search_form = IS_Search_Form::load_from_request();

		return $this->search_form;
	}

	/**
	 * Get index table size.
	 *
	 * @since 5.0
	 *
	 * @param bool $force Force reloading from DB.
	 * @return int The index size.
	 */
	public function get_index_size() {
		$index_size = self::$index_size;
		if ( empty( $index_size ) ) {
			$index_size       = $this->model->count_index_size();
			self::$index_size = $index_size;
		}
		return $index_size;
	}

	/**
	 * Set index table size.
	 *
	 * Used for testing.
	 *
	 * @since 5.0
	 *
	 * @param int  $count The index size count.
	 * @param bool $force Force reloading from DB.
	 * @return IS_Option The retrieved object.
	 */
	public function set_index_size( $count ) {
		self::$index_size = intval( $count );
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
				case 's':
					$this->$property = sanitize_text_field( $value );
					break;
			}
		}
	}
}
