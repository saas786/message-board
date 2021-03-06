<?php
/**
 * Topic template functions for theme authors.
 *
 * @package    MessageBoard
 * @subpackage Includes
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2014, Justin Tadlock
 * @link       https://github.com/justintadlock/message-board
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Creates a new topic query and checks if there are any topics found.  Note that we ue the main 
 * WordPress query if viewing the topic archive or a single topic.  This function is a wrapper 
 * function for the standard WP `have_posts()`, but this function should be used instead because 
 * it must also create a query of its own under some circumstances.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_topic_query() {
	$mb = message_board();

	/* If a query has already been created, let's roll. */
	if ( !is_null( $mb->topic_query->query ) ) {

		$have_posts = $mb->topic_query->have_posts();

		if ( empty( $have_posts ) )
			wp_reset_postdata();

		return $have_posts;
	}

	/* Use the main WP query when viewing a single topic or topic archive. */
	if ( mb_is_single_topic() || mb_is_topic_archive() || mb_is_user_page( array( 'topics', 'topic-subscriptions', 'bookmarks' ) ) ) {
		global $wp_the_query;
		
		$mb->topic_query = $wp_the_query;
	}

	/* Create a new query if all else fails. */
	else {

		$statuses = array( mb_get_open_post_status(), mb_get_close_post_status(), mb_get_publish_post_status(), mb_get_private_post_status() );

		if ( current_user_can( 'read_hidden_topics' ) )
			$statuses[] = mb_get_hidden_post_status();

		$per_page = mb_get_topics_per_page();

		$defaults = array(
			'post_type'           => mb_get_topic_post_type(),
			'post_status'         => $statuses,
			'posts_per_page'      => $per_page,
			'paged'               => get_query_var( 'paged' ),
			'orderby'             => 'menu_order',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
		);

		if ( mb_is_single_forum() ) {
			$defaults['post_parent'] = get_queried_object_id();
			add_filter( 'the_posts', 'mb_posts_sticky_filter', 10, 2 );
			add_filter( 'the_posts', 'mb_posts_super_filter', 10, 2 );
		}

		$mb->topic_query = new WP_Query( $defaults );
	}

	return $mb->topic_query->have_posts();
}

/**
 * Sets up the topic data for the current topic in The Loop.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_the_topic() {
	return message_board()->topic_query->the_post();
}

/* ====== Topic ID ====== */

/**
 * Displays the topic ID.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_id( $topic_id = 0 ) {
	echo mb_get_topic_id( $topic_id );
}

/**
 * Returns the topic ID.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return int
 */
function mb_get_topic_id( $topic_id = 0 ) {
	$mb = message_board();

	if ( is_numeric( $topic_id ) && 0 < $topic_id )
		$_topic_id = $topic_id;

	elseif ( $mb->topic_query->in_the_loop && isset( $mb->topic_query->post->ID ) )
		$_topic_id = $mb->topic_query->post->ID;

	elseif ( $mb->search_query->in_the_loop && isset( $mb->search_query->post->ID ) && mb_is_topic( $mb->search_query->post->ID ) )
		$_topic_id = $mb->search_query->post->ID;

	elseif ( mb_is_single_topic() )
		$_topic_id = get_queried_object_id();

	elseif ( get_query_var( 'topic_id' ) )
		$_topic_id = get_query_var( 'topic_id' );

	else
		$_topic_id = 0;

	return apply_filters( 'mb_get_topic_id', absint( $_topic_id ), $topic_id );
}

/* ====== Conditionals ====== */

/**
 * Checks if the post is a topic.  This is a wrapper for `get_post_type()`.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return bool
 */
function mb_is_topic( $post_id = 0 ) {
	$post_id  = mb_get_topic_id( $post_id );
	$is_topic = mb_get_topic_post_type() === get_post_type( $post_id ) ? true : false;

	return apply_filters( 'mb_is_topic', $is_topic, $post_id );
}

function mb_is_single_topic( $topic = '' ) {

	if ( !is_singular( mb_get_topic_post_type() ) )
		return false;

	if ( !empty( $topic ) )
		return is_single( $topic );

	return true;
}

