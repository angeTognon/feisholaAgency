<?php

/**
 * Inverted Index Manager.
 *
 * Create index for posts, taxonomies, author, comments, etc.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Index_Manager extends IS_Base_Options {

	/**
	 * ID used to save options.
	 *
	 * @var static int
	 * @since 5.0
	 */
	public static $ID = 'is_index_manager';

	/**
	 * Singleton class.
	 *
	 * @var static self
	 * @since 5.0
	 */
	protected static $_instance;

	/**
	 * Object lock timestamp.
	 *
	 * @since 1.0.0
	 */
	protected $lock;

	/**
	 * Constants for action names.
	 *
	 * @var static string
	 * @since 5.0
	 */
	const CREATE_INDEX_ACTION = 'create_index';
	const DELETE_INDEX_ACTION = 'delete_index';
	const INDEX_POST_ACTION   = 'index_post';
	const RESET_ACTION        = 'index-reset';

	/**
	 * Constants for Index Status.
	 *
	 * @var static string
	 * @since 5.0
	 */
	const IDX_EMPTY    = 'empty';
	const IDX_CREATING = 'creating';
	const IDX_PAUSED   = 'paused';
	const IDX_CREATED  = 'created';
	const IDX_PAUSING  = 'pausing';

	/**
	 * Stores plugin index options.
	 *
	 * @since 5.0
	 * @var IS_Index_Option
	 */
	protected $index_opt;

	/**
	 * Index builder object.
	 *
	 * @since 5.0
	 * @var IS_Index_Builder
	 */
	protected $builder;

	/**
	 * IS Index Model object.
	 *
	 * @since 5.0
	 * @var IS_Index_Model
	 */
	protected $model;

	/**
	 * Index status info.
	 *
	 * @var string
	 * @since 5.0
	 */
	protected $index_status;

	/**
	 * Last Index build results.
	 *
	 * @var array
	 * @since 5.0
	 */
	protected $build_results;

	/**
	 * Last build start timestamp
	 *
	 * @var int
	 * @since 5.0
	 */
	protected $build_start_time;

	/**
	 * Last build end timestamp
	 *
	 * @var int
	 * @since 5.0
	 */
	protected $build_end_time;

	/**
	 * Index build quantity per request.
	 *
	 * @var int
	 * @since 5.0
	 */
	protected $build_per_page;

	/**
	 * Index build offset to restart from.
	 *
	 * @var int
	 * @since 5.0
	 */
	protected $build_offset;

	/**
	 * Index build errors.
	 *
	 * @var array {
	 *      Error array in the format: $post_id => $error.
	 *      @type int $post_id The post id which error occured.
	 *      @type string $error The post indexing error message.
	 * }
	 * @since 5.0
	 */
	protected $index_errors;

	/**
	 * Initializes this class.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		parent::__construct();

		$index_opt       = IS_Index_Options::getInstance();
		$this->index_opt = $index_opt;

		$helper        = new IS_Index_Helper( $index_opt );
		$model         = new IS_Index_Model();
		$this->model   = $model;
		$this->builder = new IS_Index_Builder( $index_opt, $helper, $model );

		// Check for table updates.
		if ( $this->is_index_admin_page() ) {
			$this->model->update_db();
		}
	}

	/**
	 * Verifies if current page is the admin index settings page.
	 *
	 * @since 5.0
	 * @return bool True if it is the index settings page.
	 */
	public function is_index_admin_page() {
		$is_index_admin_page = false;

		$args = $_GET;
		if ( ! empty( $args['page'] ) && 'ivory-search-settings' == $args['page']
			&& ! empty( $args['tab'] ) && 'index' == $args['tab']
		) {
			$is_index_admin_page = true;
		}

		return $is_index_admin_page;
	}

	/**
	 * Initializes index build related hoooks.
	 *
	 * @since 5.0
	 */
	public function init_build_hooks() {

		if ( is_admin() ) {
			add_action( 'wp_ajax_' . self::CREATE_INDEX_ACTION, array( $this, 'ajax_create_index' ), 1 );
			add_action( 'wp_ajax_' . self::DELETE_INDEX_ACTION, array( $this, 'ajax_delete_index' ), 1 );
			add_action( 'wp_ajax_' . self::INDEX_POST_ACTION, array( $this, 'ajax_index_post' ), 1 );

			// add_action( 'template_redirect', array( $this,'process_actions', 1 ) );
			$this->process_actions();
		}
	}

	/**
	 * Process actions in admin.
	 *
	 * @since 5.0
	 */
	public function process_actions() {
		$action = isset( $_POST['action'] ) && -1 != $_POST['action']
			? $_POST['action']
			: false;

		if ( self::RESET_ACTION == $action ) {

			if ( ! check_admin_referer( self::RESET_ACTION )
				|| ! current_user_can( 'is_edit_search_form' )
			) {
				wp_die( esc_html__( 'You are not allowed to reset these configurations.', 'add-search-to-menu' ) );
			}

			$this->index_opt->set_defaults( true );
			$this->index_opt->delete_object_lock( false );
			$this->index_opt->save();

			$redirect_to = add_query_arg( array( 'message' => 'index-reset' ) );
			wp_safe_redirect( esc_url_raw( $redirect_to ) );
			exit();
		} else {
			if ( ! empty( $_REQUEST['message'] )
				&& 'index-reset' == $_REQUEST['message']
				&& ! empty( $_REQUEST['settings-updated'] )
			) {
				unset( $_GET['message'] );
			}
		}
	}

	/**
	 * Initializes index create and delete hooks.
	 * Fired when content is created, deleted or updated.
	 *
	 * @since 5.0
	 */
	public function init_index_hooks() {
		if ( $this->index_opt->auto_index_enabled ) {
			// Post indexing.
			add_action( 'save_post', array( $this, 'index_post' ), 99 );
			add_action( 'delete_post', array( $this, 'delete_post_index' ) );

			// Attachment indexing.
			add_action( 'add_attachment', array( $this, 'index_post' ), 99 );
			add_action( 'edit_attachment', array( $this, 'index_post' ), 99 );
			add_action( 'delete_attachment', array( $this, 'delete_post_index' ) );

			// Comment indexing.
			add_action( 'wp_insert_comment', array( $this, 'index_comment' ) );
			add_action( 'edit_comment', array( $this, 'index_comment' ) );
			add_action( 'trashed_comment', array( $this, 'delete_comment_index' ) );
			add_action( 'deleted_comment', array( $this, 'delete_comment_index' ) );
		}
	}

	/**
	 * Get index status.
	 *
	 * How many posts, terms and errors.
	 *
	 * @since 5.0
	 * @return string The index status text.
	 */
	public function get_index_status( $only_status = false, $results_lbl = '' ) {
		$results = array(
			__( 'Content Status', 'add-search-to-menu' ),
			sprintf(
				'%d %s',
				$this->count_posts_to_index(),
				__( 'contents can be indexed.', 'add-search-to-menu' )
			),
			null,
			__( 'Index Status', 'add-search-to-menu' ),
			sprintf(
				'%d %s',
				$this->model->count_indexed_posts(),
				__( 'unique content in the index', 'add-search-to-menu' )
			),
			sprintf(
				'%d %s',
				$this->model->count_indexed_terms(),
				__( 'unique terms in the index', 'add-search-to-menu' )
			),
			sprintf(
				'%d %s',
				$this->model->count_index_size(),
				__( 'index records', 'add-search-to-menu' )
			),
			null,
		);

		if ( ! $only_status ) {
			$build_results = $this->build_results;
			if ( ! empty( $build_results ) && is_array( $build_results ) ) {
				if ( $results_lbl ) {
					$results[] = $results_lbl;
				} else {
					$results[] = __( 'Last build results:', 'add-search-to-menu' );
				}

				$results = array_merge( $results, $build_results );
			}

			$errors = $this->index_errors;
			if ( ! empty( $errors ) && is_array( $errors ) ) {
				$results[] = sprintf(
					'%d %s:',
					count( $errors ),
					__( 'index error(s)', 'add-search-to-menu' )
				);
				$results   = array_merge( $results, $errors );
			}
		}

		$status = implode( PHP_EOL, $results );

		$this->index_status = $status;
		return $this->index_status;
	}

	/**
	 * Get create index AJAX data to use in client side.
	 *
	 * @since 5.0
	 * @return array The data: {
	 *      @type string ajax_url The ajax request url.
	 *      @type string method The request method.
	 *      @type string action The request action name to fire wp_ajax.
	 *      @type string _isnonce The security nonce to validate the request.
	 *      @type int page The page request number.
	 *      @type int indexed The indexed quantity.
	 *      @type int terms The number of terms indexed.
	 *      @type string start_msg The start message feedback to the user.
	 *      @type string btn_label The button label.
	 * }
	 */
	public function get_ajax_create_index_data() {
		$page    = 1;
		$total   = $this->count_posts_to_index();
		$indexed = $this->model->count_indexed_posts();

		$idx_status = $this->get_idx_status();

		return array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'method'     => 'POST',
			'action'     => self::CREATE_INDEX_ACTION,
			'_isnonce'   => wp_create_nonce( self::CREATE_INDEX_ACTION ),
			'page'       => $page,
			'indexed'    => $indexed,
			'total'      => $total,
			'start_msg'  => __( 'Creating Index...', 'add-search-to-menu' ),
			'btn_labels' => array(
				self::IDX_EMPTY    => __( 'Create Index', 'add-search-to-menu' ),
				self::IDX_CREATING => __( 'Pause Creating Index', 'add-search-to-menu' ),
				self::IDX_PAUSING  => __( 'Pausing Creating Index', 'add-search-to-menu' ),
				self::IDX_PAUSED   => __( 'Resume Creating Index', 'add-search-to-menu' ),
				self::IDX_CREATED  => __( 'Recreate Index', 'add-search-to-menu' ),
			),
			'idx_status' => $idx_status,
		);
	}

	public function get_idx_status() {
		$idx_status = self::IDX_EMPTY;
		$total      = $this->count_posts_to_index();
		$indexed    = $this->model->count_indexed_posts();

		if ( $this->build_offset > 0 && $this->build_offset < $total ) {
			$idx_status = self::IDX_PAUSED;
		} elseif ( 0 < $total && 0 == $this->build_offset && 0 < $indexed ) {
			$idx_status = self::IDX_CREATED;
		}

		return $idx_status;
	}

	/**
	 * Create index AJAX handler.
	 *
	 * Handle the request and process indexing posts.
	 * Verify security before indexing.
	 *
	 * @since 5.0
	 * @return json|array The results object : {
	 *      @type string btn_label The button label.
	 *      @type string results The indexing results to show.
	 *      @type int total Total posts to index.
	 *      @type int indexed The indexed quantity.
	 *      @type int terms The number of terms indexed.
	 *      @type int page The page request number.
	 *      @type bool end If true, indicates the end of indexing.
	 * }
	 */
	public function ajax_create_index() {

		$page = ! empty( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$total   = 0;
		$results = '';
		$indexed = 0;
		$idx_status = $this->get_idx_status();

		if ( wp_verify_nonce( @$_POST['_isnonce'], self::CREATE_INDEX_ACTION )
				&& ! $this->check_object_lock() ) {
			$this->set_object_lock();

			switch ( $idx_status ) {
				case self::IDX_EMPTY:
				case self::IDX_CREATED:
					if ( 1 == $page ) {
						$this->delete_index();
						$this->build_results = array();
					}
					break;
			}
			$idx_status = self::IDX_CREATING;

			$ret = $this->get_posts_to_index(
				$this->get_build_pagination_args( $page )
			);

			$this->set_start_time();
			$indexed = $this->build_offset;
			$posts   = $ret['posts'];
			$total   = $ret['total'];
			$terms   = 0;

			foreach ( $posts as $post ) {
				ob_start();
				$rows = 0;
				try {
					if ( $post instanceof WP_Post && $post->ID ) {

						$this->delete_post_index( $post->ID );
						$rows = $this->builder->index_post( $post );
					}
				} catch ( \Exception $e ) {
					$this->add_index_error( $post->ID, $e );
					$this->save();
				}
				/**
				 * Avoid garbage that may arise when using do_shortcode and post content filters.
				 * This garbage can raise errors in JSON parsing.
				 * So just keep in cache and trash away.
				 */
				ob_get_clean();

				$indexed++;
				$terms += $rows;

				// save periodically to avoid offset loss in case of error.
				if ( $indexed % 5 == 0 ) {
					$this->save();
				}
			}

			$this->set_end_time();
			$exec_time = $this->calc_exec_time();

			$results               = sprintf(
				'- %s %d %s (%d / %d), %d %s, %d %s',
				__( 'indexed', 'add-search-to-menu' ),
				$indexed - $this->build_offset,
				__( 'contents', 'add-search-to-menu' ),
				$indexed,
				$total,
				$terms,
				__( 'terms', 'add-search-to-menu' ),
				$exec_time,
				__( 'seconds', 'add-search-to-menu' )
			);
			$this->build_results[] = $results;

			$page++;
			$this->build_offset = $indexed;
			$results = $this->get_index_status( false, __( 'Creating Index...', 'add-search-to-menu' ) );

			if ( $this->build_offset >= $total ) {
				$page               = 1;
				$finished_msg = __( 'Finished Creating Index.', 'add-search-to-menu' );
				$results            .= PHP_EOL . $finished_msg . PHP_EOL;
				$this->build_results[] = $finished_msg;
				$idx_status         = self::IDX_CREATED;
				$this->build_offset = 0;
			}

			$this->delete_object_lock( false );
			$this->save();

		} elseif ( $this->check_object_lock() ) {
			$results = __( 'Error: Index is already executing. Try again in a few minutes.', 'add-search-to-menu' );
		} else {
			$results = __( 'Error: Index was not created', 'add-search-to-menu' );
		}

		$ret = array(
			'idx_status' => esc_html( $idx_status ),
			'results'    => $results,
			'total'      => intval( $total ),
			'indexed'    => intval( $indexed ),
			'page'       => intval( $page ),
		);

		$ret = wp_parse_args(
			$ret,
			$this->get_ajax_create_index_data()
		);
		echo json_encode( $ret );
		wp_die();
	}

	/**
	 * Get posts to index.
	 *
	 * @since 5.0
	 * @return array [
	 *      'posts' => The posts to index.
	 *      'total' => The total posts found.
	 *      'pages'=> The number of pages, according to posts_per_page param.
	 * ]
	 */
	public function get_posts_to_index( $args = array() ) {
		$args  = $this->get_query_args( $args );
		$query = new WP_Query( $args );

		$ret = array(
			'posts' => $query->get_posts(),
			'total' => $query->found_posts,
		);

		return $ret;
	}

	/**
	 * Get posts to index count.
	 *
	 * @since 5.0
	 * @return int The quantity of found posts to index.
	 */
	public function count_posts_to_index() {
		$args  = $this->get_query_args(
			array(
				'fields' => 'ids',
			)
		);
		$query = new WP_Query( $args );

		return $query->found_posts;
	}

	/**
	 * Get posts to index query vars.
	 *
	 * @since 5.0
	 * @param array $args The query args to merge.
	 * @return array The default query args to find posts to index.
	 */
	protected function get_query_args( $args = array() ) {
		$defaults = array(
			'post_type'      => $this->index_opt->post_types,
			'posts_per_page' => 10,
			'post_status'    => array(
				'publish',
				'pending',
				'draft',
				'future',
				'private',
				'inherit', // attachments
			),
			'order'          => 'ASC',
			'orderby'        => 'ID',
		);

		$args = wp_parse_args( $args, $defaults );
		return apply_filters( 'is_get_posts_to_index_args', $args );

	}

	/**
	 * Get pagination args for WP_Query.
	 *
	 * @since 5.0
	 * @param int    $page The current page of build index.
	 * @param string $btn_action The action name.
	 * @return array The query args with pagination info.
	 */
	protected function get_build_pagination_args( $page ) {
		$exec_time     = $this->calc_exec_time();
		$max_exec_time = @ini_get( 'max_execution_time' ) * .8;
		$max_exec_time = min( $max_exec_time, 45 );
		$max_per_page  = apply_filters( 'is_index_max_per_page', 100 );
		$multiplier    = 2;
		$per_page      = $this->build_per_page;
		$build_offset  = $this->build_offset;
		$idx_status    = $this->get_idx_status();

		if ( 1 == $page ) {
			switch ( $idx_status ) {
				case self::IDX_CREATING:
				case self::IDX_PAUSED:
					$per_page     = 10;
					$build_offset = $this->model->count_indexed_posts();
					break;
				default:
					$per_page     = 10;
					$build_offset = 0;
					break;
			}
		} else {

			if ( $exec_time > 0 && $max_exec_time > $exec_time ) {
				$div = floor( $max_exec_time / $exec_time );

				if ( $div > ( 2 * $multiplier ) ) {
					$per_page *= $multiplier;
				} elseif ( $div > 1.5 ) {
					$per_page += 10;
				}

			}
			elseif( $exec_time > $max_exec_time ) {
				$per_page -= 10;
			}

			if ( $per_page > $max_per_page ) {
				$per_page = $max_per_page;
			}
			if ( $per_page < 10 ) {
				$per_page = 10;
			}
		}

		$args = array(
			'posts_per_page' => $per_page,
			'offset'         => $build_offset,
		);

		$this->build_per_page = $per_page;
		$this->build_offset   = $build_offset;

		return $args;
	}

	/**
	 * Get delete index AJAX data to use in client side.
	 *
	 * @since 5.0
	 * @return array The data: {
	 *      @type string ajax_url The ajax request url.
	 *      @type string method The request method.
	 *      @type string action The request action name to fire wp_ajax.
	 *      @type string _isnonce The security nonce to validate the request.
	 *      @type string start_msg The start message feedback to the user.
	 * }
	 */
	public function get_ajax_delete_index_data() {
		return array(
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'method'    => 'POST',
			'action'    => self::DELETE_INDEX_ACTION,
			'_isnonce'  => wp_create_nonce( self::DELETE_INDEX_ACTION ),
			'start_msg' => esc_html__( 'Deleting Index...', 'add-search-to-menu' ),
		);
	}

	/**
	 * Handle AJAX request to delete all the existing indexes.
	 *
	 * Verify security before deleting.
	 *
	 * @since 5.0
	 * @return json|array {
	 *      Results array in the form: 'results' => The index status.
	 *      @type string $results The results.
	 * }
	 */
	public function ajax_delete_index() {
		$results = '';
		if ( wp_verify_nonce( @$_POST['_isnonce'], self::DELETE_INDEX_ACTION ) ) {
			$this->delete_index();
			$results = __( 'Index deleted', 'add-search-to-menu' );
		} else {
			$results = __( 'Error: Index was not deleted', 'add-search-to-menu' );
		}
		$results .= PHP_EOL . PHP_EOL . $this->get_index_status( true );
		$ret      = array(
			'results'   => esc_textarea( $results ),
			'btn_label' => esc_html__( 'Create Index', 'add-search-to-menu' ),
		);

		echo json_encode( $ret );
		wp_die();
	}

	/**
	 * Deletes all indexes.
	 *
	 * @since 5.0
	 */
	public function delete_index() {
		$this->model->clear_index_table();
		$this->reset_index_errors();
		$this->build_results = array();
		$this->__set( 'build_per_page', 10 );
		$this->__set( 'build_offset', 0 );

		$this->save();
	}

	/**
	 * Get index post AJAX data to use in client side.
	 *
	 * @since 5.0
	 * @return array The data: {
	 *      @type string ajax_url The ajax request url.
	 *      @type string method The request method.
	 *      @type string action The request action name to fire wp_ajax.
	 *      @type string _isnonce The security nonce to validate the request.
	 *      @type int $post_id The post ID to request ajax. It is set on client side.
	 *      @type string start_msg The start message feedback to the user.
	 * }
	 */
	public function get_ajax_index_post_data() {
		return array(
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'method'    => 'POST',
			'action'    => self::INDEX_POST_ACTION,
			'_isnonce'  => wp_create_nonce( self::INDEX_POST_ACTION ),
			'post_id'   => 0,
			'start_msg' => esc_html__( 'Indexing content...', 'add-search-to-menu' ),
		);
	}

	/**
	 * Handle AJAX request to index a post.
	 *
	 * Verify security before indexing.
	 *
	 * @since 5.0
	 * @param int $post_id The post id in $_POST request.
	 * @return json|array {
	 *      Results array in the form: 'results' => The index status.
	 *      @type string $results The results.
	 * }
	 */
	public function ajax_index_post() {
		$error   = true;
		$post_id = ! empty( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$results = '';
		if ( $post_id && wp_verify_nonce( @$_POST['_isnonce'], self::INDEX_POST_ACTION ) ) {

			ob_start();

			if ( $this->index_post( $post_id ) ) {
				$results = esc_html__( 'Content Indexed ID: ', 'add-search-to-menu' ) . $post_id;
				$error   = false;
			}

			/**
			 * Avoid garbage that may arise when using do_shortcode and post content filters.
			 * This garbage can raise errors in JSON parsing.
			 * So just keep in cache and trash away.
			 */
			ob_get_clean();
		}

		if ( $error ) {
			$results = esc_html__( 'Error: content was not indexed', 'add-search-to-menu' );
			$results .= ! empty( $this->index_errors[ $post_id ] ) ? $this->index_errors[ $post_id ] : '';
		}
		$results .= PHP_EOL . PHP_EOL . $this->get_index_status( true );
		$ret      = array(
			'results' => $results,
		);

		echo json_encode( $ret );
		wp_die();
	}

	/**
	 * Index individual posts.
	 *
	 * Save index error when occurs.
	 *
	 * @since 5.0
	 * @param int $post_id The post_id to index.
	 * @return int The quantity of terms indexed in this post.
	 */
	public function index_post( $post_id ) {
		$rows    = 0;
		$post_id = intval( $post_id );

		if ( $post_id > 0 ) {
			try {
				$this->remove_index_error( $post_id );
				$rows = $this->builder->index_post( $post_id, true );
			} catch ( \Exception $e ) {
				$this->add_index_error( $post_id, $e );
				$this->save();
			}
		}

		return $rows;
	}

	/**
	 * Delete post index.
	 *
	 * @since 5.0
	 * @param int $post_id The post ID to delete index.
	 */
	public function delete_post_index( $post_id ) {
		$post_id = intval( $post_id );
		$this->builder->delete_post_index( $post_id );
	}

	/**
	 * Index post comment.
	 *
	 * Reindex post when comments are added or removed.
	 *
	 * @since 5.0
	 * @param int $comment_id The comment ID to index|remove.
	 */
	public function index_comment( $comment_id = 0 ) {
		$indexable = $this->index_opt->index_comments;

		$comment_id = intval( $comment_id );
		if ( $indexable && $comment_id ) {
			$comment = get_comment( $comment_id );
			$allowed = array( 1, 'trash', false );
			if ( $comment
					&& 'comment' == $comment->comment_type
					&& in_array( $comment->comment_approved, $allowed )
			) {
				$post_id = $comment->comment_post_ID;
				$this->index_post( $post_id );
			}
		}
	}

	/**
	 * Remove post comment.
	 *
	 * Reindex post when comments are added or removed.
	 *
	 * @since 5.0
	 * @param int $comment_id The comment ID to remove.
	 */
	public function delete_comment_index( $comment_id = 0 ) {
		$comment_id = intval( $comment_id );
		$this->index_comment( $comment_id );
	}

	/**
	 * Add post indexing error.
	 *
	 * @since 5.0
	 * @param int       $post_id The post ID which error occured.
	 * @param Exception $e The exception object.
	 */
	public function add_index_error( $post_id, $e ) {
		$post_id = intval( $post_id );
		$msg     = " [post_id]: $post_id - [error]: " . $e->getMessage();

		$this->index_errors[ $post_id ] = $msg;
	}

	/**
	 * Remove post indexing error.
	 *
	 * @since 5.0
	 * @param int $post_id The post ID which error occured.
	 */
	public function remove_index_error( $post_id ) {
		$post_id = intval( $post_id );
		unset( $this->index_errors[ $post_id ] );
	}

	/**
	 * Reset post indexing errors.
	 *
	 * @since 5.0
	 */
	public function reset_index_errors() {
		$this->index_errors = array();
	}

	/**
	 * Set execution start time.
	 *
	 * @since 5.0
	 */
	public function set_start_time() {
		$this->set_timestamp( 'build_start_time' );
	}

	/**
	 * Set execution end time.
	 *
	 * @since 5.0
	 */
	public function set_end_time() {
		$this->set_timestamp( 'build_end_time' );
	}

	/**
	 * Set timestamp in a property.
	 *
	 * @since 5.0
	 * @param string    $property The class property name.
	 * @param int       $time Optional. The UNIX timestamp.
	 * @param Exception $e The exception object.
	 */
	public function set_timestamp( $property, $time = 0 ) {
		$value = time();
		if ( ! empty( $time ) && intval( $time ) ) {
			$value = $time;
		}
		if ( in_array( $property, array( 'build_start_time', 'build_end_time' ) ) ) {
			$this->$property = $value;
		}
	}

	/**
	 * Calculates the build execution time for the last request.
	 *
	 * @since 5.0
	 * @return int The execution time in seconds of the last request.
	 */
	public function calc_exec_time() {
		$exec_time = 0;

		if ( ! empty( $this->build_end_time ) && ! empty( $this->build_start_time ) ) {
			$exec_time = intval( $this->build_end_time ) - intval( $this->build_start_time );
		}

		return max( $exec_time, 1 );
	}

	/**
	 * Get class properties default values.
	 *
	 * @since 5.0
	 * @return array The default values.
	 */
	public function get_defaults() {
		return array(
			'index_status'     => '',
			'build_results'    => array(),
			'build_start_time' => 2,
			'build_end_time'   => 2,
			'build_per_page'   => 10,
			'build_offset'     => 0,
			'index_errors'     => array(),
		);
	}

	public function get_build_results( $as_string = false ) {
		$build_results = $this->build_results;
		if ( $as_string ) {
			$build_results = implode( PHP_EOL, $build_results );
		}

		return $build_results;
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
				case 'index_status':
					$this->$property = sanitize_textarea_field( $value );
					break;

				case 'build_results':
					if ( is_array( $value ) ) {
						$this->$property = array_map( 'sanitize_text_field', $value );
					}
					break;

				case 'index_errors':
					$this->$property = $value;
					break;

				case 'build_offset':
				case 'build_per_page':
					$this->$property = intval( $value );
					break;

				case 'build_start_time':
				case 'build_end_time':
					$this->set_timestamp( $property, $value );
					break;

				default:
					$this->$property = $value;
					break;
				case 'index_opt':
					if ( is_array( $value ) ) {
						$this->$property = $value;
					}
					break;
			}
		}
	}
}
