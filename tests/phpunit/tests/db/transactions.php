<?php
/**
 * Tests for wpdb's transaction API with savepoint nesting.
 *
 * Per MODERNIZATION_PLAN.md Phase 2 task 3 (MWC26 §7.4).
 *
 * @group database
 * @group transactions
 *
 * @covers wpdb::begin_transaction
 * @covers wpdb::commit
 * @covers wpdb::rollback
 * @covers wpdb::in_transaction
 * @covers wpdb::transaction_level
 */
class Tests_DB_Transactions extends WP_UnitTestCase {

	public function test_in_transaction_is_false_initially() {
		$this->assertFalse( $this->wpdb()->in_transaction() );
		$this->assertSame( 0, $this->wpdb()->transaction_level() );
	}

	public function test_begin_transaction_opens_a_transaction_and_levels_one() {
		$wpdb = $this->wpdb();
		$wpdb->begin_transaction();

		$this->assertTrue( $wpdb->in_transaction() );
		$this->assertSame( 1, $wpdb->transaction_level() );
	}

	public function test_commit_closes_the_transaction_and_levels_zero() {
		$wpdb = $this->wpdb();
		$wpdb->begin_transaction();
		$wpdb->commit();

		$this->assertFalse( $wpdb->in_transaction() );
		$this->assertSame( 0, $wpdb->transaction_level() );
	}

	public function test_rollback_closes_the_transaction_and_levels_zero() {
		$wpdb = $this->wpdb();
		$wpdb->begin_transaction();
		$wpdb->rollback();

		$this->assertFalse( $wpdb->in_transaction() );
		$this->assertSame( 0, $wpdb->transaction_level() );
	}

	public function test_commit_without_begin_returns_false() {
		$this->assertFalse( $this->wpdb()->commit() );
		$this->assertSame( 0, $this->wpdb()->transaction_level() );
	}

	public function test_rollback_without_begin_returns_false() {
		$this->assertFalse( $this->wpdb()->rollback() );
		$this->assertSame( 0, $this->wpdb()->transaction_level() );
	}

	public function test_nested_begin_uses_savepoint_and_levels_two() {
		$wpdb = $this->wpdb();
		$wpdb->begin_transaction();
		$wpdb->begin_transaction();

		$this->assertSame( 2, $wpdb->transaction_level() );
		$this->assertTrue( $wpdb->in_transaction() );
	}

	public function test_nested_commit_releases_savepoint_and_drops_one_level() {
		$wpdb = $this->wpdb();
		$wpdb->begin_transaction();
		$wpdb->begin_transaction();
		$wpdb->commit();

		$this->assertSame( 1, $wpdb->transaction_level() );
		$this->assertTrue( $wpdb->in_transaction() );
	}

	public function test_nested_rollback_undoes_savepoint_and_drops_one_level() {
		$wpdb = $this->wpdb();
		$wpdb->begin_transaction();
		$wpdb->begin_transaction();
		$wpdb->rollback();

		$this->assertSame( 1, $wpdb->transaction_level() );
		$this->assertTrue( $wpdb->in_transaction() );
	}

	public function test_outer_commit_after_nested_rollback_commits_outer_work() {
		$wpdb = $this->wpdb();

		$wpdb->query( "CREATE TEMPORARY TABLE wp_tx_test ( id INT PRIMARY KEY, val VARCHAR(10) )" );

		$wpdb->begin_transaction();
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (1, 'outer')" );
		$wpdb->begin_transaction();
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (2, 'inner')" );
		$wpdb->rollback();
		$wpdb->commit();

		$rows = $wpdb->get_results( 'SELECT * FROM wp_tx_test ORDER BY id' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'outer', $rows[0]->val );

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS wp_tx_test' );
	}

	public function test_rollback_undoes_all_writes_in_transaction() {
		$wpdb = $this->wpdb();

		$wpdb->query( "CREATE TEMPORARY TABLE wp_tx_test ( id INT PRIMARY KEY, val VARCHAR(10) )" );

		$wpdb->begin_transaction();
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (1, 'a')" );
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (2, 'b')" );
		$wpdb->rollback();

		$rows = $wpdb->get_results( 'SELECT * FROM wp_tx_test' );
		$this->assertCount( 0, $rows );

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS wp_tx_test' );
	}

	public function test_commit_persists_all_writes_in_transaction() {
		$wpdb = $this->wpdb();

		$wpdb->query( "CREATE TEMPORARY TABLE wp_tx_test ( id INT PRIMARY KEY, val VARCHAR(10) )" );

		$wpdb->begin_transaction();
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (1, 'a')" );
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (2, 'b')" );
		$wpdb->commit();

		$rows = $wpdb->get_results( 'SELECT * FROM wp_tx_test ORDER BY id' );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'a', $rows[0]->val );
		$this->assertSame( 'b', $rows[1]->val );

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS wp_tx_test' );
	}

	public function test_three_levels_of_nesting_unwind_in_lifo_order() {
		$wpdb = $this->wpdb();

		$wpdb->begin_transaction();
		$this->assertSame( 1, $wpdb->transaction_level() );

		$wpdb->begin_transaction();
		$this->assertSame( 2, $wpdb->transaction_level() );

		$wpdb->begin_transaction();
		$this->assertSame( 3, $wpdb->transaction_level() );

		$wpdb->rollback();
		$this->assertSame( 2, $wpdb->transaction_level() );

		$wpdb->rollback();
		$this->assertSame( 1, $wpdb->transaction_level() );

		$wpdb->commit();
		$this->assertSame( 0, $wpdb->transaction_level() );
		$this->assertFalse( $wpdb->in_transaction() );
	}

	public function test_nested_rollback_undoes_only_savepoint_scoped_writes() {
		$wpdb = $this->wpdb();

		$wpdb->query( "CREATE TEMPORARY TABLE wp_tx_test ( id INT PRIMARY KEY, val VARCHAR(10) )" );

		$wpdb->begin_transaction();
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (1, 'outer-1')" );

		$wpdb->begin_transaction();
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (2, 'inner-1')" );
		$wpdb->query( "INSERT INTO wp_tx_test VALUES (3, 'inner-2')" );
		$wpdb->rollback();

		$wpdb->query( "INSERT INTO wp_tx_test VALUES (4, 'outer-2')" );
		$wpdb->commit();

		$rows = $wpdb->get_results( 'SELECT * FROM wp_tx_test ORDER BY id' );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'outer-1', $rows[0]->val );
		$this->assertSame( 'outer-2', $rows[1]->val );

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS wp_tx_test' );
	}

	public function test_begin_after_an_implicit_commit_resumes_cleanly() {
		$wpdb = $this->wpdb();

		$wpdb->begin_transaction();
		$wpdb->commit();

		$wpdb->begin_transaction();
		$this->assertSame( 1, $wpdb->transaction_level() );

		$wpdb->commit();
		$this->assertSame( 0, $wpdb->transaction_level() );
	}
}
