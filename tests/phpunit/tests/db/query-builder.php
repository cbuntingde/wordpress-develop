<?php
/**
 * Tests for the QueryBuilder — fluent typed SQL builder.
 *
 * Pure unit tests, no wpdb, no DB connection. Per
 * MODERNIZATION_PLAN.md Phase 2 task 2 (MWC26 §7.3).
 *
 * @group database
 * @group query-builder
 *
 * @covers QueryBuilder
 */
class Tests_DB_QueryBuilder extends WP_UnitTestCase {

	/**
	 * Loads the database module once for the suite.
	 *
	 * @beforeClass
	 */
	public static function wpSetUpBeforeClass(): void {
		require_once ABSPATH . WPINC . '/database/load.php';
	}

	public function test_select_default_projects_star() {
		$builder = QueryBuilder::select();
		$this->assertSame( array( '*' ), $builder->columns() );
		$this->assertSame( 'SELECT', $builder->state()['type'] );
	}

	public function test_select_with_columns_stores_them() {
		$builder = QueryBuilder::select( 'ID', 'post_title' );
		$this->assertSame( array( 'ID', 'post_title' ), $builder->columns() );
	}

	public function test_select_from_quotes_target_identifier() {
		$dialect = new MySqlDialect();
		$builder = QueryBuilder::select( 'ID' )->from( 'wp_posts' );

		$this->assertSame( 'SELECT `ID` FROM `wp_posts`', $builder->toSql( $dialect ) );
		$this->assertSame( array(), $builder->bindings() );
	}

