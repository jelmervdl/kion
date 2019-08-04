<?php

error_reporting(E_ALL | E_STRICT);

require 'lib/orm.php';

use \orm\dsl as q;

/**
 * @sql_table books
 */
class Book extends orm\schema\Model
{
	/**
	 * @sql_type INTEGER PRIMARY KEY AUTOINCREMENT
	 */
	public $id;

	/**
	 * @sql_type TEXT
	 */
	public $author;

	/**
	 * @sql_type TEXT
	 */
	public $title;

	/**
	 * @sql_type INTEGER
	 */
	public $pages;
}

$schema = new orm\schema\Schema(Book::class);

assert($schema->column('pages')->sql_type == 'INTEGER');

unlink('test.sqlite');
$db = new PDO('sqlite:test.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$orm = new orm\ORM($db, Book::class);

assert(preg_match(
	'/^SELECT books\.title as title FROM books WHERE \(title = :title\w+\)$/',
	$orm->query()->select(['title'])->filter(orm\dsl\eq('title', 'The Thing'))->compile($schema, $db)->sql));

assert(preg_match(
	'/^SELECT COUNT\(\*\) FROM books WHERE \(\(\(title = :title\w+\) AND \(author != :author\w+\)\) OR \(lower\(books\.author\) = books\.title\)\)$/',
	$orm->query()
		->select([q\sql('COUNT(*)')])
		->filter(q\or_([
			q\and_([
				q\eq('title', 'The Thing'),
				q\neq('author', 'Jelmer')
			]),
			q\eq(q\func::lower($schema->column('author')), $schema->column('title'))
		]))->compile($schema, $db)->sql));

$orm->createTable();

$book = new Book([
	'author' => 'Greg Egan',
	'title' => 'Diaspora',
	'pages' => 200
]);

$orm->insert($book);

$orm->insert(new Book([
	'author' => 'Blake Crouch',
	'title' => 'Dark Matter',
	'pages' => 342
]));

assert($orm->query()->filter(q\eq('title', 'Diaspora'))->one()->author === 'Greg Egan');

assert($orm->query()->select(['count' => q\sql('COUNT(*)')])->one()['count'] == 2);

assert($orm->query()->select([q\sql('COUNT(*)')])->scalar() == 2);