<?php

namespace kion\models;

/**
 * @sql_table pages
 */
class Page extends \orm\schema\Model
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
	public $title;

	/**
	 * @sql_type TEXT
	 */
	public $body;

	/**
	 * @sql_type INTEGER
	 */
	public $created_on;

	/**
	 * @sql_type INTEGER REFERENCES "users" ("id") ON UPDATE CASCADE ON DELETE RESTRICT
	 */
	public $created_by;

	/**
	 * @sql_type INTEGER REFERENCES "pages" ("id") ON UPDATE CASCADE ON DELETE SET NULL
	 */
	public $replaced_by;

	/**
	 * @sql_type INTEGER DEFAULT NULL
	 */
	public $deleted_on;
}