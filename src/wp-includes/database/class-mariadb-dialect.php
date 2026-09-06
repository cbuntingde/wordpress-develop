<?php
/**
 * Class MariaDbDialect — DatabaseDialect implementation for MariaDB
 * 10.11+ and the maintained newer LTS (currently 11.8).
 *
 * Vendor detection happens in wpdb::db_connect(); this class is
 * instantiated only when the connected engine is MariaDB.
 *
 * @package WordPress
 */

declare(strict_types=1);

final class MariaDbDialect implements DatabaseDialect {

	/**
	 * Quotes a single identifier segment. Doubles any embedded
	 * backticks so a maliciously named identifier cannot escape
	 * the quoting.
	 *
	 * @param string $segment The unquoted identifier segment.
	 * @return string The quoted segment.
	 */
	private function quoteSegment( string $segment ): string {
		return '`' . str_replace( '`', '``', $segment ) . '`';
	}

	public function quoteIdentifier( string $name ): string {
		$segments = explode( '.', $name );
		$quoted   = array_map(
			fn ( string $segment ): string => $this->quoteSegment( $segment ),
			$segments
		);

		return implode( '.', $quoted );
	}

	public function supports( string $feature ): bool {
		switch ( $feature ) {
			case DatabaseFeatures::UPSERT_SYNTAX:
			case DatabaseFeatures::JSON_PATH_EXTRACT:
			case DatabaseFeatures::CTE:
			case DatabaseFeatures::WINDOW_FUNCTIONS:
			case DatabaseFeatures::GENERATED_COLUMNS:
			case DatabaseFeatures::RETURNING_CLAUSE:
			case DatabaseFeatures::SEQUENCES:
				return true;

			default:
				return false;
		}
	}

	public function buildUpsert( string $table, array $data, array $update_columns, array $key_columns ): Query {
		return new Query(
			$table,
			array_keys( $data ),
			array_values( $data )
		);
	}

	public function buildJsonExpression( string $column, string $path ): Expression {
		$path_literal = "'" . str_replace( "'", "''", $path ) . "'";

		return new Expression(
			sprintf(
				'JSON_EXTRACT(%s, %s)',
				$this->quoteIdentifier( $column ),
				$path_literal
			),
			array()
		);
	}
}