	public function test_select_where_emits_placeholder_and_collects_binding() {
		$dialect = new MySqlDialect();
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )->where( 'ID', 123 );

		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE `ID` = %d',
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 123 ), $builder->bindings() );
	}

	public function test_where_op_supports_all_comparison_operators() {
		$dialect = new MySqlDialect();
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->whereOp( 'ID', '>', 10 )
			->whereOp( 'ID', '<=', 20 );

		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE `ID` > %d AND `ID` <= %d',
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 10, 20 ), $builder->bindings() );
	}

	public function test_where_in_emits_one_placeholder_per_value() {
		$dialect = new MySqlDialect();
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->whereIn( 'ID', array( 1, 2, 3 ) );

		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE `ID` IN (%d, %d, %d)',
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 1, 2, 3 ), $builder->bindings() );
	}

	public function test_where_not_in_emits_not_in() {
		$dialect = new MySqlDialect();
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->whereNotIn( 'post_status', array( 'trash', 'auto-draft' ) );

		$this->assertSame(
			"SELECT * FROM `wp_posts` WHERE `post_status` NOT IN (%s, %s)",
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 'trash', 'auto-draft' ), $builder->bindings() );
	}

	public function test_where_null_and_not_null() {
		$dialect = new MySqlDialect();

		$builder_a = QueryBuilder::select( '*' )->from( 'wp_posts' )->whereNull( 'post_parent' );
		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE `post_parent` IS NULL',
			$builder_a->toSql( $dialect )
		);
		$this->assertSame( array(), $builder_a->bindings() );

		$builder_b = QueryBuilder::select( '*' )->from( 'wp_posts' )->whereNotNull( 'post_parent' );
		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE `post_parent` IS NOT NULL',
			$builder_b->toSql( $dialect )
		);
	}

	public function test_where_between_emits_two_placeholders() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->whereBetween( 'ID', 1, 100 );

		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE `ID` BETWEEN %d AND %d',
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 1, 100 ), $builder->bindings() );
	}

	public function test_where_raw_passes_sql_through() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->whereRaw( 'MATCH(post_title) AGAINST (%s)', array( 'hello' ) );

		$this->assertSame(
			"SELECT * FROM `wp_posts` WHERE MATCH(post_title) AGAINST (%s)",
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 'hello' ), $builder->bindings() );
	}

	public function test_inner_join_quotes_target_and_keeps_on_raw() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::select( 'p.ID', 'pm.meta_value' )
			->from( 'wp_posts' )
			->innerJoin( 'wp_postmeta', 'pm.post_id = p.ID' );

		$this->assertSame(
			'SELECT `p`.`ID`, `pm`.`meta_value` FROM `wp_posts` INNER JOIN `wp_postmeta` ON pm.post_id = p.ID',
			$builder->toSql( $dialect )
		);
	}

	public function test_left_and_right_join_render_with_typed_keyword() {
		$dialect = new MySqlDialect();

		$builder_left = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->leftJoin( 'wp_postmeta', 'wp_postmeta.post_id = wp_posts.ID' );
		$this->assertStringContainsString( 'LEFT JOIN', $builder_left->toSql( $dialect ) );

		$builder_right = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->rightJoin( 'wp_postmeta', 'wp_postmeta.post_id = wp_posts.ID' );
		$this->assertStringContainsString( 'RIGHT JOIN', $builder_right->toSql( $dialect ) );
	}

	public function test_group_by_and_order_by_render_in_order() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->groupBy( 'post_type', 'post_status' )
			->orderBy( 'ID', 'DESC' );

		$this->assertSame(
			'SELECT * FROM `wp_posts` GROUP BY `post_type`, `post_status` ORDER BY `ID` DESC',
			$builder->toSql( $dialect )
		);
	}

	public function test_limit_and_offset_emit_placeholders() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::select( '*' )->from( 'wp_posts' )
			->orderBy( 'ID' )
			->limit( 10 )
			->offset( 20 );

		$this->assertSame(
			'SELECT * FROM `wp_posts` ORDER BY `ID` ASC LIMIT %d OFFSET %d',
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 10, 20 ), $builder->bindings() );
	}

	public function test_full_query_chain_produces_ordered_sql_and_bindings() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::select( 'p.ID', 'p.post_title' )
			->from( 'wp_posts' )
			->innerJoin( 'wp_postmeta', 'wp_postmeta.post_id = p.ID' )
			->where( 'p.post_type', 'post' )
			->whereOp( 'p.ID', '>', 0 )
			->whereIn( 'p.post_status', array( 'publish', 'future' ) )
			->whereNull( 'p.post_parent' )
			->groupBy( 'p.ID' )
			->orderBy( 'p.ID', 'DESC' )
			->limit( 5 )
			->offset( 10 );

		$expected_sql      = 'SELECT `p`.`ID`, `p`.`post_title` FROM `wp_posts`'
			. ' INNER JOIN `wp_postmeta` ON wp_postmeta.post_id = p.ID'
			. ' WHERE `p`.`post_type` = %s'
			. ' AND `p`.`ID` > %d'
			. ' AND `p`.`post_status` IN (%s, %s)'
			. ' AND `p`.`post_parent` IS NULL'
			. ' GROUP BY `p`.`ID`'
			. ' ORDER BY `p`.`ID` DESC'
			. ' LIMIT %d OFFSET %d';
		$expected_bindings = array( 'post', 0, 'publish', 'future', 5, 10 );

		$this->assertSame( $expected_sql, $builder->toSql( $dialect ) );
		$this->assertSame( $expected_bindings, $builder->bindings() );
	}

	public function test_mariadb_dialect_renders_identically_to_mysql_for_select() {
		$mysql    = new MySqlDialect();
		$mariadb  = new MariaDbDialect();
		$builder  = QueryBuilder::select( '*' )->from( 'wp_options' )
			->where( 'option_name', 'siteurl' )
			->limit( 1 );

		$this->assertSame(
			$builder->toSql( $mysql ),
			$builder->toSql( $mariadb )
		);
	}

	public function test_insert_with_set_emits_columns_and_values() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::insert( 'wp_options' )
			->set( 'option_name', 'siteurl' )
			->set( 'option_value', 'https://example.com' );

		$this->assertSame(
			"INSERT INTO `wp_options` (`option_name`, `option_value`) VALUES (%s, %s)",
			$builder->toSql( $dialect )
		);
		$this->assertSame(
			array( 'siteurl', 'https://example.com' ),
			$builder->bindings()
		);
	}

	public function test_update_renders_set_and_where_clauses() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::update( 'wp_posts' )
			->set( 'post_status', 'publish' )
			->where( 'ID', 7 );

		$this->assertSame(
			"UPDATE `wp_posts` SET `post_status` = %s WHERE `ID` = %d",
			$builder->toSql( $dialect )
		);
		$this->assertSame(
			array( 'publish', 7 ),
			$builder->bindings()
		);
	}

	public function test_delete_renders_target_and_where() {
		$dialect = new MySqlDialect();
		$builder  = QueryBuilder::delete( 'wp_postmeta' )->where( 'post_id', 9 );

		$this->assertSame(
			'DELETE FROM `wp_postmeta` WHERE `post_id` = %d',
			$builder->toSql( $dialect )
		);
		$this->assertSame( array( 9 ), $builder->bindings() );
	}

	public function test_limit_rejects_negative_value() {
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' );
		$this->expectException( \InvalidArgumentException::class );
		$builder->limit( -1 );
	}

	public function test_offset_rejects_negative_value() {
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' );
		$this->expectException( \InvalidArgumentException::class );
		$builder->offset( -1 );
	}

	public function test_order_by_rejects_unknown_direction() {
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' );
		$this->expectException( \InvalidArgumentException::class );
		$builder->orderBy( 'ID', 'SIDEWAYS' );
	}

	public function test_state_returns_accumulated_query_as_plain_arrays() {
		$builder = QueryBuilder::update( 'wp_posts' )
			->set( 'post_status', 'draft' )
			->where( 'ID', 1 );

		$state = $builder->state();

		$this->assertSame( 'UPDATE', $state['type'] );
		$this->assertSame( 'wp_posts', $state['target'] );
		$this->assertCount( 1, $state['set_values'] );
		$this->assertCount( 1, $state['wheres'] );
		$this->assertSame( 'post_status', $state['set_values'][0]['column'] );
		$this->assertSame( 'draft', $state['set_values'][0]['value'] );
	}

	public function test_format_for_int_emits_d_placeholder() {
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )->where( 'ID', 5 );
		$this->assertSame( array( 5 ), $builder->bindings() );
		$this->assertStringContainsString( '%d', $builder->toSql( new MySqlDialect() ) );
	}

	public function test_format_for_float_emits_F_placeholder() {
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )->where( 'rating', 4.5 );
		$this->assertSame( array( 4.5 ), $builder->bindings() );
		$this->assertStringContainsString( '%F', $builder->toSql( new MySqlDialect() ) );
	}

	public function test_format_for_string_emits_s_placeholder() {
		$builder = QueryBuilder::select( '*' )->from( 'wp_posts' )->where( 'post_type', 'post' );
		$this->assertSame( array( 'post' ), $builder->bindings() );
		$this->assertStringContainsString( '%s', $builder->toSql( new MySqlDialect() ) );
	}

	public function test_where_in_with_empty_values_renders_safe_clause() {
		$dialect = new MySqlDialect();

		// Empty `IN ()` is invalid SQL in MySQL/MariaDB. The builder
		// collapses the empty list to a literal that produces the right
		// result without ever hitting the database: 1 = 0 for `IN ()`
		// (no match possible) and 1 = 1 for `NOT IN ()` (everything
		// matches).
		$builder_in = QueryBuilder::select( '*' )->from( 'wp_posts' )->whereIn( 'ID', array() );
		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE 1 = 0',
			$builder_in->toSql( $dialect )
		);

		$builder_not_in = QueryBuilder::select( '*' )->from( 'wp_posts' )->whereNotIn( 'ID', array() );
		$this->assertSame(
			'SELECT * FROM `wp_posts` WHERE 1 = 1',
			$builder_not_in->toSql( $dialect )
		);
	}

	public function test_wpdb_build_query_renders_against_connected_dialect() {
		$dialect = $this->wpdb()->dialect;
		$this->assertInstanceOf( DatabaseDialect::class, $dialect );

		$builder = QueryBuilder::select( '*' )->from( 'wp_options' )->where( 'option_name', 'siteurl' );
		$sql     = $this->wpdb()->buildQuery( $builder );

		$this->assertSame(
			"SELECT * FROM `wp_options` WHERE `option_name` = 'siteurl'",
			$sql
		);
	}
}
