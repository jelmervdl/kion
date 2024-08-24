<?php

namespace tpl;

class TemplateException extends \LogicException
{
	//
}

class Template
{
	private $__TEMPLATE__;
	
	private $__DATA__ = [];
	
	private $__PARENT__ = null;

	private $__STACK__ = [];

	private $__MACROS__ = [];

	public function __construct($file, array $data = [])
	{
		if (!file_exists($file))
			throw new TemplateException("File {$file} not found");

		$this->__TEMPLATE__ = $file;
		$this->__DATA__ = $data;
	}

	public function set($key, $value)
	{
		$this->__DATA__[$key] = $value;
	}

	public function render()
	{
		ob_start();

		try {
			extract($this->__DATA__);
			include $this->__TEMPLATE__;
		} catch (\Exception $e) {
			ob_end_clean();
			throw $e;
		}

		if ($this->__PARENT__) {
			$this->__PARENT__->set('default', ob_get_clean());
			return $this->__PARENT__->render();
		} else {
			return ob_get_clean();
		}
	}

	protected function extends($template, array $data = [])
	{
		if ($this->__PARENT__)
			throw new TemplateException('Cannot call Template::extend twice from the same template');
		$this->__PARENT__ = new Template(dirname($this->__TEMPLATE__) . '/' . $template, array_merge($this->__DATA__, $data));
	}

	protected function begin($block_name)
	{
		if (!$this->__PARENT__)
			throw new TemplateException('Cannot begin a block while not extending a parent template');

		array_push($this->__STACK__, function($content) use ($block_name) {
			$this->__PARENT__->set($block_name, $content);
		});

		ob_start();
	}

	protected function macro($block_name)
	{
		$block_args = array_slice(func_get_args(), 1);

		if (!isset($this->__MACROS__[$block_name]))
			throw new TemplateException("No macro '$block_name' defined");

		$macro = $this->__MACROS__[$block_name];

		array_push($this->__STACK__, function($content) use ($macro, $block_args) {
			echo call_user_func_array($macro, array_merge([$content], $block_args));
		});

		ob_start();
	}

	protected function end()
	{
		if (!$this->__STACK__)
			throw new TemplateException('Calling Template::end while not in a block. Template::begin missing?');

		array_pop($this->__STACK__)(ob_get_clean());
	}

	public function define($name, callable $macro)
	{
		$this->__MACROS__[$name] = $macro;
	}

	public function __call(string $name, array $arguments)
	{
		return call_user_func_array($this->__MACROS__[$name], $arguments);
	}
}

function render_template(string $path, array $data = [])
{
	$tpl = new Template($path, $data);

	$tpl->define('html', function($data) {
		return \htmlspecialchars($data, ENT_COMPAT, 'utf-8');
	});

	$tpl->define('attr', function($data) {
		return \htmlspecialchars($data, ENT_QUOTES, 'utf-8');
	});

	$tpl->define('datetime', function($data, $format='Y-m-d H:i:s') {
		return (new \DateTime("@{$data}"))->format($format);
	});

	return $tpl->render();
}
