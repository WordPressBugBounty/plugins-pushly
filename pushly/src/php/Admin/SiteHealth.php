<?php

namespace Pushly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteHealth {

	/** @var LogStore */
	private $store;

	public function __construct( LogStore $store ) {
		$this->store = $store;
	}

	public function register_hooks(): void {
		add_filter( 'debug_information', [ $this, 'add_debug_info' ] );
		add_filter( 'site_status_tests', [ $this, 'add_status_tests' ] );
	}

	/**
	 * Adds a "Pushly" section to Site Health → Info.
	 *
	 * @param array $info Existing debug information sections.
	 * @return array Modified debug information sections.
	 */
	public function add_debug_info( array $info ): array {
		$options = get_option( 'pushly', [] );

		$sdk_key = $options['sdk_key'] ?? '';
		if ( strlen( $sdk_key ) >= 4 ) {
			$masked = str_repeat( '*', strlen( $sdk_key ) - 4 ) . substr( $sdk_key, -4 );
		} else {
			$masked = str_repeat( '*', strlen( $sdk_key ) );
		}

		$enabled_post_types = $options['enabled_post_types'] ?? [];
		$post_types_display = ! empty( $enabled_post_types )
			? implode( ', ', $enabled_post_types )
			: __( 'None', 'pushly' );

		$info['pushly'] = [
			'label'  => __( 'Pushly', 'pushly' ),
			'fields' => [
				'plugin_version'        => [
					'label' => __( 'Plugin version', 'pushly' ),
					'value' => PUSHLY__PLUGIN_VERSION,
				],
				'sdk_key'               => [
					'label' => __( 'SDK key', 'pushly' ),
					'value' => $masked,
				],
				'api_key_configured'    => [
					'label' => __( 'API key configured', 'pushly' ),
					'value' => ! empty( $options['api_key'] )
						? __( 'Yes', 'pushly' )
						: __( 'No', 'pushly' ),
				],
				'sending_enabled'       => [
					'label' => __( 'Sending enabled', 'pushly' ),
					'value' => ! empty( $options['sending_enabled'] )
						? __( 'Yes', 'pushly' )
						: __( 'No', 'pushly' ),
				],
				'debug_logging_enabled' => [
					'label' => __( 'Debug logging enabled', 'pushly' ),
					'value' => ! empty( $options['debug_logging_enabled'] )
						? __( 'Yes', 'pushly' )
						: __( 'No', 'pushly' ),
				],
				'enabled_post_types'    => [
					'label' => __( 'Enabled post types', 'pushly' ),
					'value' => $post_types_display,
				],
				'log_entry_count'       => [
					'label' => __( 'Log entry count', 'pushly' ),
					'value' => $this->store->count(),
				],
			],
		];

		return $info;
	}

	/**
	 * Registers a direct status test for API key configuration.
	 *
	 * @param array $tests Existing site status tests.
	 * @return array Modified site status tests.
	 */
	public function add_status_tests( array $tests ): array {
		$tests['direct']['pushly_api_key'] = [
			'label' => __( 'Pushly API key configuration', 'pushly' ),
			'test'  => [ $this, 'test_api_key_configured' ],
		];

		return $tests;
	}

	/**
	 * Tests whether the API key is configured when sending is enabled.
	 *
	 * @return array Site Health test result.
	 */
	public function test_api_key_configured(): array {
		$options         = get_option( 'pushly', [] );
		$sending_enabled = ! empty( $options['sending_enabled'] );
		$api_key_set     = ! empty( $options['api_key'] );

		if ( $sending_enabled && ! $api_key_set ) {
			return [
				'label'       => __( 'Pushly sending is enabled without an API key', 'pushly' ),
				'status'      => 'critical',
				'badge'       => [
					'label' => __( 'Pushly', 'pushly' ),
					'color' => 'red',
				],
				'description' => '<p>' . __( 'Pushly notification sending is enabled, but no API key has been configured. Notifications cannot be delivered without a valid API key.', 'pushly' ) . '</p>',
				'test'        => 'pushly_api_key',
			];
		}

		return [
			'label'       => __( 'Pushly API key is configured correctly', 'pushly' ),
			'status'      => 'good',
			'badge'       => [
				'label' => __( 'Pushly', 'pushly' ),
				'color' => 'blue',
			],
			'description' => '<p>' . __( 'The Pushly plugin API key configuration is valid.', 'pushly' ) . '</p>',
			'test'        => 'pushly_api_key',
		];
	}
}
