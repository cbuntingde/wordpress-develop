<?php
/**
 * Class Query — structured representation of a single SQL statement.
 *
 * Dialects produce Query objects via DatabaseDialect methods and the
 * upcoming query builder (Phase 2 task 2). Nothing here is rendered
 * to a SQL string at this stage; the query builder consumes the
 * structured fields and emits SQL when the statement executes.
 *
 * The minimal shape (target, columns, parameters) is what task 1
 * needs. Task 2 grows it with wheres, joins, ordering, limits, etc.
 *
 * @package WordPress
 */

declare(strict_types=1);

final readonly class Query {
	public string $target;
	public array $columns;
	public array $parameters;

	public function __construct(
		string $target,
		array $columns,
		array $parameters
	) {
		$this->target     = $target;
		$this->columns    = $columns;
		$this->parameters = $parameters;
	}
}
