<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\SystemSettingDefinition;
use App\Models\SystemSettingModule;

class SystemSettingDefinitionController extends Controller
	{
	/**
	 * Index method.
	 */
	public function index(): void
		{
		$this->requireCatalogAccess('system_setting_definition.index');

		$model = new SystemSettingDefinition();
		$filters = [
			'id'         => isset($_GET['id']) ? trim($_GET['id']) : '',
			'module_id'  => isset($_GET['module_id']) ? trim($_GET['module_id']) : '',
			'key_name'   => isset($_GET['key_name']) ? trim($_GET['key_name']) : '',
			'label'      => isset($_GET['label']) ? trim($_GET['label']) : '',
			'value_type' => isset($_GET['value_type']) ? trim($_GET['value_type']) : '',
			'active'     => isset($_GET['active']) ? trim($_GET['active']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(SystemSettingDefinition::sortableColumns(), 'sort_order', 'asc');
		$definitions = $model->search($filters, $perPage, $offset, $sort);
		$total = $model->count($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$modules = (new SystemSettingModule())->getAll();

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'sort'       => $sort,
				'data'       => $definitions,
			]);
			}

		$this->view('system_setting_definitions/index', [
			'title'       => 'Definições de configurações',
			'definitions' => $definitions,
			'modules'     => $modules,
			'valueTypes'   => SystemSettingDefinition::valueTypes(),
			'filters'     => $filters,
			'page'        => $page,
			'totalPages'  => $totalPages,
			'total'       => $total,
			'sort'        => $sort,
		]);
		}

	/**
	 * Create method.
	 */
	public function create(): void
		{
		$this->requireCatalogAccess('system_setting_definition.create');
		$this->view('system_setting_definitions/create', [
			'title'      => 'Nova definição de configuração',
			'modules'    => (new SystemSettingModule())->getAll(true),
			'valueTypes' => SystemSettingDefinition::valueTypes(),
		]);
		}

	/**
	 * Store method.
	 */
	public function store(): void
		{
		$this->requireCatalogAccess('system_setting_definition.store');

		try
			{
			(new SystemSettingDefinition())->create($_POST);
			setFlashMessage('success', 'Definição de configuração cadastrada com sucesso.');
			$this->redirect('system_setting_definition');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar a definição. Verifique os dados e tente novamente.');
			$this->redirect('system_setting_definition/create');
			}
		}

	/**
	 * Edit method.
	 *
	 * @param int|string $id Definition identifier.
	 */
	public function edit($id): void
		{
		$this->requireCatalogAccess('system_setting_definition.edit');
		$definition = (new SystemSettingDefinition())->find($id);

		if (!$definition)
			{
			setFlashMessage('danger', 'Definição de configuração não encontrada.');
			$this->redirect('system_setting_definition');
			}

		$this->view('system_setting_definitions/edit', [
			'title'      => 'Editar definição de configuração',
			'definition' => $definition,
			'modules'    => (new SystemSettingModule())->getAll(),
			'valueTypes' => SystemSettingDefinition::valueTypes(),
		]);
		}

	/**
	 * Update method.
	 *
	 * @param int|string $id Definition identifier.
	 */
	public function update($id): void
		{
		$this->requireCatalogAccess('system_setting_definition.update');

		try
			{
			(new SystemSettingDefinition())->update($id, $_POST);
			setFlashMessage('success', 'Definição de configuração atualizada com sucesso.');
			$this->redirect('system_setting_definition');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar a definição. Verifique os dados e tente novamente.');
			$this->redirect('system_setting_definition/edit/' . $id);
			}
		}

	/**
	 * Informations method.
	 *
	 * @param int|string $id Definition identifier.
	 */
	public function informations($id): void
		{
		$this->requireCatalogAccess('system_setting_definition.informations');
		$definition = (new SystemSettingDefinition())->find($id);

		if (!$definition)
			{
			setFlashMessage('danger', 'Definição de configuração não encontrada.');
			$this->redirect('system_setting_definition');
			}

		$this->view('system_setting_definitions/informations', [
			'title'      => 'Informações sobre a definição de configuração',
			'definition' => $definition,
		]);
		}

	/**
	 * Delete method.
	 *
	 * @param int|string $id Definition identifier.
	 */
	public function delete($id): void
		{
		$this->requireCatalogAccess('system_setting_definition.delete');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('system_setting_definition');
			}

		try
			{
			(new SystemSettingDefinition())->delete($id);
			setFlashMessage('success', 'Definição de configuração excluída com sucesso.');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível excluir a definição. Verifique se ela possui valores por tenant.');
			}

		$this->redirect('system_setting_definition');
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
