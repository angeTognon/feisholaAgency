<?php

class IS_Search_Form {

	const post_type = 'is_search_form';

	const S_ENG_WP = 'wp';
	const S_ENG_INDEX = 'index';

	private static $found_items = 0;
	private static $current = null;

	private $id;
	private $name;
	private $title;
	private $locale;
	private $properties = array();

	private function __construct( $post = null ) {
		$post = get_post( $post );

		if ( $post && self::post_type == get_post_type( $post ) ) {
			$this->id = $post->ID;
			$this->name = $post->post_name;
			$this->title = $post->post_title;
			$this->locale = get_post_meta( $post->ID, '_is_locale', true );

			$properties = $this->get_properties();

			foreach ( $properties as $key => $value ) {
				if ( metadata_exists( 'post', $post->ID, $key ) ) {
					$properties[$key] = get_post_meta( $post->ID, $key, true );
				}
			}

			$this->properties = $properties;
		}
	}

	public static function get_instance( $post ) {
		$post = get_post( $post );

		if ( ! $post || self::post_type != get_post_type( $post ) ) {
			return false;
		}

		return self::$current = new self( $post );
	}

	/**
	 * Load IS Search Form from request.
	 *
	 * @since 5.0
	 *
	 * @param int $is_id Optional. The search form ID to load.
	 * @return self The retrieved object.
	 */
	public static function load_from_request() {
		$search_form = null;
		$is_id = null;

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$is_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : null;
		} else {
			$is_id = absint( get_query_var( 'id' ) );
		}

		$is_settings = get_option( 'is_settings', array() );
		if ( empty( $is_id ) && empty( $is_settings['default_search'] ) ) {
			$page = get_page_by_path( 'default-search-form', OBJECT, 'is_search_form' );
			if ( ! empty( $page ) ) {
				$is_id = $page->ID;
			}
		}

		if ( ! empty( $is_id ) && is_numeric( $is_id ) ) {
			$search_form = self::get_instance( $is_id );
		}

