<?php

namespace Pushly\Admin;

class Util {
	private static ?array $_settings = null;
	private static ?array $_options = null;

	/**
	 * Returns the full options array if an API key is configured, otherwise null.
	 */
	public static function get_api_options(): ?array {
		$options = get_option( 'pushly' );
		if ( empty( $options['api_key'] ) ) {
			self::log_to_event_stream( 'no_api_key', 'Settings does not contain `api_key`.' );
			return null;
		}

		return $options;
	}

	/**
	 * Encrypts a value using AES-256-CTR with a random IV.
	 * Returns the original value unchanged if OpenSSL is unavailable.
	 */
	public static function encrypt_api_key( string $salt, string $passphrase, string $value ): string {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $value;
		}

		$method = 'aes-256-ctr';
		$iv_len = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $iv_len );

		$raw_value = openssl_encrypt( $value . $salt, $method, $passphrase, 0, $iv );
		if ( ! $raw_value ) {
			self::log_to_event_stream( 'encrypt_api_key_failed', 'Failed to encrypt API key.' );
		}

		return base64_encode( $iv . $raw_value );
	}

	/**
	 * Decrypts an AES-256-CTR encrypted value and validates the salt.
	 * Returns false if decryption fails or the salt does not match.
	 *
	 * @return string|false
	 */
	public static function decrypt_api_key( string $salt, string $passphrase, string $value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $value;
		}

		$raw_value = base64_decode( $value, true );
		$method    = 'aes-256-ctr';
		$iv_len    = openssl_cipher_iv_length( $method );
		$iv        = substr( $raw_value, 0, $iv_len );
		$raw_value = substr( $raw_value, $iv_len );

		$decrypted = openssl_decrypt( $raw_value, $method, $passphrase, 0, $iv );
		if ( ! $decrypted || substr( $decrypted, -strlen( $salt ) ) !== $salt ) {
			self::log_to_event_stream( 'decrypt_api_key_failed', 'Failed to decrypt API key.' );
			return false;
		}

		return substr( $decrypted, 0, -strlen( $salt ) );
	}

	/**
	 * Sends a structured debug event to the Pushly event stream.
	 * Only fires when the WORDPRESS_DEBUG_EVENTS flag is enabled on the domain.
	 */
	public static function log_to_event_stream( string $error_type, string $error_message, $data = null ): void {
		try {
			if ( self::$_options === null ) {
				self::$_options = get_option( 'pushly' ) ?: [];
			}

			if ( empty( self::$_options['sdk_key'] ) || empty( self::$_options['domain_id'] ) ) {
				return;
			}

			if ( self::$_settings === null ) {
				// Cache domain settings for 5 minutes to avoid an HTTP call on every log
				$cache_key = 'pushly_domain_settings_' . md5( self::$_options['sdk_key'] );
				$cached    = get_transient( $cache_key );

				if ( $cached !== false ) {
					self::$_settings = $cached;
				} else {
					$request = wp_remote_get( 'https://' . PUSHLY__CDN_DOMAIN . '/domain-settings/' . self::$_options['sdk_key'] );
					if ( is_wp_error( $request ) ) {
						return;
					}

					self::$_settings = json_decode( wp_remote_retrieve_body( $request ), true ) ?: [];
					set_transient( $cache_key, self::$_settings, 5 * MINUTE_IN_SECONDS );
				}
			}

			if ( empty( self::$_settings['domain']['flags'] ) || ! in_array( 'WORDPRESS_DEBUG_EVENTS', self::$_settings['domain']['flags'], true ) ) {
				return;
			}

			global $wp_version;

			if ( $data !== null ) {
				$error_message .= ' (' . serialize( $data ) . ')';
			}

			$payload = [
				'domain_id' => self::$_options['domain_id'],
				'action'    => 'error',
				'data'      => [
					'error_type'    => "wordpress_{$error_type}",
					'error_message' => $error_message,
				],
				'meta'      => [
					'application' => [
						'identifier' => 'wordpress',
						'version'    => $wp_version,
					],
					'sdk'         => [
						'name'    => 'pushly-wordpress-plugin',
						'version' => PUSHLY__PLUGIN_VERSION,
					],
					'event'       => [ 'version' => 3 ],
				],
			];

			wp_remote_request( 'https://' . PUSHLY__K_DOMAIN . '/event-stream', [
				'method' => 'POST',
				'body'   => wp_json_encode( $payload ),
			] );
		} catch ( \Exception $e ) {
			// Intentionally silent — logging must never cause a visible error
		}
	}
}
