<?php

namespace Pushly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LogWriter {

	private const ALLOWED_SEVERITIES = [ 'debug', 'info', 'warning', 'error' ];

	/** @var array<int, array{timestamp: string, severity: string, event_type: string, message: string, post_id: ?int, context: ?string}> */
	private array $buffer = [];

	private LogStore $store;

	private bool $enabled;

	/**
	 * @param LogStore $store   The database storage layer for log entries.
	 * @param bool     $enabled Whether debug logging is active.
	 */
	public function __construct( LogStore $store, bool $enabled ) {
		$this->store   = $store;
		$this->enabled = $enabled;
	}

	/**
	 * Registers the shutdown hook to flush buffered entries.
	 */
	public function register_hooks(): void {
		add_action( 'shutdown', [ $this, 'flush' ] );
	}

	/**
	 * Adds a log entry to the in-memory buffer.
	 * No-ops when debug logging is disabled or severity is invalid.
	 *
	 * @param string     $severity   One of: debug, info, warning, error.
	 * @param string     $event_type Machine-readable event identifier.
	 * @param string     $message    Human-readable message.
	 * @param int|null   $post_id    Associated post ID, if applicable.
	 * @param array|null $context    Additional structured data.
	 */
	public function log( string $severity, string $event_type, string $message, ?int $post_id = null, ?array $context = null ): void {
		if ( ! $this->enabled ) {
			return;
		}

		if ( ! in_array( $severity, self::ALLOWED_SEVERITIES, true ) ) {
			return;
		}

		$this->buffer[] = [
			'timestamp'  => gmdate( 'Y-m-d H:i:s' ),
			'severity'   => $severity,
			'event_type' => $event_type,
			'message'    => $message,
			'post_id'    => $post_id,
			'context'    => $context !== null ? wp_json_encode( $context ) : null,
		];
	}

	/**
	 * Writes a single entry directly to the store, bypassing the buffer.
	 * Used for environment snapshots that must persist immediately.
	 *
	 * @param string     $severity   One of: debug, info, warning, error.
	 * @param string     $event_type Machine-readable event identifier.
	 * @param string     $message    Human-readable message.
	 * @param int|null   $post_id    Associated post ID, if applicable.
	 * @param array|null $context    Additional structured data.
	 */
	public function log_immediate( string $severity, string $event_type, string $message, ?int $post_id = null, ?array $context = null ): void {
		$entry = [
			'timestamp'  => gmdate( 'Y-m-d H:i:s' ),
			'severity'   => $severity,
			'event_type' => $event_type,
			'message'    => $message,
			'post_id'    => $post_id,
			'context'    => $context !== null ? wp_json_encode( $context ) : null,
		];

		$this->store->insert_batch( [ $entry ] );
	}

	/**
	 * Flushes all buffered entries to the LogStore in a single batch.
	 * Called on the WordPress shutdown action.
	 * Catches all exceptions silently to ensure logging never interrupts the request.
	 * Clears the buffer regardless of success or failure.
	 */
	public function flush(): void {
		if ( empty( $this->buffer ) ) {
			return;
		}

		try {
			$this->store->insert_batch( $this->buffer );
		} catch ( \Exception $e ) {
			// Intentionally silent — logging must never cause a visible error.
		}

		$this->buffer = [];
	}

	/**
	 * Returns whether debug logging is enabled.
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}
}
