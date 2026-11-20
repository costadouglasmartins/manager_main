<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Services\SupportChatTokenService;
use App\Services\VoiceCallIceService;

/**
 * Voice calls between support attendants (root users) and tenant customers.
 * The call runs inside the support chat window.
 * The WebSocket server checks the chat, the attendant and the tenant setting.
 */
class VoiceCallController extends Controller
	{
	/**
	 * Allows only root users.
	 */
	private function requireRoot(): array
		{
		$this->requireAuth();

		if (!Authorization::isRoot())
			{
			if ($this->wantsJson())
				{
				$this->json([
					'error' => 'forbidden',
					'message' => 'Only root users can place support voice calls.',
				], 403);
				}

			http_response_code(403);
			$this->view('errors/403', ['title' => 'Acesso negado', 'ability' => 'voice_call.root']);
			exit;
			}

		return $_SESSION['manager_main']['user'] ?? [];
		}

	/**
	 * Returns a WebSocket token for the voice call channel.
	 */
	public function wsAuth()
		{
		$user = $this->requireRoot();

		$this->json([
			'token' => SupportChatTokenService::issue([
				'system' => 'manager_main',
				'role' => 'voice',
				'user_id' => (int) ($user['id'] ?? 0),
				'user_name' => (string) ($user['name'] ?? ''),
				'username' => (string) ($user['username'] ?? ''),
			]),
			'websocketUrl' => SupportChatTokenService::websocketUrl(),
		]);
		}

	/**
	 * Returns STUN/TURN servers with short-lived credentials.
	 */
	public function iceServers()
		{
		$user = $this->requireRoot();

		$this->json([
			'iceServers' => VoiceCallIceService::iceServers((int) ($user['id'] ?? 0)),
		]);
		}
	}

?>
