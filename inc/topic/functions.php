<?php

function mb_get_topic_post_type() {
	return apply_filters( 'mb_get_topic_post_type', 'forum_topic' );
}

function mb_get_multi_topic_reply_ids( $topic_ids ) {
	global $wpdb;

	return $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'open' AND post_parent IN ( " . implode( ',', $topic_ids ) . " ) ORDER BY post_date DESC", mb_get_reply_post_type() ) );
}

function mb_get_topic_subscribers( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );

	if ( empty( $topic_id ) )
		return;

	$users = wp_cache_get( 'mb_get_topic_subscribers_' . $topic_id, 'message-board-users' );

	if ( false === $users ) {
		$users = mb_set_topic_subscribers( $topic_id );
	}

	return apply_filters( 'mb_get_topic_subscribers', $users );
}

function mb_set_topic_subscribers( $topic_id ) {
	global $wpdb;

	$users = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_topic_subscriptions' and FIND_IN_SET( '{$topic_id}', meta_value ) > 0" );
	wp_cache_set( 'mb_get_topic_subscribers_' . $topic_id, $users, 'message-board-users' );

	return $users;
}

function mb_get_topic_bookmarkers( $topic_id = 0 ) {
	$topic_id = mb_get_topic_id( $topic_id );

	if ( empty( $topic_id ) )
		return;

	$users = wp_cache_get( 'mb_get_topic_bookmarkers_' . $topic_id, 'message-board-users' );

	if ( false === $users ) {
		$users = mb_set_topic_bookmarkers();
	}

	return apply_filters( 'mb_get_topic_bookmarkers', $users );
}

function mb_set_topic_bookmarkers( $topic_id ) {
	global $wpdb;

	$users = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_topic_bookmarks' and FIND_IN_SET( '{$topic_id}', meta_value ) > 0" );
	wp_cache_set( 'mb_get_topic_bookmarkers_' . $topic_id, $users, 'message-board-users' );

	return $users;
}

function mb_reset_topic_latest( $topic_id ) {

	$reply_ids = mb_get_topic_reply_ids( $topic_id );

	if ( !empty( $reply_ids ) ) {

		$reply_ids = array_reverse( $reply_ids );
		$last_reply_id = array_shift( $reply_ids );

		$post_date = get_post_field( 'post_date', $last_reply_id );

		update_post_meta( $topic_id, '_topic_activity_datetime',       $post_date );
		update_post_meta( $topic_id, '_topic_activity_datetime_epoch', mysql2date( 'U', $post_date ) );
		update_post_meta( $topic_id, '_topic_last_reply_id',           $last_reply_id );

	} else {
		$post_date = get_post_field( 'post_date', $topic_id );

		update_post_meta( $topic_id, '_topic_activity_datetime',       $post_date );
		update_post_meta( $topic_id, '_topic_activity_datetime_epoch', mysql2date( 'U', $post_date ) );

		delete_post_meta( $topic_id, '_topic_last_reply_id' );
	}

	$postarr = array();
	$postarr['ID'] = absint( $topic_id );
	$postarr['menu_order'] = mysql2date( 'U', $post_date );
	wp_update_post( $postarr );
}

function mb_set_topic_reply_count( $topic_id ) {

	$replies = mb_get_topic_reply_ids( $topic_id );

	$count = !empty( $replies ) ? count( $replies ) : 0;

	update_post_meta( $topic_id, '_topic_reply_count', $count );
}

function mb_set_topic_voices( $topic_id ) {
	global $wpdb;

	$voices = $wpdb->get_col( $wpdb->prepare( "SELECT post_author FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s AND post_status = 'open'", absint( $topic_id ), mb_get_reply_post_type() ) );

	$topic_author = mb_get_topic_author_id( $topic_id );

	$voices = array_merge( array( $topic_author ), (array)$voices );
	$voices = array_unique( $voices );

	$_voices = implode( ',', wp_parse_id_list( array_filter( $voices ) ) );
	update_post_meta( $topic_id, '_topic_voices', $_voices );
	update_post_meta( $topic_id, '_topic_voice_count', count( $_voices ) );

	return $voices;
}

function mb_reset_topic_data( $post, $reset_latest = false ) {

	$post = is_object( $post ) ? $post : get_post( $post );

	$forum_id         = mb_get_topic_forum_id( $post->ID );
	$forum_last_topic = mb_get_forum_last_topic_id( $forum_id );

	/* Reset forum topic count. */
	mb_set_forum_topic_count( $forum_id );

	/* Reset forum reply count. */
	mb_set_forum_reply_count( $forum_id );

	/* If this is the last topic, reset forum latest data. */
	if ( $post->ID === absint( $forum_last_topic ) || true === $reset_latest )
		mb_reset_forum_latest( $forum_id );

	/* Reset user topic count. */
	mb_set_user_topic_count( $post->post_author );
}



