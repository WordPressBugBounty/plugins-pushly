<?php

namespace Pushly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LogStore {

	public const ROW_CAP = 5000;

	private string $table_name;

	private \wpdb $wpdb;

	/**
	 * @param \wpdb|null $wpdb Optional wpdb instance for testability.
	 *                         Defaults to the WordPress global $wpdb when null.
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		if ( $wpdb === null ) {
			global $wpdb;
		}
		$this->wpdb       = $wpdb;
		$this->table_name = $this->wpdb->prefix . 'pushly_activity_log';
	}

	/**
	 * Creates the activity log table using dbDelta.
	 * Called on plugin activation.
	 */
	public function create_table(): void {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp DATETIME NOT NULL,
			severity VARCHAR(10) NOT NULL,
			event_type VARCHAR(100) NOT NULL,
			message TEXT NOT NULL,
			post_id BIGINT UNSIGNED NULL DEFAULT NULL,
			context TEXT NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_timestamp (timestamp),
			KEY idx_severity (severity)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drops the activity log table entirely.
	 * Called on plugin uninstall.
	 */
	public function drop_table(): void {
		$this->wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
	}

	/**
	 * Inserts multiple log entries in a single multi-row INSERT.
	 * Enforces the row cap before inserting.
	 *
	 * @param array<int, array{severity: string, event_type: string, message: string, post_id: ?int, context: ?string}> $entries
	 */
	public function insert_batch( array $entries ): void {
		if ( empty( $entries ) ) {
			return;
		}

		$this->enforce_row_cap( count( $entries ) );

		$placeholders = [];
		$values       = [];

		foreach ( $entries as $entry ) {
			$placeholders[] = '(%s, %s, %s, %s, %s, %s)';

			$context = null;
			if ( isset( $entry['context'] ) ) {
				$context = is_string( $entry['context'] )
					? $entry['context']
					: wp_json_encode( $entry['context'] );
				if ( $context === false ) {
					$context = null;
				}
			}

			$values[] = $entry['timestamp'];
			$values[] = $entry['severity'];
			$values[] = $entry['event_type'];
			$values[] = $entry['message'];
			$values[] = $entry['post_id'] ?? null;
			$values[] = $context;
		}

		$sql = "INSERT INTO {$this->table_name} (timestamp, severity, event_type, message, post_id, context) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic placeholders built above
		$this->wpdb->query( $this->wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Enforces the row cap by deleting oldest entries when the current count
	 * plus the incoming batch would exceed the cap.
	 *
	 * @param int $incoming_count Number of new entries about to be inserted.
	 */
	private function enforce_row_cap( int $incoming_count ): void {
		$current_count = $this->count();
		$overflow      = ( $current_count + $incoming_count ) - self::ROW_CAP;

		if ( $overflow <= 0 ) {
			return;
		}

		// Fetch the oldest IDs into PHP first, then delete.
		// This avoids MySQL's restriction on referencing the target table
		// in a subquery of a DELETE statement (which also fails on temporary tables).
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} ORDER BY timestamp ASC, id ASC LIMIT %d",
				$overflow
			)
		);

		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic placeholders built above
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
				$ids
			)
		);
	}

	/**
	 * Queries log entries with pagination, severity filter, and text search.
	 *
	 * @param int         $page       Page number (1-based).
	 * @param int         $per_page   Entries per page.
	 * @param string[]|null $severities Filter by severity levels.
	 * @param string|null $search     Text search against message and event_type.
	 *
	 * @return array{entries: array, total: int, page: int, per_page: int, total_pages: int}
	 */
	public function query( int $page = 1, int $per_page = 50, ?array $severities = null, ?string $search = null ): array {
		$where  = $this->build_where_clause( $severities, $search );
		$values = $this->build_where_values( $severities, $search );

		// Count total matching entries.
		$count_sql = "SELECT COUNT(*) FROM {$this->table_name}" . $where;
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, $values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $this->wpdb->get_var( $count_sql );
		}

		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
		$offset      = ( $page - 1 ) * $per_page;

		// Fetch entries for the requested page.
		$query_sql = "SELECT * FROM {$this->table_name}" . $where
			. " ORDER BY timestamp DESC, id DESC LIMIT %d OFFSET %d";

		$query_values   = array_merge( $values, [ $per_page, $offset ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$entries = $this->wpdb->get_results( $this->wpdb->prepare( $query_sql, $query_values ) );

		return [
			'entries'     => $entries ?: [],
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		];
	}

	/**
	 * Returns all entries matching filters with a defensive LIMIT.
	 * Used for export.
	 *
	 * @param string[]|null $severities Filter by severity levels.
	 * @param string|null   $search     Text search against message and event_type.
	 *
	 * @return array<int, object>
	 */
	public function query_all( ?array $severities = null, ?string $search = null ): array {
		$where  = $this->build_where_clause( $severities, $search );
		$values = $this->build_where_values( $severities, $search );

		$sql = "SELECT * FROM {$this->table_name}" . $where
			. " ORDER BY timestamp DESC, id DESC LIMIT %d";

		$query_values = array_merge( $values, [ self::ROW_CAP ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$entries = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $query_values ) );

		return $entries ?: [];
	}

	/**
	 * Returns the total number of log entries.
	 */
	public function count(): int {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	/**
	 * Deletes all log entries.
	 */
	public function truncate(): void {
		$this->wpdb->query( "DELETE FROM {$this->table_name}" );
	}

	/**
	 * Deletes entries older than 14 days. Called by WP-Cron.
	 */
	public function prune_old_entries(): void {
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE timestamp < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( 14 * DAY_IN_SECONDS ) )
			)
		);
	}

	/**
	 * Returns the full table name.
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Builds the WHERE clause for query and query_all.
	 *
	 * @param string[]|null $severities Severity filter.
	 * @param string|null   $search     Text search string.
	 *
	 * @return string SQL WHERE clause (including leading " WHERE") or empty string.
	 */
	private function build_where_clause( ?array $severities, ?string $search ): string {
		$conditions = [];

		if ( ! empty( $severities ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $severities ), '%s' ) );
			$conditions[] = "severity IN ({$placeholders})";
		}

		if ( ! empty( $search ) ) {
			$conditions[] = '(message LIKE %s OR event_type LIKE %s)';
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		return ' WHERE ' . implode( ' AND ', $conditions );
	}

	/**
	 * Builds the values array for the WHERE clause placeholders.
	 *
	 * @param string[]|null $severities Severity filter.
	 * @param string|null   $search     Text search string.
	 *
	 * @return array<int, string> Values for wpdb::prepare().
	 */
	private function build_where_values( ?array $severities, ?string $search ): array {
		$values = [];

		if ( ! empty( $severities ) ) {
			foreach ( $severities as $severity ) {
				$values[] = $severity;
			}
		}

		if ( ! empty( $search ) ) {
			$like     = '%' . $this->wpdb->esc_like( $search ) . '%';
			$values[] = $like;
			$values[] = $like;
		}

		return $values;
	}
}
