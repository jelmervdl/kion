<?php

namespace tpl;

class TemplateException extends \LogicException
{
	//
}

class Template
{
	private $__TEMPLATE__;
	private $__DATA__;
	private $__PARENT__;
	private $__BLOCK__;

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
		extract($this->__DATA__);
		include $this->__TEMPLATE__;

		if ($this->__PARENT__) {
			$this->__PARENT__->set('default', ob_get_clean());
			return $this->__PARENT__->render();
		} else {
			return ob_get_clean();
		}
	}

	protected function extends($template)
	{
		if ($this->__PARENT__)
			throw new TemplateException('Cannot call Template::extend twice from the same template');
		$this->__PARENT__ = new Template(dirname($this->__TEMPLATE__) . '/' . $template, $this->__DATA__);
	}

	protected function begin($block_name)
	{
		if (!$this->__PARENT__)
			throw new TemplateException('Cannot begin a block while not extending a parent template');

		if ($this->__BLOCK__)
			throw new TemplateException('Cannot have a block inside a block in templates');

		$this->__BLOCK__ = $block_name;
		ob_start();
	}

	protected function end()
	{
		if (!$this->__BLOCK__)
			throw new TemplateException('Calling Template::end while not in a block. Template::begin missing?');

		$this->__PARENT__->set($this->__BLOCK__, ob_get_clean());
		$this->__BLOCK__ = null;
	}
	
	static public function html($data)
	{
		return htmlspecialchars($data, ENT_COMPAT, 'utf-8');
	}

	static public function attr($data)
	{
		return htmlspecialchars($data, ENT_QUOTES, 'utf-8');
	}
}

function render_template($path, array $data = [])
{
	return (new Template($path, $data))->render();
}