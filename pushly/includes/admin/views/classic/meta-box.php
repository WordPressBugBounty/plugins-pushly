<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function build_classic_meta_box_html(
	$send_notification,
	$customize_notification_content,
	$custom_title,
	$custom_body
) {
	ob_start();
    echo wp_nonce_field( 'pushly_save_notification_meta_box', 'pushly_meta_box_nonce' );
	?>

    <div class="inside">
        <p>
            <input type="checkbox"
                   id="pushly_meta_send_notification"
                   name="pushly_send_notification"
				<?php echo !empty($send_notification) ? 'checked' : ''; ?>
            />
            <strong><label for="pushly_meta_send_notification">Send Notification</label></strong>
        </p>

        <p>
            <input type="checkbox"
                   id="pushly_meta_customize_content"
                   name="pushly_customize_notification_content"
				<?php echo !empty($customize_notification_content) ? 'checked' : ''; ?>
            />
            <strong><label for="pushly_meta_customize_content">Customize Content</label></strong>
        </p>

        <div
            id="pushly_meta_customize_content_section"
            style="display: <?php echo $customize_notification_content ? 'block' : 'none'; ?>;"
        >
            <p class="post-attributes-label-wrapper page-template-label-wrapper">
                <label for="pushly_meta_custom_title" class="post-attributes-label">Title</label>
            </p>
            <input type="text"
                  id="pushly_meta_custom_title"
                  name="pushly_custom_title"
                  value="<?php echo esc_attr($custom_title); ?>"
            />

            <p class="post-attributes-label-wrapper page-template-label-wrapper">
                <label for="pushly_meta_custom_body" class="post-attributes-label">Body</label>
            </p>
            <input type="text"
                  id="pushly_meta_custom_body"
                  name="pushly_custom_body"
                  value="<?php echo esc_attr($custom_body); ?>"
            />
        </div>
    </div>

<?php
	$content = ob_get_contents();
	ob_end_clean();

	return $content;
}

?>
