<?php
/**
 * Class QueryBuilder — fluent typed SQL builder.
 *
 * The builder accumulates a structured query (type, target, joins,
 * wheres, ordering, limits, set clauses) and renders to a prepared
 * SQL string with a flat bindings array. Callers feed the output to
 * wpdb::prepare() for value escaping and substitution, exactly like
 * the long-standing %s/%d/%i placeholder form.
 *
 * Per MODERNIZATION_PLAN.md Phase 2 task 2 (MWC26 §7.3). This is the
 * typed foundation core callers migrate to in Phase 3A/4A/4B;
 * wpdb::prepare() remains in place until no core caller uses it
 * directly.
 *
 * @package WordPress
 */

declare(strict_types=1);

final class QueryBuilder {

	/**
	 * Statement type. One of SELECT, INSERT, UPDATE, DELETE.
	 */
	private string $type = 'SELECT';

	/**
	 * Columns to project for SELECT, or column list for INSERT.
	 *
	 * @var list<string>
	 */
	private array $columns = array();

	/**
	 * Target table name for INSERT/UPDATE/DELETE and the FROM clause
	 * for SELECT.
	 */
	private ?string $target = null;

	/**
	 * Join clauses.
	 *
	 * @var list<array{type: string, target: string, on: string}>
	 */
	private array $joins = array();

	/**
	 * WHERE clauses in render order.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $wheres = array();

	/**
	 * GROUP BY columns.
	 *
	 * @var list<string>
	 */
	private array $group_by = array();

	/**
	 * ORDER BY clauses.
	 *
	 * @var list<array{column: string, direction: string}>
	 */
	private array $order_by = array();

	/**
	 * LIMIT clause value or null when unset.
	 */
	private ?int $limit = null;

	/**
	 * OFFSET clause value or null when unset.
	 */
	private ?int $offset = null;

	/**
	 * SET clauses for UPDATE / INSERT VALUES.
	 *
	 * @var list<array{column: string, value: mixed, format: string}>
	 */
	private array $set_values = array();

	/**
	 * VALUES rows for INSERT (each row is a column => value map).
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $value_rows = array();

	/**
	 * Starts a SELECT statement. Default projection is *.
	 *
	 * @param string ...$columns Columns to project, or `*` (the default) for every column.
	 */
	public static function select( string ...$columns ): self {
		$builder           = new self();
		$builder->type     = 'SELECT';
		$builder->columns  = array() === $columns ? array( '*' ) : array_values( $columns );

		return $builder;
	}

	/**
	 * Starts an INSERT statement against `$table`.
	 */
	public static function insert( string $table ): self {
		$builder        = new self();
		$builder->type  = 'INSERT';
		$builder->target = $table;

		return $builder;
	}

	/**
	 * Starts an UPDATE statement against `$table`.
	 */
	public static function update( string $table ): self {
		$builder        = new self();
		$builder->type  = 'UPDATE';
		$builder->target = $table;

		return $builder;
	}

	/**
	 * Starts a DELETE statement against `$table`.
	 */
	public static function delete( string $table ): self {
		$builder        = new self();
		$builder->type  = 'DELETE';
		$builder->target = $table;

		return $builder;
	}

	/**
	 * Sets the FROM target for a SELECT.
	 */
	public function from( string $table ): self {
		$this->target = $table;

		return $this;
	}

	/**
	 * Adds an INNER JOIN.
	 *
	 * @param string $table Joined table.
	 * @param string $on    Raw ON expression (caller is responsible for identifier quoting).
	 */
	public function innerJoin( string $table, string $on ): self {
		return $this->addJoin( 'INNER', $table, $on );
	}

	/**
	 * Adds a LEFT JOIN.
	 *
	 * @param string $table Joined table.
	 * @param string $on    Raw ON expression (caller is responsible for identifier quoting).
	 */
	public function leftJoin( string $table, string $on ): self {
		return $this->addJoin( 'LEFT', $table, $on );
	}

	/**
	 * Adds a RIGHT JOIN.
	 *
	 * @param string $table Joined table.
	 * @param string $on    Raw ON expression (caller is responsible for identifier quoting).
	 */
	public function rightJoin( string $table, string $on ): self {
		return $this->addJoin( 'RIGHT', $table, $on );
	}

	private function addJoin( string $type, string $table, string $on ): self {
		$this->joins[] = array(
			'type'   => $type,
			'target' => $table,
			'on'     => $on,
		);

		return $this;
	}

