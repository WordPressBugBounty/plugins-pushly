<?php

namespace Pushly\Admin;

use Pushly\Models\Notification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post {
	private array $options;

	private ?LogWriter $log_writer;

	public function __construct( array $options, ?LogWriter $log_writer = null ) {
		$this->options    = $options;
		$this->log_writer = $log_writer;
	}

	/**
	 * Delegates to LogWriter::log() when a LogWriter is available, otherwise no-ops.
	 *
	 * @param string     $severity   One of: debug, info, warning, error.
	 * @param string     $event_type Machine-readable event identifier.
	 * @param string     $message    Human-readable message.
	 * @param int|null   $post_id    Associated post ID, if applicable.
	 * @param array|null $context    Additional structured data.
	 */
	private function log( string $severity, string $event_type, string $message, ?int $post_id = null, ?array $context = null ): void {
		if ( $this->log_writer === null ) {
			return;
		}

		$this->log_writer->log( $severity, $event_type, $message, $post_id, $context );
	}

	/**
	 * Builds the standard log message prefix for send-flow log entries.
	 *
	 * Format: "Post '{title}' (ID: {id}, type: {type}, editor: {editor})"
	 */
	private function build_log_prefix( \WP_Post $post ): string {
		$editor = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? 'gutenberg' : 'classic';

		return sprintf(
			"Post '%s' (ID: %d, type: %s, editor: %s)",
			$post->post_title,
			$post->ID,
			$post->post_type,
			$editor
		);
	}

	public function register_hooks(): void {
		if ( ! $this->is_sending_configured() ) {
			return;
		}

		add_action( 'admin_init', [ $this, 'register_post_meta' ] );
		add_action( 'rest_api_init', [ $this, 'register_post_meta' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_meta_box_assets_for_gutenberg' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_meta_box_assets_for_classic' ] );
		add_action( 'transition_post_status', [ $this, 'transition_post_status' ], 10, 3 );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box_for_classic' ] );
		add_action( 'admin_notices', [ $this, 'emit_notice' ] );
		add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
	}

	/**
	 * Returns true when all required settings for notification sending are present.
	 */
	private function is_sending_configured(): bool {
		return ! empty( $this->options['sdk_key'] )
			&& ! empty( $this->options['sending_enabled'] )
			&& ! empty( $this->options['api_key'] );
	}

	// -------------------------------------------------------------------------
	// Meta Registration
	// -------------------------------------------------------------------------

	public function register_post_meta(): void {
		if ( empty( $this->options['enabled_post_types'] ) ) {
			return;
		}

		foreach ( $this->options['enabled_post_types'] as $post_type ) {
			register_post_meta( $post_type, 'pushly_notification_id', [
				'single'       => true,
				'type'         => 'string',
				'show_in_rest' => true,
			] );

			register_post_meta( $post_type, 'pushly_send_notification', [
				'single'            => true,
				'type'              => 'boolean',
				'default'           => ! empty( $this->options['auto_send_enabled'] ),
				'show_in_rest'      => true,
				'sanitize_callback' => 'wp_validate_boolean',
			] );

			register_post_meta( $post_type, 'pushly_customize_notification_content', [
				'single'            => true,
				'type'              => 'boolean',
				'show_in_rest'      => true,
				'sanitize_callback' => 'wp_validate_boolean',
			] );

			register_post_meta( $post_type, 'pushly_custom_title', [
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			] );

			register_post_meta( $post_type, 'pushly_custom_body', [
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			] );

			register_post_meta( $post_type, 'pushly_customize_audience', [
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'wp_validate_boolean',
			] );

			register_post_meta( $post_type, 'pushly_audience_ids', [
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
			] );
		}
	}

	// -------------------------------------------------------------------------
	// Asset Enqueueing
	// -------------------------------------------------------------------------

	public function enqueue_meta_box_assets_for_gutenberg(): void {
		if ( ! $this->is_enabled_post_screen() ) {
			return;
		}

		$asset_file = PUSHLY__DIR_BUILD . '/meta-box.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'pushly',
			plugins_url( 'build/meta-box.js', PUSHLY__DIR_BUILD ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'pushly-style',
			plugins_url( 'build/meta-box.css', PUSHLY__DIR_BUILD ),
			array_filter( $asset['dependencies'], fn( $style ) => wp_style_is( $style, 'registered' ) ),
			$asset['version']
		);
	}

	public function enqueue_meta_box_assets_for_classic(): void {
		if ( ! $this->is_enabled_post_screen() || $this->is_using_gutenberg() ) {
			return;
		}

		wp_enqueue_script(
			'pushly-classic',
			plugins_url( 'includes/admin/views/classic/meta-box.js', PUSHLY__DIR . '/pushly.php' ),
			[ 'jquery' ],
			PUSHLY__PLUGIN_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Classic Editor Meta Box
	// -------------------------------------------------------------------------

	public function add_meta_box_for_classic(): void {
		if ( empty( $this->options['enabled_post_types'] ) ) {
			return;
		}

		add_meta_box(
			'pushly_meta_box',
			__( 'Pushly Notifications', 'pushly' ),
			[ $this, 'build_classic_meta_box' ],
			$this->options['enabled_post_types'],
			'side',
			'default',
			[ '__back_compat_meta_box' => true ]
		);
	}

	public function build_classic_meta_box( \WP_Post $post ): void {
		$meta = $this->get_post_meta( $post->ID );

		$send_notification              = $meta['pushly_send_notification'] ?? ! empty( $this->options['auto_send_enabled'] );
		$customize_notification_content = ! empty( $meta['pushly_customize_notification_content'] );
		$custom_title                   = $meta['pushly_custom_title'] ?? null;
		$custom_body                    = $meta['pushly_custom_body'] ?? null;

		require_once PUSHLY__DIR . '/includes/admin/views/classic/meta-box.php';
		pushly_render_classic_meta_box(
			$send_notification,
			$customize_notification_content,
			$custom_title,
			$custom_body
		);
	}

	// -------------------------------------------------------------------------
	// Notification Send Flow
	// -------------------------------------------------------------------------

	public function transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( empty( $this->options['enabled_post_types'] ) ) {
			Util::log_to_event_stream( 'empty_enabled_post_types', 'Did not send notification due to empty enabled_post_types.' );
			return;
		}

		if ( ! in_array( $post->post_type, $this->options['enabled_post_types'], true ) ) {
			if ( in_array( $new_status, [ 'publish', 'future' ], true ) ) {
				Util::log_to_event_stream( 'disabled_post_type', "Did not send notification due to `{$post->post_type}` not being an enabled post type." );
			}
			return;
		}

		$this->on_should_save_notification( $post, $old_status, $new_status );
	}

	/**
	 * Registers the appropriate late-firing hook based on whether the request
	 * is a REST call (Gutenberg) or a classic form POST.
	 *
	 * rest_after_insert_{post_type} fires after WordPress has committed all meta to the DB.
	 * save_post fires after meta boxes have saved in the classic editor.
	 */
	private function on_should_save_notification( \WP_Post $post, string $old_status, string $new_status ): void {
		if ( $old_status === 'trash' ) {
			return;
		}

		if ( ! in_array( $new_status, [ 'publish', 'future' ], true ) ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			add_action( "rest_after_insert_{$post->post_type}", [ $this, 'save_notification_from_post' ], 99, 2 );
		} else {
			$post_id = $post->ID;
			add_action( 'save_post', function ( int $saved_post_id ) use ( $post_id ): void {
				if ( $saved_post_id === $post_id ) {
					$post = get_post( $saved_post_id );
					if ( $post ) {
						$this->save_notification_from_post( $post );
					}
				}
			}, 999 );
		}
	}

	public function save_notification_from_post( \WP_Post $post, ?\WP_REST_Request $request = null ): void {
		$prefix = $this->build_log_prefix( $post );

		try {
			// Log send flow start
			$this->log(
				'info',
				'send_flow_start',
				"{$prefix}: Send flow started",
				$post->ID
			);

			// Verify nonce for classic editor requests
			if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				$nonce = isset( $_POST['pushly_meta_box_nonce'] )
					? sanitize_text_field( wp_unslash( $_POST['pushly_meta_box_nonce'] ) )
					: '';
				if ( ! wp_verify_nonce( $nonce, 'pushly_save_notification_meta_box' ) ) {
					Util::log_to_event_stream( 'nonce_verification_failed', 'Did not send notification due to nonce verification failure.' );
					$this->log(
						'warning',
						'nonce_check',
						"{$prefix}: Send skipped — nonce verification failed",
						$post->ID
					);
					return;
				}
			}

			// Transient lock prevents race conditions from concurrent requests
			$lock_key = "pushly_sending_{$post->ID}";
			if ( get_transient( $lock_key ) ) {
				Util::log_to_event_stream( 'concurrent_request', 'Skipping notification send due to concurrent request already processing for post.' );
				$this->log(
					'warning',
					'transient_lock',
					"{$prefix}: Send skipped — concurrent request already processing",
					$post->ID
				);
				return;
			}
			set_transient( $lock_key, true, 30 );

			$meta = $this->get_post_meta( $post->ID, $request );

			if ( empty( $meta['pushly_send_notification'] ) ) {
				Util::log_to_event_stream( 'send_notification_status', 'Did not send notification due to false pushly_send_notification status.' );
				$this->log(
					'debug',
					'meta_check',
					"{$prefix}: Send skipped — pushly_send_notification meta is false",
					$post->ID
				);
				delete_transient( $lock_key );
				return;
			}

			$existing_notification_id = get_post_meta( $post->ID, 'pushly_notification_id', true );
			if ( ! empty( $existing_notification_id ) ) {
				Util::log_to_event_stream( 'post_already_sent', "Did not send notification due to notification already being sent for post ({$existing_notification_id})." );
				$this->log(
					'info',
					'duplicate_check',
					"{$prefix}: Send skipped — notification already sent (notification ID: {$existing_notification_id})",
					$post->ID,
					[ 'notification_id' => $existing_notification_id ]
				);
				delete_transient( $lock_key );
				return;
			}

			if ( $post->post_status !== 'publish' ) {
				Util::log_to_event_stream( 'invalid_post_status', "Did not send notification due to invalid post status ({$post->post_status})." );
				$this->log(
					'debug',
					'post_status_check',
					"{$prefix}: Send skipped — post status is '{$post->post_status}', expected 'publish'",
					$post->ID,
					[ 'post_status' => $post->post_status ]
				);
				delete_transient( $lock_key );
				return;
			}

			// Resolve title and body
			if ( ! empty( $meta['pushly_customize_notification_content'] ) && ! empty( $meta['pushly_custom_title'] ) ) {
				$title = $meta['pushly_custom_title'];
				$body  = $meta['pushly_custom_body'] ?? null;
			} else {
				$title = $post->post_title;
				$body  = null;
			}

			// Resolve audience
			$notification_meta = [];
			if ( ! empty( $meta['pushly_customize_audience'] ) && ! empty( $meta['pushly_audience_ids'] ) ) {
				$notification_meta['segment_ids'] = $meta['pushly_audience_ids'];
			}

			$payload = [
				'ID'           => $post->ID,
				'title'        => stripslashes( wp_specialchars_decode( $title ) ),
				'body'         => $body ? stripslashes( wp_specialchars_decode( $body ) ) : null,
				'landing_url'  => get_permalink( $post->ID ),
				'schedule_date' => $post->post_date_gmt,
				'post_type'    => $post->post_type,
				'post_status'  => $post->post_status,
				'editor_type'  => ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? 'gutenberg' : 'classic',
			];

			$tags = get_the_tags( $post->ID );
			if ( ! empty( $tags ) ) {
				$payload['tag_names'] = array_map( fn( $tag ) => $tag->name, $tags );
			}

			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$payload['category_names'] = array_map( fn( $cat ) => $cat->name, $categories );
			}

			if ( has_post_thumbnail( $post->ID ) ) {
				$payload['image_id'] = get_post_thumbnail_id( $post->ID );
			}

			$notification = Notification::from_post( $payload, $notification_meta );
			if ( $notification ) {
				$this->log(
					'info',
					'notification_build',
					"{$prefix}: Notification built successfully",
					$post->ID
				);

				$response = $this->api_save_notification( $notification, $post );
				if ( ! empty( $response['id'] ) ) {
					update_post_meta( $post->ID, 'pushly_notification_id', $response['id'] );
				}
			} else {
				$this->log(
					'error',
					'notification_build',
					"{$prefix}: Notification build failed — returned null",
					$post->ID
				);
			}

			delete_transient( $lock_key );
		} catch ( \Exception $e ) {
			Util::log_to_event_stream( 'unknown_exception', "Encountered unknown exception during send: {$e->getMessage()}" );
			$this->log(
				'error',
				'send_exception',
				"{$prefix}: Error — uncaught exception: {$e->getMessage()}",
				$post->ID,
				[ 'exception_class' => get_class( $e ), 'exception_message' => $e->getMessage() ]
			);
			delete_transient( "pushly_sending_{$post->ID}" );
		}
	}

	// -------------------------------------------------------------------------
	// Utilities
	// -------------------------------------------------------------------------

	/**
	 * Returns all pushly_ post meta for a given post, preferring the request body
	 * over the database. Gutenberg/REST sends meta in the JSON request body, and
	 * the database may not be up-to-date at the time save_notification_from_post
	 * runs. Falls back to the database for scheduled post transitions and other
	 * cases where the request body is empty.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_post_meta( int $post_id, ?\WP_REST_Request $request = null ): array {
		$meta = [];

		// REST API (Gutenberg): read meta from the WP_REST_Request object.
		if ( $request !== null ) {
			$request_meta = $request->get_param( 'meta' );
			if ( is_array( $request_meta ) ) {
				foreach ( $request_meta as $key => $value ) {
					if ( str_starts_with( $key, 'pushly_' ) ) {
						$meta[ $key ] = $value;
					}
				}
			}
		}

		// Classic editor sends meta fields via $_POST alongside the nonce.
		// Only read $_POST after verifying the meta-box nonce; otherwise the
		// data is untrusted (and PluginCheck rightly flags an un-nonced read).
		if ( empty( $meta ) && isset( $_POST['pushly_meta_box_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['pushly_meta_box_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'pushly_save_notification_meta_box' ) ) {
				foreach ( $_POST as $key => $value ) {
					if ( str_starts_with( $key, 'pushly_' ) && $key !== 'pushly_meta_box_nonce' ) {
						$meta[ $key ] = is_string( $value )
							? sanitize_text_field( wp_unslash( $value ) )
							: map_deep( wp_unslash( $value ), 'sanitize_text_field' );
					}
				}
			}
		}

		// Fall back to the database using single-key lookups so that
		// register_post_meta defaults are respected. The no-key form
		// of get_post_meta() does NOT return defaults for missing rows.
		if ( empty( $meta ) ) {
			$keys = [
				'pushly_send_notification',
				'pushly_customize_notification_content',
				'pushly_custom_title',
				'pushly_custom_body',
				'pushly_customize_audience',
				'pushly_audience_ids',
			];

			foreach ( $keys as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				if ( $value !== '' && $value !== false ) {
					$meta[ $key ] = $value;
				}
			}
		}

		if ( ! empty( $meta['pushly_customize_audience'] )
			&& ! empty( $meta['pushly_audience_ids'] )
			&& is_string( $meta['pushly_audience_ids'] )
		) {
			// allowed_classes => false prevents PHP object injection if the input
			// is ever attacker-controlled (defense in depth; the value reaches
			// here only after nonce verification or from get_post_meta).
			$decoded = unserialize( $meta['pushly_audience_ids'], [ 'allowed_classes' => false ] );
			$meta['pushly_audience_ids'] = is_array( $decoded ) ? $decoded : [];
		}

		return $meta;
	}

	protected function is_enabled_post_screen(): bool {
		if ( empty( $this->options['enabled_post_types'] ) ) {
			return false;
		}

		$screen = get_current_screen();
		return $screen
			&& $screen->base === 'post'
			&& in_array( $screen->post_type, $this->options['enabled_post_types'], true );
	}

	protected function is_using_gutenberg(): bool {
		if ( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) {
			return true;
		}

		$screen = get_current_screen();
		return $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}

	public function emit_notice(): void {
		if ( $this->is_using_gutenberg() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}

		$message = get_transient( 'pushly_notice' );
		if ( $message ) {
			delete_transient( 'pushly_notice' );
			printf(
				'<div class="notice error is-dismissible"><p>Pushly Notifications: %s</p></div>',
				esc_html( __( 'Failed to Send - ', 'pushly' ) . $message )
			);
		}
	}

	// -------------------------------------------------------------------------
	// REST API
	// -------------------------------------------------------------------------

	public function register_api_routes(): void {
		register_rest_route( 'pushly/v1', '/segments', [
			'methods'             => 'GET',
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'callback'            => fn() => rest_ensure_response( $this->api_get_segments() ),
		] );
	}

	protected function api_get_segments(): array {
		$segments = [];

		try {
			$options = Util::get_api_options();
			if ( $options === null ) {
				return $segments;
			}

			$api_key  = Util::decrypt_api_key( $options['sdk_key'], $options['sdk_key'], $options['api_key'] );
			$url      = 'https://' . PUSHLY__API_DOMAIN . "/domains/{$options['domain_id']}/segments?pagination=0&source=standard&include_default=0&fields=id,name";
			$response = wp_remote_get( $url, [ 'headers' => [ 'X-API-KEY' => $api_key ] ] );

			if ( is_wp_error( $response ) ) {
				Util::log_to_event_stream( 'segment_fetch_error', $response->get_error_message() );
			} else {
				$body = json_decode( $response['body'], true );
				if ( ( $body['status'] ?? '' ) === 'success' ) {
					$segments = $body;
				} elseif ( ! empty( $body['message'] ) ) {
					Util::log_to_event_stream( 'segment_fetch_error', $body['message'] );
				}
			}
		} catch ( \Exception $e ) {
			Util::log_to_event_stream( 'unknown_exception', "Encountered unknown exception during segment fetch: {$e->getMessage()}" );
		}

		return $segments;
	}

	protected function api_save_notification( Notification $notification, ?\WP_Post $post = null ): ?array {
		/**
		 * Filters the notification before it is sent to the Pushly API.
		 * Return a non-null array to short-circuit the HTTP request (useful for testing).
		 *
		 * @param null|array  $pre_response  Return non-null to short-circuit.
		 * @param Notification $notification  The notification about to be sent.
		 */
		$pre = apply_filters( 'pushly_pre_send_notification', null, $notification );
		if ( $pre !== null ) {
			return $pre;
		}

		$options = Util::get_api_options();
		if ( $options === null ) {
			return null;
		}

		$api_key = Util::decrypt_api_key( $options['sdk_key'], $options['sdk_key'], $options['api_key'] );

		if ( ! empty( $notification->id ) ) {
			$url      = 'https://' . PUSHLY__API_DOMAIN . "/domains/{$options['domain_id']}/notifications/{$notification->id}";
			$method   = 'PATCH';
			$response = wp_remote_request( $url, [
				'method'  => 'PATCH',
				'body'    => wp_json_encode( $notification ),
				'headers' => [ 'Content-Type' => 'application/json', 'X-API-KEY' => $api_key ],
			] );
		} else {
			$url      = 'https://' . PUSHLY__API_DOMAIN . "/domains/{$options['domain_id']}/notifications";
			$method   = 'POST';
			$response = wp_remote_post( $url, [
				'body'    => wp_json_encode( $notification ),
				'headers' => [ 'Content-Type' => 'application/json', 'X-API-KEY' => $api_key ],
			] );
		}

		// Log API request dispatch
		$prefix = ( $post !== null ) ? $this->build_log_prefix( $post ) : '';

		if ( $post !== null ) {
			$this->log(
				'debug',
				'api_request',
				"{$prefix}: API request dispatched — {$method} {$url}",
				$post->ID,
				[ 'url' => $url, 'method' => $method ]
			);
		}

		if ( is_wp_error( $response ) ) {
			Util::log_to_event_stream( 'send_error', $response->get_error_message() );
			if ( $post !== null ) {
				$this->log(
					'error',
					'api_response',
					"{$prefix}: API error — {$response->get_error_message()} (URL: {$url})",
					$post->ID,
					[ 'url' => $url, 'error' => $response->get_error_message() ]
				);
			}
			return null;
		}

		$http_status = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( $response['body'], true );

		if ( ( $body['status'] ?? '' ) === 'success' ) {
			if ( $post !== null ) {
				$notification_id = $body['data']['id'] ?? null;
				$this->log(
					'info',
					'api_response',
					"{$prefix}: Notification created (notification ID: {$notification_id})",
					$post->ID,
					[ 'notification_id' => $notification_id, 'http_status' => $http_status ]
				);
			}
			return $body['data'];
		}

		$error_message = $body['message'] ?? 'Unknown error';
		if ( ! empty( $body['message'] ) ) {
			Util::log_to_event_stream( 'send_error', $body['message'] );
		}

		if ( $post !== null ) {
			$this->log(
				'error',
				'api_response',
				"{$prefix}: API error — HTTP {$http_status}: \"{$error_message}\" (URL: {$url})",
				$post->ID,
				[ 'url' => $url, 'http_status' => $http_status, 'error_message' => $error_message ]
			);
		}

		return null;
	}
}
