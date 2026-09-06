<?php
/**
 * Class DatabaseFeatures — feature names DatabaseDialect::supports()
 * accepts. Both dialect implementations recognize the same constant
 * set; values differ by vendor and version.
 *
 * @package WordPress
 */

declare(strict_types=1);

final class DatabaseFeatures {
	public const UPSERT_SYNTAX      = 'upsert_syntax';
	public const JSON_PATH_EXTRACT  = 'json_path_extract';
	public const WINDOW_FUNCTIONS   = 'window_functions';
	public const CTE                = 'cte';
	public const RETURNING_CLAUSE   = 'returning_clause';
	public const SEQUENCES          = 'sequences';
	public const GENERATED_COLUMNS  = 'generated_columns';

	private function __construct() {
	}
}
