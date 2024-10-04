<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function build_sdk(
	$sdk_key,
    $sw_root
) {
	ob_start();
?>
<script>
    var PushlySDK = window.PushlySDK || [];
    function pushly() { PushlySDK.push(arguments) }
    pushly('load', {
        domainKey: decodeURIComponent("<?php echo rawurlencode((string) $sdk_key); ?>"),
        sw: <?php echo wp_json_encode(esc_url($sw_root . 'assets/js/pushly-sdk-worker.js.php'), JSON_UNESCAPED_SLASHES); ?>,
        swScope: <?php echo wp_json_encode(esc_url($sw_root), JSON_UNESCAPED_SLASHES); ?>
    });
</script>

<?php
	$content = ob_get_contents();
	ob_end_clean();

	return $content;
}

?>