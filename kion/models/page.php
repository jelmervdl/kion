<?php

namespace kion\models;

/**
 * @sql_table pages
 * @sql_pragma CREATE UNIQUE INDEX "uniq_uri" ON "pages" ("uri")
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
}