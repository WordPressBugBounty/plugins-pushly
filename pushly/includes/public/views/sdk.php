<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits the Pushly SDK initialization snippet.
 *
 * Outputs the inline <script> directly with all dynamic values JSON-encoded
 * for safe embedding in JS contexts.
 *
 * @param string $sdk_key The Pushly SDK / domain key.
 * @param string $sw_root Base plugin URL used to build the service-worker path and scope.
 */
function pushly_render_sdk_snippet( $sdk_key, $sw_root ) {
	?>
<script>
	var PushlySDK = window.PushlySDK || [];
	function pushly() { PushlySDK.push(arguments) }
	pushly('load', {
		domainKey: decodeURIComponent("<?php echo rawurlencode( (string) $sdk_key ); ?>"),
		sw: <?php echo wp_json_encode( esc_url( $sw_root . 'assets/js/pushly-sdk-worker.js.php' ), JSON_UNESCAPED_SLASHES ); ?>,
		swScope: <?php echo wp_json_encode( esc_url( $sw_root ), JSON_UNESCAPED_SLASHES ); ?>
	});
</script>
	<?php
}
