<?php
/**
 * Adds custom rewrite rules and related functionality.  This file houses the functions for getting the 
 * correct slugs for various pages of the board.
 *
 * @package    MessageBoard
 * @subpackage Includes
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2014, Justin Tadlock
 * @link       https://github.com/justintadlock/message-board
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/* Add custom rewrite rules. */
add_action( 'init', 'mb_rewrite_rules', 5 );

/* Cancel redirect on paged single forums and topics. */
add_filter( 'redirect_canonical', 'mb_redirect_canonical', 10, 2 );

/* Custom query vars. */
add_filter( 'query_vars', 'mb_query_vars' );

/**
 * Returns the board root/index slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_root_slug() {
	return apply_filters( 'mb_root_slug', 'board' );
}

/**
 * Returns the board root/index slug or an empty string, depending on whether we need to use the root slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_maybe_get_root_slug() {
	return true == apply_filters( 'mb_maybe_get_root_slug', true ) ? trailingslashit( mb_get_root_slug() ) : '';
}

/**
 * Returns the forums slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_forum_slug() {
	return apply_filters( 'mb_forum_slug', mb_maybe_get_root_slug() . 'forums' );
}

/**
 * Returns the topics slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_topic_slug() {
	return apply_filters( 'mb_topic_slug', mb_maybe_get_root_slug() . 'topics' );
}

/**
 * Returns the reply slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_reply_slug() {
	return apply_filters( 'mb_get_reply_slug', mb_maybe_get_root_slug() . 'replies' );
}

/**
 * Returns the users slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_user_slug() {
	return apply_filters( 'mb_get_user_slug', mb_maybe_get_root_slug() . 'users' );
}

/**
 * Returns the login slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_login_slug() {
	return apply_filters( 'mb_get_login_slug', mb_maybe_get_root_slug() . 'login' );
}

/**
 * Returns the edit slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_edit_slug() {
	return apply_filters( 'mb_get_edit_slug', mb_maybe_get_root_slug() . 'edit' );
}

/**
 * Returns the search slug.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_search_slug() {
	return apply_filters( 'mb_get_search_slug', mb_maybe_get_root_slug() . 'search' );
}

/**
 * Adds custom query vars.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function mb_query_vars( $vars ) {

	$vars[] = 'mb_custom';
	$vars[] = 'mb_action';
	$vars[] = 'mb_role';
	$vars[] = 'forum_id';
	$vars[] = 'topic_id';
	$vars[] = 'reply_id';
	$vars[] = 'user_id';

	return $vars;
}

/**
 * Sets up custom rewrite rules for pages that aren't handled by the CPT and CT APIs but are needed by 
 * the plugin.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_rewrite_rules() {

	$topic_type = mb_get_topic_post_type();

	/* Overwrite the topic rewrite rules. */
	add_filter( "{$topic_type}_rewrite_rules", 'mb_forum_topic_rewrite_rules' );

	/* Get slugs. */
	$user_slug  = mb_get_user_slug();
	$login_slug = mb_get_login_slug();
	$edit_slug  = mb_get_edit_slug();

	/* Get query vars. */
	$user_page_qv = 'mb_user_page';

	/* Add rewrite tag for single user pages. */
	add_rewrite_tag( '%' . $user_page_qv . '%', '([^/]+)' );

	/* User archive rewrite rules. */
	add_rewrite_rule( $user_slug . '/?$',  'index.php?mb_custom=users', 'top' );
	add_rewrite_rule( $user_slug . '/page/?([0-9]{1,})/?$', 'index.php?mb_custom=users&paged=$matches[1]', 'top' );

	/* User role archive rewrite rules. */
	add_rewrite_rule( $user_slug . '/roles/([^/]+)/page/?([0-9]{1,})/?$', 'index.php?mb_custom=users&mb_role=$matches[1]&paged=$matches[2]', 'top' );
	add_rewrite_rule( $user_slug . '/roles/([^/]+)/?$',                   'index.php?mb_custom=users&mb_role=$matches[1]',                   'top' );
	add_rewrite_rule( $user_slug . '/roles/page/?([0-9]{1,})/?$',                   'index.php?mb_custom=roles&paged=$matches[1]',                   'top' );
	add_rewrite_rule( $user_slug . '/roles/?$',                   'index.php?mb_custom=roles',                   'top' );

	$user_pages = 'forums|topics|replies|bookmarks|topic-subscriptions|forum-subscriptions';

	/* Single user rewrite rules. */
	add_rewrite_rule( $user_slug . '/([^/]+)/(' . $user_pages . ')/page/?([0-9]{1,})/?$', 'index.php?mb_custom=users&author_name=$matches[1]&' . $user_page_qv . '=$matches[2]&paged=$matches[3]', 'top' );
	add_rewrite_rule( $user_slug . '/([^/]+)/(' . $user_pages . ')/?$',                   'index.php?mb_custom=users&author_name=$matches[1]&' . $user_page_qv . '=$matches[2]',                   'top' );
	add_rewrite_rule( $user_slug . '/([^/]+)/?$',                                         'index.php?mb_custom=users&author_name=$matches[1]',                                                     'top' );

	/* Login page. */
	add_rewrite_rule( $login_slug . '/?$', 'index.php?mb_custom=login', 'top' );

	$search_slug = trailingslashit( mb_get_root_slug() ) . 'search';

	add_rewrite_rule( $search_slug . '/?$', 'index.php?mb_custom=search', 'top' );

	/* Edit page. */
	//add_rewrite_rule( $edit_slug . '/?$', 'index.php?mb_custom=edit', 'top' );
}

