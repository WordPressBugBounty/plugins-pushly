<?php
/*
 * Pushly service-worker shim.
 *
 * NOTE: This file is INTENTIONALLY directly accessible via its plugin URL
 * (e.g. /wp-content/plugins/pushly/assets/js/pushly-sdk-worker.js.php).
 * Browsers register it as a service worker at this exact path, and existing
 * Pushly users have service workers already registered against this URL.
 * Changing the URL, or gating it on ABSPATH, would break web push for every
 * site currently using the plugin.
 *
 * For that reason, Plugin Check's `missing_direct_file_access_protection`
 * warning for this file is a known false positive and must remain.
 *
 * The file should logically live under src/public/views, but is preserved
 * here for backwards compatibility with WP plugin v1.
 */

header( 'Content-Type: application/javascript' );
header( 'X-Robots-Tag: none' );
header( 'Service-Worker-Allowed: /' );

?>
importScripts("https://cdn.p-n.io/pushly-sw.min.js" + ((self.location || {}).search || ""));

