<?php

declare(strict_types=1);

namespace orm\traits;

trait Deletable
{
	public function query(): \orm\Query
	{
		return parent::query()->filter(\orm\dsl\is_null($this->schema->deleted_on));
	}

	public function deleted(): \orm\Query
	{
		return parent::query()->filter(\orm\dsl\is_not_null($this->schema->deleted_on));
	}

	public function delete(object $object)
	{
		$object->deleted_on = (new \DateTime())->getTimestamp();
		parent::update($object);
	}

	public function restore(object $object)
	{
		$object->deleted_on = null;
		parent::update($object);
	}

	public function prune()
	{
		foreach ($this->deleted() as $object)
			parent::delete($object);
	}
}

trait Replaceable {
	public function query(): \orm\Query
	{
		return parent::query()->filter(\orm\dsl\is_null($this->schema->replaced_by));
	}

	public function current(object $object): object
	{
		while ($object->replaced_by !== null)
			$object = parent::query()->filter(\orm\dsl\eq($this->schema->id, $object->replaced_by))->one();

		return $object;
	}

	public function versions(object $object): array
	{
		$history = [];

		$previous = $object;

		while ($previous) {
			array_unshift($history, $previous);
			$previous = parent::query()->filter(\orm\dsl\eq($this->schema->replaced_by, $previous->id))->first();
		}

		$next = $object;

		while ($next->replaced_by !== null) {
			$next = parent::query()->filter(\orm\dsl\eq($this->schema->id, $next->replaced_by))->one();
			array_push($history, $next);
		}

		return $history;
	}

	public function update(object $object)
	{
		try {
			$this->db->beginTransaction();

			$backup = clone $object;

			$object->id = null;
			parent::insert($object);

			$backup->replaced_by = $object->id;
			parent::update($backup);

			$this->db->commit();
		} catch (Exception $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	public function revert(object $object)
	{
		try {
			$this->db->beginTransaction();

			$current = $this->current($object);

			$backup = clone $object;

			$object->id = null;
			parent::insert($object);

			$current->replaced_by = $object->id;
			parent::update($current);

			$this->db->commit();
		} catch (Exception $e) {
			$this->db->rollback();
			throw $e;
		}
	}
}
