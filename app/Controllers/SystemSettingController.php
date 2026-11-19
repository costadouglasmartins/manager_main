<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\SystemSetting;
use App\Models\Tenant;

class SystemSettingController extends Controller
	{
	/**
	 * Displays tenant settings grouped by module.
	 **/
	public function index(): void
		{
		$this->requireSettingsAccess('system_setting.index');

		$tenantModel = new Tenant();
		$settingModel = new SystemSetting();
		$tenants = $tenantModel->getAll();
		$tenantId = (int) ($_GET['tenant_id'] ?? ($tenants[0]['id'] ?? 0));
		$values = $tenantId > 0 ? $settingModel->tenantValues($tenantId) : [];

		$this->view('system_settings/index', [
			'title' => 'System settings',
			'tenants' => $tenants,
			'tenantId' => $tenantId,
			'modules' => $settingModel->groupedDefinitions(),
			'values' => $values,
		]);
		}

	/**
	 * Saves tenant setting overrides.
	 **/
	public function update(): void
		{
		$this->requireSettingsAccess('system_setting.update');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('system_setting');
			}

		$tenantId = (int) ($_POST['tenant_id'] ?? 0);
		if ($tenantId <= 0)
			{
			setFlashMessage('danger', 'Invalid tenant.');
			$this->redirect('system_setting');
			}

		$values = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];
		try
			{
			(new SystemSetting())->saveTenantValues($tenantId, $values);
			setFlashMessage('success', 'Settings saved.');
			}
		catch (\InvalidArgumentException $e)
			{
			setFlashMessage('danger', $e->getMessage());
			}
		catch (\Throwable $e)
			{
			error_log('SystemSettingController::update error: ' . $e->getMessage());
			setFlashMessage('danger', 'Settings could not be saved.');
			}

		$this->redirect('system_setting?tenant_id=' . $tenantId);
		}

	/**
	 * Requires root access or an explicit settings permission.
	 * @param string $ability Ability name.
	 * @return void
	 **/
	private function requireSettingsAccess(string $ability): void
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
