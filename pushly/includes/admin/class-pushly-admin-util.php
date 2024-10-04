<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pushly_Admin_Util {
	private static $_settings;
	private static $_options;

	public static function get_api_options() {
		$options = get_option( 'pushly' );
		if ( empty( $options['api_key'] ) ) {
			Pushly_Admin_Util::log_to_event_stream( "no_api_key", "Settings does not contain `api_key`." );
		} else {
			return $options;
		}
	}

	public static function encrypt_api_key( $salt, $passphrase, $value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $value;
		}

		$method = 'aes-256-ctr';
		$iv_len = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $iv_len );

		$raw_value = openssl_encrypt( $value . $salt, $method, $passphrase, 0, $iv );
		if ( ! $raw_value ) {
			Pushly_Admin_Util::log_to_event_stream( "encrypt_api_key_failed", "Failed to encrypt API key." );
		}

		return base64_encode( $iv . $raw_value );
	}

	public static function decrypt_api_key( $salt, $passphrase, $value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $value;
		}

		$raw_value = base64_decode( $value, true );

		$method = 'aes-256-ctr';
		$iv_len = openssl_cipher_iv_length( $method );
		$iv     = substr( $raw_value, 0, $iv_len );

		$raw_value = substr( $raw_value, $iv_len );

		$value = openssl_decrypt( $raw_value, $method, $passphrase, 0, $iv );
		if ( ! $value || substr( $value, - strlen( $salt ) ) !== $salt ) {
			Pushly_Admin_Util::log_to_event_stream( "decrypt_api_key_failed", "Failed to decrypt API key." );

			return false;
		}

		return substr( $value, 0, - strlen( $salt ) );
	}

	public static function log_to_event_stream( $error_type, $error_message, $data = null ) {
		try {
			if ( empty( self::$_options ) ) {
				self::$_options = get_option( 'pushly' );
			}

			if ( ! empty( self::$_options["sdk_key"] ) && ! empty( self::$_options["domain_id"] ) ) {
				if ( empty( self::$_settings ) ) {
					$settings_request = wp_remote_get( "https://" . PUSHLY__CDN_DOMAIN . "/domain-settings/" . self::$_options["sdk_key"] );
					if ( is_wp_error( $settings_request ) ) {
						return;
					}

					self::$_settings = json_decode( wp_remote_retrieve_body( $settings_request ), true );
				}

				if ( ! empty( self::$_settings["domain"]["flags"] ) && in_array( "WORDPRESS_DEBUG_EVENTS", self::$_settings["domain"]["flags"] ) ) {
					global $wp_version;

					if ( ! empty( $data ) ) {
						$error_message .= " (" . serialize( $data ) . ")";
					}

					$payload = [
						"domain_id" => self::$_options["domain_id"],
						"action"    => "error",
						"data"      => [
							"error_type"    => "wordpress_{$error_type}",
							"error_message" => $error_message
						],
						"meta"      => [
							"application" => [
								"identifier" => "wordpress",
								"version"    => $wp_version
							],
							"sdk"         => [
								"name"    => "pushly-wordpress-plugin",
								"version" => PUSHLY__PLUGIN_VERSION
							],
							"event"       => [
								"version" => 3
							]
						]
					];

					wp_remote_request( "https://" . PUSHLY__K_DOMAIN . "/event-stream", [
						'method' => 'POST',
						'body'   => wp_json_encode( $payload ),
					] );
				}
			}
		} catch ( Exception $e ) {
			// nothing to do
		}
	}
}