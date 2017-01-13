<?php
/**
 * CSL Feed Functions
 *
 * Creates an options page where users can set the interval of imports and the authors to
 * import the posts as.
 *
 * @package  CSL_Feed_Importer
 */

namespace Lift\Campus_Insiders\CSL_Feed_Importer;

/**
 * Override Rel Canonical on CSL Posts
 *
 * @param  string   $canonical_url The original canonical url
 * @param  \WP_Post $post          A WP_Post object
 * @return string                  Filtered canonical url
 */
function csl_posts_rel_canonical( $canonical_url, \WP_Post $post ) {
	if ( ! has_term( 'collegiate-starleague', 'scci_conference', $post ) ) {
		return $canonical_url;
	}

	$canonical = get_the_guid( absint( $post_id ) );
	if ( $canonical && ( false !== strpos( $canonical, 'cstarleague.com' ) ) ) {
		$canonical_url = $canonical;
	}

	return $canonical_url;
}
add_filter( 'get_canonical_url', __NAMESPACE__ . '\\csl_posts_rel_canonical', 10, 2 );
