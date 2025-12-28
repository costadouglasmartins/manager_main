<?php

namespace App\Core;

class App
	{

	protected $controller = 'HomeController';
	protected $method = 'index';
	protected $params = [];

	public function __construct()
		{
		$url = $this->parseUrl();

		$controllerName = isset($url[0]) ? ucfirst($url[0]) . 'Controller' : $this->controller;
		$controllerFile = __DIR__ . '/../Controllers/' . $controllerName . '.php';

		if (file_exists($controllerFile))
			{
			require_once $controllerFile;
			$controllerClass = "App\\Controllers\\" . $controllerName;
			$this->controller = new $controllerClass();
			unset($url[0]);
			}
		else
			{
			http_response_code(404);
			echo "Controller not found: $controllerName";
			exit;
			}

		if (isset($url[1]) && method_exists($this->controller, $url[1]))
			{
			$this->method = $url[1];
			unset($url[1]);
			}

		$this->params = $url ? array_values($url) : [];

		call_user_func_array([$this->controller, $this->method], $this->params);
		}

	private function parseUrl()
		{
		return isset($_GET['url']) ? explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL)) : [];
		}
	}

?>