function mb_is_topic_archive() {
	return get_query_var( 'mb_custom' ) ? false : is_post_type_archive( mb_get_topic_post_type() );
}

/**
 * Conditional check to see if a topic allows new replies to be created.
 *
 * @since  1.0.0
 * @access public
 * @param  int    $topic
 * @return bool
 */
function mb_topic_allows_replies( $topic_id = 0 ) {
	$topic_id = mb_get_forum_id( $topic_id );
	$forum_id = mb_get_topic_forum_id( $topic_id );
	$allow    = true;

	/* Check if the topic type allows replies. */
	if ( !mb_topic_type_allows_replies( mb_get_topic_type( $topic_id ) ) )
		$allow = false;

	/* Check if the topic status allows replies. */
	elseif ( !mb_topic_status_allows_replies( mb_get_topic_status( $topic_id ) ) )
		$allow = false;

	/* If there's a parent forum, check if it allows topics (no topics == no replies). */
	elseif ( 0 < $forum_id && !mb_forum_allows_topics( $forum_id ) )
		$allow = false;

	return apply_filters( 'mb_topic_allows_replies', $allow, $topic_id );
}

/* ====== Lead Topic ====== */

/**
 * Whether to show the topic when viewing a single topic page.  By default, the topic is shown 
 * on page #1, but it's not shown on subsequent pages if the topic is paginated.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_show_lead_topic() {
	return apply_filters( 'mb_show_lead_topic', mb_is_topic_paged() ? false : true );
}

/* ====== Topic Edit ====== */

function mb_topic_edit_url( $topic_id = 0 ) {
	echo mb_get_topic_edit_url( $topic_id );
}

function mb_get_topic_edit_url( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	return apply_filters( 'mb_get_topic_edit_url', get_edit_post_link( $topic_id ), $topic_id );
}

function mb_topic_edit_link( $topic_id = 0 ) {
	echo mb_get_topic_edit_link( $topic_id );
}

function mb_get_topic_edit_link( $topic_id = 0 ) {

	$link = '';
	$url = mb_get_topic_edit_url( $topic_id );

	if ( !empty( $url ) )
		$link = sprintf( '<a href="%s" class="topic-edit-link edit-link">%s</a>', $url, __( 'Edit', 'message-board' ) );

	return apply_filters( 'mb_get_topic_edit_link', $link );
}

/* ====== Topic Status ====== */

function mb_topic_status( $topic_id = 0 ) {
	echo mb_get_topic_status( $topic_id );
}

function mb_get_topic_status( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = $topic_id ? get_post_status( $topic_id ) : '';

	return apply_filters( 'mb_get_topic_status', $status, $topic_id );
}

/**
 * Whether the topic's post status is a "public" post status.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return bool
 */
