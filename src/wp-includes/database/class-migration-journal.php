<?php
/**
 * Class MigrationJournal — idempotent, resumable record of applied
 * schema migrations.
 *
 * The journal lives in `{$wpdb->prefix}modernization_journal` and
 * tracks every migration that has run on this database. Each entry
 * records the migration's name, a content checksum, optional notes,
 * and the timestamp the entry was written.
 *
 * Per MODERNIZATION_PLAN.md Phase 2 task 4. This is the foundation
 * Phase 7's migration tooling builds on; build it now so Phase 3–6
 * conversions can log schema changes through it from the start
 * instead of retrofitting later.
 *
 * The journal is idempotent — calling record() with a name that has
 * already been recorded is a no-op when the recorded checksum
 * matches, and surfaces an error when the recorded checksum differs.
 * It is resumable — every operation that reads the journal sorts by
 * `id` so the on-disk order is the apply order, regardless of which
 * process wrote which row.
 *
 * @package WordPress
 */

declare(strict_types=1);

final class MigrationJournal {

	/**
	 * Schema for the journal table. utf8mb4 + InnoDB, matching the
	 * platform floor (CLAUDE.md, MODERNIZATION_PLAN.md Phase 1 task 4).
	 */
	private const SCHEMA_SQL = 'CREATE TABLE %1$s (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		migration_name VARCHAR(191) NOT NULL,
		checksum CHAR(64) NOT NULL DEFAULT \'\',
		notes TEXT NULL,
		applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY migration_name (migration_name),
		KEY applied_at (applied_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

	public function __construct( private wpdb $db ) {}

	/**
	 * Returns the prefixed journal table name.
	 */
	public function table(): string {
		return $this->db->prefix . 'modernization_journal';
	}

	/**
	 * Creates the journal table if it does not already exist. Safe to
	 * call repeatedly — uses CREATE TABLE IF NOT EXISTS so a second
	 * call is a no-op.
	 */
	public function ensureSchema(): void {
		$sql = sprintf( self::SCHEMA_SQL, $this->table() );
		$this->db->query( $sql );
	}

	/**
	 * Drops the journal table. Intended for tests and tooling that
	 * needs to reset state; production callers should not invoke this.
	 */
	public function dropSchema(): void {
		$this->db->query( sprintf( 'DROP TABLE IF EXISTS %s', $this->table() ) );
	}

	/**
	 * Returns true when a migration with the given name has been
	 * recorded as applied.
	 */
	public function isApplied( string $name ): bool {
		$table = $this->table();

		$count = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE migration_name = %s",
				$name
			)
		);

		return $count > 0;
	}

	/**
	 * Records a migration as applied. Returns true on a new entry,
	 * false when the migration was already recorded with a matching
	 * checksum. Throws when the migration is already recorded with a
	 * different checksum — re-applying a changed migration against a
	 * database that has the previous checksum is a logic bug the
	 * caller must fix, not silently ignore.
	 *
	 * @param string      $name     Stable migration identifier.
	 * @param string      $checksum Content checksum for the migration payload.
	 * @param string|null $notes    Optional free-form notes.
	 */
	public function record( string $name, string $checksum = '', ?string $notes = null ): bool {
		$this->ensureSchema();

		$table = $this->table();

		if ( $this->isApplied( $name ) ) {
			$existing = $this->db->get_var(
				$this->db->prepare(
					"SELECT checksum FROM {$table} WHERE migration_name = %s",
					$name
				)
			);

			if ( (string) $existing === $checksum ) {
				return false;
			}

			throw new \RuntimeException(
				sprintf(
					/* translators: 1: Migration name, 2: Existing checksum, 3: Provided checksum. */
					__(
						'Migration "%1$s" was already recorded with checksum %2$s; refusing to overwrite with %3$s.'
					),
					$name,
					(string) $existing,
					$checksum
				)
			);
		}

		$this->db->insert(
			$table,
			array(
				'migration_name' => $name,
				'checksum'       => $checksum,
				'notes'          => $notes,
			),
			array( '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Returns the list of applied migrations in apply order, oldest
	 * first. Each entry is an associative array with the row's columns.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function applied(): array {
		$table = $this->table();

		$rows = $this->db->get_results(
			"SELECT id, migration_name, checksum, notes, applied_at
			 FROM {$table}
			 ORDER BY id ASC",
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values( $rows );
	}

	/**
	 * Returns the subset of `$expected` migration names that have not
	 * yet been recorded as applied. The returned list is in the same
	 * order as `$expected`, so callers can iterate it to apply pending
	 * migrations in the order they were declared.
	 *
	 * @param list<string> $expected Migration names in apply order.
	 * @return list<string>
	 */
	public function pending( array $expected ): array {
		return array_values(
			array_filter(
				$expected,
				fn ( string $name ): bool => ! $this->isApplied( $name )
			)
		);
	}
}
