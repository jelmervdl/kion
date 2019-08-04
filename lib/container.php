<?php

class MissingServiceException extends LogicException
{
	public function __construct($service)
	{
		parent::__construct("No service '$service' available");
	}
}

class Container
{
	protected $registery = [];

	protected $items = [];

	private $build_stack = [];

	public function register($service, Callable $factory)
	{
		$this->registery[$service] = $factory;
	}

	public function has($service)
	{
		return isset($this->registery[$service]);
	}

	public function get($service)
	{
		if (!isset($this->items[$service]))
			$this->items[$service] = $this->build($service);

		return $this->items[$service];
	}

	public function build($service)
	{
		if (!isset($this->registery[$service]))
			throw new MissingServiceException($service);

		if (array_search($service, $this->build_stack) !== false) {
			$trace = implode(' -> ', array_merge($this->build_stack, [$service]));
			throw new LogicException("Recursive dependency for service '$service' ($trace)");
		}

		array_push($this->build_stack, $service);

		$item = $this->invoke($this->registery[$service]);

		$popped_service = array_pop($this->build_stack);
		assert($popped_service === $service);

		return $item;
	}

	public function invoke(Callable $closure, $parameters = array())
	{
		$closure_info = new ReflectionFunction($closure);

		$arguments = [];

		foreach ($closure_info->getParameters() as $parameter) {
			if (isset($parameters[$parameter->getName()]))
				$arguments[] = $parameters[$parameter->getName()];
			else if ($this->has($parameter->getName()))
				$arguments[] = $this->get($parameter->getName());
			else if ($parameter->isDefaultValueAvailable())
				$arguments[] = $parameter->getDefaultValue();
			else
				throw new MissingServiceException($parameter->getName());
		}

		return call_user_func_array($closure, $arguments);
	}
}