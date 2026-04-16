<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Pushly
 * Plugin URI:        http://pushly.com
 * Description:       Provide Pushly push notification capability to WordPress installations
 * Version:           2.2.0
 * Author:            Pushly
 * Author URI:        http://pushly.com/
 * License:           GPLv2
 * Text Domain:       pushly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/environment.php';
require_once PUSHLY__DIR . '/vendor/autoload.php';

add_action( 'init', function (): void {
	// Settings — admin UI hooks are gated internally, but register_settings
	// must also run on rest_api_init so it's registered unconditionally.
	$settings = new \Pushly\Admin\Settings();
	$settings->register_hooks();

	// Post — hooks into both admin (meta boxes, notices) and REST (post meta,
	// transition_post_status, rest_after_insert). Must be unconditional so
	// Gutenberg REST saves work correctly.
	$options = get_option( 'pushly', [] );

	$log_store  = new \Pushly\Admin\LogStore();
	$log_writer = new \Pushly\Admin\LogWriter( $log_store, ! empty( $options['debug_logging_enabled'] ) );
	$log_writer->register_hooks();

	$post = new \Pushly\Admin\Post( $options, $log_writer );
	$post->register_hooks();

	// REST controller — runs on both admin and REST contexts
	$activity_log_controller = new \Pushly\Admin\ActivityLogController( $log_store );
	$activity_log_controller->register_hooks();

	// Frontend SDK — runs on public-facing requests only
	if ( ! is_admin() ) {
		$sdk = new \Pushly\Frontend\SDK();
		$sdk->register_hooks();
	}

	// Site Health — admin only
	if ( is_admin() ) {
		$site_health = new \Pushly\Admin\SiteHealth( $log_store );
		$site_health->register_hooks();
	}

	// WP-Cron pruning — schedule daily cleanup when debug logging is enabled.
	if ( ! empty( $options['debug_logging_enabled'] ) && ! wp_next_scheduled( 'pushly_prune_activity_log' ) ) {
		wp_schedule_event( time(), 'daily', 'pushly_prune_activity_log' );
	}

	// Hook the cron action to the LogStore pruning method.
	add_action( 'pushly_prune_activity_log', [ $log_store, 'prune_old_entries' ] );
} );

// Activation hook — create the activity log table.
register_activation_hook( __FILE__, function (): void {
	$log_store = new \Pushly\Admin\LogStore();
	$log_store->create_table();
} );

// Deactivation hook — unschedule the cron event.
register_deactivation_hook( __FILE__, function (): void {
	wp_clear_scheduled_hook( 'pushly_prune_activity_log' );
} );
