<?php

declare(strict_types=1);

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

class BadQueryException extends Exception
{
	//
}

class ORM
{
	public $db;

	public $data_class;
	
	public $schema;

	public function __construct(\PDO $db, string $data_class, schema\Schema $schema = null)
	{
		$this->db = $db;

		$this->data_class = $data_class;

		$this->schema = $schema ?? new schema\Schema($data_class);
	}

	public function query()
	{
		return new Query($this, null, null);
	}

	public function save(object $object)
	{
		if ($object->id)
			$this->update($object);
		else
			$this->insert($object);
	}

	public function insert(object $object)
	{
		if (!($object instanceof $this->data_class))
			throw new \InvalidArgumentException("Can only insert instances of {$this->data_class}");

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

		$this->db->prepare($sql)->execute($bindings);

		$object->id = $this->db->lastInsertId();
	}

	public function update(object $object)
	{
		if (!($object instanceof $this->data_class))
			throw new \InvalidArgumentException("Can only insert instances of {$this->data_class}");

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

		$this->db->prepare($sql)->execute($bindings);
	}

	public function delete(object $object)
	{
		if (!($object instanceof $this->data_class))
			throw new \InvalidArgumentException("Can only insert instances of {$this->data_class}");

		$sql = sprintf('DELETE FROM "%s" WHERE id = :id',
			$this->schema->tableName());

		$this->db->prepare($sql)->execute([':id' => $object->id]);
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
	public function __construct(ORM $model, array $columns = null, dsl\Node $filter = null, array $joins = [])
	{
		$this->model = $model;

		$this->columns = $columns ?? $this->model->schema->columns();

		$this->filter = $filter;

		$this->joins = $joins;
	}

	public function select(array $column_names): Query
	{
		$columns = [];

		foreach ($column_names as $property => $column) {
			if (is_int($property) && is_string($column))
				$property = $column;

			if (is_string($column))
				$column = $this->model->schema->column($column);

			if (!($column instanceof dsl\Node))
				throw new \InvalidArgumentException("Column for $property is not a column name or of type " . dsl\Node::class);
			
			$columns[$property] = $column;
		}

		return new Query($this->model, $columns, $this->filter, $this->joins);
	}

	public function join(string $data_class, string $alias, callable $filter)
	{
		$table_alias = uniqid($alias);

		$join = new ORM($this->model->db, $data_class, new schema\Schema($data_class, $table_alias));

		$query = new Query($join);

		$join_condition = $filter($query, $join->schema);
		
		return new Query($this->model,
			array_merge($this->columns, $join->schema->columns()),
			$this->filter,
			array_merge($this->joins, [$alias => $join]));
	}

	public function filter(dsl\Node $filter): Query
	{
		return new Query($this->model, $this->columns,
			$this->filter !== null ? dsl\and_($this->filter, $filter) : $filter, $this->joins);
	}

	public function all(): array
	{
		return $this->execute()->fetchAll();
	}

	public function first()
	{
		return $this->execute()->fetch();
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

	public function count()
	{
		return (int) $this->select([dsl\sql('COUNT(*)')])->scalar();
	}

	public function execute(array $bindings = []): \PDOStatement
	{
		$query = $this->compile($this->model->db);

		$sql = substr($query->sql, 1, -1); // strip the ( and ).
		
		try {
			$stmt = $this->model->db->prepare($sql);
		} catch (\PDOException $e) {
			throw new BadQueryException("Error in query: $sql", 0, $e);
		}

		if ($this->columns && false)
			$stmt->setFetchMode(\PDO::FETCH_ASSOC);
		else
			$stmt->setFetchMode(\PDO::FETCH_CLASS, $this->model->data_class, []);

		$stmt->execute($query->bindings);
		
		return $stmt;
	}

	public function delete()
	{
		foreach ($this->select($this->model->schema->columns())->all() as $obj)
			$this->model->delete($obj);
	}

	public function compile(\PDO $db): dsl\Fragment
	{
		$sql_select = [];

		$bindings = [];

		foreach ($this->columns as $label => $definition) {
			$fragment = $definition->compile($db);
			$sql_select[] = is_int($label)
				? $fragment->sql
				: sprintf('%s as %s', $fragment->sql, $label);
			$bindings = array_merge($bindings, $fragment->bindings);
		}

		$sql = 'SELECT ' . implode(', ', $sql_select) . ' FROM ' . $this->model->schema->tableName();

		if ($this->filter !== null) {
			$fragment = $this->filter->compile($db);
			$sql .= ' WHERE ' . $fragment->sql;
			$bindings = array_merge($bindings, $fragment->bindings);
		}

		$sql = sprintf('(%s)', $sql);

		return new dsl\Fragment($sql, $bindings);
	}
}
