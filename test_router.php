<?php

include 'lib/router.php';

$router = new Router();

$router->route('/alfa', function() {
	return 'beta';
});

assert($router->dispatch('/alfa') === 'beta');

$router->route('/beta/<string:varname>/', function($varname) {
	return "var<$varname>";
});

assert($router->dispatch('/beta/test-string/') === 'var<test-string>');

$router->route('/gamma/<string:name>/<int:num>/', function($name, $num) {
	return [$name, $num];
});

assert($router->dispatch('/gamma/hello/42/') === ['hello', 42]);