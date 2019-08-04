<?php

include 'lib/container.php';

$container = new Container();

$container->register('a', function($b) {
	return 1 + $b;
});

$container->register('b', function($c) {
	return 2 + $c;
});

$container->register('c', function() {
	return 3;
});

assert($container->get('a') === 1 + 2 + 3);

$container->register('d', function($a, $b, $c) {
	return $a + $b + $c;
});

assert($container->get('d') === 6 + 5 + 3);

$container->register('e', function() {
	static $invoked = 0;
	return ++$invoked; 
});

assert($container->get('e') === 1 && $container->get('e') === 1);

$container->register('f', function($g) {
	return 'f';
});

$container->register('g', function($h) {
	return 'g';
});

$container->register('h', function($f) {
	return 'h';
});

try {
	$container->get('f');
	assert(false, 'Expected container->get(f) to fail because recursive dependency');
} catch (LogicException $e) {
	assert($e->getMessage() === "Recursive dependency for service 'f' (f -> g -> h -> f)");
}