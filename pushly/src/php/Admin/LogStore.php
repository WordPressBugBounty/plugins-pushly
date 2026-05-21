<?php

namespace Pushly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity log persistence layer for the Pushly debug log.
 *
 * Every $wpdb call below uses $wpdb->prepare() with %i for the table identifier
 * and explicit %s / %d placeholders for values. The WordPress.DB.DirectDatabaseQuery
 * warnings are inherent to maintaining a custom plugin table (there is no
 * core-function equivalent for our schema). Caching is intentionally omitted —
 * the log is write-heavy and admin reads are paginated and infrequent.
 *
 * Where phpcs:ignore comments appear below they suppress false positives from
 * static analysis that cannot trace through dynamic placeholder construction.
 */
class LogStore {

	public const ROW_CAP = 5000;

	private string $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'pushly_activity_log';
	}

	/**
	 * Creates the activity log table using dbDelta.
	 * Called on plugin activation.
	 */
	public function create_table(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

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
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin-owned table; DDL on uninstall has no cache to invalidate.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->table_name ) );
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

		global $wpdb;

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

		$sql = 'INSERT INTO %i (timestamp, severity, event_type, message, post_id, context) VALUES ' . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built from literal SQL plus a generated list of '(%s, %s, %s, %s, %s, %s)' tuples; all dynamic values flow through %i / %s placeholders.
		$wpdb->query( $wpdb->prepare( $sql, array_merge( [ $this->table_name ], $values ) ) );
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

		global $wpdb;

		// Fetch the oldest IDs into PHP first, then delete.
		// This avoids MySQL's restriction on referencing the target table
		// in a subquery of a DELETE statement (which also fails on temporary tables).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; bounded read used to compute deletions, not for display.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY timestamp ASC, id ASC LIMIT %d', $this->table_name, $overflow ) );

		if ( empty( $ids ) ) {
			return;
		}

		$id_placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$sql             = "DELETE FROM %i WHERE id IN ({$id_placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $id_placeholders contains only '%d' tokens; every dynamic value flows through prepare().
		$wpdb->query( $wpdb->prepare( $sql, array_merge( [ $this->table_name ], $ids ) ) );
	}

	/**
	 * Queries log entries with pagination, severity filter, and text search.
	 *
	 * @param int           $page       Page number (1-based).
	 * @param int           $per_page   Entries per page.
	 * @param string[]|null $severities Filter by severity levels.
	 * @param string|null   $search     Text search against message and event_type.
	 *
	 * @return array{entries: array, total: int, page: int, per_page: int, total_pages: int}
	 */
	public function query( int $page = 1, int $per_page = 50, ?array $severities = null, ?string $search = null ): array {
		global $wpdb;

		$where  = $this->build_where_clause( $severities, $search );
		$values = $this->build_where_values( $severities, $search );

		$count_sql = 'SELECT COUNT(*) FROM %i' . $where;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built from literal SQL with hard-coded %s placeholders generated in build_where_clause(); all values flow through prepare().
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( [ $this->table_name ], $values ) ) );

		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
		$offset      = ( $page - 1 ) * $per_page;

		$query_sql = 'SELECT * FROM %i' . $where . ' ORDER BY timestamp DESC, id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where placeholders are literal; replacement count is correct at runtime (table + where-values + limit + offset).
		$entries = $wpdb->get_results( $wpdb->prepare( $query_sql, array_merge( [ $this->table_name ], $values, [ $per_page, $offset ] ) ) );

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
		global $wpdb;

		$where  = $this->build_where_clause( $severities, $search );
		$values = $this->build_where_values( $severities, $search );

		$sql = 'SELECT * FROM %i' . $where . ' ORDER BY timestamp DESC, id DESC LIMIT %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where placeholders are literal; replacement count is correct at runtime (table + where-values + limit).
		$entries = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( [ $this->table_name ], $values, [ self::ROW_CAP ] ) ) );

		return $entries ?: [];
	}

	/**
	 * Returns the total number of log entries.
	 */
	public function count(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table count; result is paired with row-cap enforcement on the next write.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table_name ) );
	}

	/**
	 * Deletes all log entries.
	 */
	public function truncate(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; admin-triggered "clear log" action.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->table_name ) );
	}

	/**
	 * Deletes entries older than 14 days. Called by WP-Cron.
	 */
	public function prune_old_entries(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; scheduled WP-Cron prune.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE timestamp < %s', $this->table_name, gmdate( 'Y-m-d H:i:s', time() - ( 14 * DAY_IN_SECONDS ) ) ) );
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
	 * Returns a string containing only literal SQL and hard-coded %s placeholders —
	 * never user input. Safe to concatenate into a prepared SQL string.
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
		global $wpdb;

		$values = [];

		if ( ! empty( $severities ) ) {
			foreach ( $severities as $severity ) {
				$values[] = $severity;
			}
		}

		if ( ! empty( $search ) ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$values[] = $like;
			$values[] = $like;
		}

		return $values;
	}
}