	/**
	 * Adds a `column = value` WHERE clause.
	 */
	public function where( string $column, mixed $value ): self {
		return $this->whereOp( $column, '=', $value );
	}

	/**
	 * Adds a `column OP value` WHERE clause with an explicit comparison operator.
	 *
	 * @param string $operator One of =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.
	 */
	public function whereOp( string $column, string $operator, mixed $value ): self {
		$this->wheres[] = array(
			'kind'     => 'comparison',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'format'   => $this->formatFor( $value ),
		);

		return $this;
	}

	/**
	 * Adds a raw WHERE expression. The SQL is emitted verbatim; bindings are
	 * mixed into the bindings array in the order they appear.
	 *
	 * @param string        $sql      Raw SQL fragment (no leading AND).
	 * @param array<mixed>  $bindings Values referenced by the raw SQL's placeholders.
	 */
	public function whereRaw( string $sql, array $bindings = array() ): self {
		$this->wheres[] = array(
			'kind'     => 'raw',
			'sql'      => $sql,
			'bindings' => array_values( $bindings ),
		);

		return $this;
	}

	/**
	 * Adds a `column IN (...)` clause.
	 *
	 * @param array<mixed> $values Values to match.
	 */
	public function whereIn( string $column, array $values ): self {
		return $this->addInClause( $column, $values, false );
	}

	/**
	 * Adds a `column NOT IN (...)` clause.
	 *
	 * @param array<mixed> $values Values to exclude.
	 */
	public function whereNotIn( string $column, array $values ): self {
		return $this->addInClause( $column, $values, true );
	}

	private function addInClause( string $column, array $values, bool $negated ): self {
		$this->wheres[] = array(
			'kind'    => 'in',
			'column'  => $column,
			'values'  => array_values( $values ),
			'negated' => $negated,
		);

		return $this;
	}

	/**
	 * Adds a `column IS NULL` clause.
	 */
	public function whereNull( string $column ): self {
		$this->wheres[] = array(
			'kind'    => 'null',
			'column'  => $column,
			'negated' => false,
		);

		return $this;
	}

	/**
	 * Adds a `column IS NOT NULL` clause.
	 */
	public function whereNotNull( string $column ): self {
		$this->wheres[] = array(
			'kind'    => 'null',
			'column'  => $column,
			'negated' => true,
		);

		return $this;
	}

	/**
	 * Adds a `column BETWEEN start AND end` clause.
	 */
	public function whereBetween( string $column, mixed $start, mixed $end ): self {
		$this->wheres[] = array(
			'kind'    => 'between',
			'column'  => $column,
			'start'   => $start,
			'end'     => $end,
			'negated' => false,
		);

		return $this;
	}

	/**
	 * Adds one or more GROUP BY columns.
	 */
	public function groupBy( string ...$columns ): self {
		foreach ( $columns as $column ) {
			$this->group_by[] = $column;
		}

		return $this;
	}

