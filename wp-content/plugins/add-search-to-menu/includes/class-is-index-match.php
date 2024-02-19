<?php
/**
 * Inverted Index Match.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Index_Match {

	/**
	 * The post ID where the term is found.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $post_id;

	/**
	 * The matched terms scores summed.
	 *
	 * @since 5.0
	 * @var int
	 */
	protected $total_score;

	/**
	 * The terms and respective scores.
	 *
	 * @since 5.0
	 * @var array
	 */
	protected $terms_scores;

	/**
	 * Creates a new instance.
	 *
	 * @since 5.0
	 * @param int $post_id The post id used to group results.
	 * @return self IS_Index_Match
	 */
	public function __construct( $post_id ) {
		$this->post_id      = intval( $post_id );
		$this->terms_scores = array();
		$this->total_score  = 0;
	}

	/**
	 * Adds a match row.
	 * Adds the score to the total.
	 *
	 * @since 5.0
	 * @param Object $row The index row from DB.
	 */
	public function add_match( $row ) {

		$key = $row->term;
		if ( is_numeric( $key ) ) {
			$key = " $key";
		}

		if ( intval( $row->post_id ) == $this->post_id
			&& ! isset( $this->terms_scores[ $key ] )
		) {
			$score                      = intval( $row->score );
			$this->terms_scores[ $key ] = $score;
			$this->total_score         += $score;
		}
	}

	/**
	 * Get term score.
	 *
	 * @since 5.0
	 * @param string $term The term to get score for.
	 * @return int The term score.
	 */
	public function get_score( $term ) {
		$score = 0;

		if ( is_array( $this->terms_scores )
			&& ! empty( $this->terms_scores[ $term ] ) ) {
			$score = $this->terms_scores[ $term ];
		}

		return $score;
	}

	/**
	 * Verifies if a term was found.
	 *
	 * @since 5.0
	 * @param string $term The term to verify.
	 * @param string $fuzzy The fuzzy match option to compare.
	 * @return bool True if found.
	 */
	public function has_term( $term, $fuzzy = 2 ) {
		$found = false;

		$terms = $this->get_terms();

		switch ( $fuzzy ) {
			case 1: // whole
				if ( is_array( $terms )
					&& in_array( $term, $terms ) ) {
					$found = true;
				}
				break;
			default:
			case 2: // partial
			case 3:// anyhwere
				if ( is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						$haystack = ' ' . $t; // fix to match numbers
						$needle   = strval( $term );
						$pos      = stripos( $haystack, $needle );
						if ( $t == $term || $pos !== false ) {
							$found = true;
							break;
						}
					}
				}
				break;
		}

		return $found;
	}

	/**
	 * Verifies any of the synonyms is present in the terms found.
	 *
	 * @since 5.0
	 * @param string $term The term to verify.
	 * @return bool True if found.
	 */
	public function has_synonyms( $synonyms ) {
		$found = false;

		$terms = $this->get_terms();
		if ( is_array( $synonyms ) && ! empty( $synonyms ) ) {
			$found = count( array_intersect( $synonyms, $terms ) );
		}

		return $found;
	}

	/**
	 * Get terms array.
	 *
	 * @since 5.0
	 * @return array <string, string> The object properties and values.
	 */
	public function get_terms() {
		$terms = array();
		if ( is_array( $this->terms_scores ) ) {
			$terms = array_keys( $this->terms_scores );
		}
		return $terms;
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
				case 'total_score':
					$this->$property = intval( $value );
					break;

				case 'terms_scores':
					if ( is_array( $value ) ) {
						$this->$property = array_map( 'sanitize_text_field', $value );
					}
					break;
			}
		}
	}
}
