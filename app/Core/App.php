<?php

namespace App\Core;

/**
 * Application front controller and router.
 */
class App
	{

	protected $controller = 'HomeController';
	protected $method = 'index';
	protected $params = [];

	/**
	 * Resolve controller, method and parameters from the request URL.
	 */
	public function __construct()
		{
		$url = $this->parseUrl();

		$controllerName = isset($url[0]) ? str_replace('_', '', ucwords($url[0], '_')) . 'Controller' : $this->controller;
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

		if (isset($url[1]))
			{
			$routeMethod = $this->controller->resolveRouteMethod($url[1]);
			if ($this->isControllerAction($routeMethod))
				{
				$this->method = $routeMethod;
				unset($url[1]);
				}
			}

		$this->params = $url ? array_values($url) : [];

		call_user_func_array([$this->controller, $this->method], $this->params);
		}

	/**
	 * Parse the `url` query parameter into route segments.
	 *
	 * @return array<int, string>
	 */
	private function parseUrl()
		{
		return isset($_GET['url']) ? explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL)) : [];
		}

	/**
	 * Determine whether a method can be invoked as a controller action.
	 *
	 * @param string $method Method name.
	 * @return bool
	 */
	private function isControllerAction(string $method): bool
		{
		if (!method_exists($this->controller, $method))
			{
			return false;
			}

		$reflection = new \ReflectionMethod($this->controller, $method);
		if (!$reflection->isPublic())
			{
			return false;
			}

		$declaringClass = $reflection->getDeclaringClass()->getName();
		if ($declaringClass === Controller::class && !in_array($method, ['exportPdf', 'exportExcel'], true))
			{
			return false;
			}

		return true;
		}
	}

?>
