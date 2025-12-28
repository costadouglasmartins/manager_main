<?php

namespace App\Core;

use App\Core\Authorization;

class Controller
	{

	/**
	 * View method
	 * @param String $view View name
	 * @param Array $data Data array
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

		// sem layout
		echo $content;
		}

	/**
	 * Json method
	 * @param type $data
	 * @param int $status
	 * @param array $headers
	 * @return void
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
	 * Wats json method
	 * @return bool
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
	 * Is authenticated method
	 * @return boolean 
	 */
	protected function isAuthenticated()
		{
		return !empty($_SESSION['manager_main']['user']);
		}

	/**
	 * Redirect method
	 * @param String $path 
	 */
	protected function redirect(string $path)
		{
		header('Location: ' . \url($path));
		exit;
		}

	/**
	 * Required auth method
	 */
	protected function requireAuth(): void
		{
		if (!$this->isAuthenticated())
			{

			// Se a requisição espera JSON, devolva JSON (não HTML via redirect)
			if ($this->wantsJson())
				{
				$this->json([
					'error' => 'unauthorized',
					'message' => 'Authentication required'
					], 401);
				}

			// Caso contrário, fluxo normal (HTML)
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
		$this->view('errors/403', ['title' => 'Acesso negado', 'ability' => $ability]);
		exit;
		}
	}

?>