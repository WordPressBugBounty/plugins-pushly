<?php
/*
 * This file should exist in src/public/views but in order to maintain backwards compatibility
 * with WP plugin v1 we leave it in the same place and accept that it lives in the wrong place.
 */

header("Content-Type: application/javascript");
header("X-Robots-Tag: none");
header("Service-Worker-Allowed: /");

?>
importScripts("https://cdn.p-n.io/pushly-sw.min.js" + (self.location || {}).search || "");
