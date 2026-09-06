<?php
/**
 * Tests for the MigrationJournal — idempotent, resumable schema
 * migration ledger.
 *
 * Per MODERNIZATION_PLAN.md Phase 2 task 4. Uses the live test
 * database; each test starts by dropping the journal table so
 * fixtures don't leak between tests.
 *
 * @group database
 * @group migration-journal
 *
 * @covers MigrationJournal
 */
class Tests_DB_MigrationJournal extends WP_UnitTestCase {

	private MigrationJournal $journal;

	public static function wpSetUpBeforeClass(): void {
		require_once ABSPATH . WPINC . '/database/load.php';
	}

	public function set_up(): void {
		parent::set_up();

		$this->journal = new MigrationJournal( $this->wpdb() );
		$this->journal->dropSchema();
	}

	public function tear_down(): void {
		$this->journal->dropSchema();

		parent::tear_down();
	}

	public function test_table_name_includes_wpdb_prefix() {
		$this->assertSame(
			$this->wpdb()->prefix . 'modernization_journal',
			$this->journal->table()
		);
	}

	public function test_ensure_schema_creates_table_on_first_call() {
		$this->journal->ensureSchema();

		$exists = (int) $this->wpdb()->get_var(
			$this->wpdb()->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$this->journal->table()
			)
		);
		$this->assertSame( 1, $exists );
	}

	public function test_ensure_schema_is_idempotent() {
		$this->journal->ensureSchema();
		$this->journal->ensureSchema();
		$this->journal->ensureSchema();

		$this->assertTrue( $this->journal->isApplied( 'never' ) === false );
	}

	public function test_record_returns_true_on_first_write_and_false_on_repeat() {
		$this->assertTrue( $this->journal->record( 'create_users_table' ) );
		$this->assertFalse( $this->journal->record( 'create_users_table' ) );
	}

	public function test_record_reuses_existing_checksum_silently() {
		$this->journal->record( 'create_users_table', 'sha256-of-payload' );
		$this->assertFalse( $this->journal->record( 'create_users_table', 'sha256-of-payload' ) );
	}

	public function test_record_throws_when_checksum_changes_for_the_same_migration() {
		$this->journal->record( 'create_users_table', 'old-checksum' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'create_users_table' );

		$this->journal->record( 'create_users_table', 'new-checksum' );
	}

	public function test_is_applied_is_false_until_recorded() {
		$this->assertFalse( $this->journal->isApplied( 'create_users_table' ) );
		$this->journal->record( 'create_users_table' );
		$this->assertTrue( $this->journal->isApplied( 'create_users_table' ) );
	}

	public function test_record_persists_notes_when_provided() {
		$this->journal->record(
			'add_index_to_postmeta',
			'sha256-of-payload',
			'Adds idx_postmeta_meta_key for hot read path.'
		);

		$table = $this->journal->table();
		$notes = $this->wpdb()->get_var(
			$this->wpdb()->prepare(
				"SELECT notes FROM {$table} WHERE migration_name = %s",
				'add_index_to_postmeta'
			)
		);

		$this->assertSame(
			'Adds idx_postmeta_meta_key for hot read path.',
			$notes
		);
	}

	public function test_applied_returns_entries_in_apply_order() {
		$this->journal->record( 'm1' );
		$this->journal->record( 'm2' );
		$this->journal->record( 'm3' );

		$rows = $this->journal->applied();

		$this->assertCount( 3, $rows );
		$this->assertSame( 'm1', $rows[0]['migration_name'] );
		$this->assertSame( 'm2', $rows[1]['migration_name'] );
		$this->assertSame( 'm3', $rows[2]['migration_name'] );
	}

	public function test_applied_returns_empty_array_when_journal_is_empty() {
		$this->journal->ensureSchema();
		$this->assertSame( array(), $this->journal->applied() );
	}

	public function test_pending_returns_only_unapplied_names_in_declared_order() {
		$this->journal->record( 'm2' );

		$pending = $this->journal->pending( array( 'm1', 'm2', 'm3', 'm4' ) );

		$this->assertSame( array( 'm1', 'm3', 'm4' ), $pending );
	}

	public function test_pending_returns_empty_when_all_applied() {
		$this->journal->record( 'm1' );
		$this->journal->record( 'm2' );

		$this->assertSame(
			array(),
			$this->journal->pending( array( 'm1', 'm2' ) )
		);
	}

	public function test_pending_returns_everything_when_journal_is_empty() {
		$expected = array( 'm1', 'm2', 'm3' );
		$this->assertSame( $expected, $this->journal->pending( $expected ) );
	}

	public function test_journal_table_uses_utf8mb4_and_innodb() {
		$this->journal->ensureSchema();

		$row = $this->wpdb()->get_row(
			$this->wpdb()->prepare(
				'SELECT CCSA.CHARACTER_SET_NAME AS charset, T.ENGINE AS engine
				 FROM information_schema.TABLES AS T
				 JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY AS CCSA
				   ON CCSA.TABLE_SCHEMA = T.TABLE_SCHEMA
				  AND CCSA.TABLE_NAME   = T.TABLE_NAME
				  AND CCSA.COLLATION_NAME = T.TABLE_COLLATION
				 WHERE T.TABLE_SCHEMA = %s
				   AND T.TABLE_NAME = %s',
				DB_NAME,
				$this->journal->table()
			),
			ARRAY_A
		);

		$this->assertSame( 'utf8mb4', $row['charset'] );
		$this->assertSame( 'InnoDB', $row['engine'] );
	}

	public function test_drop_schema_removes_the_table_so_a_subsequent_record_recreates_it() {
		$this->journal->record( 'm1' );
		$this->assertTrue( $this->journal->isApplied( 'm1' ) );

		$this->journal->dropSchema();
		$this->assertFalse( $this->journal->isApplied( 'm1' ) );

		// record() must rebuild the table so callers don't have to call
		// ensureSchema() first.
		$this->journal->record( 'm1' );
		$this->assertTrue( $this->journal->isApplied( 'm1' ) );
	}
}
