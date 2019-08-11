<?php

namespace kion\models;

abstract class Role
{
	const GUEST = 0;

	const USER = 1;

	const ADMINISTRATOR = 2;
}

/**
 * @sql_table users
 * @sql_pragma CREATE UNIQUE INDEX "uniq_email" ON "users" ("email")
 */
class User extends \orm\schema\Model
{
	/**
	 * @sql_type INTEGER PRIMARY KEY AUTOINCREMENT
	 */
	public $id;

	/**
	 * @sql_type TEXT
	 */
	public $name;

	/**
	 * @sql_type TEXT
	 */
	public $email;

	/**
	 * @sql_type TEXT
	 */
	public $password_hash;

	/**
	 * @sql_type INTEGER DEFAULT 1
	 */
	public $role;
}