/**
 * Overwrites the rewrite rules for the `topic` post type.  In particular, we need to handle the 
 * pagination on singular topics because the `reply` post type is paginated on this page.
 *
 * @todo See if this can be simplified where we're only taking care of the things we need.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $rules
 * @return array
 */
function mb_forum_topic_rewrite_rules( $rules ) {

	$topic_slug = mb_get_topic_slug();
	$topic_type = mb_get_topic_post_type();

	$rules = array(
		$topic_slug . '/[^/]+/attachment/([^/]+)/?$'                               => 'index.php?attachment=$matches[1]',
		$topic_slug . '/[^/]+/attachment/([^/]+)/trackback/?$'                     => 'index.php?attachment=$matches[1]&tb=1',
		$topic_slug . '/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
		$topic_slug . '/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?attachment=$matches[1]&feed=$matches[2]',
		$topic_slug . '/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$'      => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
		$topic_slug . '/([^/]+)/trackback/?$'                                      => 'index.php?' . $topic_type . '=$matches[1]&tb=1',
		$topic_slug . '/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$'                  => 'index.php?' . $topic_type . '=$matches[1]&feed=$matches[2]',
		$topic_slug . '/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'                       => 'index.php?' . $topic_type . '=$matches[1]&feed=$matches[2]',
		$topic_slug . '/page/?([0-9]{1,})/?$'                                      => 'index.php?post_type=' . $topic_type . '&paged=$matches[1]',
		$topic_slug . '/([^/]+)/page/([0-9]{1,})/?$'                               => 'index.php?' . $topic_type . '=$matches[1]&paged=$matches[2]',
		$topic_slug . '/([^/]+)(/[0-9]+)?/?$'                                      => 'index.php?' . $topic_type . '=$matches[1]&page=$matches[2]',
		$topic_slug . '/([^/]+)/edit/([0-9]+)?/?$'                                 => 'index.php?' . $topic_type . '=$matches[1]&edit=$matches[2]',
		$topic_slug . '/[^/]+/([^/]+)/?$'                                          => 'index.php?attachment=$matches[1]',
		$topic_slug . '/[^/]+/([^/]+)/trackback/?$'                                => 'index.php?attachment=$matches[1]&tb=1',
		$topic_slug . '/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$'            => 'index.php?attachment=$matches[1]&feed=$matches[2]',
		$topic_slug . '/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'                 => 'index.php?attachment=$matches[1]&feed=$matches[2]',
		$topic_slug . '/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$'                 => 'index.php?attachment=$matches[1]&cpage=$matches[2]'
	);

	return $rules;
}

/**
 * Makes sure any paged redirects are corrected.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $redirect_url
 * @param  string  $requested_url
 * @return string
 */
function mb_redirect_canonical( $redirect_url, $requested_url ) {

	$topic_slug = mb_get_topic_slug();
	$forum_slug = mb_get_forum_slug();

	if ( preg_match( "#{$topic_slug}|{$forum_slug}/([^/]+)|(.+?)/page/([0-9]{1,})/?$#i", $requested_url ) )
		return false;

	return $redirect_url;
}
