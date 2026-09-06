<?php
/**
 * Tests for the DatabaseDialect module — DatabaseDialect, Query,
 * Expression, DatabaseFeatures, MySqlDialect, MariaDbDialect.
 *
 * Pure unit tests; no wpdb, no DB connection. Phase 2 task 2 (the
 * query builder) and Phase 4A (domain services) will exercise the
 * dialects against real engines.
 *
 * @group database
 * @group database-dialect
 *
 * @covers MySqlDialect
 * @covers MariaDbDialect
 * @covers Query
 * @covers Expression
 * @covers DatabaseFeatures
 */
class Tests_DB_Dialects extends WP_UnitTestCase {

	/**
	 * Loads the dialect module once for the suite. The runtime loads
	 * it via wpdb's procedural require_once; tests need to do the
	 * same so the classes resolve before the test methods run.
	 *
	 * @beforeClass
	 */
	public static function wpSetUpBeforeClass(): void {
		require_once ABSPATH . WPINC . '/database/load.php';
	}

	public function test_mysql_dialect_quotes_simple_identifier() {
		$dialect = new MySqlDialect();
		$this->assertSame( '`posts`', $dialect->quoteIdentifier( 'posts' ) );
	}

	public function test_mysql_dialect_quotes_dotted_identifier() {
		$dialect = new MySqlDialect();
		$this->assertSame(
			'`wp_posts`.`ID`',
			$dialect->quoteIdentifier( 'wp_posts.ID' )
		);
	}

	public function test_mysql_dialect_quotes_three_segment_identifier() {
		$dialect = new MySqlDialect();
		$this->assertSame(
			'`db`.`wp_posts`.`ID`',
			$dialect->quoteIdentifier( 'db.wp_posts.ID' )
		);
	}

	public function test_mysql_dialect_escapes_embedded_backtick() {
		$dialect = new MySqlDialect();
		$this->assertSame(
			'`a``b`',
			$dialect->quoteIdentifier( 'a`b' )
		);
	}

	public function test_mysql_dialect_quotes_dotted_identifier_with_embedded_backtick() {
		$dialect = new MySqlDialect();
		$this->assertSame(
			'`wp_options`.`option``value`',
			$dialect->quoteIdentifier( 'wp_options.option`value' )
		);
	}

	public function test_mariadb_dialect_quotes_simple_identifier() {
		$dialect = new MariaDbDialect();
		$this->assertSame( '`posts`', $dialect->quoteIdentifier( 'posts' ) );
	}

	public function test_mariadb_dialect_quotes_dotted_identifier() {
		$dialect = new MariaDbDialect();
		$this->assertSame(
			'`wp_options`.`option_value`',
			$dialect->quoteIdentifier( 'wp_options.option_value' )
		);
	}

	public function test_dialects_are_parity_on_quote_identifier() {
		$mysql    = new MySqlDialect();
		$mariadb  = new MariaDbDialect();
		$samples  = array( 'posts', 'wp_posts.ID', 'a`b', 'wp_options.option_value' );

		foreach ( $samples as $name ) {
			$this->assertSame(
				$mysql->quoteIdentifier( $name ),
				$mariadb->quoteIdentifier( $name ),
				'Quote parity must hold for ' . $name
			);
		}
	}

	public function test_mysql_dialect_supports_common_features() {
		$dialect = new MySqlDialect();
		$this->assertTrue( $dialect->supports( DatabaseFeatures::UPSERT_SYNTAX ) );
		$this->assertTrue( $dialect->supports( DatabaseFeatures::JSON_PATH_EXTRACT ) );
		$this->assertTrue( $dialect->supports( DatabaseFeatures::CTE ) );
		$this->assertTrue( $dialect->supports( DatabaseFeatures::WINDOW_FUNCTIONS ) );
		$this->assertTrue( $dialect->supports( DatabaseFeatures::GENERATED_COLUMNS ) );
	}

	public function test_mysql_dialect_does_not_support_returning_or_sequences() {
		$dialect = new MySqlDialect();
		$this->assertFalse( $dialect->supports( DatabaseFeatures::RETURNING_CLAUSE ) );
		$this->assertFalse( $dialect->supports( DatabaseFeatures::SEQUENCES ) );
	}

