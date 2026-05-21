<?php
/**
 * Pushly plugin uninstall handler.
 *
 * Drops the custom activity log table and removes all plugin settings.
 * This is a full uninstall — if the user re-installs, they start from a clean slate.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Drop the custom activity log table.
$pushly_log_store = new \Pushly\Admin\LogStore();
$pushly_log_store->drop_table();

// Remove all plugin settings.
delete_option( 'pushly' );
