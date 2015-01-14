<?php if ( !current_user_can( 'access_reply_form' ) )
	return;
?>

<form id="mb-reply-form" method="post" action="<?php mb_topic_url(); ?>">

	<fieldset>
		<legend><?php mb_reply_label( 'add_new_item' ); ?></legend>

		<p>
			<label for="mb_reply_content" name="mb_reply_content"><?php mb_reply_label( 'mb_form_content' ); ?></label>
			<textarea id="mb_reply_content" name="mb_reply_content" placeholder="<?php echo esc_attr( mb_get_reply_label( 'mb_form_content_placeholder' ) ); ?>"><?php echo format_to_edit( mb_code_trick_reverse( mb_get_reply_content( mb_get_reply_id(), 'raw' ) ) ); ?></textarea>
		</p>

		<p>
			<input type="submit" value="<?php esc_attr_e( 'Submit', 'message-board' ); ?>" />
		</p>

		<?php if ( !mb_is_user_subscribed_topic( mb_get_topic_id() ) ) : ?>
			<p>
				<label>
					<input type="checkbox" name="mb_reply_subscribe" value="1" />
					<?php _e( 'Notify me of follow-up posts via email', 'message-board' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<input type="hidden" name="mb_reply_topic_id" value="<?php mb_topic_id(); ?>" />

		<?php wp_nonce_field( 'mb_new_reply_action', 'mb_new_reply_nonce', false ); ?>

	</fieldset>
</form>