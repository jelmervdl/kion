<?php

namespace orm\schema;
use orm\Exception;
use orm\dsl\Node;
use orm\dsl\Fragment;

class SchemaException extends Exception
{
	//
}

class LoadException extends Exception
{
	public $errors;

	public function __construct(array $errors)
	{
		$message = ['Could not load data due to the following errors:'];

		foreach ($errors as $key => $error)
			$message[] = "{$key}: {$error}";

		parent::__construct(implode("\n", $message));

		$this->errors = $errors;
	}
}

class Schema
{
	protected $data_class;

	protected $table_name = null;

	protected $columns = [];

	public function __construct(string $data_class, string $table_name = null)
	{
		$this->data_class = $data_class;

		$refl = new \ReflectionClass($data_class);

		if ($table_name !== null)
			$this->table_name = $table_name;
		else if (preg_match('/@sql_table ([\w_]+)/', $refl->getDocComment(), $match))
			$this->table_name = $match[1];

		foreach ($refl->getProperties(\ReflectionProperty::IS_PUBLIC) as $property)
			$this->columns[$property->getName()] = $this->deriveColumn($property);
	}

	public function __get(string $column): Column
	{
		return $this->column($column);
	}

	public function annotations(string $keyword): array
	{
		$refl = new \ReflectionClass($this->data_class);

		preg_match_all(
			'/@' . preg_quote($keyword) . ' (.+?)($|\*+\/)/m',
			$refl->getDocComment(),
			$matches,
			PREG_PATTERN_ORDER);
		
		return $matches[1];
	}

	public function tableName(): string
	{
		if (!$this->table_name)
			throw new SchemaException("{$this->data_class} has no @sql_table attribute");

		return $this->table_name;
	}

	public function columns(): array
	{
		return $this->columns;
	}

	public function hasColumn(string $property_name): bool
	{
		return isset($this->columns[$property_name]);
	}

	public function column(string $property_name): Column
	{
		if (!$this->hasColumn($property_name))
			throw new \InvalidArgumentException("data_class {$this->data_class} has no property {$property_name}, or it is not mapped to a SQL column.");

		return $this->columns[$property_name];
	}

	protected function deriveColumn(\ReflectionProperty $property): Column
	{
		if (!preg_match('/@sql_type (.+?)($|\*+\/)/m', $property->getDocComment(), $match))
			throw new SchemaException("{$this->data_class}::{$property->getName()} has no @sql_type attribute");

		return new Column($this, $property->getName(), $match[1]);
	}
}

abstract class Model
{
	public function __construct(array $values = [])
	{
		$this->load($values);
	}

	public function load(array $values, array $keys = null)
	{
		$schema = new Schema(get_class($this));

		if ($keys === null)
			$keys = array_keys($schema->columns());

		$errors = [];

		foreach ($values as $key => $value) {
			if (array_search($key, $keys) === false)
				continue;
			else if (!$schema->hasColumn($key))
				$errors[$key] = "Model has no property '{$key}'";
			else
				$this->$key = $value;
		}

		if ($errors)
			throw new LoadException($errors);
	}
}

class Column implements Node
{
	public $name;

	public $sql_type;

	public function __construct(Schema $schema, string $name, string $sql_type)
	{
		$this->schema = $schema;

		$this->name = $name;

		$this->sql_type = $sql_type;
	}

	public function compile(\PDO $db): Fragment
	{
		return new Fragment("{$this->schema->tableName()}.{$this->name}", []);
	}
}