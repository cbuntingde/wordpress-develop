<?php
/**
 * Interface DatabaseDialect — the typed contract every MySQL/MariaDB
 * surface in this fork builds on. Implementations translate the
 * structured query and expression types into the vendor's SQL.
 *
 * Vendor detection happens once, in wpdb::db_connect(). Callers do
 * not branch on vendor; they read $wpdb->dialect and call the methods
 * here.
 *
 * @package WordPress
 */

declare(strict_types=1);

interface DatabaseDialect {

	/**
	 * Quotes an identifier for safe inclusion in a SQL statement.
	 *
	 * Splits dotted names (e.g. `wp_posts.ID`) on the dot and quotes
	 * each segment, so callers can pass either a column name, a table
	 * name, or a fully-qualified `table.column` name.
	 *
	 * Embedded backticks are escaped by doubling, matching the
	 * MySQL/MariaDB identifier-quoting rule.
	 *
	 * @param string $name The identifier or dotted identifier path.
	 * @return string The quoted identifier, safe to interpolate into SQL.
	 */
	public function quoteIdentifier( string $name ): string;

	/**
	 * Reports whether the connected engine supports a named feature.
	 *
	 * Feature names are the constants on DatabaseFeatures. An unknown
	 * name returns false; this method never throws.
	 *
	 * @param string $feature One of the DatabaseFeatures::FEATURE_* constants.
	 * @return bool True when the engine supports the feature on the running version.
	 */
	public function supports( string $feature ): bool;

	/**
	 * Builds a structured UPSERT statement for the running engine.
	 *
	 * @param string $table          Target table name (unquoted; the dialect quotes it).
	 * @param array  $data           Associative array of column => value to insert.
	 * @param array  $update_columns Columns to update on duplicate key.
	 * @param array  $key_columns    Columns that uniquely identify a row.
	 * @return Query A structured query object the query builder will render and execute.
	 */
	public function buildUpsert( string $table, array $data, array $update_columns, array $key_columns ): Query;

	/**
	 * Builds a structured JSON path expression for the running engine.
	 *
	 * @param string $column The column holding the JSON document.
	 * @param string $path   A JSON path expression (e.g. '$.user.email').
	 * @return Expression A structured expression the query builder will render.
	 */
	public function buildJsonExpression( string $column, string $path ): Expression;
}
