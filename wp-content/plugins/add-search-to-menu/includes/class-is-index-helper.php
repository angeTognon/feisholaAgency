<?php

/**
 * Inverted Index Helper.
 *
 * Commom functions shared in admin and public.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Index_Helper {

	/**
	 * Stores plugin index options.
	 *
	 * @since 5.0
	 * @var IS_Index_Option
	 */
	protected $index_opt;

	/**
	 * Initializes this class.
	 *
	 * @since 5.0
	 * @param IS_Index_Option $index_opt The index options.
	 */
	public function __construct( $index_opt ) {
		$this->index_opt = $index_opt;
	}

	/**
	 * Set internal encoding to handle multi byte encodings.
	 *
	 * @since 5.0
	 * @param IS_Index_Option $index_opt The index options.
	 */
	public function set_encoding() {
		if ( function_exists( 'mb_internal_encoding' ) ) {
			mb_internal_encoding( 'UTF-8' );
		}
	}

	/**
	 * Tokenizes strings.
	 *
	 * Tokenizes strings, removes punctuation, converts to lowercase and removes
	 * stopwords. The function accepts both strings and arrays of strings as
	 * source material. If the parameter is an array of string, each string is
	 * tokenized separately and the resulting tokens are combined into one array.
	 *
	 * @since 5.0
	 * @param string|array $string The string, or an array of strings, to tokenize.
	 * @param int          $min_word_length The minimum word length to include. Default 3.
	 * @param bool         $expand_content The flag to expand shortcodes and blocks. Default false.
	 * @return int[] An array of tokens as the keys and their frequency as the value.
	 */
	public function tokenize_string( $string, $min_word_length = 0, $expand_content = false ) {
		$tokens    = array();
		$delimiter = "\n\t ";
		if ( empty( $min_word_length ) ) {
			$min_word_length = $this->index_opt->min_word_length;
		}

		if ( ! $string || ( ! is_string( $string ) && ! is_array( $string ) ) ) {
			return array();
		}

		if ( is_array( $string ) ) {
			$string = $this->array_to_string( $string );
		}

		$this->set_encoding();

		if ( $expand_content ) {
			$string = $this->block_rendering( $string );

			$string = $this->handle_shortcodes( $string );
		}

		$string = $this->strip_invisibles( $string );

		$string = $this->strip_all_tags( $string );

		$string = $this->strtolower( $string );

		$string = $this->remove_punctuation( $string );

		$token = strtok( $string, $delimiter );
		while ( false !== $token ) {
			$token = $this->mb_trim( $token );
			$token = $this->remove_stopwords( $token );

			$token  = strval( $token );
			$length = $this->strlen( $token );

			if ( $length >= $min_word_length ) {
				if ( is_numeric( $token ) ) {
					// $token ends up as an array index, and numbers don't work there.
					$token = " $token";
				}

				if ( ! isset( $tokens[ $token ] ) ) {
					$tokens[ $token ] = 1;
				} else {
					$tokens[ $token ]++;
				}
			}

			$token = strtok( $delimiter );
		}

		return $tokens;
	}

	/**
	 * Recursively flattens a multidimensional array to produce a string.
	 *
	 * @since 5.0
	 * @param array $array The source array.
	 * @return string The array contents as a string.
	 */
	public function array_to_string( array $array ) {
		$return_value = '';
		foreach ( new RecursiveIteratorIterator( new RecursiveArrayIterator( $array ) ) as $value ) {
			$return_value .= ' ' . $value;
		}
		return trim( $return_value );
	}

	/**
	 * Renders Gutenberg blocks.
	 *
	 * @see do_blocks()
	 *
	 * @since 5.0
	 * @param string $content The post content.
	 * @return string The post content with the rendered content added.
	 */
	public function block_rendering( $content ) {

		if( ! function_exists( 'parse_blocks' ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		$output = '';

		foreach ( $blocks as $block ) {
			/**
			 * Filters the Gutenberg block before it is rendered.
			 *
			 * @see render_block
			 *
			 * @param array $block The Gutenberg block element.
			 */
			$block = apply_filters( 'is_block_to_render', $block );

			if ( ! $block ) {
				continue;
			}

			if (
				! isset( $block['attrs']['className'] )
				|| false === strstr( $block['attrs']['className'], 'is_noindex' )
			) {
				/**
				 * Filters the Gutenberg block after it is rendered.
				 *
				 * The value is the output from render_block( $block ). Feel free to
				 * modify it as you wish.
				 *
				 * @see render_block
				 *
				 * @param string The rendered block content.
				 * @param array  $block The Gutenberg block being rendered.
				 *
				 * @return string The filtered block content.
				 */
				$output .= apply_filters( 'is_rendered_block', render_block( $block ), $block );
			}
		}

		return $output;
	}

	/**
	 * Handle shorcodes.
	 *
	 * Check expand_shortcodes option to remove or expand before indexing.
	 *
	 * @since 5.0
	 * @param string $string The string to handle containing shortcodes.
	 * @return string The string after handling the shortcoes.
	 */
	public function handle_shortcodes( $string ) {

		$expand_shortcodes = $this->index_opt->expand_shortcodes;
		if ( $expand_shortcodes ) {

			$this->disable_problematic_shortcodes();

			$string = $this->remove_page_builder_shortcodes( $string );

			$string = do_shortcode( $string );

		} else {
			$string = strip_shortcodes( $string );
		}

		return trim( $string );
	}

	/**
	 * Disables problematic shortcode before indexing to avoid problems.
	 *
	 * The disabled shortcodes are first removed with
	 * remove_shortcode() and then given a reference to `__return_empty_string`.
	 *
	 * @since 5.0
	 */
	public function disable_problematic_shortcodes() {
		$disable_shortcodes = apply_filters(
			'is_disable_shortcodes_array',
			array(
				'contact-form', // Jetpack Contact Form causes an error message.
				'starrater', // GD Star Rating rater shortcode causes problems.
				'responsive-flipbook', // Responsive Flipbook causes problems.
				'avatar_upload', // WP User Avatar is incompatible.
				'product_categories', // A problematic WooCommerce shortcode.
				'recent_products', // A problematic WooCommerce shortcode.
				'php', // PHP Code for Posts.
				'watupro', // Watu PRO doesn't co-operate.
				'starbox', // Starbox shortcode breaks search.
				'cfdb-save-form-post', // Contact Form DB.
				'cfdb-datatable',
				'cfdb-table',
				'cfdb-json',
				'cfdb-value',
				'cfdb-count',
				'cfdb-html',
				'woocommerce_cart', // WooCommerce.
				'woocommerce_checkout',
				'woocommerce_order_tracking',
				'woocommerce_my_account',
				'woocommerce_edit_account',
				'woocommerce_change_password',
				'woocommerce_view_order',
				'woocommerce_logout',
				'woocommerce_pay',
				'woocommerce_thankyou',
				'woocommerce_lost_password',
				'woocommerce_edit_address',
				'tc_process_payment',
				'maxmegamenu', // Max Mega Menu.
				'searchandfilter', // Search and Filter.
				'downloads', // Easy Digital Downloads.
				'download_history',
				'purchase_history',
				'download_checkout',
				'purchase_link',
				'download_cart',
				'edd_profile_editor',
				'edd_login',
				'edd_register',
				'swpm_protected', // Simple Membership Partially Protected content.
				'gravityform', // Gravity Forms.
				'sdm_latest_downloads', // SDM Simple Download Monitor.
				'slimstat', // Slimstat Analytics.
				'ninja_tables', // Ninja Tables.
			)
		);

		foreach ( $disable_shortcodes as $shortcode ) {
			if ( ! empty( $shortcode ) ) {
				remove_shortcode( trim( $shortcode ) );
				add_shortcode( trim( $shortcode ), '__return_empty_string' );
			}
		}
	}

	/**
	 * Removes page builder short codes from content.
	 *
	 * Page builder shortcodes cause problems in excerpts and add junk to posts in
	 * indexing. This function cleans them out.
	 *
	 * @since 5.0
	 * @param string $content The content to clean.
	 * @return string The content without page builder shortcodes.
	 */
	public function remove_page_builder_shortcodes( $content ) {
		$context = current_filter();
		/**
		 * Filters the page builder shortcode.
		 *
		 * @param array  An array of page builder shortcode regexes.
		 * @param string Context, ie. the current filter hook, if you want your
		 * changes to only count for indexing or for excerpts. 
		 */
		$search_array = apply_filters(
			'is_page_builder_shortcodes',
			array(
				// Remove content.
				'/\[et_pb_code.*?\].*\[\/et_pb_code\]/im',
				'/\[et_pb_sidebar.*?\].*\[\/et_pb_sidebar\]/im',
				'/\[et_pb_fullwidth_slider.*?\].*\[\/et_pb_fullwidth_slider\]/im',
				'/\[et_pb_fullwidth_code.*?\].*\[\/et_pb_fullwidth_code\]/im',
				'/\[vc_raw_html.*?\].*\[\/vc_raw_html\]/im',
				'/\[fusion_imageframe.*?\].*\[\/fusion_imageframe\]/im',
				'/\[fusion_code.*?\].*\[\/fusion_code\]/im',
				// Remove only the tags.
				'/\[\/?et_pb.*?\]/im',
				'/\[\/?vc.*?\]/im',
				'/\[\/?mk.*?\]/im',
				'/\[\/?cs_.*?\]/im',
				'/\[\/?av_.*?\]/im',
				'/\[\/?fusion_.*?\]/im',
				'/\[maxmegamenu.*?\]/im',
				'/\[ai1ec.*?\]/im',
				'/\[eme_.*?\]/im',
				'/\[layerslider.*?\]/im',
				// Divi garbage.
				'/@ET-DC@.*?@/im',
			),
			$context
		);
		$content      = preg_replace( $search_array, ' ', $content );
		return $content;
	}

	/**
	 * Handles phrases.
	 *
	 * Not used, for further versions.
	 *
	 * @since 5.0
	 * @param string $string The string to search phrases.
	 * @return array An array of phrases (strings).
	 */
	public function handle_phrases( $string ) {
		$string_for_phrases = is_array( $string ) ? implode( ' ', $string ) : $string;
		$phrases            = $this->extract_phrases( $string_for_phrases );
		$phrase_words       = array();
		foreach ( $phrases as $phrase ) {
			$phrase_words = array_merge( $phrase_words, explode( ' ', $phrase ) );
		}
		return $phrase_words;
	}

	/**
	 * Extract phrases from a string.
	 *
	 * Finds all phrases wrapped in quotes (curly or straight).
	 * Not used, for further versions.
	 *
	 * @since 5.0
	 * @param string $string The string to search phrases.
	 * @return array An array of phrases (strings).
	 */
	public function extract_phrases( $string ) {
		// replace curly quotes to straight
		$normalized_query = str_replace( array( '”', '“' ), '"', $string );
		$pos              = $this->stripos( $normalized_query, '"' );

		$phrases = array();
		while ( false !== $pos ) {
			if ( $pos + 2 > $this->strlen( $normalized_query ) ) {
				$pos = false;
				continue;
			}
			$start = $this->stripos( $normalized_query, '"', $pos );
			$end   = false;
			if ( false !== $start ) {
				$end = $this->stripos( $normalized_query, '"', $start + 2 );
			}
			if ( false === $end ) {
				// Just one " in the query.
				$pos = $end;
				continue;
			}
			$phrase = $this->substr(
				$normalized_query,
				$start + 1,
				$end - $start - 1
			);
			$phrase = trim( $phrase );

			// Do not count single-word phrases as phrases.
			if ( ! empty( $phrase ) && count( explode( ' ', $phrase ) ) > 1 ) {
				$phrases[] = $phrase;
			}
			$pos = $end + 1;
		}

		return $phrases;
	}

	/**
	 * Removes stopwords.
	 *
	 * @since 5.0
	 * @param string $token The string to compare for stopwords.
	 * @return string|null The passed token or empty if it is a stopword.
	 */
	public function remove_stopwords( $token ) {

		$stopwords = $this->get_stopwords();

		if ( in_array( $token, $stopwords ) ) {
			$token = '';
		}

		return $token;
	}

	/**
	 * Gets stopwords from settings.
	 *
	 * @since 5.0
	 * @param bool $reload The flag to reload stopwords.
	 * @return array An array of stopwords.
	 */
	public function get_stopwords( $reload = false ) {
		static $stopwords;

		if ( ! is_array( $stopwords ) || $reload ) {
			$stopwords = $this->index_opt->get_is_option( 'stopwords', array() );

			if ( ! empty( $stopwords ) ) {
				$stopwords = explode( ',', $stopwords );
				$stopwords = array_map( 'trim', $stopwords );
				$stopwords = array_map( array( 'self', 'strtolower' ), $stopwords );
			}
		}

		return $stopwords;
	}

	/**
	 * Removes punctuation from a string.
	 *
	 * This function removes some punctuation and replaces some punctuation with spaces.
	 * Uses the index options object.
	 *
	 * @since 5.0
	 * @param string $a The source string.
	 * @return string The string without punctuation.
	 */
	public function remove_punctuation( $a ) {
		if ( ! is_string( $a ) ) {
			// In case something sends a non-string here.
			return '';
		}

		$a = preg_replace( '/&lt;(\d|\s)/', '\1', $a );
		$a = html_entity_decode( $a, ENT_QUOTES );

		$hyphen_replacement = ' ';
		$endash_replacement = ' ';
		$emdash_replacement = ' ';
		if ( IS_Index_Options::PUNC_OPT_REMOVE == $this->index_opt->hyphens ) {
			$hyphen_replacement = '';
			$endash_replacement = '';
			$emdash_replacement = '';
		}
		if ( IS_Index_Options::PUNC_OPT_KEEP == $this->index_opt->hyphens ) {
			$hyphen_replacement = 'HYPHENTAIKASANA';
			$endash_replacement = 'ENDASHTAIKASANA';
			$emdash_replacement = 'EMDASHTAIKASANA';
		}

		$quote_replacement = ' ';
		if ( IS_Index_Options::PUNC_OPT_REMOVE == $this->index_opt->quotes ) {
			$quote_replacement = '';
		}

		$ampersand_replacement = ' ';
		if ( IS_Index_Options::PUNC_OPT_REMOVE == $this->index_opt->ampersands ) {
			$ampersand_replacement = '';
		}
		if ( IS_Index_Options::PUNC_OPT_KEEP == $this->index_opt->ampersands ) {
			$ampersand_replacement = 'AMPERSANDTAIKASANA';
		}

		$decimal_replacement = ' ';
		if ( IS_Index_Options::PUNC_OPT_REMOVE == $this->index_opt->decimals ) {
			$decimal_replacement = '';
		}
		if ( IS_Index_Options::PUNC_OPT_KEEP == $this->index_opt->decimals ) {
			$decimal_replacement = 'DESIMAALITAIKASANA';
		}

		$replacement_array = array(
			'ß'                     => 'ss',
			'ı'                     => 'i',
			'₂'                     => '2',
			'·'                     => '',
			'…'                     => '',
			'€'                     => '',
			'®'                     => '',
			'©'                     => '',
			'™'                     => '',
			'&shy;'                 => '',
			"\xC2\xAD"              => '',
			'&nbsp;'                => ' ',
			chr( 194 ) . chr( 160 ) => ' ', // now breaking space
			'×'                     => ' ',
			'&#8217;'               => $quote_replacement,
			"'"                     => $quote_replacement,
			'’'                     => $quote_replacement,
			'‘'                     => $quote_replacement,
			'”'                     => $quote_replacement,
			'“'                     => $quote_replacement,
			'„'                     => $quote_replacement,
			'´'                     => $quote_replacement,
			'″'                     => $quote_replacement,
			'-'                     => $hyphen_replacement,
			'–'                     => $endash_replacement,
			'—'                     => $emdash_replacement,
			'&#038;'                => $ampersand_replacement,
			'&amp;'                 => $ampersand_replacement,
			'&'                     => $ampersand_replacement,
		);

		$a = preg_replace( '/\.(\d)/', $decimal_replacement . '\1', $a );

		$a = str_replace( "\r", ' ', $a );
		$a = str_replace( "\n", ' ', $a );
		$a = str_replace( "\t", ' ', $a );

		$a = stripslashes( $a );

		$a = str_replace( array_keys( $replacement_array ), array_values( $replacement_array ), $a );

		$a = preg_replace( '/[[:punct:]]+/u', ' ', $a );
		$a = preg_replace( '/[[:space:]]+/', ' ', $a );

		$a = str_replace( 'AMPERSANDTAIKASANA', '&', $a );
		$a = str_replace( 'HYPHENTAIKASANA', '-', $a );
		$a = str_replace( 'ENDASHTAIKASANA', '–', $a );
		$a = str_replace( 'EMDASHTAIKASANA', '—', $a );
		$a = str_replace( 'DESIMAALITAIKASANA', '.', $a );

		$a = trim( $a );

		return $a;
	}


	/**
	 * Strips tags from contents, keeping the allowed tags.
	 *
	 * @uses strip_invisibles() Used to remove scripts and other tags.
	 * @see  strip_tags()                  Used to remove tags.
	 *
	 * @since 5.0
	 * @param string|null $content The content.
	 * @return string The content without tags.
	 */
	public function strip_tags( $content ) {
		if ( ! is_string( $content ) ) {
			$content = strval( $content );
		}
		$content = $this->strip_invisibles( $content );

		$space_tags = array(
			'/(<\/?p.*?>)/',
			'/(<\/?br.*?>)/',
			'/(<\/?h[1-6].*?>)/',
			'/(<\/?div.*?>)/',
			'/(<\/?blockquote.*?>)/',
			'/(<\/?hr.*?>)/',
			'/(<\/?li.*?>)/',
			'/(<img.*?>)/',
		);

		$content = preg_replace( $space_tags, '$1 ', $content );
		return strip_tags(
			$content,
			apply_filters( 'is_allowable_tags', '' )
		);
	}

	/**
	 * Strips invisible elements from text.
	 *
	 * Strips <style>, <script>, <object>, <embed>, <applet>, <noscript>, <noembed>,
	 * <iframe>, and <del> tags and their contents from the text.
	 *
	 * @since 5.0
	 * @param string $text The source text.
	 * @return string The processed text.
	 */
	public function strip_invisibles( $text ) {
		if ( ! is_string( $text ) ) {
			$text = strval( $text );
		}
		$text = preg_replace(
			array(
				'@<style[^>]*?>.*?</style>@siu',
				'@<script[^>]*?.*?</script>@siu',
				'@<object[^>]*?.*?</object>@siu',
				'@<embed[^>]*?.*?</embed>@siu',
				'@<applet[^>]*?.*?</applet>@siu',
				'@<noscript[^>]*?.*?</noscript>@siu',
				'@<noembed[^>]*?.*?</noembed>@siu',
				'@<iframe[^>]*?.*?</iframe>@siu',
				'@<del[^>]*?.*?</del>@siu',
			),
			' ',
			$text
		);
		return $text;
	}

	/**
	 * Strips all tags from content, keeping non-tags that look like tags.
	 *
	 * Strips content that matches <[!a-zA-Z\/]*> to remove HTML tags and HTML
	 * comments, but not things like "<30 grams, 4>1".
	 *
	 * @since 5.0
	 * @param string $content The content.
	 * @return string The content with tags stripped.
	 */
	public function strip_all_tags( $content ) {
		if ( ! is_string( $content ) ) {
			$content = '';
		}
		return preg_replace( '/<[!a-zA-Z\/][^>]*>/', ' ', $content );
	}

	/**
	 * Returns the position of substring in the string.
	 *
	 * Uses mb_stripos() if possible, falls back to mb_strpos() and mb_strtoupper()
	 * if that cannot be found, and falls back to just strpos() if even that is not
	 * possible.
	 *
	 * @since 5.0
	 * @param string $haystack String where to look.
	 * @param string $needle   The string to look for.
	 * @param int    $offset   Where to start, default 0.
	 * @return mixed False, if no result or $offset outside the length of $haystack,
	 * otherwise the position (which can be non-false 0!).
	 */
	public function stripos( $haystack, $needle, $offset = 0 ) {
		if ( ! is_string( $haystack ) ) {
			$haystack = strval( $haystack );
		}
		if ( ! is_string( $needle ) ) {
			$needle = strval( $needle );
		}
		if ( is_null( $offset ) ) {
			$offset = 0;
		}
		if ( $offset > $this->strlen( $haystack ) ) {
			return false;
		}

		if ( preg_match( '/[\?\*]/', $needle ) ) {
			// There's a ? or an * in the string, which means it's a wildcard search
			// query (a Premium feature) and requires some extra steps.
			$needle_regex = str_replace(
				array( '?', '*' ),
				array( '.', '.*' ),
				preg_quote( $needle, '/' )
			);
			$pos_found    = false;
			while ( ! $pos_found ) {
				preg_match(
					"/$needle_regex/i",
					$haystack,
					$matches,
					PREG_OFFSET_CAPTURE,
					$offset
				);
				/**
				 * This trickery is necessary, because PREG_OFFSET_CAPTURE gives
				 * wrong offsets for multibyte strings. The mb_strlen() gives the
				 * correct offset, the rest of this is because the $offset received
				 * as a parameter can be before the first $position, leading to an
				 * infinite loop.
				 */
				$pos = isset( $matches[0][1] )
					? mb_strlen( substr( $haystack, 0, $matches[0][1] ) )
					: false;
				if ( $pos && $pos > $offset ) {
					$pos_found = true;
				} elseif ( $pos ) {
					$offset++;
				} else {
					$pos_found = true;
				}
			}
		} elseif ( function_exists( 'mb_stripos' ) ) {
			if ( '' === $haystack ) {
				$pos = false;
			} else {
				$pos = mb_stripos( $haystack, $needle, $offset );
			}
		} elseif ( function_exists( 'mb_strpos' ) && function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' ) ) {
			$pos = mb_strpos(
				mb_strtoupper( $haystack ),
				mb_strtoupper( $needle ),
				$offset
			);
		} else {
			$pos = strpos( strtoupper( $haystack ), strtoupper( $needle ), $offset );
		}
		return $pos;
	}

	/**
	 * Returns the length of the string.
	 *
	 * Uses mb_strlen() if available, otherwise falls back to strlen().
	 *
	 * @since 5.0
	 * @param string $s The string to measure.
	 * @return int The length of the string.
	 */
	public function strlen( $s ) {
		if ( ! is_string( $s ) ) {
			$s = strval( $s );
		}
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $s );
		}
		return strlen( $s );
	}

	/**
	 * Multibyte friendly strtolower.
	 *
	 * If multibyte string functions are available, returns mb_strtolower() and
	 * falls back to strtolower() if multibyte functions are not available.
	 *
	 * @since 5.0
	 * @param string $string The string to lowercase.
	 * @return string $string The string in lowercase.
	 */
	public function strtolower( $string ) {
		if ( ! is_string( $string ) ) {
			$string = strval( $string );
		}
		if ( ! function_exists( 'mb_strtolower' ) ) {
			return strtolower( $string );
		} else {
			return mb_strtolower( $string );
		}
	}

	/**
	 * Multibyte friendly substr.
	 *
	 * If multibyte string functions are available, returns mb_substr() and falls
	 * back to substr() if multibyte functions are not available.
	 *
	 * @since 5.0
	 * @param string   $string The source string.
	 * @param int      $start  If start is non-negative, the returned string will
	 * start at the start'th position in str, counting from zero. If start is
	 * negative, the returned string will start at the start'th character from the
	 * end of string.
	 * @param int|null $length Maximum number of characters to use from string. If
	 * omitted or null is passed, extract all characters to the end of the string.
	 * @return string $string The string in lowercase.
	 */
	public function substr( $string, $start, $length = null ) {
		if ( ! is_string( $string ) ) {
			$string = strval( $string );
		}
		if ( ! function_exists( 'mb_substr' ) ) {
			return substr( $string, $start, $length );
		} else {
			return mb_substr( $string, $start, $length );
		}
	}

	/**
	 * Trims multibyte strings.
	 *
	 * Removes the 194+160 non-breakable spaces, removes null bytes and removes
	 * whitespace.
	 *
	 * @since 5.0
	 * @param string $string The source string.
	 * @return string Trimmed string.
	 */
	public function mb_trim( $string ) {
		$string = str_replace( chr( 194 ) . chr( 160 ), '', $string );
		$string = str_replace( "\0", '', $string );
		$string = preg_replace( '/(^\s+)|(\s+$)/us', '', $string );
		return $string;
	}

	/**
	 * Gets existing properties values.
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
				case 'index_opt':
					if ( is_array( $value ) ) {
						$this->$property = $value;
					}
					break;
			}
		}
	}
}
