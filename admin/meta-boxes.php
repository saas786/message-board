<?php
/**
 * Callback functions for the various meta boxes used on the post screen in the admin for all 
 * the plugin's post types.
 *
 * @package    MessageBoard
 * @subpackage Admin
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2014, Justin Tadlock
 * @link       https://github.com/justintadlock/message-board
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Custom `submitdiv` meta box.  This replaces the WordPress default because it has too many things 
 * hardcoded that we cannot overwrite, particularly dealing with post statuses.
 *
 * @since  1.0.0
 * @access public
 * @param  object  $post
 * @param  array   $args
 * @return void
 */
function mb_submit_meta_box( $post, $args = array() ) {
	global $action;

	$avail_statuses   = array();
	$post_type        = $post->post_type;
	$post_type_object = get_post_type_object( $post_type );

	if ( mb_is_forum( $post->ID ) )
		$avail_statuses = mb_get_forum_post_statuses();

	elseif ( mb_is_topic( $post->ID ) )
		$avail_statuses = mb_get_topic_post_statuses();

	elseif ( mb_is_reply( $post->ID ) )
		$avail_statuses = mb_get_reply_post_statuses();

	$can_publish = current_user_can( $post_type_object->cap->publish_posts ); ?>

	<div class="submitbox" id="submitpost">

		<div id="minor-publishing">

			<div id="misc-publishing-actions">

				<div class="misc-pub-section misc-pub-post-status">
<?php $st_object = !empty( $post->post_status ) && in_array( $post->post_status, $avail_statuses ) ? get_post_status_object( $post->post_status ) : get_post_status_object( mb_get_open_post_status() ); ?>

<label for="post_status"><?php printf( __( 'Status: %s', 'message-board' ), "<strong class='mb-current-status'>{$st_object->label}</strong>" ); ?></label>

<a href="#post-status-select" class="edit-post-status hide-if-no-js">
	<span aria-hidden="true"><?php _e( 'Edit' ); ?></span> 
	<span class="screen-reader-text"><?php _e( 'Edit status' ); ?></span>
</a>

					<div id="post-status-select" class="hide-if-js">
						<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ('auto-draft' == $post->post_status ) ? 'draft' : $post->post_status); ?>" />
							<div id="post_status">
						<?php if ( mb_is_forum( $post->ID ) ) :
							mb_dropdown_forum_status( array( 'selected' => $post->post_status ) );
						elseif ( mb_is_topic( $post->ID ) ) :
							mb_dropdown_topic_status( array( 'selected' => $post->post_status ) );
						elseif ( mb_is_reply( $post->ID ) ) :
							mb_dropdown_reply_status( array( 'selected' => $post->post_status ) );
						endif;
						?>
 <a href="#post_status" class="save-post-status hide-if-no-js button"><?php _e('OK'); ?></a>
 <a href="#post_status" class="cancel-post-status hide-if-no-js button-cancel"><?php _e('Cancel'); ?></a>
							</div><!-- #post_status -->
					</div><!-- #post-status-select -->

				</div><!-- .misc-pub-section -->

<?php
/* translators: Publish box date format, see http://php.net/date */
$datef = __( 'M j, Y @ G:i' );
if ( 0 != $post->ID ) {
	$stamp = __('Date: <b>%1$s</b>');
	$date = date_i18n( $datef, strtotime( $post->post_date ) );
} else {
	$stamp = __('Publish <b>immediately</b>');
	$date = date_i18n( $datef, strtotime( current_time('mysql') ) );
}

?>

<?php if ( mb_is_topic( $post->ID ) ) : ?>
<?php $_m_order = 0 != $post->ID ? $post->post_date : current_time( 'mysql' ); ?>
<input type="hidden" name="menu_order" value="<?php echo esc_attr( mysql2date( 'U', $_m_order ) ); ?>" />
<?php endif; ?>

<div class="misc-pub-section curtime misc-pub-curtime">
	<span id="timestamp">
	<?php printf($stamp, $date); ?></span>
</div>

				<div class="misc-pub-section">
