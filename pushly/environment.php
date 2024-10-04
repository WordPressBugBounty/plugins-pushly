<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PUSHLY__ENV' ) ) {
	define( 'PUSHLY__ENV', "prod" );
}

if ( ! defined( 'PUSHLY__PLUGIN_DIR' ) ) {
	define( 'PUSHLY__PLUGIN_DIR', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PUSHLY__DIR' ) ) {
	define( 'PUSHLY__DIR', dirname( __FILE__ ) );
}

if ( ! defined( 'PUSHLY__SDK_DOMAIN' ) ) {
	define( 'PUSHLY__SDK_DOMAIN', 'cdn.p-n.io' );
}

if ( ! defined( 'PUSHLY__DIR_BUILD' ) ) {
	define( 'PUSHLY__DIR_BUILD', PUSHLY__DIR . '/build' );
}

if ( ! defined( 'PUSHLY__DIR_SRC' ) ) {
	define( 'PUSHLY__DIR_SRC', PUSHLY__DIR . '/src' );
}

if ( ! defined( 'PUSHLY__API_DOMAIN' ) ) {
	define( 'PUSHLY__API_DOMAIN', "api.pushly.com" );
}

if ( ! defined( 'PUSHLY__CDN_DOMAIN' ) ) {
	define( 'PUSHLY__CDN_DOMAIN', "pushlycdn.com" );
}

if ( ! defined( 'PUSHLY__K_DOMAIN' ) ) {
	define( 'PUSHLY__K_DOMAIN', "k.p-n.io" );
}

if ( ! defined( 'PUSHLY__PLUGIN_VERSION' ) ) {
	$version_array = get_file_data( PUSHLY__DIR . "/pushly.php", array( 'Version' ), 'plugin' );
	if ( ! empty( $version_array[0] ) ) {
		define( "PUSHLY__PLUGIN_VERSION", $version_array[0] );
	} else {
		define( "PUSHLY__PLUGIN_VERSION", '0.0.0' );
	}
}
