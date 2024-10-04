<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pushly_Admin_Settings {
	/**
	 * @var Pushly_Admin_Settings
	 */
	private static $_instance;

	public static function instance() {
		if ( empty( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'register_settings_errors' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'plugin_action_links_' . plugin_basename( PUSHLY__DIR . "/pushly.php" ), [
			$this,
			'add_links_to_plugin'
		] );
	}

	/**
	 * Adds the Pushly menu link to the admin
	 */
	public function add_menu_page() {
		$icon = 'data:image/svg+xml;base64,ICAgIDxzdmcKICAgICAgICB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiAgICAgICAgdmlld0JveD0iMCAwIDEwMjQgMTAyNCIKICAgICAgICBkYXRhLWljb249InB1c2hseSIKICAgICAgICBoZWlnaHQ9IjEwMjQiCiAgICAgICAgd2lkdGg9IjEwMjQiCiAgICAgICAgZmlsbD0iIzRkYmNhMiIKICAgICAgICBhcmlhLWhpZGRlbj0idHJ1ZSIKICAgID4KICAgICAgICA8cGF0aAogICAgICAgICAgICBkPSJNOTc0LjYwMiwxNTUuNjJjLTE5LjQ1Ni0yOC40NjctNDQuNzI2LTUwLjgyMy03NS4xMzEtNjYuNTA0Qzg2OC4zNyw3My4xMDEsODMzLjg5LDY1LDc5Ni45NCw2NUg2MjcuNTcKCQljLTEzMy40MzgsMC0xOTEuMzEyLDE1MC41MDMtMTk1Ljc1MywxNzIuMTAxbC0xMzcuMzQsNjMzLjU1N2MtNC43NDUsMjMuMTA1LTAuNDA4LDQ0LjQ3LDEyLjU4Miw2MS43NjJsMC41ODMsMC43NTQKCQljMTMuODUxLDE3LjAxNiwzMy42ODEsMjUuOTk5LDU3LjM1MiwyNS45OTljMjEuNTE2LDAsNDEuODg0LTcuNDk5LDYwLjUxNS0yMi4yOThsMC42MjYtMC40OTYKCQljMTguMDMxLTE1LjMwMywyOS40NjctMzMuOTc1LDMzLjg5OC01NS42MTFMNTA5LjE5OSw2NjFoMC4wOTZsMC4xNTYtMUg2ODMuMzVjMTUuNTgsMCwzMS4zMTctMS41ODUsNDcuMTE1LTQuNDcKCQljMS45NTUtMC4zMTIsMy44OTMtMC43MjYsNS44MzEtMS4wODNjMC4yOTUtMC4wNjEsMC41ODItMC4xMDMsMC44NzctMC4xNzJjMTYuOTQ1LTMuMjQxLDMzLjYxMi04LjU3MSw1My41MjEtMTYuOTMxCgkJYzM1LjE0OS0xNC44NzYsNjcuOTk2LTM2LjA4MSw5Ny40ODktNjIuODljNjMuMjg3LTU2LjU5NiwxMDQuMjA2LTEyNi4wNjIsMTIxLjY0Ni0yMDYuNTYzCgkJQzEwMjcuMTU2LDI4Ni4yNTcsMTAxNS4zMDQsMjE0LjgxOCw5NzQuNjAyLDE1NS42MnogTTg0NC4zMzQsMzU3LjU5Yy04LjI2NCwzNy41NTctMjYuMTksNjkuMzkxLTU0LjgzMSw5Ny4zMTEKCQljLTQuODE0LDQuNTQ5LTkuNjAzLDguNTYzLTE0LjM5OSwxMi4zMDhDNzUzLjA0MSw0ODQuMDUsNzMxLjA1Niw0OTIsNzA4LjM3Niw0OTJoLTE2My4yMWwyNi4zMDQtMTIzLjcxOWwxNS44MTUtNzQuMzU5CgkJYzYuMTA4LTE2LjE0MSwxNi41NTQtMzAuMzQ5LDMxLjE5NS00Mi4yNTRsMC42LTAuMjY5QzYzMi42NzEsMjQxLjA0MSw2NDcuMjE4LDIzNCw2NjIuNDQyLDIzMmgxMDkuMjkKCQljMjYuNDQyLDAsNDUuNjgyLDEwLjczLDYwLjU1OCwzNC4yNEM4NDcuOTIzLDI5Mi4wMzYsODUxLjgzNCwzMjEuODU4LDg0NC4zMzQsMzU3LjU5eiIKICAgICAgICAvPgogICAgICAgIDxwYXRoCiAgICAgICAgICAgIGQ9Ik0zNTUuMzEzLDE4Mi42MzZsLTAuMzgyLTAuNDg2Yy04LjgzOC0xMC44NDUtMjEuNDczLTE2LjU4LTM2LjU1OC0xNi41OGMtMTMuNzM4LDAtMjYuNzIxLDQuNzg0LTM4LjYxNywxNC4yMjEKCQlsLTAuMzgyLDAuMzE3Yy0xMS41MTUsOS43NjMtMTguODA1LDIxLjc3NC0yMS42MiwzNS41ODJMMjI2Ljc4NSwzNjJoLTAuMjA5bC04Mi4yMywzODYuODY3CgkJYy0zLjAzMiwxNC43MzYtMC4yNjEsMjguMzUzLDguMDIxLDM5LjM3MWwwLjM4MywwLjQ5MWM4LjgyOCwxMC44NTMsMjEuNDcyLDE2LjU2NywzNi41NTcsMTYuNTY3YzEzLjczOSwwLDI2LjcyOS00Ljc4LDM4LjYtMTQuMjE4CgkJbDAuMzkyLTAuMzJjMTEuNTE0LTkuNzYsMTguNzg3LTIxLjcwNiwyMS42MzctMzUuNTE0TDI4MC45MDQsNjA5aDAuMTk5bDgyLjIzOS0zODYuOTI4CgkJQzM2Ni4zNjcsMjA3LjMzLDM2My41ODYsMTkzLjY2NywzNTUuMzEzLDE4Mi42MzZ6IgogICAgICAgIC8+CiAgICAgICAgPHBhdGgKICAgICAgICAgICAgZD0iTTE2OC40OTQsMjQwLjVsLTAuMzM5LTAuNDNjLTcuNzI1LTkuNTA2LTE4LjgyMS0xNC41MzMtMzIuMDM5LTE0LjUzM2MtMTIuMDM1LDAtMjMuNDE4LDQuMTkyLTMzLjgzNywxMi40NjFsLTAuMzQ4LDAuMjgyCgkJYy0xMC4wOCw4LjU2LTE2LjQ3NiwxOC44NDItMTguOTUyLDMwLjk0Mkw3OC4zMjEsMjg3aC0wLjE3NEw2LjA3NSw2MjYuMzI5Yy0yLjY1OSwxMi45MzEtMC4yMjYsMjQuOTc2LDcuMDIxLDM0LjY0N2wwLjMzOSwwLjQ4MgoJCWM3LjczNCw5LjUwNiwxOC44MzEsMTQuNTU4LDMyLjA0OCwxNC41NThjMTIuMDQ0LDAsMjMuNDI3LTQuMTc1LDMzLjgzOC0xMi40NTZsMC4zMzktMC4yNjJjMTAuMDk3LTguNTYsMTYuNDc2LTE5LjAyLDE4Ljk2LTMxLjEzMwoJCUwxMjUuNzY4LDUwNGgwLjE4Mmw0OS41ODQtMjI4LjkzN0MxNzguMTg0LDI2Mi4xNDEsMTc1Ljc1OSwyNTAuMTY0LDE2OC40OTQsMjQwLjV6IgogICAgICAgIC8+CiAgICA8L3N2Zz4=';

		add_menu_page(
			'Pushly',
			'Pushly',
			'manage_options',
			'pushly',
			[ $this, 'menu_page_html' ],
			$icon
		);
	}

	/**
	 * Renders temporary placeholder HTML that will be replaced with the React form after load
	 *
	 * @return void
	 */
	public function menu_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="wrap" id="pushly-settings">%s</div>',
			esc_html__( 'Loading…', 'pushly' )
		);
	}

	/**
	 * Enqueues the JS and CSS fields required to display the Settings page.
	 *
	 * @param string $admin_page The page that user is currently visiting
	 */
	public function enqueue_scripts( $admin_page ) {
		if ( 'toplevel_page_pushly' !== $admin_page ) {
			// we don't need to do this unless the user is actually on the Settings page
			return;
		}

		$asset_file = PUSHLY__DIR_BUILD . '/settings.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = require_once $asset_file;

			wp_enqueue_script(
				'pushly-script',
				plugins_url( 'build/settings.js', PUSHLY__DIR_BUILD ),
				$asset['dependencies'],
				$asset['version'],
				[
					'in_footer' => true,
				]
			);

			// adds environment info to the window
			wp_add_inline_script(
				'pushly-script',
				'const pushly_env = ' . json_encode( [
					'CDN_DOMAIN' => PUSHLY__CDN_DOMAIN,
					'API_NONCE'  => wp_create_nonce( 'wp_rest' ),
				] ),
				'before'
			);

			wp_enqueue_style(
				'pushly-style',
				plugins_url( 'build/settings.css', PUSHLY__DIR_BUILD ),
				array_filter(
					$asset['dependencies'],
					function ( $style ) {
						return wp_style_is( $style, 'registered' );
					}
				),
				$asset['version']
			);
		}
	}

	/**
	 * Adds a "settings" and "support" link to the plugin row on the Plugins page
	 *
	 * @param string[] $actions The default actions that are automatically included
	 *
	 * @return string[]
	 */
	public function add_links_to_plugin( $actions ) {
		$settings = [ 'settings' => '<a href="' . admin_url( 'admin.php?page=pushly' ) . '">' . __( 'Settings', 'pushly' ) . '</a>' ];

		return array_merge( $actions, $settings );
	}

	/**
	 * Registers the plugin settings that will be used from the Settings page.
	 *
	 * TODO: Document settings
	 */
	public function register_settings() {
		$new_options = [];

		// this migrates from the v1 Pushly plugin options store
		$legacy_options = get_option( 'pushly_options' );
		if ( ! empty( $legacy_options ) ) {
			delete_option( 'pushly_options' );

			if ( ! empty( $legacy_options['pushly_domain_key'] ) ) {
				$new_options['sdk_key'] = $legacy_options['pushly_domain_key'];
			}
		}

		// any new settings that are added should be defaulted here
		$current_options = get_option( 'pushly', [] );
		if ( empty( $current_options['enabled_post_types'] ) ) {
			$new_options['enabled_post_types'] = [ 'post' ];
		}

		if ( ! empty( $new_options ) ) {
			update_option( 'pushly', array_merge( $new_options, $current_options ) );
		}

		$schema = [
			'type'       => 'object',
			'properties' => [
				'domain_id'                 => [
					'type' => 'integer',
				],
				'sdk_key'                   => [
					'type' => 'string',
				],
				'api_key'                   => [
					'type' => [ 'string', 'null' ],
				],
				'sending_enabled'           => [
					'type' => 'boolean',
				],
				'auto_send_enabled'         => [
					'type' => 'boolean',
				],
				'enabled_post_types'        => [
					'type' => 'array'
				],
				'initialization_disabled'   => [
					'type' => 'boolean',
				],
				'show_post_success_message' => [
					'type' => 'boolean',
				],
			],
		];

		register_setting(
			'options',
			'pushly',
			[
				'type'              => 'object',
				'show_in_rest'      => [
					'schema' => $schema,
				],
				'sanitize_callback' => function ( $input ) {
					$current_options    = get_option( 'pushly' );
					$input['domain_id'] = filter_var( $input['domain_id'], FILTER_SANITIZE_NUMBER_INT );
					$input['sdk_key']   = sanitize_text_field( $input['sdk_key'] );

					if ( ! empty( $input['sending_enabled'] ) ) {
						if ( empty( $input['api_key'] ) ) {
							return new WP_Error(
								'missing_required_property',
								'API key must be provided when sending is enabled.',
								[ 'status' => 400 ]
							);
						}

						// if the passed in API key is the same as what's already set then its already encrypted
						if ( ! empty( $current_options ) && $current_options['api_key'] !== $input['api_key'] ) {
							$input['api_key'] = Pushly_Admin_Util::encrypt_api_key(
								$input['sdk_key'],
								$input['sdk_key'],
								$input['api_key']
							);
						}
					}

					return $input;
				},
			]
		);
	}

	public function register_settings_errors() {
		settings_errors( 'pushly' );
	}
}