	/**
	 * Adds an ORDER BY column. Direction is ASC by default.
	 */
	public function orderBy( string $column, string $direction = 'ASC' ): self {
		$direction = strtoupper( $direction );
		if ( 'ASC' !== $direction && 'DESC' !== $direction ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s: Invalid direction value. */
					__( 'ORDER BY direction must be ASC or DESC, got: %s' ),
					$direction
				)
			);
		}

		$this->order_by[] = array(
			'column'    => $column,
			'direction' => $direction,
		);

		return $this;
	}

	/**
	 * Sets a non-negative LIMIT.
	 */
	public function limit( int $limit ): self {
		if ( $limit < 0 ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %d: Invalid limit value. */
					__( 'LIMIT must be non-negative, got: %d' ),
					$limit
				)
			);
		}
		$this->limit = $limit;

		return $this;
	}

	/**
	 * Sets a non-negative OFFSET.
	 */
	public function offset( int $offset ): self {
		if ( $offset < 0 ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %d: Invalid offset value. */
					__( 'OFFSET must be non-negative, got: %d' ),
					$offset
				)
			);
		}
		$this->offset = $offset;

		return $this;
	}

	/**
	 * Adds a SET clause for UPDATE / INSERT VALUES.
	 */
	public function set( string $column, mixed $value ): self {
		$this->set_values[] = array(
			'column' => $column,
			'value'  => $value,
			'format' => $this->formatFor( $value ),
		);

		return $this;
	}

	/**
	 * Adds a VALUES row for INSERT. Each row is a column => value map.
	 *
	 * @param array<string, mixed> $row Column => value pairs for the row.
	 */
	public function row( array $row ): self {
		$this->value_rows[] = $row;

		return $this;
	}

	/**
	 * Returns the prepared SQL for the accumulated query.
	 *
	 * Identifiers are quoted through the dialect. Values are emitted
	 * as %s/%d/%F placeholders in the same order they appear in
	 * bindings() — feed that pair to wpdb::prepare() to escape and
	 * substitute them.
	 */
	public function toSql( DatabaseDialect $dialect ): string {
		switch ( $this->type ) {
			case 'SELECT':
				return $this->renderSelect( $dialect );
			case 'INSERT':
				return $this->renderInsert( $dialect );
			case 'UPDATE':
				return $this->renderUpdate( $dialect );
			case 'DELETE':
				return $this->renderDelete( $dialect );
		}

		return '';
	}

	/**
	 * Returns the flat bindings array matching the placeholders emitted
	 * by toSql(). Feed both to wpdb::prepare() in order.
	 *
	 * @return list<mixed>
	 */
	public function bindings(): array {
		$bindings = array();

		foreach ( $this->wheres as $where ) {
			$bindings = array_merge( $bindings, $this->bindingsFor( $where ) );
		}

		if ( null !== $this->limit ) {
			$bindings[] = $this->limit;
		}

		if ( null !== $this->offset ) {
			$bindings[] = $this->offset;
		}

		foreach ( $this->set_values as $set ) {
			$bindings[] = $set['value'];
		}

		foreach ( $this->value_rows as $row ) {
			foreach ( $row as $value ) {
				$bindings[] = $value;
			}
		}

		return $bindings;
	}

	/**
	 * Returns the column list (for INSERT) — needed by the SQL renderer
	 * to emit a stable, dialect-quoted column header.
	 *
	 * @return list<string>
	 */
	public function columns(): array {
		return $this->columns;
	}

	/**
	 * Returns the type, target, joins, wheres, ordering, grouping, limit,
	 * offset, set values, and value rows as plain arrays.
	 *
	 * Exposed for callers that need to inspect the accumulated state
	 * (tests, dry-run tooling) without rendering SQL.
	 *
	 * @return array<string, mixed>
	 */
	public function state(): array {
		return array(
			'type'        => $this->type,
			'columns'     => $this->columns,
			'target'      => $this->target,
			'joins'       => $this->joins,
			'wheres'      => $this->wheres,
			'group_by'    => $this->group_by,
			'order_by'    => $this->order_by,
			'limit'       => $this->limit,
			'offset'      => $this->offset,
			'set_values'  => $this->set_values,
			'value_rows'  => $this->value_rows,
		);
	}

	private function formatFor( mixed $value ): string {
		if ( is_int( $value ) || is_bool( $value ) ) {
			return '%d';
		}
		if ( is_float( $value ) ) {
			return '%F';
		}
		if ( null === $value ) {
			return 'NULL';
		}

		return '%s';
	}

	private function renderSelect( DatabaseDialect $dialect ): string {
		$columns_sql = $this->renderColumns( $dialect );

		$target = null === $this->target ? '' : $dialect->quoteIdentifier( $this->target );
		$sql    = "SELECT {$columns_sql} FROM {$target}";

		foreach ( $this->joins as $join ) {
			$sql .= sprintf(
				' %s JOIN %s ON %s',
				$join['type'],
				$dialect->quoteIdentifier( $join['target'] ),
				$join['on']
			);
		}

		$where_sql = $this->renderWheres( $dialect );
		if ( '' !== $where_sql ) {
			$sql .= ' WHERE ' . $where_sql;
		}

		if ( array() !== $this->group_by ) {
			$quoted = array_map(
				static fn ( string $column ): string => $dialect->quoteIdentifier( $column ),
				$this->group_by
			);
			$sql   .= ' GROUP BY ' . implode( ', ', $quoted );
		}

		if ( array() !== $this->order_by ) {
			$parts = array();
			foreach ( $this->order_by as $order ) {
				$parts[] = $dialect->quoteIdentifier( $order['column'] ) . ' ' . $order['direction'];
			}
			$sql .= ' ORDER BY ' . implode( ', ', $parts );
		}

		if ( null !== $this->limit ) {
			$sql .= ' LIMIT %d';
		}

		if ( null !== $this->offset ) {
			$sql .= ' OFFSET %d';
		}

		return $sql;
	}

	private function renderInsert( DatabaseDialect $dialect ): string {
		$target = null === $this->target ? '' : $dialect->quoteIdentifier( $this->target );

		if ( array() === $this->value_rows ) {
			return "INSERT INTO {$target} () VALUES ()";
		}

		$columns    = array_keys( $this->value_rows[0] );
		$columns_q  = array_map(
			static fn ( string $column ): string => $dialect->quoteIdentifier( $column ),
			$columns
		);
		$columns_sql = implode( ', ', $columns_q );

		$row_placeholders = array();
		foreach ( $this->value_rows as $row ) {
			$formats = array();
			foreach ( $row as $value ) {
				$formats[] = $this->formatFor( $value );
			}
			$row_placeholders[] = '(' . implode( ', ', $formats ) . ')';
		}

		return sprintf(
			'INSERT INTO %s (%s) VALUES %s',
			$target,
			$columns_sql,
			implode( ', ', $row_placeholders )
		);
	}

	private function renderUpdate( DatabaseDialect $dialect ): string {
		$target = null === $this->target ? '' : $dialect->quoteIdentifier( $this->target );

		if ( array() === $this->set_values ) {
			return "UPDATE {$target} SET";
		}

		$parts = array();
		foreach ( $this->set_values as $set ) {
			$parts[] = sprintf(
				'%s = %s',
				$dialect->quoteIdentifier( $set['column'] ),
				$set['format']
			);
		}

		$sql = sprintf( 'UPDATE %s SET %s', $target, implode( ', ', $parts ) );

		$where_sql = $this->renderWheres( $dialect );
		if ( '' !== $where_sql ) {
			$sql .= ' WHERE ' . $where_sql;
		}

		if ( null !== $this->limit ) {
			$sql .= ' LIMIT %d';
		}

		return $sql;
	}

	private function renderDelete( DatabaseDialect $dialect ): string {
		$target = null === $this->target ? '' : $dialect->quoteIdentifier( $this->target );
		$sql    = "DELETE FROM {$target}";

		$where_sql = $this->renderWheres( $dialect );
		if ( '' !== $where_sql ) {
			$sql .= ' WHERE ' . $where_sql;
		}

		if ( null !== $this->limit ) {
			$sql .= ' LIMIT %d';
		}

		return $sql;
	}

	private function renderColumns( DatabaseDialect $dialect ): string {
		if ( array() === $this->columns ) {
			return '*';
		}

		return implode(
			', ',
			array_map(
				static function ( string $column ) use ( $dialect ): string {
					return '*' === $column ? '*' : $dialect->quoteIdentifier( $column );
				},
				$this->columns
			)
		);
	}

	private function renderWheres( DatabaseDialect $dialect ): string {
		$parts = array();
		foreach ( $this->wheres as $i => $where ) {
			$prefix  = 0 === $i ? '' : ' AND ';
			$parts[] = $prefix . $this->renderWhere( $dialect, $where );
		}

		return implode( '', $parts );
	}

	private function renderWhere( DatabaseDialect $dialect, array $where ): string {
		switch ( $where['kind'] ) {
			case 'comparison':
				return sprintf(
					'%s %s %s',
					$dialect->quoteIdentifier( $where['column'] ),
					$where['operator'],
					$where['format']
				);

			case 'raw':
				return $where['sql'];

			case 'in':
				if ( array() === $where['values'] ) {
					return $where['negated']
						? '1 = 1'
						: '1 = 0';
				}
				$formats  = array_map(
					fn ( mixed $value ): string => $this->formatFor( $value ),
					$where['values']
				);
				$operator = $where['negated'] ? 'NOT IN' : 'IN';

				return sprintf(
					'%s %s (%s)',
					$dialect->quoteIdentifier( $where['column'] ),
					$operator,
					implode( ', ', $formats )
				);

			case 'null':
				return sprintf(
					'%s IS %sNULL',
					$dialect->quoteIdentifier( $where['column'] ),
					$where['negated'] ? 'NOT ' : ''
				);

			case 'between':
				return sprintf(
					'%s %sBETWEEN %s AND %s',
					$dialect->quoteIdentifier( $where['column'] ),
					$where['negated'] ? 'NOT ' : '',
					$this->formatFor( $where['start'] ),
					$this->formatFor( $where['end'] )
				);
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $where
	 * @return list<mixed>
	 */
	private function bindingsFor( array $where ): array {
		switch ( $where['kind'] ) {
			case 'comparison':
				return array( $where['value'] );

			case 'raw':
				return $where['bindings'];

			case 'in':
				return $where['values'];

			case 'null':
				return array();

			case 'between':
				return array( $where['start'], $where['end'] );
		}

		return array();
	}
}