function mb_is_topic_public( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = get_post_status_object( mb_get_topic_status( $topic_id ) );

	return apply_filters( 'mb_is_topic_public', (bool) $status->public, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "open" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_open( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_open', mb_get_open_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "close" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_closed( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_closed', mb_get_close_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "private" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_private( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_private', mb_get_private_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "hidden" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_hidden( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_hidden', mb_get_hidden_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "spam" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_spam( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_spam', mb_get_spam_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "trash" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_trash( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_trash', mb_get_trash_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see whether a topic has the "orphan" post status.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_orphan( $topic_id = 0 ) {
	$reply_id = mb_get_topic_id( $topic_id );
	$status   = mb_get_topic_status( $topic_id );

	return apply_filters( 'mb_is_topic_orphan', mb_get_orphan_post_status() === $status ? true : false, $topic_id );
}

/**
 * Conditional check to see if a topic status allows new replies to be created.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $status
 * @return bool
 */
function mb_topic_status_allows_replies( $status ) {

	$statuses = array( mb_get_open_post_status(), mb_get_private_post_status(), mb_get_hidden_post_status() );
	$allowed  = in_array( $status, $statuses ) ? true : false;

	return apply_filters( 'mb_topic_status_allows_replies', $allowed, $status );
}

function mb_topic_toggle_open_url( $topic_id = 0 ) {
	echo mb_get_topic_toggle_open_open_url( $topic_id = 0 );
}

function mb_get_topic_toggle_open_url( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	if ( mb_is_topic_open( $topic_id ) || !current_user_can( 'open_topic', $topic_id ) )
		return '';

	$url = add_query_arg( array( 'topic_id' => $topic_id, 'action' => 'mb_toggle_open' ) );
	$url = wp_nonce_url( $url, "open_topic_{$topic_id}", 'mb_nonce' );

	return $url;
}

function mb_topic_toggle_open_link( $topic_id = 0 ) {
	echo mb_get_topic_toggle_open_link( $topic_id );
}

function mb_get_topic_toggle_open_link( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	$url = mb_get_topic_toggle_open_url( $topic_id );

	if ( empty( $url ) )
		return '';

	$status = get_post_status_object( mb_get_open_post_status() );

	$link = sprintf( '<a class="mb-topic-open-link" href="%s">%s</a>', $url, $status->mb_label_verb );

	return $link;
}

function mb_topic_toggle_close_url( $topic_id = 0 ) {
	echo mb_get_topic_toggle_close_url( $topic_id = 0 );
}

function mb_get_topic_toggle_close_url( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	if ( mb_is_topic_closed( $topic_id ) || !current_user_can( 'close_topic', $topic_id ) )
		return '';

	$url = add_query_arg( array( 'topic_id' => $topic_id, 'action' => 'mb_toggle_close' ) );
	$url = wp_nonce_url( $url, "close_topic_{$topic_id}", 'mb_nonce' );

	return $url;
}

function mb_topic_toggle_close_link( $topic_id = 0 ) {
	echo mb_get_topic_toggle_close_link( $topic_id );
}

function mb_get_topic_toggle_close_link( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	$url = mb_get_topic_toggle_close_url( $topic_id );

	if ( empty( $url ) )
		return '';

	$status = get_post_status_object( mb_get_close_post_status() );

	$link = sprintf( '<a class="mb-topic-close-link" href="%s">%s</a>', $url, $status->mb_label_verb );

	return $link;
}

function mb_topic_toggle_spam_url( $topic_id = 0 ) {
	echo mb_get_topic_toggle_spam_url( $topic_id = 0 );
}

function mb_get_topic_toggle_spam_url( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	$url = add_query_arg( array( 'topic_id' => $topic_id, 'action' => 'mb_toggle_spam' ) );
	$url = wp_nonce_url( $url, "spam_topic_{$topic_id}", 'mb_nonce' );

	return $url;
}

function mb_topic_toggle_spam_link( $topic_id = 0 ) {
	echo mb_get_topic_toggle_spam_link( $topic_id );
}

function mb_get_topic_toggle_spam_link( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	if ( !current_user_can( 'spam_topic', $topic_id ) )
		return '';

	$text = mb_is_topic_spam( $topic_id ) ? __( 'Unspam', 'message-board' ) : get_post_status_object( mb_get_spam_post_status() )->mb_label_verb;

	$link = sprintf( '<a class="toggle-spam-link" href="%s">%s</a>', mb_get_topic_toggle_spam_url( $topic_id ), $text );

	return $link;
}

function mb_topic_toggle_trash_url( $topic_id = 0 ) {
	echo mb_get_topic_toggle_trash_url( $topic_id = 0 );
}

function mb_get_topic_toggle_trash_url( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	$url = add_query_arg( array( 'topic_id' => $topic_id, 'action' => 'mb_toggle_trash' ) );
	$url = wp_nonce_url( $url, "trash_topic_{$topic_id}", 'mb_nonce' );

	return $url;
}

function mb_topic_toggle_trash_link( $topic_id = 0 ) {
	echo mb_get_topic_toggle_trash_link( $topic_id );
}

function mb_get_topic_toggle_trash_link( $topic_id = 0 ) {

	$topic_id = mb_get_topic_id( $topic_id );

	if ( !current_user_can( 'delete_topic', $topic_id ) )
		return '';

	$topic_id = mb_get_topic_id( $topic_id );

	$text = mb_is_topic_trash( $topic_id ) ? __( 'Restore', 'message-board' ) : get_post_status_object( mb_get_trash_post_status() )->label;

	$link = sprintf( '<a class="toggle-trash-link" href="%s">%s</a>', mb_get_topic_toggle_trash_url( $topic_id ), $text );

	return $link;
}

/* ====== Topic Labels ====== */

function mb_topic_label( $label ) {
	echo mb_get_topic_label( $label );
}

function mb_get_topic_label( $label ) {
	$labels = get_post_type_object( mb_get_topic_post_type() )->labels;

	return $labels->$label;
}

/**
 * Outputs a topics labels.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_topic_states( $topic_id = 0 ) {
	echo mb_get_topic_states( $topic_id );
}

/**
 * Returns a topic's labels.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_topic_states( $topic_id = 0 ) {
	$topic_id       = mb_get_topic_id( $topic_id );
	$labels = array();

	if ( mb_is_topic_super( $topic_id ) && ( mb_is_topic_archive() || mb_is_single_forum() ) )
		$labels['super'] = __( '[Sticky]', 'message-board' );

	elseif ( mb_is_topic_sticky( $topic_id ) && mb_is_single_forum() )
		$labels['sticky'] = __( '[Sticky]', 'message-board' );

	if ( mb_is_topic_closed( $topic_id ) )
		$labels['closed'] = __( '[Closed]', 'message-board' );

	$labels = apply_filters( 'mb_topic_labels', $labels, $topic_id );

	if ( !empty( $labels ) ) {

		$formatted = '';

		foreach ( $labels as $key => $value )
			$formatted .= sprintf( '<span class="topic-label %s">%s</span> ', sanitize_html_class( "topic-label-{$key}" ), $value );

		return sprintf( '<span class="topic-labels">%s</span>', $formatted );
	}

	return '';
}

/* ====== Topic Content ====== */

/**
 * Displays the topic content.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_content( $topic_id = 0 ) {
	echo mb_get_topic_content( $topic_id );
}

/**
 * Returns the topic content.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_content( $topic_id = 0, $mode = 'display' ) {
	$topic_id = mb_get_topic_id( $topic_id );

	$content = $topic_id ? get_post_field( 'post_content', $topic_id, 'raw' ) : '';

	if ( 'raw' === $mode )
		return $content;
	else
		return apply_filters( 'mb_get_topic_content', $content, $topic_id );
}

/* ====== Topic Title ====== */

/**
 * Displays the single topic title.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_single_topic_title() {
	echo mb_get_single_topic_title();
}

function mb_get_single_topic_title() {
	return apply_filters( 'mb_get_single_topic_title', single_post_title( '', false ) );
}

function mb_topic_archive_title() {
	echo mb_get_topic_archive_title();
}

function mb_get_topic_archive_title() {
	return apply_filters( 'mb_get_topic_archive_title', post_type_archive_title( '', false ) );
}

/**
 * Displays the topic title.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_title( $topic_id = 0 ) {
	echo mb_get_topic_title( $topic_id );
}

/**
 * Returns the topic title.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_title( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$title    = $topic_id ? get_post_field( 'post_title', $topic_id ) : '';

	return apply_filters( 'mb_get_topic_title', $title, $topic_id );
}

/* ====== Topic URL ====== */

/**
 * Displays the topic URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_url( $topic_id = 0 ) {
	echo mb_get_topic_url( $topic_id );
}

/**
 * Returns the topic URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_url( $topic_id = 0 ) {
	$topic_id  = mb_get_topic_id( $topic_id );
	$topic_url = $topic_id ? get_permalink( $topic_id ) : '';

	return apply_filters( 'mb_get_topic_url', $topic_url, $topic_id );
}

/**
 * Displays the topic link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_link( $topic_id = 0 ) {
	echo mb_get_topic_link( $topic_id );
}

/**
 * Returns the topic link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_link( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$url      = mb_get_topic_url( $topic_id );
	$title    = mb_get_topic_title( $topic_id );
	$link     = $url ? sprintf( '<a class="mb-topic-link" href="%s">%s</a>', $url, $title ) : '';

	if ( !$link && $title )
		$link = sprintf( '<span class="mb-topic-link">%s</span>', $title );

	return apply_filters( 'mb_get_topic_link', $link, $topic_id );
}

/**
 * Displays the topic date.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id,
 * @param  string  $format
 * @return void
 */
function mb_topic_date( $topic_id = 0, $format = '' ) {
	echo mb_get_topic_date( $topic_id, $format );
}

/**
 * Returns the topic date.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id,
 * @param  string  $format
 * @return void
 */
function mb_get_topic_date( $topic_id = 0, $format = '' ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$format   = !empty( $format ) ? $format : get_option( 'date_format' );

	return get_post_time( $format, false, $topic_id, true );
}

/**
 * Displays the topic time.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id,
 * @param  string  $format
 * @return void
 */
function mb_topic_time( $topic_id = 0, $format = '' ) {
	echo mb_get_topic_time( $topic_id, $format );
}

/**
 * Returns the topic time.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id,
 * @param  string  $format
 * @return void
 */
function mb_get_topic_time( $topic_id = 0, $format = '' ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$format   = !empty( $format ) ? $format : get_option( 'time_format' );

	return get_post_time( $format, false, $topic_id, true );
}

/**
 * Outputs the topic natural time (e.g., 1 month ago, 5 minutes ago, etc.)
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_natural_time( $topic_id = 0 ) {
	echo mb_get_topic_natural_time( $topic_id );
}

/**
 * Outputs the topic natural time (e.g., 1 month ago, 5 minutes ago, etc.)
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_natural_time( $topic_id = 0 ) {
	$topic_id   = mb_get_topic_id( $topic_id );
	$topic_time = $topic_id ? mb_natural_time( get_post_time( 'U', false, $topic_id, true ) ) : '';

	return apply_filters( 'mb_get_topic_natural_time', $topic_time, $topic_id );
}

/* ====== Topic Author ====== */

/**
 * Displays the topic author ID.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_author_id( $topic_id = 0 ) {
	echo mb_get_topic_author_id( $topic_id );
}

/**
 * Returns the topic autor ID.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return int
 */
function mb_get_topic_author_id( $topic_id = 0 ) {
	$topic_id  = mb_get_topic_id( $topic_id );
	$author_id = get_post_field( 'post_author', $topic_id );

	return apply_filters( 'mb_get_topic_author_id', absint( $author_id ), $topic_id );
}

/**
 * Displays the topic author.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_author( $topic_id = 0 ) {
	echo mb_get_topic_author( $topic_id );
}

/**
 * Returns the topic author.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_author( $topic_id = 0 ) {
	return apply_filters( 'mb_get_topic_author', mb_get_post_author( $topic_id ), $topic_id );
}

/**
 * Displays the topic author profile URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_author_profile_url( $topic_id = 0 ) {
	echo mb_get_topic_author_profile_url( $topic_id );
}

/**
 * Returns the topic author profile URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_author_profile_url( $topic_id = 0 ) {
	return apply_filters( 'mb_get_topic_author_profile_url', mb_get_post_author_profile_url( $topic_id ), $topic_id );
}

/**
 * Displays the topic author profile link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_author_profile_link( $topic_id = 0 ) {
	echo mb_get_topic_author_profile_link( $topic_id );
}

/**
 * Returns the topic author profile link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_author_profile_link( $topic_id = 0 ) {
	return apply_filters( 'mb_get_topic_author_profile_link', mb_get_post_author_profile_link( $topic_id ), $topic_id );
}

/* ====== Topic Forum ====== */

function mb_get_topic_forum_id( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$forum_id = $topic_id ? get_post( $topic_id )->post_parent : 0;

	return apply_filters( 'mb_get_topic_forum_id', $forum_id, $topic_id );
}

function mb_topic_forum_link( $topic_id = 0 ) {
	echo mb_get_topic_forum_link( $topic_id );
}

function mb_get_topic_forum_link( $topic_id = 0 ) {
	$forum_id   = mb_get_topic_forum_id( $topic_id );
	$forum_link = mb_get_forum_link( $forum_id );

	return apply_filters( 'mb_get_topic_forum_link', $forum_link, $forum_id, $topic_id );
}

/* ====== Last Activity ====== */

/**
 * Prints the topic last activity time.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_topic_last_active_time( $topic_id = 0 ) {
	echo mb_get_topic_last_active_time( $topic_id );
}

/**
 * Returns the topic last activity time.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_topic_last_active_time( $topic_id = 0 ) {

	$topic_id     = mb_get_topic_id( $topic_id );
	$time         = get_post_meta( $topic_id, mb_get_topic_activity_datetime_meta_key(), true );
	$natural_time = $topic_id ? mb_natural_time( mysql2date( 'U', $time ) ) : '';

	return apply_filters( 'mb_get_topic_last_active_time', $natural_time, $time, $topic_id );
}

/* ====== Last Reply ID ====== */

function mb_topic_last_reply_id( $topic_id = 0 ) {
	echo mb_get_topic_last_reply_id( $topic_id );
}

/**
 * Returns the last topic reply ID.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @retrn  int
 */
function mb_get_topic_last_reply_id( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$reply_id = get_post_meta( $topic_id, mb_get_topic_last_reply_id_meta_key(), true );

	$mb_reply_id = !empty( $reply_id ) && is_numeric( $reply_id ) ? absint( $reply_id ) : 0;

	return apply_filters( 'mb_get_topic_last_reply_id', $mb_reply_id, $topic_id );
}

/* ====== Last Post Author ====== */

/**
 * Displays the last post author for a topic.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_last_poster( $topic_id = 0 ) {
	echo mb_get_topic_last_poster( $topic_id );
}

/**
 * Returns the last post author for a topic.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_last_poster( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$reply_id = mb_get_topic_last_reply_id( $topic_id );

	$author = !empty( $reply_id ) ? mb_get_reply_author( $reply_id ) : mb_get_topic_author( $topic_id );

	return apply_filters( 'mb_get_topic_last_poster', $author, $reply_id, $topic_id );
}

/* ====== Last Post URL ====== */

/**
 * Displays the last post URL for a topic.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_last_post_url( $topic_id = 0 ) {
	echo mb_get_topic_last_post_url( $topic_id );
}

/**
 * Returns a topic's last post URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_last_post_url( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );
	$reply_id = mb_get_topic_last_reply_id( $topic_id );

	$url = $reply_id ? mb_get_reply_url( $reply_id ) : '';

	if ( !$url && $topic_id ) {
		$url = user_trailingslashit( mb_get_topic_url( $topic_id ) );
		$url = $url ? "{$url}#{$topic_id}" : '';
	}

	return apply_filters( 'mb_get_topic_last_post_url', $url, $reply_id, $topic_id );
}

/* ====== Post/Reply Count ====== */

/**
 * Displays the topic reply count.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_reply_count( $topic_id = 0 ) {
	echo mb_get_topic_reply_count( $topic_id );
}

/**
 * Returns the topic reply count.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return int
 */
function mb_get_topic_reply_count( $topic_id = 0 ) {
	$topic_id    = mb_get_topic_id( $topic_id );
	$reply_count = get_post_meta( $topic_id, mb_get_topic_reply_count_meta_key(), true );

	return apply_filters( 'mb_get_topic_reply_count', absint( $reply_count ), $topic_id );
}

/**
 * Displays the topic post count (topic + reply count).
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_post_count( $topic_id = 0 ) {
	echo mb_get_topic_post_count( $topic_id );
}

/**
 * Returns the topic post count (topic + reply count).
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_post_count( $topic_id = 0 ) {
	$post_count = 1 + mb_get_topic_reply_count( $topic_id );

	return apply_filters( 'mb_get_topic_post_count', $post_count, $topic_id );
}

/* ====== Topic Voices ====== */

/**
 * Displays the topic voice count.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_voice_count( $topic_id = 0 ) {
	echo mb_get_topic_voice_count( $topic_id );
}

/**
 * Retuurns the topic voice count.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return int
 */
function mb_get_topic_voice_count( $topic_id = 0 ) {
	$topic_id     = mb_get_topic_id( $topic_id );
	$voice_count  = get_post_meta( $topic_id, mb_get_topic_voice_count_meta_key(), true );

	$voice_count = $voice_count ? absint( $voice_count ) : count( mb_get_topic_voices( $topic_id ) );

	return apply_filters( 'mb_get_topic_voice_count', $voice_count, $topic_id );
}

/**
 * Returns an array of user IDs (topic voices).
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return array
 */
function mb_get_topic_voices( $topic_id = 0 ) {
	$topic_id     = mb_get_topic_id( $topic_id );

	/* @todo - Make this a single call before release. */
	$voices = get_post_meta( $topic_id, mb_get_topic_voices_meta_key() );

	/* @todo - remove count check and just use explode() before release. */
	if ( 1 < count( $voices ) ) {
		delete_post_meta( $topic_id, '_topic_voices' );
		$voices = mb_reset_topic_voices( $topic_id );
	} else {
		$voices = explode( ',', array_shift( $voices ) );
	}

	$voices = !empty( $voices ) ? $voices : array( mb_get_topic_author_id( $topic_id ) );

	return apply_filters( 'mb_get_topic_voices', $voices, $topic_id );
}

/* ====== Pagination ====== */

/**
 * Checks if viewing a paginated topic. Only for use on single topic pages.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function mb_is_topic_paged() {
	return mb_is_single_topic() && is_paged() ? true : false;
}

/**
 * Outputs pagination links for single topic pages (the replies are paginated).
 *
 * @since  1.0.0
 * @access public
 * @param  array  $args
 * @return string
 */
function mb_loop_topic_pagination( $args = array() ) {
	return mb_pagination( $args, message_board()->topic_query );
}

/**
 * Outputs pagination links for single topic pages (the replies are paginated).
 *
 * @since  1.0.0
 * @access public
 * @param  array  $args
 * @return string
 */
function mb_single_topic_pagination( $args = array() ) {
	return mb_pagination( $args, message_board()->reply_query );
}

/* ====== Topic Form ====== */

/**
 * Outputs the URL to the new topic form.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_topic_form_url() {
	echo mb_get_topic_form_url();
}

/**
 * Returns the URL to the new topic form.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function mb_get_topic_form_url() {

	if ( mb_is_single_forum() && !mb_is_forum_open( get_queried_object_id() ) )
		$url = '';
	else
		$url = esc_url( '#mb-topic-form' );

	return apply_filters( 'mb_topic_form_url', $url );
}

/**
 * Outputs a link to the new topic form.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $args
 * @return void
 */
function mb_topic_form_link( $args = array() ) {
	echo mb_get_topic_form_link( $args );
}

/**
 * Returns a link to the new topic form.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $args
 * @return string
 */
function mb_get_topic_form_link( $args = array() ) {

	if ( !current_user_can( 'create_topics' ) )
		return '';

	$url  = mb_get_topic_form_url();
	$link = '';

	$defaults = array(
		'text' => __( 'New Topic &rarr;', 'message-board' ),
		'wrap' => '<a %s>%s</a>',
		'before' => '',
		'after' => '',
	);

	$args = wp_parse_args( $args, $defaults );

	if ( !empty( $url ) ) {

		$attr = sprintf( 'class="new-topic-link new-topic" href="%s"', $url );

		$link = sprintf( $args['before'] . $args['wrap'] . $args['after'], $attr, $args['text'] );
	}

	return apply_filters( 'mb_get_topic_form_link', $link, $args );
}

/**
 * Displays the new topic form.
 *
 * @todo Set up system of hooks.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_topic_form() {
	mb_get_template_part( 'form-topic', 'new' );
}

/**
 * Displays the edit topic form.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_topic_edit_form() {
	mb_get_template_part( 'form-topic', 'edit' );
}

/**
 * Topic content editor.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mb_topic_editor() {
	add_action( 'wp_enqueue_editor', 'mb_dequeue_editor_scripts'  );
	add_filter( 'the_editor',        'mb_topic_the_editor_filter' );

	wp_editor( 
		format_to_edit( mb_code_trick_reverse( mb_get_topic_content( mb_get_topic_id(), 'raw' ) ) ),
		'mb_topic_content',
		array(
			'tinymce'       => false,
			'media_buttons' => false,
			'editor_height' => 250
		)
	);
}

/* ====== Topic Subscriptions ====== */

/**
 * Displays the topic subscribe URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_topic_subscribe_url( $topic_id = 0 ) {
	echo mb_get_topic_subscribe_url( $topic_id );
}

/**
 * Returns the topic subscribe URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_subscribe_url( $topic_id = 0 ) {

	if ( !mb_is_subscriptions_active() )
		return '';

	$topic_id = mb_get_topic_id( $topic_id );

	$url = $topic_id && current_user_can( 'read_topic', $topic_id ) ? add_query_arg( array( 'mb_action' => 'toggle_subscribe', 'topic_id' => $topic_id ) ) : '';

	if ( $url )
		$url = wp_nonce_url( $url, "subscribe_topic_{$topic_id}", 'mb_nonce' );

	return apply_filters( 'mb_get_topic_subscribe_url', esc_url( $url ), $topic_id );
}

/**
 * Displays the topic un/subscribe link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_subscribe_link( $topic_id = 0 ) {
	echo mb_get_topic_subscribe_link( $topic_id );
}

/**
 * Returns the topic un/subscribe link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_subscribe_link( $topic_id = 0 ) {

	if ( !mb_is_subscriptions_active() )
		return '';

	$topic_id = mb_get_topic_id( $topic_id );
	$link     = '';

	if ( is_user_logged_in() ) {

		$user_id = get_current_user_id();
		$url     = mb_get_topic_subscribe_url( $topic_id );
		$text    = mb_is_user_subscribed_topic( $user_id, $topic_id ) ? __( 'Unsubscribe', 'message-board' ) : __( 'Subscribe', 'message-board' );

		if ( !empty( $url ) )
			$link = sprintf( '<a class="mb-subscribe-link" href="%s">%s</a>', $url, $text ); 
	}

	return apply_filters( 'mb_get_topic_subscribe_link', $link, $topic_id );
}

/* ====== Topic Bookmarks ====== */

/**
 * Displays the topic bookmark URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_bookmark_url( $topic_id = 0 ) {
	echo mb_get_topic_bookmark_url( $topic_id );
}

/**
 * Returns the topic bookmark URL.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_bookmark_url( $topic_id = 0 ) {

	if ( !mb_is_bookmarks_active() )
		return '';

	$topic_id = mb_get_topic_id( $topic_id );

	$url = $topic_id && current_user_can( 'read_topic', $topic_id ) ? add_query_arg( array( 'mb_action' => 'toggle_bookmark', 'topic_id' => $topic_id ) ) : '';

	if ( $url )
		$url = wp_nonce_url( $url, "bookmark_topic_{$topic_id}", 'mb_nonce' );

	return apply_filters( 'mb_get_topic_bookmark_url', esc_url( $url ), $topic_id );
}

/**
 * Displays the topic un/bookmark link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return void
 */
function mb_topic_bookmark_link( $topic_id = 0 ) {
	echo mb_get_topic_bookmark_link( $topic_id );
}

/**
 * Returns the topic un/bookmark link.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $topic_id
 * @return string
 */
function mb_get_topic_bookmark_link( $topic_id = 0 ) {

	if ( !mb_is_bookmarks_active() )
		return '';

	$topic_id = mb_get_topic_id( $topic_id );
	$link     = '';

	if ( is_user_logged_in() ) {

		$user_id = get_current_user_id();
		$url     = mb_get_topic_bookmark_url( $topic_id );
		$text    = mb_is_topic_user_bookmark( $user_id, $topic_id ) ? __( 'Unbookmark', 'message-board' ) : __( 'Bookmark', 'message-board' );

		if ( !empty( $url ) )
			$link = sprintf( '<a class="mb-bookmark-link" href="%s">%s</a>', $url, $text ); 
	}

	return apply_filters( 'mb_get_topic_bookmark_link', $link, $topic_id );
}
