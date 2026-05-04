<?php

namespace App\Core;

use App\Core\Authorization;

class Controller
	{
	/**
	 * Route aliases accepted by the controller.
	 *
	 * @var array<string, string>
	 */
	protected $routeAliases = [];

	/**
	 * Resolve a route method alias to the controller method that should run.
	 *
	 * @param string $routeMethod Method segment received from the URL.
	 * @return string Controller method name.
	 */
	public function resolveRouteMethod(string $routeMethod): string
		{
		if (isset($this->routeAliases[$routeMethod]) && is_string($this->routeAliases[$routeMethod]))
			{
			return $this->routeAliases[$routeMethod];
			}

		return $routeMethod;
		}

	/**
	 * Render a view and optionally wrap it with the main layout.
	 *
	 * @param string $view View path relative to `app/Views` without extension.
	 * @param array<string, mixed> $data Variables extracted into the view scope.
	 * @param bool $useLayout Whether to render the default layout.
	 */
	protected function view(string $view, array $data = [], bool $useLayout = true): void
		{
		extract($data);

		$viewFile = __DIR__ . '/../Views/' . $view . '.php';

		ob_start();
		if (file_exists($viewFile))
			{
			require $viewFile;
			}
		else
			{
			echo "View {$view} not found!";
			}
		$content = ob_get_clean();

		if ($useLayout)
			{
			require __DIR__ . '/../Views/layouts/main.php';
			return;
			}

		// Render content without layout wrapper.
		echo $content;
		}

	/**
	 * Send a JSON response and terminate the request.
	 *
	 * @param mixed $data Response payload.
	 * @param int $status HTTP status code.
	 * @param array<string, string> $headers Additional response headers.
	 */
	protected function json($data, int $status = 200, array $headers = []): void
		{
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');

		foreach ($headers as $k => $v)
			{
			header($k . ': ' . $v);
			}

		echo json_encode(
			$data,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		exit;
		}

	/**
	 * Determine whether the current request expects JSON output.
	 */
	protected function wantsJson(): bool
		{
		if (isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json')
			{
			return true;
			}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if (stripos($accept, 'application/json') !== false)
			{
			return true;
			}

		$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
		if (strtolower($xhr) === 'xmlhttprequest')
			{
			return true;
			}

		return false;
		}

	/**
	 * Check whether the current session has an authenticated user.
	 *
	 * @return bool
	 */
	protected function isAuthenticated()
		{
		return !empty($_SESSION['manager_main']['user']);
		}

	/**
	 * Redirect to an application route and terminate execution.
	 *
	 * @param string $path Relative application route.
	 */
	protected function redirect(string $path)
		{
		header('Location: ' . \url($path));
		exit;
		}

	/**
	 * Require authentication for the current request.
	 */
	protected function requireAuth(): void
		{
		if (!$this->isAuthenticated())
			{

			// Return JSON when the request expects an API response.
			if ($this->wantsJson())
				{
				$this->json([
					'error' => 'unauthorized',
					'message' => 'Authentication required'
					], 401);
				}

			// Fallback to regular HTML flow.
			$this->redirect('user/login');
			exit;
			}
		}

	/**
	 * Require a specific permission (ability).
	 */
	protected function requirePermission(string $ability): void
		{
		$this->requireAuth();

		if (Authorization::can($ability))
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'error' => 'forbidden',
				'message' => 'You do not have permission to access this resource',
				'ability' => $ability,
			], 403);
			}

		http_response_code(403);
		$this->view('errors/403', ['title' => 'Access denied', 'ability' => $ability]);
		exit;
		}
	}

?>
