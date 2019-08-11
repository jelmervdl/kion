<?php

namespace orm\dsl;
use orm\schema\Schema;


function and_(array $args)
{
	return combine(' AND ', $args);
}

function or_(array $args)
{
	return combine(' OR ', $args);
}

function eq($name, $value)
{
	return compare('=', $name, $value);
}

function neq($name, $value)
{
	return compare('!=', $name, $value);
}

function combine($operator, array $args)
{
	return new CompilerNode(function(Schema $schema, \PDO $db) use ($operator, $args) {
		$sql_parts = [];
		$bindings = [];

		foreach ($args as $arg) {
			$condition = $arg->compile($schema, $db);
			$sql_parts[] = $condition->sql;
			$bindings[] = $condition->bindings;
		}

		return new Fragment(
			sprintf('(%s)', implode($operator, $sql_parts)),
			call_user_func_array('array_merge', $bindings)
		);
	});
}

function compare($operator, $name, $value)
{
	return new CompilerNode(function(Schema $schema, \PDO $db) use ($operator, $name, $value) {
		$bindings = [];

		if ($name instanceof Node) {
			$name_node = $name->compile($schema, $db);
			$bindings = array_merge($bindings, $name_node->bindings);
			$sql_name = $name_node->sql;
			$placeholder = uniqid(':p');
		} else {
			throw new \LogicException('Dont do this');

			$sql_name = $name;
			$placeholder = uniqid(':' . $name);
		}

		if ($value instanceof Node) {
			$value_node = $value->compile($schema, $db);
			$bindings = array_merge($bindings, $value_node->bindings);
			$sql_value = $value_node->sql;
		} else {
			$bindings[$placeholder] = $value;
			$sql_value = $placeholder;
		}

		return new Fragment(
			sprintf('(%s %s %s)', $sql_name, $operator, $sql_value),
			$bindings
		);
	});
}

function sql($sql, array $bindings = [])
{
	return new CompilerNode(function(Schema $schema, \PDO $db) use ($sql, $bindings) {
		return new Fragment($sql, $bindings);
	});
}

class func
{
	public static function __callStatic($name, $arguments)
	{
		return new CompilerNode(function(Schema $schema, \PDO $db) use ($name, $arguments) {
			$bindings = [];

			$sql_args = [];

			foreach ($arguments as $argument) {
				if ($argument instanceof Node) {
					$fragment = $argument->compile($schema, $db);
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
	public function compile(Schema $schema, \PDO $db);
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

	public function compile(Schema $schema, \PDO $db)
	{
		return call_user_func($this->implementation, $schema, $db);
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
}
