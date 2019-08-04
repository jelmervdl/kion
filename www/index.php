<?php

namespace kion;

chdir('..');

require_once 'lib/router.php';
require_once 'lib/auth.php';
require_once 'lib/orm.php';
require_once 'lib/tpl.php';
require_once 'kion/models/page.php';

use orm\dsl as q;
use function tpl\render_template;

$app = new \Router();

$app->container->register('router', function() use ($app) {
	return $app;
});

$app->container->register('db', function() {
	$db = new \PDO('sqlite:var/kion.sqlite');
	$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	return $db;
});

$app->container->register('pages', function($db) {
	return new \orm\ORM($db, models\Page::class);
});

$app->exceptionHandler(\orm\NotFoundException::class, function($exception) {
	return [render_template('tpl/not-found.phtml', ['exception' => $exception]), 404];
});

$app->route('/admin/pages/', function($current_user, $pages) {
	assert_admin($current_user);
	return render_template('tpl/admin/pages.phtml', ['pages' => $pages->query()->all()]);
});

$app->route('/<path:page_uri>', function($page_uri, $pages) {
	$page = $pages->query()->filter(q\eq('uri', $page_uri))->one();
	return render_template('tpl/page.phtml', ['page' => $page]);
});

$app->route('/', function($router) {
	return $router->dispatch('/index');
});

$app->execute();