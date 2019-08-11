<?php

define('NONCE_SALT', 'G@\rE9\CE3c]-Gh{Evsem3z.N-CZZkn-');

chdir('..');

require_once 'lib/util.php';
require_once 'lib/router.php';
require_once 'lib/auth.php';
require_once 'lib/orm.php';
require_once 'lib/tpl.php';
require_once 'lib/form.php';
require_once 'kion/models/page.php';
require_once 'kion/models/user.php';

use orm\dsl as q;
use orm\NotFoundException;
use orm\schema\LoadException;
use kion\models\{Page, User, Role};
use function tpl\render_template;

function assert_admin($current_user)
{
	if ($current_user->role != Role::ADMINISTRATOR)
		throw new \auth\UnauthorizedException('This page required the role of administrator, your account has the role of ' . get_enum(Role::class, $current_user->role));
}

function redirect($destination)
{
	return [
		sprintf('Redirecting you to <a href="%s">%1$s</a>...', htmlspecialchars($destination, ENT_QUOTES, 'utf-8')),
		['Location' => $destination],
		302
	];
}

$app = new Router();

/* Website services */

$app->container->register('router', function() use ($app) {
	return $app;
});

$app->container->register('db', function() {
	$db = new PDO('sqlite:var/kion.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	return $db;
});

$app->container->register('pages', function($db) {
	return new orm\ORM($db, Page::class);
});

$app->container->register('current_user', function() {
	return new User(['role' => Role::GUEST]);
});

/* Exception handlers */

$app->exceptionHandler(auth\UnauthorizedException::class, function($exception, $current_user) {
		if ($current_user->role === Role::GUEST)
		return redirect(edit_url('/admin/login', ['next' => $_SERVER['REQUEST_URI'], 'message' => $exception->getMessage()]));
	else
		return [render_template('tpl/401-unauthorized.phtml', compact('exception')), 401];
});

$app->exceptionHandler(NotFoundException::class, function($exception) {
	return [render_template('tpl/404-not-found.phtml', ['exception' => $exception]), 404];
});

$app->exceptionHandler(NoRouteException::class, function($exception) {
	return [render_template('tpl/404-not-found.phtml', ['exception' => $exception]), 404];
});

$app->exceptionHandler(Exception::class, function($exception) {
	return [render_template('tpl/500-error.phtml', ['exception' => $exception]), 500];
});

/* Routes */

$app->route('/admin/', ['assert_admin'], function() {
	return render_template('tpl/admin/index.phtml');
});

$app->route('/admin/pages/', ['assert_admin'], function($pages) {
	return render_template('tpl/admin/pages.phtml', ['pages' => $pages->query()->all()]);
});

$app->route('/admin/pages/<int:page_id>/', ['assert_admin'], 
$app->route('/admin/pages/new', ['assert_admin'], function($pages, $page_id = null) {
	$page = $page_id === null
		? new Page()
		: $pages->query()->filter(q\eq('id', $page_id))->one();

	$errors = [];

	try {
		if (form\is_submitted('page')) {
			$page->load($_POST);
			$pages->save($page);
			return redirect('/admin/pages/');
		}
	} catch (PDOException $e) {
		$errors = ['uri' => $e->getMessage()];
	} catch (LoadException $e) {
		$errors = $e->errors;
	}

	return render_template('tpl/admin/page-form.phtml', compact('page', 'errors'));
}));

$app->route('/<path:page_uri>', function($page_uri, $pages) {
	$page = $pages->query()->filter(q\eq('uri', $page_uri))->one();
	return render_template('tpl/page.phtml', ['page' => $page]);
});

$app->route('/', function($router) {
	return $router->dispatch('/index');
});

/* Go! */

$app->execute();