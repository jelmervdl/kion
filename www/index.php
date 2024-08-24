<?php

define('NONCE_SALT', 'G@\rE9\CE3c]-Gh{Evsem3z.N-CZZkn-');

chdir('..');

require_once 'lib/util.php';
require_once 'lib/router.php';
require_once 'lib/auth.php';
require_once 'lib/orm.php';
require_once 'lib/orm/traits.php';
require_once 'lib/tpl.php';
require_once 'lib/form.php';
require_once 'kion/models/page.php';
require_once 'kion/models/user.php';
require_once 'kion/models/file.php';

use orm\dsl as q;
use orm\NotFoundException;
use orm\schema\LoadException;
use kion\models\{Page, User, Role, File};
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
	class ReplaceablePages extends orm\ORM {
		use orm\traits\Replaceable;
	}

	class DeletablePages extends ReplaceablePages {
		use orm\traits\Deletable;
	}

	return new class ($db, Page::class) extends DeletablePages {
		public function all(): orm\Query {
			return orm\ORM::query();
		}

		public function insert(object $object) {
			if ($this->query()->filter(q\eq($this->schema->uri, $object->uri))->count() > 0)
				throw new LoadException(['uri' => 'This URI is already used by another page']);

			parent::insert($object);
		}
	};
});

$app->container->register('users', function($db) {
	return new orm\ORM($db, User::class);
});

$app->container->register('files', function($db) {
	class PruneFiles extends orm\ORM {
		public function insert(object $object) {
			if ($file->uploaded_file) {
				$file->path = uniqid('file_') . pathinfo($file->name, PATHINFO_EXTENSION);
				move_uploaded_file($file->uploaded_file, 'var/files/' . $file->path);
			}

			parent::insert($object);
		}

		public function delete(object $file) {
			parent::delete($file);

			if ($file->path && file_exists('var/files/' . $file->path))
				unlink('var/files/' . $file->path);
		}
	}

	class ReplaceableFiles extends PruneFiles {
		use orm\traits\Replaceable;
	}

	return new class($db, File::class) extends ReplaceableFiles {
		use orm\traits\Deletable;
	};
});

$app->container->register('current_user', function($users) {
	if (!empty($_SESSION['user_id']))
		return $users->query()->filter(q\eq($users->schema->id, $_SESSION['user_id']))->one();
	else
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

$app->route('/admin/pages/<int:page_id>/', ['assert_admin'], function($pages, $page_id) {
	$page = $pages->all()->filter(q\eq($pages->schema->id, $page_id))->one();

	if (form\is_submitted('restore-page-%d', $page->id)) {
		$pages->restore($page);
		return redirect('/admin/pages/');
	}

	if (form\is_submitted('revert-page-%d', $page->id)) {
		$pages->revert($page);
		return redirect('admin/pages/');
	}

	// Get the page history, previous and future versions
	$versions = $pages->versions($page);

	// The latest version is in the current implementation always the current version
	$current = end($versions);

	return render_template('tpl/admin/page.phtml', compact('page', 'current', 'versions'));
});

$app->route('/admin/pages/<int:page_id>/edit', ['assert_admin'], 
$app->route('/admin/pages/new', ['assert_admin'], function($pages, $current_user, $page_id = null) {
	$page = $page_id === null
		? new Page()
		: $pages->query()->filter(q\eq($pages->schema->id, $page_id))->one();

	$errors = [];

	try {
		if (form\is_submitted('page')) {
			$page->load($_POST);
			$page->created_on = (new DateTime())->getTimestamp();
			$page->created_by = $current_user->id;
			$pages->save($page);
			return redirect('/admin/pages/');
		}
	} catch (LoadException $e) {
		$errors = $e->errors;
	}

	return render_template('tpl/admin/page-form.phtml', compact('page', 'errors'));
}));

$app->route('/admin/pages/<int:page_id>/delete', ['assert_admin'], function($pages, $page_id) {
	$page = $pages->query()->filter(q\eq($pages->schema->id, $page_id))->one();

	if (form\is_submitted('delete-page-%d', $page->id)) {
		$pages->delete($page);
		return redirect('/admin/pages/');
	}

	return render_template('tpl/admin/page-delete.phtml', compact('page'));
});

$app->route('/admin/users/', ['assert_admin'], function($users) {
	return render_template('tpl/admin/users.phtml', ['users' => $users->query()->all()]);
});

$app->route('/admin/users/<int:user_id>/', ['assert_admin'],
$app->route('/admin/users/new', ['assert_admin'], function($users, $user_id = null) {
	$user = $user_id === null
		? new User()
		: $users->query()->filter(q\eq($users->schema->id, $user_id))->one();

	$errors = [];

	try {
		if (form\is_submitted('user')) {
			$user->load($_POST);
			$users->save($user);
			return redirect('/admin/users/');
		}
	} catch (PDOException $e) {
		$errors = ['email' => $e->getMessage()];
	} catch (LoadException $e) {
		$errors = $e->errors;
	}

	return render_template('tpl/admin/user-form.phtml', compact('user', 'errors'));
}));

$app->route('/admin/files/', ['assert_admin'], function($files) {
	return render_template('tpl/admin/files.phtml', ['files' => $files->active()->all()]);
});

$app->route('/admin/bin/', ['assert_admin'], function($pages, $files) {
	return render_template('tpl/admin/binned.phtml', [
		'pages' => $pages->deleted()->all(),
		'files' => $files->deleted()->all()
	]);
});

$app->route('/admin/login', function($users) {
	$errors = [];

	if (form\is_submitted('login')) {
		try {
			$user = $users->query()->filter(q\eq(q\func::lower($users->schema->email), q\func::lower($_POST['email'])))->one();
			
			if (!password_verify($_POST['password'], $user->password_hash))
				throw new InvalidPasswordException();

			if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
				$user->password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
				$users->save($user);
			}

			$_SESSION['user_id'] = $user->id;

			return redirect($_GET['next'] ?? '/admin/');
		} catch (NotFoundException $e) {
			$errors = ['email' => 'Email address not known'];
		} catch (InvalidPasswordException $e) {
			$errors = ['password', 'Invalid password'];
		}
	}

	return render_template('tpl/admin/login-form.phtml', compact('errors'));
});

$app->route('/<path:page_uri>', function($page_uri, $pages) {
	$page = $pages->query()->filter(q\eq($pages->schema->uri, $page_uri))->one();
	return render_template('tpl/page.phtml', ['page' => $page]);
});

$app->route('/', function($router) {
	return $router->dispatch('/index');
});

/* Go! */

session_start();

$app->execute();