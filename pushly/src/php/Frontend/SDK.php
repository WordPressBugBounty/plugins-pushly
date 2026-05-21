<?php

namespace Pushly\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SDK {
	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'insert_header' ], 10 );
		add_filter( 'script_loader_tag', [ $this, 'async_enqueue' ], 10, 2 );
	}

	public function insert_header(): void {
		$sdk_key                 = null;
		$initialization_disabled = false;

		$options = get_option( 'pushly' );
		if ( ! empty( $options['sdk_key'] ) ) {
			$sdk_key                 = $options['sdk_key'];
			$initialization_disabled = ! empty( $options['initialization_disabled'] );
		} else {
			// v1 backwards compatibility
			$legacy = get_option( 'pushly_options' );
			if ( ! empty( $legacy['domain_key'] ) ) {
				$sdk_key = $legacy['domain_key'];
			}
		}

		if ( ! $sdk_key || $initialization_disabled ) {
			return;
		}

		wp_enqueue_script(
			'pushly-sdk',
			'https://' . PUSHLY__SDK_DOMAIN . '/pushly-sdk.min.js?domain_key=' . rawurlencode( $sdk_key ),
			[],
			PUSHLY__PLUGIN_VERSION,
			true
		);

		require_once PUSHLY__DIR . '/includes/public/views/sdk.php';
		pushly_render_sdk_snippet( $sdk_key, PUSHLY__PLUGIN_DIR );
	}

	public function async_enqueue( string $tag, string $handle ): string {
		if ( 'pushly-sdk' === $handle && false === strpos( $tag, 'async' ) ) {
			return str_replace( '<script ', '<script async ', $tag );
		}

		return $tag;
	}
}
