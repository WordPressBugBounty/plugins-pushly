<?php

namespace Pushly\Admin;

class Settings {
	private const MENU_ICON = 'data:image/svg+xml;base64,ICAgIDxzdmcKICAgICAgICB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiAgICAgICAgdmlld0JveD0iMCAwIDEwMjQgMTAyNCIKICAgICAgICBkYXRhLWljb249InB1c2hseSIKICAgICAgICBoZWlnaHQ9IjEwMjQiCiAgICAgICAgd2lkdGg9IjEwMjQiCiAgICAgICAgZmlsbD0iIzRkYmNhMiIKICAgICAgICBhcmlhLWhpZGRlbj0idHJ1ZSIKICAgID4KICAgICAgICA8cGF0aAogICAgICAgICAgICBkPSJNOTc0LjYwMiwxNTUuNjJjLTE5LjQ1Ni0yOC40NjctNDQuNzI2LTUwLjgyMy03NS4xMzEtNjYuNTA0Qzg2OC4zNyw3My4xMDEsODMzLjg5LDY1LDc5Ni45NCw2NUg2MjcuNTcKCQljLTEzMy40MzgsMC0xOTEuMzEyLDE1MC41MDMtMTk1Ljc1MywxNzIuMTAxbC0xMzcuMzQsNjMzLjU1N2MtNC43NDUsMjMuMTA1LTAuNDA4LDQ0LjQ3LDEyLjU4Miw2MS43NjJsMC41ODMsMC43NTQKCQljMTMuODUxLDE3LjAxNiwzMy42ODEsMjUuOTk5LDU3LjM1MiwyNS45OTljMjEuNTE2LDAsNDEuODg0LTcuNDk5LDYwLjUxNS0yMi4yOThsMC42MjYtMC40OTYKCQljMTguMDMxLTE1LjMwMywyOS40NjctMzMuOTc1LDMzLjg5OC01NS42MTFMNTA5LjE5OSw2NjFoMC4wOTZsMC4xNTYtMUg2ODMuMzVjMTUuNTgsMCwzMS4zMTctMS41ODUsNDcuMTE1LTQuNDcKCQljMS45NTUtMC4zMTIsMy44OTMtMC43MjYsNS44MzEtMS4wODNjMC4yOTUtMC4wNjEsMC41ODItMC4xMDMsMC44NzctMC4xNzJjMTYuOTQ1LTMuMjQxLDMzLjYxMi04LjU3MSw1My41MjEtMTYuOTMxCgkJYzM1LjE0OS0xNC44NzYsNjcuOTk2LTM2LjA4MSw5Ny40ODktNjIuODljNjMuMjg3LTU2LjU5NiwxMDQuMjA2LTEyNi4wNjIsMTIxLjY0Ni0yMDYuNTYzCgkJQzEwMjcuMTU2LDI4Ni4yNTcsMTAxNS4zMDQsMjE0LjgxOCw5NzQuNjAyLDE1NS42MnogTTg0NC4zMzQsMzU3LjU5Yy04LjI2NCwzNy41NTctMjYuMTksNjkuMzkxLTU0LjgzMSw5Ny4zMTEKCQljLTQuODE0LDQuNTQ5LTkuNjAzLDguNTYzLTE0LjM5OSwxMi4zMDhDNzUzLjA0MSw0ODQuMDUsNzMxLjA1Niw0OTIsNzA4LjM3Niw0OTJoLTE2My4yMWwyNi4zMDQtMTIzLjcxOWwxNS44MTUtNzQuMzU5CgkJYzYuMTA4LTE2LjE0MSwxNi41NTQtMzAuMzQ5LDMxLjE5NS00Mi4yNTRsMC42LTAuMjY5QzYzMi42NzEsMjQxLjA0MSw2NDcuMjE4LDIzNCw2NjIuNDQyLDIzMmgxMDkuMjkKCQljMjYuNDQyLDAsNDUuNjgyLDEwLjczLDYwLjU1OCwzNC4yNEM4NDcuOTIzLDI5Mi4wMzYsODUxLjgzNCwzMjEuODU4LDg0NC4zMzQsMzU3LjU5eiIKICAgICAgICAvPgogICAgICAgIDxwYXRoCiAgICAgICAgICAgIGQ9Ik0zNTUuMzEzLDE4Mi42MzZsLTAuMzgyLTAuNDg2Yy04LjgzOC0xMC44NDUtMjEuNDczLTE2LjU4LTM2LjU1OC0xNi41OGMtMTMuNzM4LDAtMjYuNzIxLDQuNzg0LTM4LjYxNywxNC4yMjEKCQlsLTAuMzgyLDAuMzE3Yy0xMS41MTUsOS43NjMtMTguODA1LDIxLjc3NC0yMS42MiwzNS41ODJMMjI2Ljc4NSwzNjJoLTAuMjA5bC04Mi4yMywzODYuODY3CgkJYy0zLjAzMiwxNC43MzYtMC4yNjEsMjguMzUzLDguMDIxLDM5LjM3MWwwLjM4MywwLjQ5MWM4LjgyOCwxMC44NTMsMjEuNDcyLDE2LjU2NywzNi41NTcsMTYuNTY3YzEzLjczOSwwLDI2LjcyOS00Ljc4LDM4LjYtMTQuMjE4CgkJbDAuMzkyLTAuMzJjMTEuNTE0LTkuNzYsMTguNzg3LTIxLjcwNiwyMS42MzctMzUuNTE0TDI4MC45MDQsNjA5aDAuMTk5bDgyLjIzOS0zODYuOTI4CgkJQzM2Ni4zNjcsMjA3LjMzLDM2My41ODYsMTkzLjY2NywzNTUuMzEzLDE4Mi42MzZ6IgogICAgICAgIC8+CiAgICAgICAgPHBhdGgKICAgICAgICAgICAgZD0iTTE2OC40OTQsMjQwLjVsLTAuMzM5LTAuNDNjLTcuNzI1LTkuNTA2LTE4LjgyMS0xNC41MzMtMzIuMDM5LTE0LjUzM2MtMTIuMDM1LDAtMjMuNDE4LDQuMTkyLTMzLjgzNywxMi40NjFsLTAuMzQ4LDAuMjgyCgkJYy0xMC4wOCw4LjU2LTE2LjQ3NiwxOC44NDItMTguOTUyLDMwLjk0Mkw3OC4zMjEsMjg3aC0wLjE3NEw2LjA3NSw2MjYuMzI5Yy0yLjY1OSwxMi45MzEtMC4yMjYsMjQuOTc2LDcuMDIxLDM0LjY0N2wwLjMzOSwwLjQ4MgoJCWM3LjczNCw5LjUwNiwxOC44MzEsMTQuNTU4LDMyLjA0OCwxNC41NThjMTIuMDQ0LDAsMjMuNDI3LTQuMTc1LDMzLjgzOC0xMi40NTZsMC4zMzktMC4yNjJjMTAuMDk3LTguNTYsMTYuNDc2LTE5LjAyLDE4Ljk2LTMxLjEzMwoJCUwxMjUuNzY4LDUwNGgwLjE4Mmw0OS41ODQtMjI4LjkzN0MxNzguMTg0LDI2Mi4xNDEsMTc1Ljc1OSwyNTAuMTY0LDE2OC40OTQsMjQwLjV6IgogICAgICAgIC8+CiAgICA8L3N2Zz4=';

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'maybe_migrate_options' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'register_settings_errors' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action(
			'plugin_action_links_' . plugin_basename( PUSHLY__DIR . '/pushly.php' ),
			[ $this, 'add_links_to_plugin' ]
		);
	}

	public function add_menu_page(): void {
		add_menu_page(
			'Pushly',
			'Pushly',
			'manage_options',
			'pushly',
			[ $this, 'menu_page_html' ],
			self::MENU_ICON
		);
	}

	public function menu_page_html(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="wrap" id="pushly-settings">%s</div>',
			esc_html__( 'Loading…', 'pushly' )
		);
	}

	public function enqueue_scripts( string $admin_page ): void {
		if ( 'toplevel_page_pushly' !== $admin_page ) {
			return;
		}

		$asset_file = PUSHLY__DIR_BUILD . '/settings.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'pushly-script',
			plugins_url( 'build/settings.js', PUSHLY__DIR_BUILD ),
			$asset['dependencies'],
			$asset['version'],
			[ 'in_footer' => true ]
		);

		$options = get_option( 'pushly', [] );

		wp_add_inline_script(
			'pushly-script',
			'const pushly_env = ' . wp_json_encode( [
				'CDN_DOMAIN'            => PUSHLY__CDN_DOMAIN,
				'API_NONCE'             => wp_create_nonce( 'wp_rest' ),
				'debug_logging_enabled' => ! empty( $options['debug_logging_enabled'] ),
			] ),
			'before'
		);

		wp_enqueue_style(
			'pushly-style',
			plugins_url( 'build/settings.css', PUSHLY__DIR_BUILD ),
			array_filter( $asset['dependencies'], fn( $style ) => wp_style_is( $style, 'registered' ) ),
			$asset['version']
		);
	}

	/**
	 * @param string[] $actions
	 * @return string[]
	 */
	public function add_links_to_plugin( array $actions ): array {
		return array_merge( $actions, [
			'settings' => '<a href="' . admin_url( 'admin.php?page=pushly' ) . '">' . __( 'Settings', 'pushly' ) . '</a>',
		] );
	}

	/**
	 * One-time option migrations. Hooked to admin_init only so DB writes
	 * never happen on REST requests.
	 */
	public function maybe_migrate_options(): void {
		$new_options = [];

		$legacy_options = get_option( 'pushly_options' );
		if ( ! empty( $legacy_options ) ) {
			delete_option( 'pushly_options' );
			if ( ! empty( $legacy_options['pushly_domain_key'] ) ) {
				$new_options['sdk_key'] = $legacy_options['pushly_domain_key'];
			}
		}

		$current_options = get_option( 'pushly', [] );
		if ( empty( $current_options['enabled_post_types'] ) ) {
			$new_options['enabled_post_types'] = [ 'post' ];
		}

		if ( ! empty( $new_options ) ) {
			update_option( 'pushly', array_merge( $new_options, $current_options ) );
		}
	}

	public function register_settings(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'domain_id'               => [ 'type' => 'integer' ],
				'sdk_key'                 => [ 'type' => 'string' ],
				'api_key'                 => [ 'type' => [ 'string', 'null' ] ],
				'sending_enabled'         => [ 'type' => 'boolean' ],
				'auto_send_enabled'       => [ 'type' => 'boolean' ],
				'enabled_post_types'      => [ 'type' => 'array' ],
				'initialization_disabled' => [ 'type' => 'boolean' ],
				'debug_logging_enabled'   => [ 'type' => 'boolean' ],
			],
		];

		register_setting(
			'options',
			'pushly',
			[
				'type'              => 'object',
				'show_in_rest'      => [ 'schema' => $schema ],
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function sanitize_settings( array $input ) {
		$current_options    = get_option( 'pushly', [] );
		$input['domain_id'] = (int) filter_var( $input['domain_id'], FILTER_SANITIZE_NUMBER_INT );
		$input['sdk_key']   = sanitize_text_field( $input['sdk_key'] );

		// Detect debug_logging_enabled transition from false → true and write environment snapshot.
		$was_enabled = ! empty( $current_options['debug_logging_enabled'] );
		$now_enabled = ! empty( $input['debug_logging_enabled'] );

		if ( $now_enabled && ! $was_enabled ) {
			try {
				$sdk_key = $input['sdk_key'] ?? '';
				if ( strlen( $sdk_key ) >= 4 ) {
					$masked = str_repeat( '*', strlen( $sdk_key ) - 4 ) . substr( $sdk_key, -4 );
				} else {
					$masked = str_repeat( '*', strlen( $sdk_key ) );
				}

				$snapshot_context = [
					'plugin_version'           => PUSHLY__PLUGIN_VERSION,
					'wordpress_version'        => get_bloginfo( 'version' ),
					'php_version'              => phpversion(),
					'sdk_key'                  => $masked,
					'api_key_configured'       => ! empty( $input['api_key'] ),
					'sending_enabled'          => ! empty( $input['sending_enabled'] ),
					'auto_send_enabled'        => ! empty( $input['auto_send_enabled'] ),
					'enabled_post_types'       => $input['enabled_post_types'] ?? [],
					'debug_logging_enabled_at' => gmdate( 'c' ),
					'active_theme'             => wp_get_theme()->get( 'Name' ),
					'multisite'                => is_multisite(),
				];

				$log_store  = new LogStore();
				$log_writer = new LogWriter( $log_store, true );
				$log_writer->log_immediate(
					'info',
					'environment_snapshot',
					'Environment snapshot: Captured — Debug logging enabled at ' . gmdate( 'c' ),
					null,
					$snapshot_context
				);
			} catch ( \Exception $e ) {
				// Silently suppress — settings save must proceed even if snapshot fails.
			}
		}

		if ( ! empty( $input['sending_enabled'] ) ) {
			if ( empty( $input['api_key'] ) ) {
				return new \WP_Error(
					'missing_required_property',
					'API key must be provided when sending is enabled.',
					[ 'status' => 400 ]
				);
			}

			// Encrypt the API key if it's new or has changed from the stored value.
			// On first save, current_options['api_key'] is empty so we always encrypt.
			// On subsequent saves, we only re-encrypt if the user entered a different key.
			$stored_key = $current_options['api_key'] ?? '';
			if ( empty( $stored_key ) || $stored_key !== $input['api_key'] ) {
				$input['api_key'] = Util::encrypt_api_key(
					$input['sdk_key'],
					$input['sdk_key'],
					$input['api_key']
				);
			}
		}

		return $input;
	}

	public function register_settings_errors(): void {
		settings_errors( 'pushly' );
	}
}
