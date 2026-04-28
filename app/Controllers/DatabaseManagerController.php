<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Services\DatabaseManagerService;

class DatabaseManagerController extends Controller
	{

	/**
	 * Show database management dashboard and latest execution report.
	 */
	public function index(): void
		{
		$this->authorizeDatabaseManager('database_manager.index');

		$service = new DatabaseManagerService();
		$catalog = $service->getCatalog();
		$stats = $service->summarizeCatalog($catalog);
		$report = $_SESSION['manager_main']['database_manager_report'] ?? null;
		unset($_SESSION['manager_main']['database_manager_report']);

		$this->view('database_manager/index', [
			'title' => 'Database manager',
			'catalog' => $catalog,
			'stats' => $stats,
			'report' => $report,
		]);
		}

	/**
	 * Execute an SQL script against selected tenant databases.
	 */
	public function execute(): void
		{
		$this->authorizeDatabaseManager('database_manager.execute');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('database_manager');
			return;
			}

		$service = new DatabaseManagerService();
		$scope = isset($_POST['target_scope']) ? trim((string) $_POST['target_scope']) : 'all_tenants';
		$databaseStart = isset($_POST['database_start']) ? trim((string) $_POST['database_start']) : '';
		$databaseEnd = isset($_POST['database_end']) ? trim((string) $_POST['database_end']) : '';
		$sql = isset($_POST['sql_command']) ? (string) $_POST['sql_command'] : '';

		if (!$this->wantsJson())
			{
			$this->executeSynchronously($service, $scope, $databaseStart, $databaseEnd, $sql);
			return;
			}

		$this->prepareStream();

		$validationError = $service->validateSql($sql);
		if ($validationError !== null)
			{
			$this->pushStreamEvent([
				'type' => 'validation_error',
				'message' => $validationError,
			]);
			return;
			}

		try
			{
			$targets = $service->resolveTargets($scope, $databaseStart, $databaseEnd);
			}
		catch (\InvalidArgumentException $e)
			{
			$this->pushStreamEvent([
				'type' => 'validation_error',
				'message' => $e->getMessage(),
			]);
			return;
			}

		if (empty($targets))
			{
			$this->pushStreamEvent([
				'type' => 'validation_error',
				'message' => 'No databases were found for the selected filters.',
			]);
			return;
			}

		$total = count($targets);
		$startedAt = date('c');
		$successCount = 0;
		$errorCount = 0;
		$results = [];

		$this->pushStreamEvent([
			'type' => 'started',
			'total' => $total,
			'started_at' => $startedAt,
			'target_scope' => $scope,
			'database_start' => $service->normalizeDatabaseNumber($databaseStart),
			'database_end' => $service->normalizeDatabaseNumber($databaseEnd),
			'sql_preview' => $service->buildSqlPreview($sql),
		]);

		foreach ($targets as $index => $target)
			{
			$current = $index + 1;
			$result = $service->executeScript(
				(string) $target['database_name'],
				$sql
			);

			if ($result['success'])
				{
				$successCount++;
				}
			else
				{
				$errorCount++;
				}

			$payload = [
				'type' => 'progress',
				'current' => $current,
				'total' => $total,
				'percent' => (int) round(($current / $total) * 100),
				'database_name' => $target['database_name'],
				'database_number' => $target['database_number'],
				'tenant_id' => $target['tenant_id'],
				'tenant_name' => $target['tenant_name'],
				'tenant_status' => $target['tenant_status'],
				'success' => $result['success'],
				'message' => $result['message'],
				'duration_ms' => $result['duration_ms'],
				'finished_at' => date('c'),
			];

			$results[] = $payload;
			$this->pushStreamEvent($payload);
			}

		$this->pushStreamEvent([
			'type' => 'summary',
			'total' => $total,
			'success_count' => $successCount,
			'error_count' => $errorCount,
			'started_at' => $startedAt,
			'finished_at' => date('c'),
			'results' => $results,
		]);
		}

	/**
	 * Execute SQL in synchronous mode and store the final report in session.
	 */
	private function executeSynchronously(
		DatabaseManagerService $service,
		string $scope,
		string $databaseStart,
		string $databaseEnd,
		string $sql
	): void
		{
		$validationError = $service->validateSql($sql);
		if ($validationError !== null)
			{
			$this->storeReport([
				'type' => 'warning',
				'title' => 'Execution not started',
				'message' => $validationError,
				'results' => [],
				'success_count' => 0,
				'error_count' => 0,
			]);
			$this->redirect('database_manager');
			return;
			}

		try
			{
			$targets = $service->resolveTargets($scope, $databaseStart, $databaseEnd);
			}
		catch (\InvalidArgumentException $e)
			{
			$this->storeReport([
				'type' => 'warning',
				'title' => 'Execution not started',
				'message' => $e->getMessage(),
				'results' => [],
				'success_count' => 0,
				'error_count' => 0,
			]);
			$this->redirect('database_manager');
			return;
			}

		if (empty($targets))
			{
			$this->storeReport([
				'type' => 'warning',
				'title' => 'No targets found',
				'message' => 'No databases were found for the selected filters.',
				'results' => [],
				'success_count' => 0,
				'error_count' => 0,
			]);
			$this->redirect('database_manager');
			return;
			}

		$results = [];
		$successCount = 0;
		$errorCount = 0;

		foreach ($targets as $target)
			{
			$result = $service->executeScript((string) $target['database_name'], $sql);

			if ($result['success'])
				{
				$successCount++;
				}
			else
				{
				$errorCount++;
				}

			$results[] = [
				'database_name' => $target['database_name'],
				'database_number' => $target['database_number'],
				'tenant_name' => $target['tenant_name'],
				'success' => $result['success'],
				'message' => $result['message'],
				'duration_ms' => $result['duration_ms'],
			];
			}

		$this->storeReport([
			'type' => ($errorCount > 0 ? 'warning' : 'success'),
			'title' => 'Execution completed',
			'message' => sprintf(
				'%d database(s) processed, %d succeeded and %d failed.',
				count($targets),
				$successCount,
				$errorCount
			),
			'results' => $results,
			'success_count' => $successCount,
			'error_count' => $errorCount,
		]);

		$this->redirect('database_manager');
		}

	/**
	 * Ensure the current user can access database manager features.
	 *
	 * @param string $ability Required ability for the action.
	 */
	private function authorizeDatabaseManager(string $ability): void
		{
		$this->requireAuth();

		if (Authorization::isRoot() || Authorization::can($ability))
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

	/**
	 * Configure response headers and buffers for NDJSON streaming.
	 */
	private function prepareStream(): void
		{
		ignore_user_abort(true);
		set_time_limit(0);

		@ini_set('output_buffering', 'off');
		@ini_set('zlib.output_compression', '0');

		while (ob_get_level() > 0)
			{
			@ob_end_flush();
			}

		header('Content-Type: application/x-ndjson; charset=utf-8');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');
		header('X-Accel-Buffering: no');
		}

	/**
	 * Push one NDJSON event to the response stream.
	 *
	 * @param array<string, mixed> $payload
	 */
	private function pushStreamEvent(array $payload): void
		{
		echo json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
		) . "\n";

		@ob_flush();
		flush();
		}

	/**
	 * Persist an execution report for display after redirect.
	 *
	 * @param array<string, mixed> $report
	 */
	private function storeReport(array $report): void
		{
		$_SESSION['manager_main']['database_manager_report'] = $report;
		}
	}

?>
