<?php

namespace kion\models;

/**
 * @sql_table files
 */
class File extends \orm\schema\Model
{
	/**
	 * @sql_type INTEGER PRIMARY KEY AUTOINCREMENT
	 */
	public $id;

	/**
	 * @sql_type TEXT
	 */
	public $uri;

	/**
	 * @sql_type TEXT
	 */
	public $name;

	/**
	 * @sql_type TEXT
	 */
	public $path;

	/**
	 * @sql_type INTEGER
	 */
	public $created_on;

	/**
	 * @sql_type INTEGER REFERENCES "users"("id") ON UPDATE CASCADE ON DELETE RESTRICT
	 */
	public $created_by;

	/**
	 * When you upload a new version of a file, it's "replaced".
	 * @sql_type INTEGER REFERENCES "files"("id") ON UPDATE CASCADE ON DELETE SET NULL
	 */
	public $replaced_by;

	/**
	 * When you "bin" a file, it's marked as hidden.
	 * @sql_type INTEGER DEFAULT NULL
	 */
	public $deleted_on;
}
