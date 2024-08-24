<?php

namespace orm\dsl;


function and_(...$args)
{
	return combine(' AND ', $args);
}

function or_(...$args)
{
	return combine(' OR ', $args);
}

function in_($name, ...$args)
{
	if (count($args) === 1 && $args[0] instanceof \orm\Query)
		$set = $args[0];
	else
		$set = combine(', ', $args);

	return format('%s IN %s', $name, $set);
}

function eq($name, $value)
{
	return format('%s = %s', $name, $value);
}

function neq($name, $value)
{
	return format('%s != %s', $name, $value);
}

function like($name, $value)
{
	return format('%s LIKE %s', $name, $value);
}

function ilike($name, $value)
{
	return like(func::lower($name), func::lower($value));
}

function is_null($name)
{
	return format('%s IS NULL', $name);
}

function is_not_null($name)
{
	return format('%s IS NOT NULL', $name);
}

function format($format, ...$args)
{
	return new CompilerNode(function(\PDO $pdo) use ($format, $args) {
		$sql_parts = [];
		$bindings = [];

		foreach ($args as $arg) {
			if ($arg instanceof Node) {
				$condition = $arg->compile($pdo);
				$sql_parts[] = $condition->sql;
				$bindings[] = $condition->bindings;
			} else {
				$placeholder = uniqid(':p');
				$sql_parts[] = $placeholder;
				$bindings[] = [$placeholder => $arg];
			}
		}

		return new Fragment(
			vsprintf($format, $sql_parts),
			call_user_func_array('array_merge', $bindings)
		);
	});
}

function combine($operator, array $args)
{
	return new CompilerNode(function(\PDO $db) use ($operator, $args) {
		$sql_parts = [];
		$bindings = [];

		foreach ($args as $arg) {
			$condition = $arg->compile($db);
			$sql_parts[] = $condition->sql;
			$bindings[] = $condition->bindings;
		}

		return new Fragment(
			sprintf('(%s)', implode($operator, $sql_parts)),
			call_user_func_array('array_merge', $bindings)
		);
	});
}

function sql($sql, array $bindings = [])
{
	return new CompilerNode(function(\PDO $db) use ($sql, $bindings) {
		return new Fragment($sql, $bindings);
	});
}

class func
{
	public static function __callStatic($name, $arguments)
	{
		return new CompilerNode(function(\PDO $db) use ($name, $arguments) {
			$bindings = [];

			$sql_args = [];

			foreach ($arguments as $argument) {
				if ($argument instanceof Node) {
					$fragment = $argument->compile($db);
					$bindings = array_merge($bindings, $fragment->bindings);
					$sql_args[] = $fragment->sql;
				} else {
					$placeholder = uniqid(':' . $name);
					$bindings[$placeholder] = $argument;
					$sql_args[] = $placeholder;
				}
			}

			$sql_args_expr = implode(', ', $sql_args);

			return new Fragment("{$name}({$sql_args_expr})", $bindings);
		});
	}
}

interface Node
{
	public function compile(\PDO $db);
}

/**
 * Represents a condition or a combination in the condition AST.
 */
class CompilerNode implements Node
{
	public function __construct(Callable $implementation)
	{
		$this->implementation = $implementation;
	}

	public function compile(\PDO $db)
	{
		return call_user_func($this->implementation, $db);
	}
}

/**
 * Represents a bit of compiled SQL and the bindings to placeholders mentioned.
 **/
class Fragment
{
	public function __construct($sql, $bindings)
	{
		$this->sql = $sql;

		$this->bindings = $bindings;
	}

	public function __toString()
	{
		return sprintf('<pre>%s</pre>',
			preg_replace_callback(
				'/:[0-9a-z]+/',
				function($match) {
					return sprintf('<var>%s</var>', $this->bindings[$match[0]]);
				},
				$this->sql));
	}
}