<i class="dashicons dashicons-admin-users"></i> <?php printf( __( 'Author: %s', 'message-board' ), '<strong>' . get_the_author_meta( 'display_name', $post->post_author ) . '</strong>' ); ?>
				</div>

				<?php do_action( 'post_submitbox_misc_actions' ); ?>

			</div><!-- #misc-publishing-actions -->

			<div class="clear"></div>

		</div><!-- #minor-publishing -->

		<div id="major-publishing-actions">

			<?php do_action( 'post_submitbox_start' ); ?>

			<div id="delete-action">
				<?php if ( current_user_can( 'delete_post', $post->ID ) ) :
					if ( !EMPTY_TRASH_DAYS ) :
						$delete_text = __( 'Delete Permanently', 'message-board' );
					else :
						$delete_text = __( 'Move to Trash', 'message-board' );
					endif; ?>
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>"><?php echo $delete_text; ?></a>
				<?php endif; ?>
			</div><!-- #delete-action -->

			<div id="publishing-action">
				<span class="spinner"></span>
				<?php if ( 0 == $post->ID || !in_array( $post->post_status, $avail_statuses ) ) : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Publish', 'message-board' ) ?>" />
					<?php submit_button( __( 'Publish', 'message-board' ), 'primary button-large', 'mb-publish', false, array( 'accesskey' => 'p' ) ); ?>
				<?php else : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Update', 'message-board' ) ?>" />
					<input name="save" type="submit" class="button button-primary button-large" id="publish" accesskey="p" value="<?php esc_attr_e( 'Update', 'message-board' ) ?>" />
				<?php endif; ?>
			</div><!-- #publishing-action -->

			<div class="clear"></div>

		</div><!-- #major-publishing-actions -->

	</div><!-- #submitpost -->
<?php }

/**
 * Forum attribute meta box.  This handles the forum type, parent, and menu order.
 *
 * @since  1.0.0
 * @access public
 * @param  object  $post
 * @return void
 */
function mb_forum_attributes_meta_box( $post ) {

	wp_nonce_field( '_mb_forum_attr_nonce', 'mb_forum_attr_nonce' );

	$forum_types = mb_get_forum_type_objects(); ?>

	<p>
		<label for="mb_forum_type">
			<strong><?php _e( 'Forum Type:', 'message-board' ); ?></strong>
		</label>
	</p>
	<p>
		<?php mb_dropdown_forum_type( array( 'selected' => mb_get_forum_type( $post->ID ) ) ); ?>
	</p>

	<p>
		<label for="mb_parent_forum">
			<strong><?php _e( 'Parent Forum:', 'message-board' ); ?></strong>
		</label>
	</p>
	<p>
		<?php mb_dropdown_forums(
			array(
				'name'              => 'parent_id',
				'id'                => 'mb_parent_forum',
				'show_option_none'  => __( '(no parent)', 'message-board' ),
				'option_none_value' => 0,
				'selected'          => $post->post_parent
			)
		); ?>
	</p>

	<p>
		<label for="mb_menu_order"><strong><?php _e( 'Order:', 'message-board' ); ?></strong></label>
	</p>
	<p>
		<input type="number" name="menu_order" id="mb_menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>" />
	</p><?php
}

/**
 * Topic attributes meta box.  This handles whether the topic is sticky and the parent forum.
 *
 * @since  1.0.0
 * @access public
 * @param  object  $post
 * @return void
 */
function mb_topic_attributes_meta_box( $post ) {

	wp_nonce_field( '_mb_topic_attr_nonce', 'mb_topic_attr_nonce' );

	$topic_type_object = get_post_type_object( mb_get_topic_post_type() ); ?>

	<p>
		<label for="mb_topic_type">
			<strong><?php _e( 'Topic Type:', 'message-board' ); ?></strong>
		</label>
	</p>
	<p>
		<?php mb_dropdown_topic_type( array( 'selected' => mb_get_topic_type( $post->ID ) ) ); ?>
	</p>

	<p>
		<label for="mb_parent_forum">
			<strong><?php echo $topic_type_object->labels->parent_item_colon; ?></strong>
		</label>
	</p>
	<p>
		<?php mb_dropdown_forums(
			array(
				'child_type' => mb_get_topic_post_type(),
				'name'       => 'parent_id',
				'id'         => 'mb_parent_forum',
				'selected'   => !empty( $post->post_parent ) ? $post->post_parent : mb_get_default_forum_id()
			)
		); ?>
	</p><?php
}

/**
 * Reply info meta box.  Displays relevant information about the reply.  This box doesn't have editable 
 * content in it.
 *
 * @since  1.0.0
 * @access public
 * @param  object  $post
 * @return void
 */
function mb_reply_info_meta_box( $post ) {

	$reply_id = mb_get_reply_id( $post->ID );
	$topic_id = mb_get_reply_topic_id( $reply_id );
	$forum_id = mb_get_reply_forum_id( $reply_id );

	$topic_object = get_post_type_object( mb_get_topic_post_type() );
	$forum_object = get_post_type_object( mb_get_forum_post_type() ); ?>

	<p><?php printf( __( 'Topic: %s', 'message-board' ), mb_get_topic_link( $topic_id ) ); ?></p>
	<p><?php printf( __( 'Forum: %s', 'message-board' ), mb_get_forum_link( $forum_id ) ); ?></p>
<?php }
