<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Log;

class LogController extends Controller
	{
	/**
	 * Displays the audit log listing with filters and pagination.
	 *
	 * @return void
	 */
	public function index(): void
		{
		$this->requirePermission('log.index');

		// Filters are kept explicit so pagination can reproduce the exact search.
		$filters = [
			'id' => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'tenant' => isset($_GET['tenant']) ? trim((string) $_GET['tenant']) : '',
			'module' => isset($_GET['module']) ? trim((string) $_GET['module']) : '',
			'action' => isset($_GET['action']) ? trim((string) $_GET['action']) : '',
			'entity_name' => isset($_GET['entity_name']) ? trim((string) $_GET['entity_name']) : '',
			'entity_id' => isset($_GET['entity_id']) ? trim((string) $_GET['entity_id']) : '',
			'performed_by' => isset($_GET['performed_by']) ? trim((string) $_GET['performed_by']) : '',
			'route' => isset($_GET['route']) ? trim((string) $_GET['route']) : '',
			'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
			'date_to' => isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '',
		];

		// Pagination values are normalized before querying the model.
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 20;
		$offset = ($page - 1) * $perPage;

		$logModel = new Log();
		$sort = sortRequest(Log::sortableColumns(), 'occurred_at', 'desc');
		$logs = $logModel->search($filters, $perPage, $offset, $sort);
		$total = $logModel->count($filters);
		$totalPages = (int) ceil($total / $perPage);

		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		$modules = $logModel->getModules();
		$actions = [
			'create' => 'Criação',
			'update' => 'Alteração',
			'delete' => 'Exclusão',
		];

		if ($this->wantsJson())
			{
			$this->json([
				'filters' => $filters,
				'page' => $page,
				'perPage' => $perPage,
				'total' => $total,
				'totalPages' => $totalPages,
				'sort' => $sort,
				'modules' => $modules,
				'actions' => $actions,
				'data' => $logs,
			]);
			}

		$this->view('logs/index', [
			'title' => 'Logs do sistema',
			'logs' => $logs,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
			'sort' => $sort,
			'modules' => $modules,
			'actions' => $actions,
		]);
		}

	/**
	 * Displays a readable detail page for a single audit event.
	 *
	 * @param int|string $id Log ID.
	 * @return void
	 */
	public function informations($id): void
		{
		$this->requirePermission('log.informations');

		$logModel = new Log();
		$log = $logModel->find($id);

		if ($log === null)
			{
			$this->redirect('log');
			}

		$this->view('logs/informations', [
			'title' => 'Detalhes do log',
			'log' => $log,
		]);
		}
	}
?>
