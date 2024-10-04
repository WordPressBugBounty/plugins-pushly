<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pushly_Admin_Post {
	/**
	 * @var Pushly_Admin_Post
	 */
	private static $_instance;

	/**
	 * Stores the pushly configuration options/settings
	 *
	 * @var array
	 */
	private $_options;

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
		$this->_options = get_option( 'pushly' );

		if (
			! empty( $this->_options )
			&& ! empty( $this->_options["sdk_key"] )
			&& ! empty( $this->_options["sending_enabled"] )
			&& ! empty( $this->_options["api_key"] )
		) {
			// Meta Box
			add_action( 'admin_init', [ $this, 'register_post_meta' ] );
			add_action( 'rest_api_init', [ $this, 'register_post_meta' ] );
			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_meta_box_assets_for_gutenberg' ], 10, 3 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_meta_box_assets_for_classic' ], 10, 3 );

			// Post Saving
			add_action( 'transition_post_status', [ $this, 'transition_post_status' ], 10, 3 );
			add_action( 'add_meta_boxes', [ $this, 'add_meta_box_for_classic' ], 1 );
			add_action( 'admin_notices', [ $this, 'emit_notice' ] );

			// API Methods
			add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
		}
	}


	/* Meta Box Methods */

	/**
	 * Registers all the meta properties that we will be storing on each individual post.
	 *
	 * pushly_notification_id: If a Pushly notification already exists for the post it will be stored in this property
	 * pushly_send_notification: Whether the "send notification" checkbox is checked or not
	 * pushly_customize_notification_content: Whether the "customize content" checkbox is checked or not
	 * pushly_custom_title: The user-supplied title that is used for the post's notification
	 * pushly_custom_body: The user-supplied body that is used for the post's notification
	 * pushly_customize_audience: Whether the "refine audience" checkbox is checked or not
	 * pushly_audience_ids: A list of segment IDs that the user has been chosen for this post's notification
	 */
	public function register_post_meta() {
		if ( ! empty( $this->_options["enabled_post_types"] ) ) {
			foreach ( $this->_options["enabled_post_types"] as $post_type ) {
				register_post_meta(
					$post_type,
					'pushly_needs_saving',
					[
						'single'       => true,
						'type'         => 'boolean',
						'show_in_rest' => false,
					]
				);

				register_post_meta(
					$post_type,
					'pushly_unique',
					[
						'single'       => true,
						'type'         => 'integer',
						'default'      => 0,
						'show_in_rest' => true,
					]
				);

				register_post_meta(
					$post_type,
					'pushly_notification_id',
					[
						'single'       => true,
						'type'         => 'string',
						'show_in_rest' => false,
					]
				);

				// whether the send notification checkbox is checked or not
				register_post_meta(
					$post_type,
					'pushly_send_notification',
					[
						'single'            => true,
						'type'              => 'boolean',
						'default'           => ! empty( $this->_options['auto_send_enabled'] ),
						'show_in_rest'      => true,
						'sanitize_callback' => 'wp_validate_boolean',

					]
				);

				register_post_meta(
					$post_type,
					'pushly_customize_notification_content',
					[
						'single'            => true,
						'type'              => 'boolean',
						'show_in_rest'      => true,
						'sanitize_callback' => 'wp_validate_boolean',

					]
				);

				register_post_meta(
					$post_type,
					'pushly_custom_title',
					[
						'single'            => true,
						'type'              => 'string',
						'show_in_rest'      => true,
						'sanitize_callback' => 'sanitize_text_field',
					]
				);

				register_post_meta(
					$post_type,
					'pushly_custom_body',
					[
						'single'            => true,
						'type'              => 'string',
						'show_in_rest'      => true,
						'sanitize_callback' => 'sanitize_text_field',
					]
				);

				register_post_meta(
					$post_type,
					'pushly_customize_audience',
					[
						'single'            => true,
						'type'              => 'boolean',
						'default'           => false,
						'show_in_rest'      => true,
						'sanitize_callback' => 'wp_validate_boolean',

					]
				);

				register_post_meta(
					$post_type,
					'pushly_audience_ids',
					[
						'single'       => true,
						'type'         => 'array',
						'show_in_rest' => array(
							'schema' => array(
								'type'  => 'array',
								'items' => array(
									'type' => 'integer',
								),
							),
						)
					]
				);
			}
		}
	}

	/**
	 * Enqueues the JS and CSS assets for Gutenberg editor page
	 *
	 * @param $admin_page
	 *
	 * @return void
	 */
	public function enqueue_meta_box_assets_for_gutenberg() {
		if ( ! empty( $this->_options["enabled_post_types"] ) ) {
			// this avoids errors when editing themes; there is probably a better way to prevent this.
			$current_screen = get_current_screen();
			if ( $current_screen->base !== 'post' || ! in_array( $current_screen->post_type, $this->_options["enabled_post_types"] ) ) {
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
	 * Enqueues the JS and CSS assets for the Classic Editor
	 *
	 * @param $admin_page
	 *
	 * @return void
	 */
	public function enqueue_meta_box_assets_for_classic( $admin_page ) {
		if ( ! empty( $this->_options["enabled_post_types"] ) ) {
			$current_screen = get_current_screen();
			if ( $current_screen->base !== 'post' || ! in_array( $current_screen->post_type, $this->_options["enabled_post_types"] ) ) {
				return;
			}

			wp_enqueue_script(
				'pushly',
				plugins_url( 'includes/admin/views/classic/meta-box.js', PUSHLY__DIR_SRC ),
				[ 'jquery' ]
			);
		}
	}

	/**
	 * Adds meta box properties and enqueues asserts for Classic editor
	 *
	 * @param $admin_page
	 *
	 * @return void
	 */
	public function add_meta_box_for_classic( $admin_page ) {
		if ( ! empty( $this->_options["enabled_post_types"] ) ) {
			add_meta_box(
				'pushly_meta_box',
				__( 'Pushly Notifications' ),
				[ $this, 'build_classic_meta_box' ],
				$this->_options["enabled_post_types"],
				'side',
				'default',
				[ '__back_compat_meta_box' => true ]
			);
		}
	}

	public function build_classic_meta_box( $post ) {
		$meta = get_post_meta( $post->ID );
		if ( $meta ) {
			// meta fields when fetched via get_post_meta are automatically nested in an array, unwind this
			foreach ( $meta as &$v ) {
				$v = array_shift( $v );
			}
		}

		$current_options                = get_option( 'pushly' );
		$send_notification              = ! empty( $meta['pushly_send_notification'] ) ? $meta['pushly_send_notification'] : $current_options['auto_send_enabled'];
		$customize_notification_content = ! empty( $meta['pushly_customize_notification_content'] ) ? $meta['pushly_customize_notification_content'] : false;
		$custom_title                   = ! empty( $meta['pushly_custom_title'] ) ? $meta['pushly_custom_title'] : null;
		$custom_body                    = ! empty( $meta['pushly_custom_body'] ) ? $meta['pushly_custom_body'] : null;

		require_once PUSHLY__DIR . '/includes/admin/views/classic/meta-box.php';
		echo build_classic_meta_box_html(
			$send_notification,
			$customize_notification_content,
			$custom_title,
			$custom_body
		);
	}

	/* POST Saving Methods */

	/**
	 * Fired when a Post's status transitions.
	 *
	 * Called by WordPress when wp_insert_post() is called.
	 *
	 * As wp_insert_post() is called by WordPress and the REST API whenever creating or updating a Post
	 * we can safely rely on this hook on any post save.
	 *
	 * @param string $new_status New Status
	 * @param string $old_status Old Status
	 * @param WP_Post $post Post
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( ! empty( $this->_options["enabled_post_types"] ) ) {
			if ( ! in_array( $post->post_type, $this->_options["enabled_post_types"] ) ) {
				if ( in_array( $new_status, [ 'publish', 'future' ] ) ) {
					Pushly_Admin_Util::log_to_event_stream( "disabled_post_type", "Did not send notification due to `{$post->post_type}` not being an enabled post type." );
				}

				return;
			}

			$this->on_should_save_notification( $post, $old_status, $new_status );
		} else {
			Pushly_Admin_Util::log_to_event_stream( "empty_enabled_post_types", "Did not send notification due to empty enabled_post_types." );
		}
	}

	/**
	 * Determines if the post should create/update a notification and hooks/calls the appropriate
	 * methods to invoke based on whether the request came from the Gutenberg, Rest API,
	 * or Classic Editor.
	 *
	 * We will never act on posts that are moving from `trash` to published.
	 * We will only act on posts that are in `publish` or `future` status. Posts in `future` status
	 * wll have their post meta set but will not be sent until they move to `publish` status.
	 *
	 * Because of these duplicate requests we have to implement logic to only act one of the requests. We will
	 * prefer to act on the Legacy request since more data is always available at that point. In order
	 * to accomplish this we will use a metadata flag `pushly_needs_saving` to conditionally invoke
	 * the desired method/hook only on the second request.
	 *
	 * @param string $new_status New Status
	 * @param string $old_status Old Status
	 * @param WP_Post $post Post
	 *
	 * @return void
	 */

	private function on_should_save_notification( $post, $old_status, $new_status ) {
		if ( $old_status === 'trash' ) {
			// to be safe, we never publish posts that were previously trashed
			return;
		}

		if ( get_post_meta( $post->ID, 'pushly_needs_saving', true ) ) {
			/**
			 * The previous request flagged that the request should be treated as a publish request (likely
			 * we're using Gutenberg and request to post.php was made after the REST API), do this now.
			 */
			delete_post_meta( $post->ID, 'pushly_needs_saving' );
			add_action( 'wp_insert_post', [ $this, 'save_notification_from_post_id' ], 999 );
		} else if ( in_array( $new_status, [ 'publish', 'future' ] ) ) {
			/**
			 * We need to determine the source of the request and act accordingly depending on if
			 * it came in via Classic Editor, Gutenberg Editor, or REST API.
			 */
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				/**
				 * The request came via the Classic Editor or a transition post background job
				 *
				 *  Metadata is included in the call to wp_insert_post(), meaning that it's saved to the Post before
				 *  we use it. So we don't need to do anything special here.
				 *
				 * We can just directly hook `wp_insert_post` if the post came in this way.
				 */
				add_action( 'wp_insert_post', [ $this, 'save_notification_from_post_id' ], 999 );
			} else if ( $this->is_gutenberg_post( $post ) ) {
				/**
				 * The request came via the Gutenberg Editor.
				 *
				 *  If Gutenberg is being used two requests may be sent:
				 *  - a REST API request that includes the post data and metadata registered *in* Gutenberg
				 *  - a Legacy request including metadata registered *outside* of Gutenberg (e.g., `add_meta_box` data)
				 *
				 * This is where we will  define our `pushly_needs_saving` meta flag to be handled hy the
				 * subsequent request.
				 */
				update_post_meta( $post->ID, 'pushly_needs_saving', 1 );
			} else {
				/**
				 * The request came via the REST API.
				 *
				 * If this is a REST API request, we can't use the `wp_insert_post` action because any metadata
				 * included in the REST API request is *not* included in the call to wp_insert_post(). Instead, we
				 * can use `rest_after_insert_*` which guarantees all metadata is saved before invocation.
				 */
				add_action( "rest_after_insert_{$post->post_type}", [ $this, 'save_notification_from_post' ], 99, 2 );
			}
		}
	}

	/**
	 * Helper function to determine if the Post is using the Gutenberg Editor.
	 *
	 * @param WP_Post $post Post
	 *
	 * @return bool Whether the post was created using Gutenberg
	 */
	private function is_gutenberg_post( $post ) {

		// This will fail if a Post is created or updated with no content and only a title.
		if ( strpos( $post->post_content, '<!-- wp:' ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * Intermediate function to always pull the post from the DB and forward on to the primary
	 * `pushly_save_notification_from_post` responsible for saving the notification.
	 *
	 * @param int $post_id Post ID
	 *
	 * @return mixed WP_Error|Notification
	 */
	public function save_notification_from_post_id( $post_id ) {
		$post = get_post( $post_id );

		return $this->save_notification_from_post( $post );
	}

	/**
	 * Primary function responsible for building the notification payload that will
	 * ultimately be sent to the API.
	 *
	 * @param $post
	 *
	 * @return Notification|void|WP_Error
	 */
	public function save_notification_from_post( $post ) {
		try {

			$meta = $this->get_request_post_meta( $post );

			/*
			 * We *always* require `pushly_send_notification` to be set in the post meta directly from a form/ajax
			 * action. This ensures that the post was created in the WordPress editor and not via a 3rd
			 * party plugin.
			 *
			 * If we want to semd notifications from posts created by 3rd party plugins this logic will need to be
			 * reworked to incorporate `auto_send_status` and a new meta field added only on default editor meta boxes.
			 */
			if ( empty( $meta['pushly_send_notification'] ) ) {
				Pushly_Admin_Util::log_to_event_stream( "send_notification_status", "Did not send notification due to false pushly_send_notification status." );

				// the notification box was not checked on the editor, post should be marked as not sending
				update_post_meta( $post->ID, 'pushly_send_notification', false );

				// nothing else to do, short circuit
				return;
			}

			/*
			 * Retrieve `pushly_notification_id` from the post. If this is set then a notification already
			 * exists for this post, so we will exit; This helps guard against duplicate notification
			 * creation.
			 */
			$pushly_notification_id = get_post_meta( $post->ID, 'pushly_notification_id', true );
			if ( ! empty( $pushly_notification_id ) ) {
				Pushly_Admin_Util::log_to_event_stream( "post_already_sent", "Did not send notification due to notification already being sent for post ({$pushly_notification_id})." );

				// since we never pre-schedule notifications we can safely exit here
				return;
			}

			// set metadata that the user has chosen to send a notification for this post
			update_post_meta( $post->ID, 'pushly_send_notification', true );

			// determine if we should use customized title/body or derive from post
			if ( ! empty( $meta['pushly_customize_notification_content'] )
			     && ! empty( $meta['pushly_custom_title'] )
			) {
				$title = $meta['pushly_custom_title'];
				$body  = ! empty( $meta['pushly_custom_body'] ) ? $meta['pushly_custom_body'] : null;

				update_post_meta( $post->ID, 'pushly_customize_notification_content', true );
				update_post_meta( $post->ID, 'pushly_custom_title', $title );
				update_post_meta( $post->ID, 'pushly_custom_body', $body );
			} else {
				$title = $post->post_title;
				// we never use a body unless the user has explicitly provided it
				$body = null;

				update_post_meta( $post->ID, 'pushly_customize_notification_content', false );
			}

			$notification_meta = [];
			if ( ! empty( $meta['pushly_customize_audience'] ) && ! empty( $meta['pushly_audience_ids'] ) ) {
				$notification_meta['segment_ids'] = $meta['pushly_audience_ids'];

				update_post_meta( $post->ID, 'pushly_customize_audience', true );
				update_post_meta( $post->ID, 'pushly_audience_ids', $meta['pushly_audience_ids'] );
			}

			// from here only needs to run if the post is in "publish" state - i.e., time to send a notification
			if ( $post->post_status === "publish" ) {
				$notification_payload = [
					'ID'            => $post->ID,
					'title'         => stripslashes( wp_specialchars_decode( $title ) ),
					'body'          => stripslashes( wp_specialchars_decode( $body ) ),
					'landing_url'   => get_permalink( $post->ID ),
					'schedule_date' => $post->post_date_gmt,
				];

				// get tags for the post - these will be used as notification keywords
				$tags = get_the_tags( $post->ID );
				if ( ! empty( $tags ) ) {
					$notification_payload['tag_names'] = array_map( function ( $value ) {
						return $value->name;
					}, $tags );
				}

				// get categories for the post - these will be used as notification keywords
				$categories = get_the_category( $post->ID );
				if ( ! empty( $categories ) ) {
					$notification_payload['category_names'] = array_map( function ( $value ) {
						return $value->name;
					}, $categories );
				}

				// get the featured image ID from the post
				if ( has_post_thumbnail( $post->ID ) ) {
					$notification_payload['image_id'] = get_post_thumbnail_id( $post->ID );
				}

				// build the Notification object that will be sent via the API
				$notification = Notification::from_post( $notification_payload, $notification_meta );
				if ( $notification ) {
					$response = $this->api_save_notification( $notification );
					if ( ! empty( $response['id'] ) ) {
						update_post_meta( $post->ID, 'pushly_notification_id', $response['id'] );
					}
				}
			} else {
				Pushly_Admin_Util::log_to_event_stream( "invalid_post_status", "Did not send notification due to invalid post status ({$post->post_status})." );
			}
		} catch ( Exception $e ) {
			Pushly_Admin_Util::log_to_event_stream( "unknown_exception", "Encountered unknown exception during send: {$e->getMessage()}" );
		}
	}

	/* Post Util Methods */

	/**
	 * We prefer to load the post meta directly from the request as the post meta is not always
	 * 100% up-to-date when retrieving from the database especially before `rest_after_insert_post`
	 * was introduced.
	 *
	 * We will fall back to the database to account for post status transitions.
	 *
	 * @param $post
	 *
	 * @return array|mixed
	 */
	protected function get_request_post_meta(
		$post
	) {
		$meta = [];

		/*
		 * Requests that use the classic editor and quick edit send data via $_POST
		 */
		if ( ! empty( $_POST ) ) {
			foreach ( $_POST as $key => $value ) {
				if ( str_starts_with( $key, "pushly_" ) ) {
					$meta[ $key ] = $value;
				}
			}
		}

		/*
		 * Requests that use the API/Gutenberg use a JSON post body rather than a form POST. We will
		 * decode the body here into an associative array and assign `meta` the same way it
		 * would have come in from $_POST.
		 */
		$json = file_get_contents( 'php://input' );
		if ( $json ) {
			$json = json_decode( $json, true );
			if ( ! empty( $json['meta'] ) && is_array( $json['meta'] ) ) {
				foreach ( $json['meta'] as $key => $value ) {
					if ( str_starts_with( $key, "pushly_" ) ) {
						$meta[ $key ] = $value;
					}
				}
			}
		}

		if ( empty( $meta ) ) {
			// likely this is a scheduled post moving to future or an after rest insert, so we grab the existing meta
			$meta = get_post_meta( $post->ID );

			if ( $meta ) {
				// meta fields when fetched via get_post_meta are automatically nested in an array, unwind this
				foreach ( $meta as &$v ) {
					$v = array_shift( $v );
				}

				// audiences are serialized when fetched from get_post_meta, deserialize to a php array
				if ( ! empty( $meta['pushly_customize_audience'] ) && ! empty( $meta['pushly_audience_ids'] ) ) {
					$meta['pushly_audience_ids'] = unserialize( $meta['pushly_audience_ids'] );
				}
			}
		}

		return $meta;
	}

	/**
	 * Emits notice HTML that will be shown when the page is rendered
	 *
	 * @return void
	 */
	public function emit_notice() {
		if ( ! $this->is_using_gutenberg() ) {
			$screen = get_current_screen();
			// Only render this notice in the post editor.
			if ( ! $screen || 'post' !== $screen->base ) {
				return;
			}

			$message = get_transient( 'pushly_notice' );
			if ( $message ) {
				delete_transient( 'pushly_notice' );

				printf(
					'<div class="notice error is-dismissible"><p>Pushly Notifications: %s</p></div>',
					esc_html( __( "Failed to Send - " ) . $message )
				);
			}
		}
	}

	/**
	 * Determines if the site is using Gutenberg or not.
	 *
	 * This method will not work if it is called too early in the WordPress initialization
	 * process. It must be used after `replace_editor` hook (or any subsequent hook) is executed.
	 *
	 * @See https://wordpress.stackexchange.com/a/309955
	 *
	 * @return bool
	 */
	protected function is_using_gutenberg() {
		if ( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) {
			return true;
		}

		$current_screen = get_current_screen();
		if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
			return true;
		}

		return false;
	}

	/* API Methods */
	public function register_api_routes() {
		register_rest_route(
			'pushly/v1',
			'/segments',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => function ( $request ) {
					return rest_ensure_response( $this->api_get_segments() );
				},
			)
		);
	}

	protected function api_get_segments(
		$options = null
	) {
		$segments = [];

		try {
			$options   = Pushly_Admin_Util::get_api_options();
			$domain_id = $options['domain_id'];
			$api_key   = Pushly_Admin_Util::decrypt_api_key( $options['sdk_key'], $options['sdk_key'], $options['api_key'] );

			$url      = "https://" . PUSHLY__API_DOMAIN . "/domains/{$domain_id}/segments?pagination=0&source=standard&include_default=0&fields=id,name";
			$response = wp_remote_get( $url, [
				'headers' => array(
					'X-API-KEY' => $api_key
				)
			] );

			if ( is_wp_error( $response ) ) {
				Pushly_Admin_Util::log_to_event_stream( "segment_fetch_error", $response->get_error_message() );
			} else {
				$response_body = json_decode( $response['body'], true );

				if ( ! empty( $response_body['status'] ) ) {
					if ( $response_body['status'] === 'success' ) {
						$segments = $response_body;
					} else if ( $response_body['status'] === 'error' && ! empty( $response_body['message'] ) ) {
						Pushly_Admin_Util::log_to_event_stream( "segment_fetch_error", $response_body['message'] );
					}
				}
			}

			return $segments;
		} catch ( Exception $e ) {
			Pushly_Admin_Util::log_to_event_stream( "unknown_exception", "Encountered unknown exception during segment fetch: {$e->getMessage()}" );
		}
	}

	protected function api_save_notification(
		$notification
	) {
		$return = null;

		$options   = Pushly_Admin_Util::get_api_options();
		$domain_id = $options['domain_id'];
		$api_key   = Pushly_Admin_Util::decrypt_api_key( $options['sdk_key'], $options['sdk_key'], $options['api_key'] );

		if ( ! empty( $notification->id ) ) {
			// updating notification
			$url      = "https://" . PUSHLY__API_DOMAIN . "/domains/{$domain_id}/notifications/{$notification->id}";
			$response = wp_remote_request( $url, array(
				'method'  => 'PATCH',
				'body'    => wp_json_encode( $notification ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-KEY'    => $api_key,
				)
			) );
		} else {
			// creating notification
			$url      = "https://" . PUSHLY__API_DOMAIN . "/domains/{$domain_id}/notifications";
			$response = wp_remote_post( $url, array(
				'body'    => wp_json_encode( $notification ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-KEY'    => $api_key,
				)
			) );
		}

		if ( is_wp_error( $response ) ) {
			Pushly_Admin_Util::log_to_event_stream( "send_error", $response->get_error_message() );
		} else {
			$response_body = json_decode( $response['body'], true );

			if ( ! empty( $response_body['status'] ) ) {
				if ( $response_body['status'] === 'success' ) {
					$return = $response_body["data"];
				} else if ( $response_body['status'] === 'error' && ! empty( $response_body['message'] ) ) {
					Pushly_Admin_Util::log_to_event_stream( "send_error", $response_body['message'] );
				}
			}
		}

		return $return;
	}
}
