<?php

namespace orm\schema;
use orm\Exception;
use orm\dsl\Node;
use orm\dsl\Fragment;

class SchemaException extends Exception
{
	//
}

class Schema
{
	protected $model;

	public function __construct($model)
	{
		$this->model = $model;
	}

	public function tableName()
	{
		$refl = new \ReflectionClass($this->model);
		
		if (!preg_match('/@sql_table ([\w_]+)/', $refl->getDocComment(), $match))
			throw new SchemaException("{$this->model} has no @sql_table attribute");

		return $match[1];
	}

	public function columns()
	{
		$refl = new \ReflectionClass($this->model);

		$columns = [];

		foreach ($refl->getProperties(\ReflectionProperty::IS_PUBLIC) as $property)
			$columns[$property->getName()] = $this->deriveColumn($property);

		return $columns;
	}

	public function column($property_name)
	{
		$columns = $this->columns();

		if (!isset($columns[$property_name]))
			throw new \InvalidArgumentException("Model {$this->model} has no property {$property_name}, or it is not mapped to a SQL column.");

		return $columns[$property_name];
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
		$schema = new Schema(get_class($this));

		foreach ($values as $key => $value)
			if ($schema->column($key))
				$this->$key = $value;
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