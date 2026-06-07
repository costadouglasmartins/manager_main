<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\SupportChat;
use App\Services\SupportChatTokenService;

class SupportChatController extends Controller
	{
	/**
	 * Ensures that only root users access the support administration area.
	 */
	private function requireRoot(): void
		{
		$this->requireAuth();

		if (Authorization::isRoot())
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'error' => 'forbidden',
				'message' => 'Only root users can access support administration.',
			], 403);
			}

		http_response_code(403);
		$this->view('errors/403', ['title' => 'Acesso negado', 'ability' => 'support_chat.root']);
		exit;
		}

	/**
	 * Renders the support chat listing.
	 */
	public function index()
		{
		$this->requireRoot();

		$model = new SupportChat();
		$filters = [
			'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
			'date_to' => isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '',
			'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
			'participant' => isset($_GET['participant']) ? trim((string) $_GET['participant']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 20;
		$offset = ($page - 1) * $perPage;
		$chats = $model->searchChats($filters, $perPage, $offset);
		$total = $model->countChats($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));

		$this->view('support_chats/index', [
			'title' => 'Chats de suporte',
			'chats' => $chats,
			'filters' => $filters,
			'page' => $page,
			'totalPages' => $totalPages,
			'total' => $total,
		]);
		}

	/**
	 * Renders the live support attendance page.
	 *
	 * @param mixed|null $chatId
	 */
	public function attend($chatId = null)
		{
		$this->requireRoot();

		$this->view('support_chats/attend', [
			'title' => 'Atendimento ao suporte',
			'initialChatId' => (int) ($chatId ?? 0),
		]);
		}

	/**
	 * Returns the initial state for the support dashboard.
	 */
	public function bootstrap()
		{
		$this->requireRoot();

		$model = new SupportChat();
		$currentUser = $_SESSION['manager_main']['user'] ?? [];

		$this->json([
			'currentUser' => [
				'id' => (int) ($currentUser['id'] ?? 0),
				'name' => (string) ($currentUser['name'] ?? ''),
				'username' => (string) ($currentUser['username'] ?? ''),
			],
			'attendants' => $model->getAttendants(),
			'chats' => $model->listChats(),
		]);
		}

	/**
	 * Returns one chat and its messages.
	 *
	 * @param mixed $chatId
	 */
	public function show($chatId)
		{
		$this->requireRoot();

		$model = new SupportChat();
		$chat = $model->findChatById((int) $chatId);

		if (!$chat)
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Chat nao encontrado.',
			], 404);
			}

		$this->json([
			'chat' => $chat,
			'messages' => $model->getMessages((int) $chatId),
		]);
		}

	/**
	 * Issues the WebSocket authentication token for the current root user.
	 */
	public function wsAuth()
		{
		$this->requireRoot();

		$user = $_SESSION['manager_main']['user'] ?? [];
		$token = SupportChatTokenService::issue([
			'system' => 'manager_main',
			'role' => 'root',
			'user_id' => (int) ($user['id'] ?? 0),
			'user_name' => (string) ($user['name'] ?? ''),
			'username' => (string) ($user['username'] ?? ''),
		]);

		$this->json([
			'token' => $token,
			'websocketUrl' => SupportChatTokenService::websocketUrl(),
		]);
		}
	}

?>
