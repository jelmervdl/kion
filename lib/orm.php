<?php

namespace orm;

require_once 'orm/dsl.php';
require_once 'orm/schema.php';

class Exception extends \RuntimeException
{
	//
}

class NotFoundException extends Exception
{
	//
}

class MultipleMatchesException extends Exception
{
	//
}

class ORM
{
	public $model;
	
	public $schema;

	public function __construct(\PDO $db, $model, $schema = null)
	{
		$this->db = $db;

		$this->model = $model;

		$this->schema = $schema ? $schema : new schema\Schema($model);
	}

	public function query()
	{
		return new Query($this->db, $this->model, $this->schema, null, null);
	}

	public function save($object)
	{
		return $object->id ? $this->update($object) : $this->insert($object);
	}

	public function insert($object)
	{
		if (!($object instanceof $this->model))
			throw new InvalidArgumentException("Can only insert instances of {$this->model}");

		$sql_columns = [];
		$sql_values = [];
		$bindings = [];

		foreach ($this->schema->columns() as $property => $column)
		{
			$placeholder = ":{$column->name}";
			$sql_columns[] = sprintf('"%s"', $column->name);
			$sql_values[] = $placeholder;
			$bindings[$placeholder] = $object->$property;
		}

		$sql = sprintf('INSERT INTO "%s" (%s) VALUES (%s)',
			$this->schema->tableName(),
			implode(', ', $sql_columns),
			implode(', ', $sql_values));

		return $this->db->prepare($sql)->execute($bindings);
	}

	public function update($object)
	{
		if (!($object instanceof $this->model))
			throw new InvalidArgumentException("Can only insert instances of {$this->model}");

		$sql_columns = [];
		$bindings = [':id' => $object->id];

		foreach ($this->schema->columns() as $property => $column)
		{
			if ($property == 'id')
				continue;
			
			$placeholder = ":{$column->name}";
			$sql_columns[] = sprintf('"%s" = %s', $column->name, $placeholder);
			$bindings[$placeholder] = $object->$property;
		}

		$sql = sprintf('UPDATE "%s" SET %s WHERE id = :id',
			$this->schema->tableName(),
			implode(', ', $sql_columns));

		return $this->db->prepare($sql)->execute($bindings);
	}

	public function createTable()
	{
		$sql_columns = [];

		foreach ($this->schema->columns() as $column)
			$sql_columns[] = sprintf('"%s" %s', $column->name, $column->sql_type);

		$sql = sprintf('CREATE TABLE "%s" (%s)',
			$this->schema->tableName(),
			implode(', ', $sql_columns));

		$this->db->exec($sql);

		foreach ($this->schema->annotations('sql_pragma') as $pragma) {
			echo $pragma;
			$this->db->exec($pragma);
		}
	}
}

class Query implements dsl\Node
{
	public function __construct(\PDO $db, $model, $schema, array $columns = null, dsl\Node $filter = null)
	{
		$this->db = $db;

		$this->model = $model;

		$this->schema = $schema;

		$this->columns = $columns;

		$this->filter = $filter;
	}

	public function select(array $column_names)
	{
		$columns = [];

		foreach ($column_names as $property => $column) {
			if (is_int($property) && is_string($column))
				$property = $column;

			if (is_string($column))
				$column = $this->schema->column($column);

			if (!($column instanceof dsl\Node))
				throw new \InvalidArgumentException("Column for $property is not a column name or of type " . dsl\Node::class);
			
			$columns[$property] = $column;
		}

		return new Query($this->db, $this->model, $this->schema, $columns, $this->filter);
	}

	public function filter($filter)
	{
		return new Query($this->db, $this->model, $this->schema, $this->columns,
			$this->filter !== null ? dsl\and_($this->filter, $filter) : $filter);
	}

	public function all()
	{
		return $this->execute()->fetchAll();
	}

	public function one()
	{
		$stmt = $this->execute();

		$row = $stmt->fetch();

		if ($row === false)
			throw new NotFoundException('No rows found');

		if ($stmt->fetch() !== false)
			throw new MultipleMatchesException('Multiple rows found');

		return $row;
	}

	public function scalar()
	{
		if ($this->columns === null)
			throw new BadMethodCallException('scalar() is only available when selecting custom columns');

		if (count($this->columns) !== 1)
			throw new BadMethodCallException('scalar() is only available when a single column');

		return $this->execute()->fetchColumn(0);
	}

	public function execute(array $bindings = [])
	{
		$query = $this->compile($this->schema, $this->db);
		$stmt = $this->db->prepare($query->sql);
		
		if ($this->columns)
			$stmt->setFetchMode(\PDO::FETCH_ASSOC);
		else
			$stmt->setFetchMode(\PDO::FETCH_CLASS, $this->model, []);

		$stmt->execute($query->bindings);
		
		return $stmt;
	}

	public function compile(schema\Schema $schema, \PDO $db)
	{
		$sql_select = [];

		$bindings = [];

		$columns = $this->columns ? $this->columns : $this->schema->columns();

		foreach ($columns as $label => $definition) {
			$fragment = $definition->compile($this->schema, $db);
			$sql_select[] = is_int($label)
				? $fragment->sql
				: sprintf('%s as %s', $fragment->sql, $label);
			$bindings = array_merge($bindings, $fragment->bindings);
		}

		$sql = 'SELECT ' . implode(', ', $sql_select) . ' FROM ' . $this->schema->tableName();

		if ($this->filter !== null) {
			$fragment = $this->filter->compile($this->schema, $db);
			$sql .= ' WHERE ' . $fragment->sql;
			$bindings = array_merge($bindings, $fragment->bindings);
		}

		return new dsl\Fragment($sql, $bindings);
	}
}