		return $search_form;
	}

	public static function count() {
		return self::$found_items;
	}

	public static function get_current() {
		return self::$current;
	}

	public static function register_post_type() {
		register_post_type( self::post_type, array(
			'labels' => array(
				'name'			=> __( 'Search Forms', 'add-search-to-menu' ),
				'singular_name' => __( 'Search Form', 'add-search-to-menu' ),
			),
			'rewrite'   => false,
			'query_var' => false,
		) );
	}

	public static function find( $args = '' ) {
		$defaults = array(
			'post_status'	 => 'any',
			'posts_per_page' => -1,
			'offset'		 => 0,
			'orderby'		 => 'ID',
			'order'			 => 'ASC',
			'suppress_filters' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		$args['post_type'] = self::post_type;

		$q = new WP_Query();
		$posts = $q->query( $args );

		self::$found_items = $q->found_posts;

		$objs = array();

		foreach ( (array) $posts as $post ) {
			$objs[] = new self( $post );
		}

		return $objs;
	}

	public static function get_template( $args = '' ) {
		global $l10n;

		$defaults = array( 'locale' => null, 'title' => '' );
		$args = wp_parse_args( $args, $defaults );

		$locale = $args['locale'];
		$title = $args['title'];

		if ( $locale ) {
			$mo_orig = $l10n['add-search-to-menu'];
			$is_i18n = IS_I18n::getInstance();
			$is_i18n->load_is_textdomain( $locale );
		}

		self::$current = $search_form = new self;
		$search_form->title =
			( $title ? $title : __( 'Untitled', 'add-search-to-menu' ) );
		$search_form->locale = ( $locale ? $locale : get_locale() );

		$properties = $search_form->get_properties();

		$search_form->properties = $properties;

		$search_form = apply_filters( 'is_search_form_default_pack',
			$search_form, $args );

		if ( isset( $mo_orig ) ) {
			$l10n['add-search-to-menu'] = $mo_orig;
		}

		return $search_form;
	}

	public function __get( $name ) {
		$message = '<code>%1$s</code> '.__( 'property of a', 'add-search-to-menu' ).' <code>IS_Search_Form</code> '.__( 'object is', 'add-search-to-menu' ).' <strong>'.__( 'no longer accessible', 'add-search-to-menu' ).'</strong>. '.__( 'Use', 'add-search-to-menu' ).' <code>%2$s</code> '.__('method instead.', 'add-search-to-menu' );

		if ( 'id' == $name ) {
			if ( WP_DEBUG ) {
				trigger_error( sprintf( $message, 'id', 'id()' ) );
			}

			return $this->id;
		} elseif ( 'title' == $name ) {
			if ( WP_DEBUG ) {
				trigger_error( sprintf( $message, 'title', 'title()' ) );
			}

			return $this->title;
		} elseif ( $prop = $this->prop( $name ) ) {
			if ( WP_DEBUG ) {
				trigger_error(
					sprintf( $message, $name, 'prop(\'' . $name . '\')' ) );
			}

			return $prop;
		}
	}

	public function initial() {
		return empty( $this->id );
	}

	public function prop( $name ) {
		$props = $this->get_properties();
		return isset( $props[$name] ) ? $props[$name] : null;
	}

	/**
	 * Get IS Search Form option.
	 *
	 * @since 5.0
	 * @param string $group The option group.
	 * @param string $name The option name.
	 * @param mixed  $default The default value.
	 * @return mixed The option value.
	 */
	public function group_prop( $group, $name, $default = null ) {
		$props = $this->get_properties();
		return isset( $props[$group][$name] ) ? $props[$group][$name] : $default;
	}

	public function get_properties() {
		$properties = (array) $this->properties;

		$properties = wp_parse_args( $properties, array(
			'_is_includes' => '',
			'_is_excludes' => '',
			'_is_settings' => '',
			'_is_ajax' => '',
			'_is_customize' => '',
		) );

		$properties = (array) apply_filters( 'is_search_form_properties',
			$properties, $this );

		return $properties;
	}

	public function set_properties( $properties ) {
		$defaults = $this->get_properties();

		$properties = wp_parse_args( $properties, $defaults );
		$properties = array_intersect_key( $properties, $defaults );

		$this->properties = $properties;
	}

	public function id() {
		return $this->id;
	}

	public function name() {
		return $this->name;
	}

	public function title() {
		return $this->title;
	}

	public function set_title( $title ) {
		$title = strip_tags( $title );
		$title = trim( $title );

		if ( '' === $title ) {
			$title = __( 'Untitled', 'add-search-to-menu' );
		}

		$this->title = $title;
	}

	public function locale() {
		if ( $this->is_valid_locale( $this->locale ) ) {
			return $this->locale;
		} else {
			return '';
		}
	}

	public function set_locale( $locale ) {
		$locale = trim( $locale );

		if ( $this->is_valid_locale( $locale ) ) {
			$this->locale = $locale;
		} else {
			$this->locale = 'en_US';
		}
	}

	/**
	 * Verifies if index search engine is selected.
	 * 
	 * @since 5.0
	 * @return bool True if it is index search engine.
	 */
	public function is_index_search() {
		$index_search = false;

		$engine = $this->group_prop( '_is_settings', 'search_engine', self::S_ENG_WP );
		if ( self::S_ENG_INDEX == $engine ) {
			$index_search = true;
		}

		return $index_search;
	}

	/* Generating Form HTML */
	public function form_html( $args = '', $display_id = '' ) {

		do_action( 'pre_is_get_search_form' );

                if ( ! isset( $args['id'] ) ) {
					return '';
				}

                $_ajax = '';
                $_customize = '';
                $_includes = '';
                $_settings = '';
                $result = '';
                $search_form = false;
                $enabled_customization = false;
                $is_site_lang = isset( $_GET['lang'] ) ? sanitize_text_field($_GET['lang']) : false;
                $is_settings = get_option( 'is_settings', array() );
                $min = ( defined( 'IS_DEBUG' ) && IS_DEBUG ) ? '' : '.min';
				$is_site_lang = isset( $_GET['lang'] ) ? sanitize_text_field( $_GET['lang'] ) : false;

				if ( $args['id'] ) {
	                $search_form = IS_Search_Form::get_instance( $args['id'] );

	                if ( ! $search_form ) {
	                    return __( 'Invalid search form.', 'add-search-to-menu' );
	                }

	                $_ajax = $this->prop('_is_ajax');
	                $_customize = $this->prop('_is_customize');
	                $_includes = $this->prop( '_is_includes' );
	                $_settings = $this->prop('_is_settings');
	                $enabled_customization = ( isset( $_customize['enable_customize'] ) || 'default-search-form' != $search_form->name() || isset( $_ajax['enable_ajax'] ) ) ? true : false;

	                if ( ! isset( $is_settings['not_load_files']['css'] ) && isset( $_ajax['enable_ajax'] ) ) {
	                        wp_enqueue_style( 'ivory-ajax-search-styles', plugins_url( '/public/css/ivory-ajax-search'.$min.'.css', IS_PLUGIN_FILE ), array(), IS_VERSION );
	                }

	                if ( isset( $_settings['disable'] ) ) {
	                        return '';
	                }
	                if ( isset( $_settings['demo'] ) && ! current_user_can( 'administrator' ) ) {
	                        return '';
	                }
            	}

                if ( ! $enabled_customization && $args['id'] ) {

                    remove_filter( 'get_search_form', array( IS_Admin_Public::getInstance(), 'get_search_form' ), 9999999 );
                    $result = get_search_form( false );
                    add_filter( 'get_search_form', array( IS_Admin_Public::getInstance(), 'get_search_form' ), 9999999 );

                    if ( 'n' !== $display_id ) {
                        $result = preg_replace('/<\/form>/', '<input type="hidden" name="id" value="' . esc_attr( $args['id'] ) . '" /></form>', $result );

						if ( ! isset( $_includes['post_type_url']  ) && isset( $_includes['post_type'] ) && count( $_includes['post_type'] ) < 2 ) {
								$result = preg_replace('/<\/form>/', '<input type="hidden" name="post_type" value="' . esc_attr( reset( $_includes['post_type'] ) ) . '" /></form>', $result );
						}
						if ( $is_site_lang ) {
							$result = preg_replace('/<\/form>/', '<input type="hidden" name="lang" value="' . esc_attr( $is_site_lang ) . '" /></form>', $result );
						}
					}

                    $result = apply_filters( 'is_default_search_form', $result );

                } else {

                $view_search_result_class = ( isset( $_ajax['search_results'] ) && 'ajax_results' === $_ajax['search_results'] ) ? 'is-disable-submit ' : '';
            	$settings = get_option( 'is_search_' . $args['id'] );

                $is_ajax_search = '';
                $data_attrs = '';
                $placeholder_text = __( 'Search here...', 'add-search-to-menu');
                $search_btn_text = __( 'Search', 'add-search-to-menu');
                $form_style = 'is-form-style-3';

                if ( $enabled_customization ) {
                    $placeholder_text = isset( $settings['placeholder-text'] ) ? $settings['placeholder-text'] : $placeholder_text;
                    $search_btn_text = isset( $settings['search-btn-text'] ) ? $settings['search-btn-text'] : $search_btn_text;
                    $form_style = isset( $settings['form-style'] ) ? $settings['form-style'] : 'is-form-style-3';
                }

                if ( isset( $_ajax['enable_ajax'] ) ) {
                    $is_ajax_search = 'is-ajax-search';
					$form_style = ( '' == $form_style ) ? 'is-form-style-3' : $form_style;
                    // Enqueue scripts.
                    wp_enqueue_script( 'ivory-ajax-search-scripts' );
                    if ( ! ivory_search_is_json_request() && isset( $_settings['highlight_terms'] ) ) {
                    	wp_enqueue_script( 'is-highlight' );
						if ( isset( $_settings['highlight_color'] ) && ! empty( $_settings['highlight_color'] ) && ! is_search() ) { ?>
							<style type="text/css" media="screen">
							#is-ajax-search-result-<?php esc_html_e( $args['id'] ); ?> .is-highlight { background-color: <?php esc_html_e( $_settings['highlight_color'] ); ?> !important;}
							#is-ajax-search-result-<?php esc_html_e( $args['id'] ); ?> .meta .is-highlight { background-color: transparent !important;}
							</style>
						<?php }
                	}

                    $min_no_for_search  = isset( $_ajax['min_no_for_search'] ) ? $_ajax['min_no_for_search'] : '1';
                    $result_box_max_height = isset( $_ajax['result_box_max_height'] ) ? $_ajax['result_box_max_height'] : '400';

                    // Add data AJAX attributes.
                    $data_attrs = 'data-min-no-for-search='. $min_no_for_search;
                    $data_attrs .= ' data-result-box-max-height='.$result_box_max_height;
                    $data_attrs .= ' data-form-id='.$args['id'];
                }

                $temp = ( 'is-form-style-default' !== $form_style ) ? 'is-form-style ' : '';
                $classes = $view_search_result_class . $temp . $form_style . ' is-form-id-' . $args['id'].' '.$is_ajax_search;
                $classes = apply_filters( 'is_search_form_classes', $classes );

 				$search_url = home_url( '/' );

	            if ( function_exists( 'pll_home_url' ) ) {
	                $search_url = pll_home_url();

	                if ( get_option( 'show_on_front' ) === 'page' ) {

	                    $current_language = pll_current_language();
	                    $default_language = pll_default_language();

	                    if ( $current_language != $default_language ) {
	                        if ( strpos( $search_url, '/' . $current_language ) !== false ) {
	                            $language_subdir = $current_language.'/';
	                            $search_url = home_url( '/' . $language_subdir );
	                        }
	                    }
	                }
	            }

                $result = '<form '.esc_attr( $data_attrs ).' class="is-search-form '. esc_attr( $classes ) .'" action="' . esc_url( $search_url ) . '" method="get" role="search" >';
                $autocomplete = apply_filters( 'is_search_form_autocomplete', 'autocomplete=off' );

                $is_form_id = ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) ? sanitize_key( $_GET['id'] ) : false;
                $search_val = ( $is_form_id && $is_form_id === $args['id'] ) ? get_search_query() : '';
                $result .= '<label for="is-search-input-' . esc_attr( $args['id'] ) . '"><span class="is-screen-reader-text">'.__( 'Search for:', 'add-search-to-menu').'</span><input  type="search" id="is-search-input-' . esc_attr( $args['id'] ) . '" name="s" value="' . esc_attr( $search_val ).'" class="is-search-input" placeholder="' . esc_attr( $placeholder_text ) . '" '. esc_attr( $autocomplete ) .' />';

                // AJAX Loader.
                if ( isset( $_ajax['enable_ajax'] ) ) {
                    $loader_image = isset( $settings['loader-image'] ) ? $settings['loader-image'] : IS_PLUGIN_URI . 'public/images/spinner.gif';
                    if ( $loader_image ) {
                            $result .= '<span class="is-loader-image" style="display: none;background-image:url('.esc_url( $loader_image).');" ></span>';
                    }
                }
                $result .= '</label>';
                if ( 'is-form-style-3' === $form_style ) {
                    $result .= '<button type="submit" class="is-search-submit"><span class="is-screen-reader-text">'.__( 'Search Button', 'add-search-to-menu').'</span><span class="is-search-icon"><svg focusable="false" aria-label="' . __( "Search", "add-search-to-menu" ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24px"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg></span></button>';
				} else if ( 'is-form-style-2' !== $form_style ) {
                    $result .= '<input type="submit" value="' . esc_attr( $search_btn_text ) . '" class="is-search-submit" />';
                }

                if ( 'n' !== $display_id ) {
                    $result .= '<input type="hidden" name="id" value="' . esc_attr( $args['id'] ) . '" />';
                }

                if ( ! isset( $_includes['post_type_url']  ) && isset( $_includes['post_type'] ) && count( $_includes['post_type'] ) < 2 ) {
                        $result .= '<input type="hidden" name="post_type" value="' . esc_attr( reset( $_includes['post_type'] ) ) . '" />';
                }

                if ( $is_site_lang ) {
                    $result .=  '<input type="hidden" name="lang" value="' . esc_attr( $is_site_lang ) . '" />';
                }
                $result .= '</form>';

                $result = apply_filters( 'is_custom_search_form', $result );
		}

                if ( isset( $is_settings['easy_edit'] ) && is_user_logged_in() && current_user_can( 'administrator' ) ) {
                    $result .= '<div class="is-link-container"><div><a class="is-edit-link" target="_blank" href="'.admin_url( 'admin.php?page=ivory-search&post='.absint( $args['id'] ).'&action=edit' ) . '">'.__( "Edit", "add-search-to-menu") .'</a>';

                    if ( ! is_customize_preview() ) {
                        if ( $enabled_customization ) {
                                $result .= ' <a class="is-customize-link" target="_blank" href="'.admin_url( 'customize.php?autofocus[section]=is_section_'.absint( $args['id'] ).'&url=' . get_the_permalink( get_the_ID() ) ) .'">'.__( "Customizer", "add-search-to-menu") .'</a>';
                        }
                    }
                    $result .= '</div></div>';
                }

                $result = apply_filters( 'is_after_search_form', $result );

		return $result;
	}

	/* Settings */

	public function setting( $name, $max = 1 ) {
		$settings = (array) explode( "\n", $this->prop( 'settings' ) );

		$pattern = '/^([a-zA-Z0-9_]+)[\t ]*:(.*)$/';
		$count = 0;
		$values = array();

		foreach ( $settings as $setting ) {
			if ( preg_match( $pattern, $setting, $matches ) ) {
				if ( $matches[1] != $name ) {
					continue;
				}

				if ( ! $max || $count < (int) $max ) {
					$values[] = trim( $matches[2] );
					$count += 1;
				}
			}
		}

		return $values;
	}

	public function is_true( $name ) {
		$settings = $this->setting( $name, false );

		foreach ( $settings as $setting ) {
			if ( in_array( $setting, array( 'on', 'true', '1' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/* Save */

	public function save() {
		$props = $this->get_properties();

		$post_content = implode( "\n", $this->array_flatten( $props ) );

		if ( $this->initial() ) {
			$post_id = wp_insert_post( array(
				'post_type' => self::post_type,
				'post_status' => 'publish',
				'post_title' => $this->title,
				'post_content' => trim( $post_content ),
			) );
		} else {
			$post_id = wp_update_post( array(
				'ID' => (int) $this->id,
				'post_status' => 'publish',
				'post_title' => $this->title,
				'post_content' => trim( $post_content ),
			) );
		}

		if ( $post_id ) {
			foreach ( $props as $prop => $value ) {
				update_post_meta( $post_id, $prop, $this->normalize_newline_deep( $value ) );
			}

			if ( $this->is_valid_locale( $this->locale ) ) {
				update_post_meta( $post_id, '_is_locale', $this->locale );
			}

			if ( $this->initial() ) {
				$this->id = $post_id;
				do_action( 'is_after_create', $this );
			} else {
				do_action( 'is_after_update', $this );
			}

			do_action( 'is_after_save', $this );
		}

		return $post_id;
	}

	public function copy() {
		$new = new self;
		$new->title = $this->title . '_copy';
		$new->locale = $this->locale;
		$new->properties = $this->properties;

		return apply_filters( 'is_copy', $new, $this );
	}

	public function delete() {
		if ( $this->initial() ) {
			return;
		}

		if ( wp_delete_post( $this->id, true ) ) {
			$this->id = 0;
			return true;
		}

		return false;
	}

	public function shortcode( $args = '' ) {
		$args = wp_parse_args( $args );

		$title = str_replace( array( '"', '[', ']' ), '', $this->title );

		$shortcode = sprintf( '[ivory-search id="%1$d" title="%2$s"]',
			$this->id, $title );

		return apply_filters( 'is_search_form_shortcode', $shortcode, $args, $this );
	}

	function is_valid_locale( $locale ) {
		$pattern = '/^[a-z]{2,3}(?:_[a-zA-Z_]{2,})?$/';
		return (bool) preg_match( $pattern, $locale );
	}

	function normalize_newline( $text, $to = "\n" ) {
		if ( ! is_string( $text ) ) {
			return $text;
		}

		$nls = array( "\r\n", "\r", "\n" );

		if ( ! in_array( $to, $nls ) ) {
			return $text;
		}

		return str_replace( $nls, $to, $text );
	}

	function normalize_newline_deep( $arr, $to = "\n" ) {

		if ( is_array( $arr ) ) {
			$result = array();

			foreach ( $arr as $key => $text ) {
				$result[$key] = $this->normalize_newline_deep( $text, $to );
			}

			return $result;
		}

		return $this->normalize_newline( $arr, $to );
	}

	function array_flatten( $input ) {
		if ( ! is_array( $input ) ) {
			return array( $input );
		}

		$output = array();

		foreach ( $input as $value ) {
			$output = array_merge( $output, $this->array_flatten( $value ) );
		}

		return $output;
	}

}
