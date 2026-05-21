<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Pushly classic-editor meta box.
 *
 * Outputs HTML directly. All dynamic values are escaped at the point of output.
 *
 * @param bool        $send_notification              Whether the "Send Notification" box is checked.
 * @param bool        $customize_notification_content Whether custom title/body is enabled.
 * @param string|null $custom_title                   Custom notification title.
 * @param string|null $custom_body                    Custom notification body.
 */
function pushly_render_classic_meta_box(
	$send_notification,
	$customize_notification_content,
	$custom_title,
	$custom_body
) {
	wp_nonce_field( 'pushly_save_notification_meta_box', 'pushly_meta_box_nonce' );
	?>

	<div class="inside">
		<p>
			<input type="checkbox"
				   id="pushly_meta_send_notification"
				   name="pushly_send_notification"
				<?php echo ! empty( $send_notification ) ? 'checked' : ''; ?>
			/>
			<strong><label for="pushly_meta_send_notification"><?php esc_html_e( 'Send Notification', 'pushly' ); ?></label></strong>
		</p>

		<p>
			<input type="checkbox"
				   id="pushly_meta_customize_content"
				   name="pushly_customize_notification_content"
				<?php echo ! empty( $customize_notification_content ) ? 'checked' : ''; ?>
			/>
			<strong><label for="pushly_meta_customize_content"><?php esc_html_e( 'Customize Content', 'pushly' ); ?></label></strong>
		</p>

		<div
			id="pushly_meta_customize_content_section"
			style="display: <?php echo $customize_notification_content ? 'block' : 'none'; ?>;"
		>
			<p class="post-attributes-label-wrapper page-template-label-wrapper">
				<label for="pushly_meta_custom_title" class="post-attributes-label"><?php esc_html_e( 'Title', 'pushly' ); ?></label>
			</p>
			<input type="text"
				   id="pushly_meta_custom_title"
				   name="pushly_custom_title"
				   value="<?php echo esc_attr( (string) $custom_title ); ?>"
			/>

			<p class="post-attributes-label-wrapper page-template-label-wrapper">
				<label for="pushly_meta_custom_body" class="post-attributes-label"><?php esc_html_e( 'Body', 'pushly' ); ?></label>
			</p>
			<input type="text"
				   id="pushly_meta_custom_body"
				   name="pushly_custom_body"
				   value="<?php echo esc_attr( (string) $custom_body ); ?>"
			/>
		</div>
	</div>
	<?php
}
