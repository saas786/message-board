<?php
/**
 * Plugin functions and filters for users.
 *
 * @package    MessageBoard
 * @subpackage Includes
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2014, Justin Tadlock
 * @link       https://github.com/justintadlock/message-board
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

function mb_set_user_forum_count( $user_id ) {
	global $wpdb;

	$open_status    = mb_get_open_post_status();
	$close_status   = mb_get_close_post_status();
	$publish_status = mb_get_publish_post_status();
	$hidden_status  = mb_get_hidden_post_status();
	$private_status = mb_get_private_post_status();

	$where = $wpdb->prepare( "WHERE post_author = %d AND post_type = %s", $user_id, mb_get_forum_post_type() );

	$status_where = "AND (post_status = '{$open_status}' OR post_status = '{$close_status}' OR post_status = '{$publish_status}' OR post_status = '{$private_status}' OR post_status = '{$hidden_status}')";

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where $status_where" );

	update_user_meta( $user_id, mb_get_user_forum_count_meta_key(), $count );

	return $count;
}

function mb_set_user_topic_count( $user_id ) {
	global $wpdb;

	$open_status    = mb_get_open_post_status();
	$close_status   = mb_get_close_post_status();
	$publish_status = mb_get_publish_post_status();
	$hidden_status  = mb_get_hidden_post_status();
	$private_status = mb_get_private_post_status();

	$where = $wpdb->prepare( "WHERE post_author = %d AND post_type = %s", $user_id, mb_get_topic_post_type() );

	$status_where = "AND (post_status = '{$open_status}' OR post_status = '{$close_status}' OR post_status = '{$publish_status}' OR post_status = '{$private_status}' OR post_status = '{$hidden_status}')";

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where $status_where" );

	update_user_meta( $user_id, mb_get_user_topic_count_meta_key(), $count );

	return $count;
}

function mb_set_user_reply_count( $user_id ) {
	global $wpdb;

	// @todo check all public reply statuses
	$where = $wpdb->prepare( "WHERE post_author = %d AND post_type = %s AND post_status = %s", $user_id, mb_get_reply_post_type(), mb_get_publish_post_status() );

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where" );

	update_user_meta( $user_id, mb_get_user_reply_count_meta_key(), $count );

	return $count;
}
