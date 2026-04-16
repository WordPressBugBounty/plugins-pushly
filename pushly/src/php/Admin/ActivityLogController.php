<?php

namespace Pushly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActivityLogController {

	/**
	 * @var LogStore
	 */
	private $store;

	/**
	 * @param LogStore $store
	 */
	public function __construct( LogStore $store ) {
		$this->store = $store;
	}

	/**
	 * Registers the REST API init hook.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers REST API routes under pushly/v1.
	 */
	public function register_routes(): void {
		register_rest_route( 'pushly/v1', '/activity-log', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_entries' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'page'     => [
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				],
				'severity' => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'search'   => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( 'pushly/v1', '/activity-log/export', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'export_entries' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'severity' => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'search'   => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( 'pushly/v1', '/activity-log/clear', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'clear_entries' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );
	}

	/**
	 * GET pushly/v1/activity-log
	 *
	 * Returns paginated, filterable log entries.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_entries( \WP_REST_Request $request ): \WP_REST_Response {
		$page       = $request->get_param( 'page' );
		$severity   = $request->get_param( 'severity' );
		$search     = $request->get_param( 'search' );
		$severities = null;

		if ( ! empty( $severity ) ) {
			$severities = array_map( 'trim', explode( ',', $severity ) );
			$severities = array_filter( $severities );
		}

		$search = ! empty( $search ) ? $search : null;

		$result = $this->store->query( $page, 50, $severities, $search );

		// Decode context JSON strings into objects for the response.
		$entries = array_map( function ( $entry ) {
			if ( ! empty( $entry->context ) ) {
				$decoded = json_decode( $entry->context );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$entry->context = $decoded;
				}
			}
			return $entry;
		}, $result['entries'] );

		return new \WP_REST_Response( [
			'entries'     => $entries,
			'total'       => $result['total'],
			'page'        => $result['page'],
			'per_page'    => $result['per_page'],
			'total_pages' => $result['total_pages'],
		] );
	}

	/**
	 * GET pushly/v1/activity-log/export
	 *
	 * Returns all matching log entries as a downloadable plain-text file.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function export_entries( \WP_REST_Request $request ): \WP_REST_Response {
		$severity   = $request->get_param( 'severity' );
		$search     = $request->get_param( 'search' );
		$severities = null;

		if ( ! empty( $severity ) ) {
			$severities = array_map( 'trim', explode( ',', $severity ) );
			$severities = array_filter( $severities );
		}

		$search  = ! empty( $search ) ? $search : null;
		$entries = $this->store->query_all( $severities, $search );

		$lines = [];
		foreach ( $entries as $entry ) {
			$context_json = '';
			if ( ! empty( $entry->context ) ) {
				$context_json = $entry->context;
			}

			$lines[] = sprintf(
				'[%s UTC] [%s] [%s] %s | context: %s',
				$entry->timestamp,
				strtoupper( $entry->severity ),
				$entry->event_type,
				$entry->message,
				$context_json
			);
		}

		$body     = implode( "\n", $lines );
		$filename = 'pushly-debug-log-' . gmdate( 'Y-m-d' ) . '.txt';

		$response = new \WP_REST_Response( $body );
		$response->header( 'Content-Type', 'text/plain; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );

		return $response;
	}

	/**
	 * DELETE pushly/v1/activity-log/clear
	 *
	 * Removes all log entries and returns the count of deleted entries.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function clear_entries( \WP_REST_Request $request ): \WP_REST_Response {
		$count = $this->store->count();
		$this->store->truncate();

		return new \WP_REST_Response( [
			'success' => true,
			'deleted' => $count,
		] );
	}

	/**
	 * Permission callback for all endpoints.
	 * Requires the manage_options capability.
	 *
	 * @return bool
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}
}
