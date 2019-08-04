<?php

require_once 'lib/container.php';


class NoRouteException extends Exception
{
	public function __construct($path)
	{
		parent::__construct("Could not find route for {$path}");
	}
}

class Router
{
	protected $routes = [];

	protected $exception_handlers = [];

	public $container;

	public $type_patterns = [
		'path' => [
			'pattern' => '(?:[a-z0-9_-]+)(?:/[a-z0-9_-]+)*',
			'parse' => 'strval',
			'stringify' => 'strval'
		],
		'int' => [
			'pattern' => '(?:[0-9]+)',
			'parse' => 'intval',
			'stringify' => 'strval'
		],
		'string' => [
			'pattern' => '(?:[a-z0-9_-]+)',
			'parse' => 'strval',
			'stringify' => 'rawurlencode'
		]
	];

	public function __construct()
	{
		$this->container = new Container();
	}

	public function route($path, $callback)
	{
		[$pattern, $slots] = $this->compilePath($path);

		$this->routes[] = [
			'pattern' => $pattern,
			'slots' => $slots,
			'callback' => $callback
		];
	}

	public function execute()
	{
		$response = $this->dispatch($_SERVER['REQUEST_URI']);

		if (!is_array($response))
			$response = [$response];

		while (($part = array_pop($response)) !== null) {
			if (is_int($part))
				http_response_code($part);
			else if (is_array($part))
				foreach ($part as $name => $value)
					header("{$name}: {$value}");
			else
				echo $part;
		}
	}

	public function dispatch($path)
	{
		try {
			foreach ($this->routes as $route) {
				if (preg_match($route['pattern'], $path, $match)) {
					$parameters = [];

					foreach ($route['slots'] as $slot => $type)
						$parameters[$slot] = call_user_func($this->type_patterns[$type]['parse'], $match[$slot]);

					return $this->container->invoke($route['callback'], $parameters);
				}
			}

			throw new NoRouteException($path);
		} catch (Exception $e) {
			return $this->dispatchException($e);
		}
	}

	public function exceptionHandler($exception_class, $callback)
	{
		$this->exception_handlers[$exception_class] = $callback;
	}

	public function dispatchException(Exception $exception)
	{
		foreach ($this->exception_handlers as $exception_class => $handler) {
			if ($exception instanceof $exception_class) {
				return $this->container->invoke($handler, ['exception' => $exception]);
			}
		}

		throw $exception;
	}

	private function compilePath($path)
	{
		$slots = array();

		$replace_slots = function($match) use (&$slots) {
			$type = $match['type'] ? $match['type'] : 'string';
			$slots[$match['name']] = $type;
			return sprintf('(?P<%s>%s)', $match['name'], $this->type_patterns[$type]['pattern']);
		};

		$pattern = preg_replace_callback('/\<(?:(?P<type>\w+):)?(?P<name>[a-z_][a-z0-9_]*)\>/i',
			$replace_slots, $path);

		return [sprintf('{^%s$}i', $pattern), $slots];
	}
}