	public function test_mariadb_dialect_supports_returning_and_sequences() {
		$dialect = new MariaDbDialect();
		$this->assertTrue( $dialect->supports( DatabaseFeatures::RETURNING_CLAUSE ) );
		$this->assertTrue( $dialect->supports( DatabaseFeatures::SEQUENCES ) );
	}

	public function test_dialects_return_false_for_unknown_feature() {
		$this->assertFalse( ( new MySqlDialect() )->supports( 'not_a_real_feature' ) );
		$this->assertFalse( ( new MariaDbDialect() )->supports( 'not_a_real_feature' ) );
	}

	public function test_dialects_are_parity_on_common_feature_set() {
		$mysql   = new MySqlDialect();
		$mariadb = new MariaDbDialect();
		$common  = array(
			DatabaseFeatures::UPSERT_SYNTAX,
			DatabaseFeatures::JSON_PATH_EXTRACT,
			DatabaseFeatures::CTE,
			DatabaseFeatures::WINDOW_FUNCTIONS,
			DatabaseFeatures::GENERATED_COLUMNS,
		);

		foreach ( $common as $feature ) {
			$this->assertSame(
				$mysql->supports( $feature ),
				$mariadb->supports( $feature ),
				'Feature parity must hold for ' . $feature
			);
		}
	}

	public function test_mysql_dialect_build_upsert_returns_structured_query() {
		$dialect  = new MySqlDialect();
		$query    = $dialect->buildUpsert(
			'wp_options',
			array( 'option_name' => 'siteurl' ),
			array( 'option_name' ),
			array( 'option_name' )
		);

		$this->assertInstanceOf( Query::class, $query );
		$this->assertSame( 'wp_options', $query->target );
		$this->assertSame( array( 'option_name' ), $query->columns );
		$this->assertSame( array( 'siteurl' ), $query->parameters );
	}

	public function test_mariadb_dialect_build_upsert_returns_structured_query() {
		$dialect = new MariaDbDialect();
		$query   = $dialect->buildUpsert(
			'wp_options',
			array(
				'option_name'  => 'siteurl',
				'option_value' => 'https://example.com',
			),
			array( 'option_value' ),
			array( 'option_name' )
		);

		$this->assertInstanceOf( Query::class, $query );
		$this->assertSame( 'wp_options', $query->target );
		$this->assertSame(
			array( 'option_name', 'option_value' ),
			$query->columns
		);
		$this->assertSame(
			array( 'siteurl', 'https://example.com' ),
			$query->parameters
		);
	}

	public function test_mysql_dialect_build_json_expression_quotes_column_and_path() {
		$dialect    = new MySqlDialect();
		$expression = $dialect->buildJsonExpression( 'wp_postmeta.meta_value', '$.url' );

		$this->assertInstanceOf( Expression::class, $expression );
		$this->assertStringContainsString( 'JSON_EXTRACT(', $expression->sql );
		$this->assertStringContainsString( '`wp_postmeta`.`meta_value`', $expression->sql );
		$this->assertStringContainsString( "'$.url'", $expression->sql );
		$this->assertSame( array(), $expression->bindings );
	}

	public function test_mariadb_dialect_build_json_expression_parity_with_mysql() {
		$mysql_expression    = ( new MySqlDialect() )->buildJsonExpression( 'wp_postmeta.meta_value', '$.url' );
		$mariadb_expression  = ( new MariaDbDialect() )->buildJsonExpression( 'wp_postmeta.meta_value', '$.url' );

		$this->assertSame( $mysql_expression->sql, $mariadb_expression->sql );
		$this->assertSame( $mysql_expression->bindings, $mariadb_expression->bindings );
	}

	public function test_dialect_build_json_expression_escapes_single_quote_in_path() {
		$dialect    = new MySqlDialect();
		$expression = $dialect->buildJsonExpression( 'data.payload', "$.it's" );

		$this->assertStringContainsString( "''", $expression->sql );
		$this->assertStringNotContainsString( "$.it's", $expression->sql );
	}

	public function test_query_value_object_is_readonly() {
		$query = new Query( 'wp_posts', array( 'ID' ), array( 1 ) );

		$this->expectException( \Error::class );
		$query->target = 'wp_options';
	}

	public function test_expression_value_object_is_readonly() {
		$expression = new Expression( 'NOW()', array() );

		$this->expectException( \Error::class );
		$expression->sql = 'CURRENT_TIMESTAMP';
	}
}
