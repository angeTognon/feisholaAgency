<?php
/**
 * Inverted Index table and row representation.
 *
 * Each property is mapped to table column.
 * CRUD and table create/drop methods.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Index_Model {

	const CLASS_DB_VERSION = '1.0.4';

	/**
	 * Index table name without prefix.
	 *
	 * @since 5.0
	 */
	const IS_INDEX_TABLE = 'is_inverted_index';

	/**
	 * WP option name to store IS DB Version.
	 *
	 * @since 5.0
	 */
	const IS_DB_VER_OPT = 'is_db_version';

	/**
	 * Fuzzy match options.
	 *
	 * @since 5.0
	 * @var int
	 */
	const FUZZY_WHOLE    = 1;
	const FUZZY_PARTIAL  = 2;
	const FUZZY_ANYWHERE = 3;

	/**
	 * The post ID where the term is found.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $post_id;

	/**
	 * The term to index.
	 *
	 * @since 5.0
	 * @var string
	 */
	protected $term;

	/**
	 * The term reverse.
	 *
	 * @since 5.0
	 * @var string
	 */
	protected $term_reverse;

	/**
	 * The term frequency.
	 *
	 * @since 5.0
	 * @var string
	 */
	protected $score = 0;

	/**
	 * Number of times the term was found in the title.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $title = 0;

	/**
	 * Number of times the term was found in the content.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $content = 0;

	/**
	 * Number of times the term was found in the excerpt.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $excerpt = 0;

	/**
	 * Number of times the term was found in the author info or name.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $author = 0;

	/**
	 * Number of times the term was found in the comments.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $comment = 0;

	/**
	 * Number of times the term was found in the post tag.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $tag = 0;

	/**
	 * Number of times the term was found in the post category.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $category = 0;

	/**
	 * Number of times the term was found in other post taxonomies.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $taxonomy = 0;

	/**
	 * Number of times the term was found in the post custom field.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $customfield = 0;

	/**
	 * Taxonomy detail.
	 *
	 * @since 5.0
	 * @var array {
	 *      @type string $key The taxonomy slug. Eg.: product_category, product_tag.
	 *      @type string $value The corresponding taxonomy value.
	 * }
	 */
	protected $taxonomy_detail = array();

	/**
	 * The post (or custom post) custom field detail.
	 *
	 * @since 5.0
	 * @var array {
	 *      @type string $key The custom field name. Eg.: _sku, _price
	 *      @type string $value The corresponding custom field value.
	 * }
	 */
	protected $customfield_detail = array();

	/**
	 * The type of the document.
	 *
	 * Eg. post, page, attachment, product.
	 *
	 * @since 5.0
	 * @var string
	 */
	protected $type;

	/**
	 * The language of the document.
	 *
	 * Eg. pt_BR, en_US.
	 *
	 * @since 5.0
	 * @var string
	 */
	protected $lang;

	/**
	 * Create a new instance.
	 *
	 * @since 5.0
	 * @return static The Mode object.
	 */
	public static function create() {
		return new static();
	}

	/**
	 * Get the table name including WP prefix.
	 *
	 * @since 5.0
	 * @return string The table name.
	 */
	public static function get_is_index_table_name() {
		global $wpdb;
		$is_index_table = $wpdb->prefix . self::IS_INDEX_TABLE;

		return $is_index_table;
	}

	/**
	 * Verify if the index table exists.
	 *
	 * @since 5.0
	 * @return bool True if table exists.
	 */
	public static function verify_is_index_table_exists( $create_if_not_exists = false ) {
		$exists = false;
		global $wpdb;

		$is_index_table = self::get_is_index_table_name();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$is_index_table'" ) === $is_index_table ) {
			$exists = true;
		}

		if ( ! $exists && $create_if_not_exists ) {
			self::create_tables();
		}

		return $exists;
	}

	/**
	 * Create inverted index table.
	 *
	 * @since 5.0
	 * @param string $is_db_version The IS DB version.
	 */
	public static function create_tables( $is_db_version = '' ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( empty( $is_db_version ) ) {
			$is_db_version = self::CLASS_DB_VERSION;
		}

		$is_index_table = self::get_is_index_table_name();

		if ( ! self::verify_is_index_table_exists() ) {
			$charset_collate_bin_column = '';
			$charset_collate            = '';

			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate_bin_column = "CHARACTER SET $wpdb->charset";
				$charset_collate            = "DEFAULT $charset_collate_bin_column";
			}

			if ( strpos( $wpdb->collate, '_' ) > 0 ) {
				$charset_collate_bin_column .= ' COLLATE ' . substr( $wpdb->collate, 0, strpos( $wpdb->collate, '_' ) ) . '_bin';
				$charset_collate            .= " COLLATE $wpdb->collate";

			} elseif ( '' === $wpdb->collate && 'utf8mb4' == $wpdb->charset ) {
				$charset_collate_bin_column .= ' COLLATE utf8mb4_bin';
				// faster but simplified, may result in undesirable sorting or comparisson in some langs
				// $charset_collate_bin_column .= ' COLLATE utf8mb4_general_ci';

			} elseif ( '' === $wpdb->collate && 'utf8' === $wpdb->charset ) {
				$charset_collate_bin_column .= ' COLLATE utf8_bin';
			}

			$sql = "CREATE TABLE $is_index_table " .
				" (post_id bigint(20) NOT NULL DEFAULT '0',
                term varchar(40) $charset_collate_bin_column NOT NULL DEFAULT '0',
                term_reverse varchar(40) $charset_collate_bin_column NOT NULL DEFAULT '0',
                score mediumint(9) NOT NULL DEFAULT '0',
				title mediumint(9) NOT NULL DEFAULT '0',
                content mediumint(9) NOT NULL DEFAULT '0',
                excerpt mediumint(9) NOT NULL DEFAULT '0',
                comment mediumint(9) NOT NULL DEFAULT '0',
                author mediumint(9) NOT NULL DEFAULT '0',
                category mediumint(9) NOT NULL DEFAULT '0',
                tag mediumint(9) NOT NULL DEFAULT '0',
                taxonomy mediumint(9) NOT NULL DEFAULT '0',
                customfield mediumint(9) NOT NULL DEFAULT '0',
                taxonomy_detail longtext NOT NULL,
                customfield_detail longtext NOT NULL,
                type varchar(210) NOT NULL DEFAULT 'post',
                lang varchar(20) NOT NULL DEFAULT 'post',
                PRIMARY KEY (post_id, term),
                KEY term (term(20)),
                KEY termrev (term_reverse(10)),
				KEY score (score),
				KEY lang (lang(5)),
                KEY type (type(20)))
                $charset_collate";

			$ret = dbDelta( $sql );

			self::save_db_version( $is_db_version );
		}
	}

	/**
	 * Manages database tables updates.
	 *
	 * @since 5.0
	 * @param string $is_db_version The DB version to upgrade to.
	 */
	public static function update_db( $is_db_version = null ) {
		global $wpdb;

		if ( empty( $is_db_version ) ) {
			$is_db_version = self::CLASS_DB_VERSION;
		}
		$is_index_table     = self::get_is_index_table_name();
		$current_db_version = self::get_db_version();
		$sql                = null;
		$ret                = null;

		if ( ! self::verify_is_index_table_exists() ) {
			self::create_tables( $is_db_version );
		}

		if ( $current_db_version !== $is_db_version ) {

			switch ( $current_db_version ) {
				default:
					self::delete_tables();
					self::create_tables( $is_db_version );
					break;
			}

			self::save_db_version( $is_db_version );
		}
	}

	/**
	 * Verifies if table index exists.
	 *
	 * @since 5.0
	 * @param string $tbl_index The index name to verify.
	 */
	protected static function index_exists( $tbl_index ) {
		global $wpdb;
		static $tbl_indexes;
		$exists = false;

		if ( empty( $tbl_indexes ) ) {
			$is_index_table = self::get_is_index_table_name();
			$sql            = "SHOW INDEX FROM $is_index_table";
			$tbl_indexes    = $wpdb->get_results( $sql );
		}

		foreach ( $tbl_indexes as $idx ) {
			if ( $tbl_index == $idx->Key_name ) {
				$exists = true;
				break;
			}
		}

		return $exists;
	}

	/**
	 * Uninstall index tables and wp_option saved.
	 *
	 * @since 5.0
	 */
	public static function uninstall() {
		self::delete_tables();

		delete_option( self::IS_DB_VER_OPT );
	}

	/**
	 * Delete inverted index tables.
	 *
	 * @since 5.0
	 * @return bool True if drop executed successfully.
	 */
	public static function delete_tables() {
		global $wpdb;
		$is_index_table = self::get_is_index_table_name();

		if ( self::verify_is_index_table_exists() ) {
			$sql = "DROP TABLE $is_index_table";
			return $wpdb->query( $sql );
		}
	}

	/**
	 * Truncate inverted index table.
	 *
	 * @since 5.0
	 */
	public static function clear_index_table() {
		global $wpdb;
		$is_index_table = self::get_is_index_table_name();

		if ( self::verify_is_index_table_exists() ) {
			$sql = "TRUNCATE TABLE $is_index_table";
			return $wpdb->query( $sql );
		}
	}

	/**
	 * Count the number of distinct posts in the index.
	 *
	 * @since 5.0
	 * @return int The number of posts found.
	 */
	public static function count_indexed_posts() {
		global $wpdb;
		$is_index_table = self::get_is_index_table_name();

		if ( self::verify_is_index_table_exists() ) {
			$sql   = "SELECT COUNT(DISTINCT(post_id)) FROM $is_index_table";
			$count = $wpdb->get_var( $sql );
		}

		if ( empty( $count ) ) {
			$count = 0;
		}

		return $count;
	}

	/**
	 * Count the number of distinct terms in the index.
	 *
	 * @since 5.0
	 * @return int The number of posts found.
	 */
	public static function count_indexed_terms() {
		global $wpdb;
		$is_index_table = self::get_is_index_table_name();

		if ( self::verify_is_index_table_exists() ) {
			$sql   = "SELECT COUNT(DISTINCT(term)) FROM $is_index_table";
			$count = $wpdb->get_var( $sql );
		}

		if ( empty( $count ) ) {
			$count = 0;
		}

		return $count;
	}

	/**
	 * Count the number of index rows.
	 *
	 * @since 5.0
	 * @return int The number of posts found.
	 */
	public static function count_index_size() {
		global $wpdb;
		$is_index_table = self::get_is_index_table_name();

		if ( self::verify_is_index_table_exists() ) {
			$sql   = "SELECT COUNT(*) FROM $is_index_table";
			$count = $wpdb->get_var( $sql );
		}

		if ( empty( $count ) ) {
			$count = 0;
		}

		return $count;
	}

	/**
	 * Verifies if the index is empty, not created.
	 *
	 * @since 5.0
	 * @return bool True if is empty.
	 */
	public static function is_index_empty() {

		return self::count_index_size() == 0;
	}

	/**
	 * Calculates term score.
	 *
	 * Use weights to multiply the found times.
	 *
	 * @since 5.0
	 * @param array $weights {
	 *      @type string $key The column name to weight.
	 *      @type int $value The weight to multiply.
	 * }
	 * @param float The calculated score.
	 */
	public function calc_score( $weights = null ) {
		$score    = 0;
		$defaults = array(
			'title'       => 5,
			'content'     => 1,
			'excerpt'     => 1,
			'author'      => 1,
			'comment'     => .75,
			'tag'         => .75,
			'category'    => .75,
			'taxonomy'    => 1,
			'customfield' => 1,
		);

		$weights = wp_parse_args( $weights, $defaults );
		$weights = apply_filters( 'is_index_calc_score_weights', $weights );

		foreach ( $weights as $prop => $weight ) {
			if ( property_exists( $this, $prop ) ) {
				$score += $this->$prop * $weight;
			}
		}
		$this->score = ceil( $score );

		return $score;
	}

	/**
	 * Save current object in th DB.
	 *
	 * @since 5.0
	 * @return int The number of inserted rows.
	 */
	public function save() {
		$ret = self::save_indexes( array( $this ) );

		return $ret;
	}

	/**
	 * Save multiple index rows in the DB.
	 *
	 * @since 5.0
	 * @param array <IS_Index_Model> The array of objects to save.
	 * @return int The number of inserted rows.
	 */
	public static function save_indexes( $rows ) {
		self::verify_is_index_table_exists( true );
		$rows_qty = 0;
		$post_id  = 0;
		global $wpdb;
		$sql          = 'INSERT INTO ' . self::get_is_index_table_name() .
			' (post_id, term, term_reverse, score, title, content, excerpt, author, comment, tag, category, taxonomy, customfield, taxonomy_detail, customfield_detail, type, lang) ' .
			' VALUES ';
		$placeholders = PHP_EOL . '(%d, %s, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %s, %s, %s, %s ),';

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$post_id = $row->post_id;
				$term    = $row->term;
				$reverse = $row->term_reverse;
				if ( self::get_column_charset( 'term' ) == 'utf8' ) {
					$term    = wp_encode_emoji( $term );
					$reverse = wp_encode_emoji( $reverse );
				}
				$sql .= $wpdb->prepare(
					$placeholders,
					$row->post_id,
					$term,
					$reverse,
					$row->score,
					$row->title,
					$row->content,
					$row->excerpt,
					$row->author,
					$row->comment,
					$row->tag,
					$row->category,
					$row->taxonomy,
					$row->customfield,
					! empty( $row->taxonomy_detail ) ? json_encode( $row->taxonomy_detail ) : '',
					! empty( $row->customfield_detail ) ? json_encode( $row->customfield_detail ) : '',
					$row->type,
					$row->lang
				);
			}
			$sql = rtrim( $sql, ',' ) . ';';

			$rows_qty = $wpdb->query( $sql );
			if ( empty( $rows_qty ) && $wpdb->last_error ) {
				$msg = __( 'Could not save index rows', 'add-search-to-menu' );
				if ( IS_Debug::is_debug_mode() ) {
					$msg .= ' [sql]: ' . PHP_EOL . $sql . PHP_EOL;
					$msg .= PHP_EOL . ' [error]: ' . $wpdb->last_error;
				}

				throw new Exception( $msg );
			}
		}

		return $rows_qty;
	}

	/**
	 * Delete all post inverted index.
	 *
	 * @since 5.0
	 * @param int $post_id The post ID to delete the index.
	 * @return int The number of deleted rows.
	 */
	public static function delete_post_index( $post_id ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			'DELETE FROM ' . self::get_is_index_table_name() .
				' WHERE post_id = %d',
			$post_id
		);
		$ret = $wpdb->query( $sql );

		return $ret;
	}

	/**
	 * Get column charset info.
	 *
	 * @since 5.0
	 * @param int $column The column name to get info for.
	 * @return string The column charset.
	 */
	public static function get_column_charset( $column ) {
		global $wpdb;

		return $wpdb->get_col_charset(
			self::get_is_index_table_name(),
			$column
		);
	}

	/**
	 * Load post inverted index.
	 *
	 * @since 5.0
	 * @param int $post_id The post ID to retrieve the index.
	 * @return array <IS_Index_Model> The array of objects to save.
	 */
	public static function load_post_index( $post_id ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::get_is_index_table_name() .
				' WHERE post_id = %d',
			$post_id
		);
		$rows = $wpdb->get_results( $sql );

		$indexes = array();
		foreach ( $rows as $row ) {
			$index                 = self::load( $row );
			$indexes[ $row->term ] = $index;
		}

		return $indexes;
	}

	/**
	 * Load model from array.
	 *
	 * @since 5.0
	 * @param array $row {
	 *      @type string $field The index field name.
	 *      @type string|int|json $val The index field value.
	 * }
	 * @return self The model loaded from array.
	 */
	public static function load( $row ) {
		$index = new self();
		foreach ( $row as $field => $val ) {
			if ( in_array( $field, array( 'taxonomy_detail', 'customfield_detail' ) ) ) {
				$index->__set( $field, (array) json_decode( $val ) );
			} else {
				$index->__set( $field, $val );
			}
		}
		return $index;
	}

	/**
	 * Search index.
	 *
	 * @since 5.0
	 * @param array $args {
	 *      @type string $term The term to search.
	 *      @type array $type The post types to search.
	 *      @type array $post__in The post IDs to include.
	 *      @type array $post__not_in The post IDs to exclude.
	 *      @type array $fuzzy_match The fuzzy search flag.
	 *      @type int $limit The post quantity limit.
	 * }
	 * @return array <self> The found index models.
	 */
	public static function search( $args ) {
		global $wpdb;

		$indexes = array();

		$defaults = array(
			'term'         => '',
			'type'         => array( 'post' ),
			'post__in'     => array(),
			'post__not_in' => array(),
			'post_status'  => array( 'publish', 'inherit' ),
			'fuzzy_match'  => false,
			'limit'        => 500,
			'subquery'	   => true,
		);

		$args = wp_parse_args( $args, $defaults );

		$is_index_table = self::get_is_index_table_name();

		$types = self::escape_array( $args['type'] );
		$sql   = "SELECT * FROM $is_index_table i WHERE type IN ( $types ) ";

		$term = sanitize_text_field( $args['term'] );
		if ( ! empty( $term ) ) {

			$post__in = $args['post__in'];
			if ( ! empty( $post__in ) && is_array( $post__in ) ) {
				$post__in = self::escape_array( $post__in );
				$sql     .= " AND post_id IN ( $post__in ) ";
			}

			$post__not_in = $args['post__not_in'];
			if ( ! empty( $post__not_in ) && is_array( $post__not_in ) ) {
				$post__not_in = self::escape_array( $post__not_in );
				$sql         .= " AND post_id NOT IN ( $post__not_in ) ";
			}

			if( ! empty( $args['subquery'] ) ) {
				$sql .= " AND post_id IN ( " . self::posts_subquery( $args ) . " ) ";
			}

			switch ( $args['fuzzy_match'] ) {
				default:
				case self::FUZZY_WHOLE:
					$sql .= $wpdb->prepare(
						' AND ( i.term LIKE %s )',
						$term
					);
					break;

				case self::FUZZY_PARTIAL:
					$sql .= $wpdb->prepare(
						' AND ( i.term LIKE %s OR term_reverse LIKE %s )',
						$term . '%',
						self::mb_strrev( $term ) . '%'
					);
					break;

				case self::FUZZY_ANYWHERE:
					$sql .= $wpdb->prepare(
						' AND ( i.term LIKE %s )',
						'%' . $term . '%'
					);
					break;
			}

			$sql .= ' ORDER BY score DESC ';

			$limit = intval( $args['limit'] );
			if ( $limit > 0 ) {
				$sql .= $wpdb->prepare(
					' LIMIT %d ',
					$limit
				);
			}

			$indexes = array();
			$rows    = $wpdb->get_results( $sql );
			foreach ( $rows as $row ) {
				$index = self::load( $row );
				$key = $row->term;
				if( is_numeric( $key ) ) {
					$key = " $key";
				}
				$indexes[ $key ][ $row->post_id ] = $index;
			}
		}

		return $indexes;
	}

	/**
	 * Create a subquery in wp_posts table.
	 * 
	 * This query restrict the join in the main query returning post_ids.
	 *
	 * @since 5.0
	 * @param array $args {
	 *      @type array $type The post types to search.
	 *      @type array $post__in The post IDs to include.
	 *      @type array $post__not_in The post IDs to exclude.
	 *      @type array $post_status The post status to include.
	 *      @type array $author The post author to search.
	 * }
	 * @return string the posts subquery sql.
	 */
	protected static function posts_subquery( $args ) {
		global $wpdb;

		$sql = '';

		$defaults = array(
			'type'         => array( 'post' ),
			'post__in'     => array(),
			'post__not_in' => array(),
			'post_status'  => array( 'publish', 'inherit' ),
			'author'       => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$post_status = $args['post_status'];
		$post_type   = $args['type'];
		$author      = $args['author'];
		$post__in    = $args['post__in'];

		$sql .= " SELECT posts.ID FROM $wpdb->posts posts ";
		$sql .= ' WHERE 1 = 1 ';
		if ( ! empty( $author ) ) {
			$author = self::escape_array( $author );
			$sql   .= " AND posts.post_author IN ( $author ) ";
		}
		if ( ! empty( $post__in ) && is_array( $post__in ) ) {
			$post__in = self::escape_array( $post__in );
			$sql     .= " AND posts.post_id IN ( $post__in ) ";
		}

		if ( ! empty( $post_status ) ) {
			$post_status = self::escape_array( $post_status );
			$sql        .= " AND posts.post_status IN ( $post_status ) ";
		}
		if ( ! empty( $post_type ) ) {
			$post_type = self::escape_array( $post_type );
			$sql      .= " AND posts.post_type IN ( $post_type ) ";
		}

		return $sql;
	}

	/**
	 * Escape array values.
	 *
	 * Sanitizes the values.
	 * Verifies if is a numeric or string values, including quotes when necessary.
	 *
	 * @since 5.0
	 * @param array $arr The values to escape.
	 * @return string The escaped value.
	 */
	public static function escape_array( $arr ) {
		global $wpdb;

		if ( ! is_array( $arr ) ) {
			$arr = array( $arr );
		}

		$escaped = array();
		foreach ( $arr as $k => $v ) {
			$v = sanitize_text_field( $v );

			if ( is_numeric( $v ) ) {
				$escaped[] = $wpdb->prepare( '%d', $v );
			} else {
				$escaped[] = $wpdb->prepare( '%s', $v );
			}
		}
		return implode( ',', $escaped );
	}

	/**
	 * Get IS DB Version.
	 *
	 * @since 5.0
	 * @return string The saved DB Version. eg: 1.2.3
	 */
	public static function get_db_version() {
		return get_option( self::IS_DB_VER_OPT );
	}

	/**
	 * Save IS DB Version.
	 *
	 * @since 5.0
	 * @param string $db_version The db version to save.
	 * @return bool True if the value was updated, false otherwise.
	 */
	public static function save_db_version( $db_version ) {
		$db_version = sanitize_text_field( $db_version );
		return update_option( self::IS_DB_VER_OPT, $db_version );
	}

	/**
	 * Get object properties as array.
	 *
	 * @since 5.0
	 * @return array <string, string> The object properties and values.
	 */
	public function to_array() {
		return get_object_vars( $this );
	}

	/**
	 * Add custom field found times.
	 *
	 * @since 5.0
	 * @param string $key The meta field key.
	 * @param int    $qty The found times quantity.
	 */
	public function add_customfield_qty( $key, $qty ) {
		$this->customfield_detail[ $key ] = $qty;

		$this->customfield += $qty;
	}

	/**
	 * Add taxonomy found times.
	 *
	 * @since 5.0
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $qty The found times quantity.
	 */
	public function add_taxonomy_qty( $taxonomy, $qty ) {
		$this->taxonomy_detail[ $taxonomy ] = $qty;
		switch ( $taxonomy ) {
			case 'category':
				$this->category += $qty;
				break;
			case 'post_tag':
				$this->tag += $qty;
				break;

			default:
				$this->taxonomy += $qty;
				break;
		}
	}

	/**
	 * Reverse a multibyte string.
	 *
	 * @since 5.0
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $qty The found times quantity.
	 */
	public static function mb_strrev( $string, $encoding = null ) {
		if ( $encoding === null ) {
			$encoding = mb_detect_encoding( $string );
		}

		$length   = mb_strlen( $string, $encoding );
		$reversed = '';
		while ( $length-- > 0 ) {
			$reversed .= mb_substr( $string, $length, 1, $encoding );
		}

		return $reversed;
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
				case 'post_id':
				case 'score':
				case 'title':
				case 'content':
				case 'excerpt':
				case 'author':
				case 'comment':
				case 'tag':
				case 'category':
				case 'taxonomy':
				case 'customfield':
					$this->$property = intval( $value );
					break;

				case 'term':
					$this->$property    = sanitize_text_field( $value );
					$this->term_reverse = self::mb_strrev( $this->term );
					break;
				default:
				case 'term_reverse':
				case 'type':
				case 'lang':
					$this->$property = sanitize_text_field( $value );
					break;

				case 'customfield':
				case 'taxonomy_detail':
				case 'customfield_detail':
					if ( is_array( $value ) ) {
						$this->$property = array_map( 'sanitize_text_field', $value );
					} else {
						$this->$property = array( sanitize_text_field( $value ) );
					}
					break;
			}
		}
	}
}
