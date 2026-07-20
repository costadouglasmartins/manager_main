<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\SupportChat;
use App\Services\SupportChatTypingService;
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
	 * Renders the persisted conversation records for one support chat.
	 *
	 * @param mixed $chatId
	 */
	public function informations($chatId): void
		{
		$this->requireRoot();

		$model = new SupportChat();
		$chatId = (int) $chatId;
		$chat = $model->findChatById($chatId);

		if (!$chat)
			{
			setFlashMessage('warning', 'Chat não encontrado.');
			$this->redirect('support_chat');
			}

		$this->view('support_chats/informations', [
			'title' => 'Registros da conversa',
			'chat' => $chat,
			'messages' => $model->getMessages($chatId, 1000),
		]);
		}

	/**
	 * Streams live chat record updates using Server-Sent Events.
	 *
	 * @param mixed $chatId
	 */
	public function events($chatId): void
		{
		$this->requireRoot();

		$model = new SupportChat();
		$chatId = (int) $chatId;

		ignore_user_abort(true);
		set_time_limit(0);

		header('Content-Type: text/event-stream; charset=utf-8');
		header('Cache-Control: no-cache, no-transform');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');

		while (ob_get_level() > 0)
			{
			ob_end_flush();
			}

		ob_implicit_flush(true);
		session_write_close();

		echo "retry: 2000\n\n";
		flush();

		$lastFingerprint = '';
		$lastPingAt = time();
		$startedAt = time();
		$maxDurationSeconds = 90;

		while (!connection_aborted() && (time() - $startedAt) < $maxDurationSeconds)
			{
			$payload = $this->supportChatEventPayload($model, $chatId);

			if (empty($payload['chat']))
				{
				$this->sendSseEvent('deleted', ['chat_id' => $chatId]);
				break;
				}

			$fingerprint = sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
			if ($fingerprint !== $lastFingerprint)
				{
				$this->sendSseEvent('snapshot', $payload);
				$lastFingerprint = $fingerprint;
				}

			if ((string) ($payload['chat']['status'] ?? '') === 'closed')
				{
				$this->sendSseEvent('complete', ['chat_id' => $chatId]);
				break;
				}

			if ((time() - $lastPingAt) >= 15)
				{
				$this->sendSseEvent('ping', ['time' => time()]);
				$lastPingAt = time();
				}

			sleep(1);
			}

		exit;
		}

	/**
	 * Reopens a closed support chat through a POST request.
	 *
	 * @param mixed $chatId
	 */
	public function reopen($chatId): void
		{
		$this->requireRoot();
		$redirectPath = $this->supportChatRedirectPath();
		$wantsJson = $this->wantsJson();

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			if ($wantsJson)
				{
				$this->json([
					'error' => 'method_not_allowed',
					'message' => 'A reabertura deve ser enviada por POST.',
				], 405, ['Allow' => 'POST']);
				}

			$this->redirect($redirectPath);
			}

		$model = new SupportChat();
		try
			{
			$chat = $model->reopenChat((int) $chatId);
			if (!$chat)
				{
				if ($wantsJson)
					{
					$this->json([
						'error' => 'chat_not_found',
						'message' => 'Chat não encontrado.',
					], 404);
					}

				setFlashMessage('warning', 'Chat não encontrado.');
				$this->redirect($redirectPath);
				}

			if ($wantsJson)
				{
				$this->json([
					'message' => 'Chat reaberto com sucesso.',
					'chat' => $chat,
				]);
				}

			setFlashMessage('success', 'Chat reaberto com sucesso.');
			$this->redirect($redirectPath);
			}
		catch (\Exception $exception)
			{
			if ($wantsJson)
				{
				$this->json([
					'error' => 'chat_reopen_failed',
					'message' => 'Não foi possível reabrir o chat.',
				], 500);
				}

			setFlashMessage('danger', 'Não foi possível reabrir o chat.');
			$this->redirect($redirectPath);
			}
		}

	/**
	 * Deletes a support chat through a POST request.
	 *
	 * @param mixed $chatId
	 */
	public function delete($chatId): void
		{
		$this->requireRoot();
		$redirectPath = $this->supportChatRedirectPath();

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect($redirectPath);
			}

		$model = new SupportChat();
		try
			{
			if ($model->deleteChat((int) $chatId))
				{
				setFlashMessage('success', 'Chat excluído com sucesso.');
				$this->redirect($redirectPath);
				}

			setFlashMessage('warning', 'Chat não encontrado.');
			$this->redirect($redirectPath);
			}
		catch (\Exception $exception)
			{
			setFlashMessage('danger', 'Não foi possível excluir o chat.');
			$this->redirect($redirectPath);
			}
		}

	/**
	 * Returns the initial state for the support dashboard.
	 */
	public function bootstrap()
		{
		$this->requireRoot();

		$model = new SupportChat();
		$currentUser = $_SESSION['manager_main']['user'] ?? [];
		$filters = [];
		$tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;
		$customerUserId = isset($_GET['customer_user_id']) ? (int) $_GET['customer_user_id'] : 0;

		if ($tenantId > 0)
			{
			$filters['tenant_id'] = $tenantId;
			}
		if ($customerUserId > 0)
			{
			$filters['customer_user_id'] = $customerUserId;
			}

		$this->json([
			'currentUser' => [
				'id' => (int) ($currentUser['id'] ?? 0),
				'name' => (string) ($currentUser['name'] ?? ''),
				'username' => (string) ($currentUser['username'] ?? ''),
			],
			'attendants' => $model->getAttendants(),
			'chats' => $model->listChats($filters),
		]);
		}

	/**
	 * Returns tenant/user associations matching tenant, person, e-mail or username.
	 */
	public function tenantUserAutocomplete(): void
		{
		$this->requireRoot();

		$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
		$rows = (new SupportChat())->autocompleteTenantUsers($term, $limit);

		$items = array_map(function (array $row): array
			{
			$databaseNumber = trim((string) ($row['database_number'] ?? ''));
			$tenantName = trim((string) ($row['tenant_name'] ?? ''));
			if ($tenantName === '')
				{
				$tenantName = $databaseNumber !== '' ? 'Tenant ' . $databaseNumber : 'Tenant #' . (int) ($row['tenant_id'] ?? 0);
				}
			$tenantLabel = $tenantName;
			if ($databaseNumber !== '' && strpos($tenantName, $databaseNumber) === false)
				{
				$tenantLabel .= ' (' . $databaseNumber . ')';
				}

			$personName = trim((string) ($row['person_name'] ?? ''));
			if ($personName === '')
				{
				$personName = 'Pessoa #' . (int) ($row['person_id'] ?? 0);
				}

			$username = trim((string) ($row['username'] ?? ''));
			if ($username === '')
				{
				$username = 'Usuário #' . (int) ($row['user_id'] ?? 0);
				}

			$label = 'Tenant: ' . $tenantLabel . ' | Pessoa: ' . $personName . ' | Usuário: ' . $username;

			return [
				'id' => (int) ($row['user_id'] ?? 0),
				'name' => $label,
				'label' => $label,
				'tenant_id' => (int) ($row['tenant_id'] ?? 0),
				'tenant_name' => $tenantName,
				'database_number' => $databaseNumber,
				'user_id' => (int) ($row['user_id'] ?? 0),
				'username' => $username,
				'person_id' => (int) ($row['person_id'] ?? 0),
				'person_name' => $personName,
				'email' => (string) ($row['email'] ?? ''),
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Opens an existing active conversation or creates one for a tenant user.
	 */
	public function openConversation(): void
		{
		$this->requireRoot();

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->json([
				'error' => 'method_not_allowed',
				'message' => 'A conversa deve ser aberta por POST.',
			], 405, ['Allow' => 'POST']);
			}

		$tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;
		$customerUserId = isset($_POST['customer_user_id']) ? (int) $_POST['customer_user_id'] : 0;
		$currentUser = $_SESSION['manager_main']['user'] ?? [];

		try
			{
			$result = (new SupportChat())->openConversation(
				$customerUserId,
				$tenantId,
				(int) ($currentUser['id'] ?? 0)
			);

			$this->json([
				'message' => $result['created'] ? 'Conversa criada com sucesso.' : 'Conversa ativa carregada.',
				'created' => (bool) $result['created'],
				'chat' => $result['chat'],
			], $result['created'] ? 201 : 200);
			}
		catch (\InvalidArgumentException $exception)
			{
			$this->json([
				'error' => 'invalid_tenant_user',
				'message' => $exception->getMessage(),
			], 422);
			}
		catch (\Throwable $exception)
			{
			$this->json([
				'error' => 'conversation_open_failed',
				'message' => 'Não foi possível abrir a conversa.',
			], 500);
			}
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
		$chatId = (int) $chatId;
		$chat = $model->findChatById($chatId);

		if (!$chat)
			{
			$this->json([
				'error' => 'chat_not_found',
				'message' => 'Chat nao encontrado.',
			], 404);
			}

		$messages = $model->getMessages($chatId);
		$model->markAttendantMessagesSeen($chatId, $messages);
		$chat = $model->findChatById($chatId) ?? $chat;

		$this->json([
			'chat' => $chat,
			'messages' => $messages,
		]);
		}

	/**
	 * Returns unread customer messages for the support notification bell.
	 */
	public function notifications()
		{
		$this->requireRoot();

		$model = new SupportChat();
		$this->json($model->getUnreadCustomerNotifications());
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

	/**
	 * Returns a safe listing route to redirect back after chat actions.
	 */
	private function supportChatRedirectPath(): string
		{
		$redirectPath = isset($_POST['redirect_to']) ? trim((string) $_POST['redirect_to']) : '';
		if ($redirectPath === '')
			{
			return 'support_chat';
			}

		if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $redirectPath) || strpos($redirectPath, '/') === 0 || strpos($redirectPath, '\\') !== false)
			{
			return 'support_chat';
			}

		return $redirectPath;
		}

	/**
	 * Builds the public chat state sent through SSE.
	 *
	 * @return array<string, mixed>
	 */
	private function supportChatEventPayload(SupportChat $model, int $chatId): array
		{
		$chat = $model->findChatById($chatId);
		if (!$chat)
			{
			return [
				'chat' => null,
				'messages' => [],
				'typing_users' => [],
			];
			}

		return [
			'chat' => $chat,
			'messages' => $model->getMessages($chatId, 1000),
			'typing_users' => SupportChatTypingService::getTypingUsers($chatId),
		];
		}

	/**
	 * Sends one Server-Sent Event frame.
	 *
	 * @param array<string, mixed> $data
	 */
	private function sendSseEvent(string $event, array $data): void
		{
		echo 'event: ' . $event . "\n";
		echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n\n";
		flush();
		}
	}

?>
