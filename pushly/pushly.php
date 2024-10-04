<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Pushly
 * Plugin URI:        http://pushly.com
 * Description:       Provide Pushly push notification capability to WordPress installations
 * Version:           2.1.9
 * Author:            Pushly
 * Author URI:        http://pushly.com/
 * License:           GPLv2
 * Text Domain:       pushly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// loads environment
require_once dirname( __FILE__ ) . '/environment.php';

require_once PUSHLY__DIR . '/includes/admin/models/notification.php';
require_once PUSHLY__DIR . '/includes/admin/class-pushly-admin-util.php';
require_once PUSHLY__DIR . '/includes/admin/class-pushly-admin-settings.php';
require_once PUSHLY__DIR . '/includes/admin/class-pushly-admin-post.php';

add_action('init', ['Pushly_Admin_Settings', 'instance']);
add_action('init', ['Pushly_Admin_Post', 'instance']);

require_once PUSHLY__DIR . '/includes/public/class-pushly-public.php';
add_action('init', ['Pushly_Public', 'instance']);
