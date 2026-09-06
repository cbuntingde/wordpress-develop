<?php
/**
 * Class Expression — a SQL fragment that can be substituted into a
 * larger query without further escaping.
 *
 * Dialects produce Expression objects via DatabaseDialect methods.
 * The query builder inlines the fragment into the surrounding SQL
 * and binds any values in $bindings to ? placeholders the renderer
 * inserts on the dialect's behalf.
 *
 * @package WordPress
 */

declare(strict_types=1);

final readonly class Expression {
	public string $sql;
	public array $bindings;

	public function __construct(
		string $sql,
		array $bindings
	) {
		$this->sql      = $sql;
		$this->bindings = $bindings;
	}
}
