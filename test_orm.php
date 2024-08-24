<?php

error_reporting(E_ALL | E_STRICT);

require 'lib/orm.php';

use \orm\dsl as q;

/**
 * @sql_table authors
 */
class Author extends orm\schema\Model
{
	/**
	 * @sql_type INTEGER PRIMARY KEY AUTOINCREMENT
	 */
	public $id;

	/**
	 * @sql_type TEXT
	 */
	public $name;
}

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
	 * @sql_type INTEGER REFERENCES "authors" ("id") ON UPDATE CASCADE ON DELETE CASCADE
	 */
	public $author_id;

	/**
	 * @sql_type TEXT
	 */
	public $title;

	/**
	 * @sql_type INTEGER
	 */
	public $pages;
}

unlink('test.sqlite');
$db = new PDO('sqlite:test.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$authors = new orm\ORM($db, Author::class);

$books = new orm\ORM($db, Book::class);

/* Test single condition formatting */

assert(preg_match(
	'/^\(SELECT books\.title as title FROM books WHERE books\.title = :p\w+\)$/',
	$books->query()
		->select(['title'])
		->filter(orm\dsl\eq($books->schema->title, 'The Thing'))
		->compile($db)->sql));

/* Test complex condition formatting */

assert(preg_match(
	'/^\(SELECT COUNT\(\*\) FROM books WHERE \(\(books\.title = :p\w+ AND books\.author != :p\w+\) OR lower\(books\.author\) = books\.title\)\)$/',
	$books->query()
		->select([q\sql('COUNT(*)')])
		->filter(q\or_(
			q\and_(
				q\eq($books->schema->title, 'The Thing'),
				q\neq($books->schema->author, 'Jelmer')
			),
			q\eq(q\func::lower($books->schema->author), $books->schema->title)
		))->compile($db)->sql));

$authors->createTable();

$books->createTable();

$greg = new Author(['name' => 'Greg Egan']);

$authors->save($greg);

assert($greg->id !== null);

$blake = new Author(['name' => 'Blake Crouch']);

$authors->save($blake);

$book = new Book([
	'author_id' => $greg->id,
	'author' => 'Greg Egan',
	'title' => 'Diaspora',
	'pages' => 200
]);

$books->insert($book);

$books->insert(new Book([
	'author_id' => $blake->id,
	'author' => 'Blake Crouch',
	'title' => 'Dark Matter',
	'pages' => 342
]));

$books->insert(new Book([
	'author_id' => $blake->id,
	'author' => 'Blake Crouch',
	'title' => 'Recursion',
	'pages' => 280
]));

/* Test simple look-up */

assert($books->query()->filter(q\eq($books->schema->title, 'Diaspora'))->one()->author === 'Greg Egan');

/* Test count */

assert($books->query()->count() === 3);

/* Test subqueries */

$greg_subquery = $authors->query()->select([$authors->schema->id])->filter(q\ilike($authors->schema->name, '%ake%'));

assert($books->query()->filter(q\in_($books->schema->author_id, $greg_subquery))->count() === 2);

echo $books->query()
	->join(Author::class, 'author_obj', function($query, $joined) use ($books) {
		return $query->filter(q\eq($joined->id, $books->schema->author_id));
	})
	->compile($db);
