<?php
/**
 * Inverted Index Match.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Index_Matches {

	/**
	 * Results for search.
	 *
	 * @since 5.0
	 * @var array
	 */
	protected $indexes;

	/**
	 * Results for search.
	 *
	 * @since 5.0
	 * @var array
	 */
	protected $matches;

	/**
	 * The IS search form.
	 *
	 * @since 5.0
	 * @var IS_Search_Form
	 */
	protected $search_form;

	public function __construct( $search_form ) {
		$this->indexes     = array();
		$this->matches     = array();
		$this->search_form = $search_form;
	}

	/**
	 * Add found matches.
	 *
	 * @since 5.0
	 * @param array $matches The index matches array.
	 */
	public function add_matches( $matches ) {

		$matches = $this->filter_matches( $matches );

		$this->indexes = array_merge( $this->indexes, $matches );

		foreach ( $matches as $term => $match ) {
			foreach ( $match as $post_id => $index ) {
				if ( empty( $this->matches[ $post_id ] ) ) {
					$this->matches[ $post_id ] = new IS_Index_Match( $post_id );
				}
				$match = $this->matches[ $post_id ];
				$match->add_match( $index );
			}
		}
	}

	/**
	 * Filter results based on search fields.
	 *
	 * @since 5.0
	 * @param array $all_matches All matches.
	 * @return array The filtered matches.
	 */
	protected function filter_matches( $all_matches ) {

		$search_form = $this->search_form;

		$search_fields = array(
			'title'        => $search_form->group_prop( '_is_includes', 'search_title' ),
			'content'      => $search_form->group_prop( '_is_includes', 'search_content' ),
			'excerpt'      => $search_form->group_prop( '_is_includes', 'search_excerpt' ),
			'tax_title'    => $search_form->group_prop( '_is_includes', 'search_tax_title' ),
			'tax_desp'     => $search_form->group_prop( '_is_includes', 'search_tax_desp' ),
			'author'       => $search_form->group_prop( '_is_includes', 'search_author' ),
			'comment'      => $search_form->group_prop( '_is_includes', 'search_comment' ),
			'custom_field' => $search_form->group_prop( '_is_includes', 'custom_field' ),
			'woo'          => $search_form->group_prop( '_is_includes', 'woo' ),
		);

		foreach ( $all_matches as $term => $matches ) {
			foreach ( $matches as $post_id => $match ) {
				$found = false;
				foreach ( $search_fields as $field => $enabled ) {
					if ( $enabled ) {
						switch ( $field ) {
							default:
								if ( property_exists( $match, $field ) && $match->$field > 0 ) {
									$found = true;
									break 2;
								}
								break;

							case 'tax_title':
							case 'tax_desp':
								$tax_query = $search_form->group_prop( '_is_includes', 'tax_query' );
								$tax_rel   = $search_form->group_prop( '_is_includes', 'tax_rel' );
								if ( property_exists( $match, 'taxonomy_detail' ) && is_array( $match->taxonomy_detail ) ) {
									if ( ! empty( $tax_query ) ) {
										$tax       = array_keys( $tax_query );
										$intersect = array_intersect( $tax, array_keys( $match->taxonomy_detail ) );
										if ( 'OR' == $tax_rel && ! empty( $intersect ) ) {
											$found = true;
										} elseif ( 'AND' == $tax_rel && count( $intersect ) == count( $tax ) ) {
											$found = true;
										}
									} elseif ( count( $match->taxonomy_detail ) > 0 ) {
										$found = true;
										break 2;
									}
								}
								break;

							case 'custom_field':
								$custom_fields = $enabled;
								$detail = @$match->customfield_detail;
								if ( isset( $detail ) && is_array( $detail ) ) {
									$m_fields = array_keys( $detail );
									if ( is_array( $custom_fields )
										&& array_intersect( $custom_fields, $m_fields ) ) {
										$found = true;
										break 2;
									}
								}
								break;

							case 'woo':
								$woo = $enabled;
								$detail = @$match->customfield_detail;
								if ( is_fs()->is_plan_or_trial__premium_only( 'pro_plus' )
									&& ! empty( $woo['sku'] )
									&& isset( $detail )
									&& is_array( $detail )
									&& array_key_exists( '_sku', $detail )
								) {
									$found = true;
									break 2;
								}
								break;
						}
					}
				}

				if ( ! $found ) {
					unset( $all_matches[ $term ][ $post_id ] );
				}
			}
		}

		return apply_filters( 'is_index_get_terms_counts', $all_matches );
	}

	/**
	 * Calculates matchs points for found terms.
	 *
	 * The rarer the term, higher the score.
	 *
	 * @since 5.0
	 * @param array $orig_search_terms The original search terms.
	 * @param int   $index_size The total number of rows in the index table.
	 * @return array The calculated match points in the form of term => points.
	 */
	public function calc_match_points( $orig_search_terms, $index_size ) {

		$match_points = array();
		// $index_size   = $this->get_index_size();

		foreach ( $this->indexes as $term => $matches ) {
			// Calc match score. The rarer the term, higher the score
			$match_pts = log( ( $index_size + 1 ) / ( 1 + count( $matches ) ) );

			if ( in_array( $term, $orig_search_terms ) ) {
				$match_pts *= $match_pts;
			}

			$match_pts             = $match_pts > 1 ? ceil( $match_pts ) : 1;
			$match_points[ $term ] = $match_pts;
		}

		return apply_filters(
			'is_index_calc_match_points',
			$match_points,
			$orig_search_terms
		);
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
