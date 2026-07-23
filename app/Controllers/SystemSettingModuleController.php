<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\SystemSettingModule;

class SystemSettingModuleController extends Controller
	{
	/**
	 * Index method.
	 */
	public function index(): void
		{
		$this->requireCatalogAccess('system_setting_module.index');

		$model = new SystemSettingModule();
		$filters = [
			'id'     => isset($_GET['id']) ? trim($_GET['id']) : '',
			'slug'   => isset($_GET['slug']) ? trim($_GET['slug']) : '',
			'name'   => isset($_GET['name']) ? trim($_GET['name']) : '',
			'active' => isset($_GET['active']) ? trim($_GET['active']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(SystemSettingModule::sortableColumns(), 'sort_order', 'asc');
		$modules = $model->search($filters, $perPage, $offset, $sort);
		$total = $model->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'sort'       => $sort,
				'data'       => $modules,
			]);
			}

		$this->view('system_setting_modules/index', [
			'title'        => 'Módulos de configurações',
			'modules'      => $modules,
			'filters'      => $filters,
			'page'         => $page,
			'totalPages'   => $totalPages,
			'total'        => $total,
			'sort'         => $sort,
		]);
		}

	/**
	 * Create method.
	 */
	public function create(): void
		{
		$this->requireCatalogAccess('system_setting_module.create');
		$this->view('system_setting_modules/create', ['title' => 'Novo módulo de configurações']);
		}

	/**
	 * Store method.
	 */
	public function store(): void
		{
		$this->requireCatalogAccess('system_setting_module.store');

		try
			{
			(new SystemSettingModule())->create($_POST);
			setFlashMessage('success', 'Módulo de configurações cadastrado com sucesso.');
			$this->redirect('system_setting_module');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar o módulo. Verifique os dados e tente novamente.');
			$this->redirect('system_setting_module/create');
			}
		}

	/**
	 * Edit method.
	 *
	 * @param int|string $id Module identifier.
	 */
	public function edit($id): void
		{
		$this->requireCatalogAccess('system_setting_module.edit');
		$module = (new SystemSettingModule())->find($id);

		if (!$module)
			{
			setFlashMessage('danger', 'Módulo de configurações não encontrado.');
			$this->redirect('system_setting_module');
			}

		$this->view('system_setting_modules/edit', [
			'title'  => 'Editar módulo de configurações',
			'module' => $module,
		]);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Module identifier.
	 */
	public function update($id): void
		{
		$this->requireCatalogAccess('system_setting_module.update');

		try
			{
			(new SystemSettingModule())->update($id, $_POST);
			setFlashMessage('success', 'Módulo de configurações atualizado com sucesso.');
			$this->redirect('system_setting_module');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o módulo. Verifique os dados e tente novamente.');
			$this->redirect('system_setting_module/edit/' . $id);
			}
		}

	/**
	 * Informations method.
	 *
	 * @param int|string $id Module identifier.
	 */
	public function informations($id): void
		{
		$this->requireCatalogAccess('system_setting_module.informations');
		$module = (new SystemSettingModule())->find($id);

		if (!$module)
			{
			setFlashMessage('danger', 'Módulo de configurações não encontrado.');
			$this->redirect('system_setting_module');
			}

		$this->view('system_setting_modules/informations', [
			'title'  => 'Informações sobre o módulo de configurações',
			'module' => $module,
		]);
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Module identifier.
	 */
	public function delete($id): void
		{
		$this->requireCatalogAccess('system_setting_module.delete');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('system_setting_module');
			}

		try
			{
			(new SystemSettingModule())->delete($id);
			setFlashMessage('success', 'Módulo de configurações excluído com sucesso.');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível excluir o módulo. Verifique se ele possui definições vinculadas.');
			}

		$this->redirect('system_setting_module');
		}

	/**
	 * Requires root access or an explicit catalog permission.
	 *
	 * @param string $ability Ability name.
	 * @return void
	 */
	private function requireCatalogAccess(string $ability): void
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
	}

?>
