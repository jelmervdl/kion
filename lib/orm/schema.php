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
	protected $model;

	protected $table_name = null;

	protected $columns = [];

	public function __construct($model)
	{
		$this->model = $model;

		$refl = new \ReflectionClass($model);

		if (preg_match('/@sql_table ([\w_]+)/', $refl->getDocComment(), $match))
			$this->table_name = $match[1];

		foreach ($refl->getProperties(\ReflectionProperty::IS_PUBLIC) as $property)
			$this->columns[$property->getName()] = $this->deriveColumn($property);
	}

	public function annotations($keyword)
	{
		$refl = new \ReflectionClass($this->model);

		preg_match_all(
			'/@' . preg_quote($keyword) . ' (.+?)($|\*+\/)/m',
			$refl->getDocComment(),
			$matches,
			PREG_PATTERN_ORDER);
		
		return $matches[1];
	}

	public function tableName()
	{
		if (!$this->table_name)
			throw new SchemaException("{$this->model} has no @sql_table attribute");

		return $this->table_name;
	}

	public function columns()
	{
		return $this->columns;
	}

	public function hasColumn($property_name)
	{
		return isset($this->columns[$property_name]);
	}

	public function column($property_name)
	{
		if (!$this->hasColumn($property_name))
			throw new \InvalidArgumentException("Model {$this->model} has no property {$property_name}, or it is not mapped to a SQL column.");

		return $this->columns[$property_name];
	}

	protected function deriveColumn(\ReflectionProperty $property)
	{
		if (!preg_match('/@sql_type (.+?)($|\*+\/)/m', $property->getDocComment(), $match))
			throw new SchemaException("{$this->model}::{$property->getName()} has no @sql_type attribute");

		return new Column($property->getName(), $match[1]);
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

	public function __construct($name, $sql_type)
	{
		$this->name = $name;

		$this->sql_type = $sql_type;
	}

	public function compile(Schema $schema, \PDO $db)
	{
		return new Fragment("{$schema->tableName()}.{$this->name}", []);
	}
}