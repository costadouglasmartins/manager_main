<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\UserPresenceService;

class UserPresenceController extends Controller
	{
	/**
	 * Returns the current user presence.
	 **/
	public function current(): void
		{
		$this->requireAuth();

		$user = $_SESSION['manager_main']['user'] ?? [];
		$presence = (new UserPresenceService())->current((int) ($user['id'] ?? 0));

		$this->json([
			'presence' => $presence,
			'statuses' => UserPresenceService::statuses(),
		]);
		}

	/**
	 * Updates the current user presence status.
	 **/
	public function status(): void
		{
		$this->requireAuth();

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->json(['error' => 'method_not_allowed', 'message' => 'Method not allowed.'], 405, ['Allow' => 'POST']);
			}

		$user = $_SESSION['manager_main']['user'] ?? [];
		$userId = (int) ($user['id'] ?? 0);
		$tenantId = isset($user['tenant_id']) && (int) $user['tenant_id'] > 0 ? (int) $user['tenant_id'] : null;
		$status = (string) ($_POST['status'] ?? '');

		try
			{
			$presence = (new UserPresenceService())->setStatus($userId, $tenantId, $status);
			$_SESSION['manager_main']['user']['presence_status'] = $presence['status'];
			$this->json(['presence' => $presence]);
			}
		catch (\RuntimeException $e)
			{
			$this->json(['error' => 'online_limit_reached', 'message' => $e->getMessage()], 409);
			}
		}
	}
?>
