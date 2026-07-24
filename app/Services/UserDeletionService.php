<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Person;
use App\Models\User;
use PDO;

/**
 * Builds a row-by-row user deletion manifest and removes the user aggregate.
 *
 * Tenant databases are never dropped. Only records associated with the user
 * are removed from manager_main and from existing manager_NNNN databases.
 */
class UserDeletionService
	{
	private PDO $db;

	public function __construct(?PDO $db = null)
		{
		$this->db = $db ?? Database::getInstance();
		}

	/** @return array<string, mixed> */
	public function impact(int $userId): array
		{
		$plan = $this->buildPlan($userId);
		$modules = $plan['modules'];

		return [
			'user' => [
				'id' => (int) $plan['user']['id'],
				'username' => (string) $plan['user']['username'],
				'name' => (string) $plan['user']['name'],
			],
			'modules' => $modules,
			'total_records' => array_sum(array_map(static fn (array $module): int => (int) $module['count'], $modules)),
			'preserved' => [
				'As bases de dados manager_NNNN',
				'Chats de suporte em que o usuário era apenas atendente ou responsável pelo encerramento',
			],
			'warnings' => $plan['warnings'],
		];
		}

	/**
	 * Remove every row listed by a fresh deletion plan.
	 */
	public function delete(int $userId): void
		{
		$plan = $this->buildPlan($userId);
		$ownsTransaction = !$this->db->inTransaction();

		try
			{
			if ($ownsTransaction)
				{
				$this->db->beginTransaction();
				}

			foreach ($plan['tenant_databases'] as $databasePlan)
				{
				$schema = $databasePlan['schema'];

				// Children first: this also works in older tenant schemas whose
				// foreign-key cascade rules are incomplete.
				$this->deleteIds($schema, 'task_executions', $databasePlan['task_execution_ids']);
				$this->deleteIds($schema, 'task_comments', $databasePlan['task_comment_ids']);
				$this->deleteIds($schema, 'task_assignees', $databasePlan['task_assignee_ids']);
				$this->deleteIds($schema, 'tasks', $databasePlan['task_ids']);
				$this->updateIdsToNull($schema, 'tasks', 'third_party_person_id', $databasePlan['detached_task_ids']);

				$this->deleteIds($schema, 'product_stocks_movements', $databasePlan['product_movement_ids']);
				$this->deleteIds($schema, 'product_images', $databasePlan['product_image_ids']);
				$this->deleteIds($schema, 'products', $databasePlan['product_ids']);

				$this->deleteIds($schema, 'schedule_events_participants', $databasePlan['schedule_participant_ids']);
				$this->deleteIds($schema, 'schedule_events', $databasePlan['schedule_event_ids']);
				$this->updateIdsToNull(
					$schema,
					'schedule_events',
					'schedule_events_recurrence_id',
					$databasePlan['detached_schedule_event_ids']
				);
				$this->deleteIds($schema, 'schedule_events_recurrences', $databasePlan['schedule_recurrence_ids']);
				}

			$central = $plan['central'];

			$this->deleteIds('manager_main', 'internal_chat_messages', $central['internal_message_ids']);
			$this->deleteInternalParticipants($userId, $central['internal_chat_ids']);
			$this->deleteIds('manager_main', 'internal_chats', $central['internal_chat_ids']);
			$this->refreshLastMessageAt('internal_chats', 'internal_chat_messages', 'internal_chat_id', $central['preserved_internal_chat_ids']);

			$this->deleteIds('manager_main', 'support_chats_messages', $central['support_message_ids']);
			$this->deleteIds('manager_main', 'support_chats', $central['support_chat_ids']);
			$this->clearSupportChatUserReferences($userId);
			$this->refreshLastMessageAt('support_chats', 'support_chats_messages', 'support_chat_id', $central['preserved_support_chat_ids']);

			$this->deleteIds('manager_main', 'financial_operations_payments', $central['financial_payment_ids']);
			$this->deleteIds('manager_main', 'financial_operations', $central['financial_operation_ids']);

			$this->deleteIds('manager_main', 'logs', $central['log_ids']);

			$this->deleteWhereUserId('user_remember_tokens', $userId);
			$this->deleteWhereUserId('user_permissions', $userId);
			$this->deleteWhereUserId('user_presences', $userId);
			$this->deleteWhereUserId('tenant_users', $userId);

			$affectedTables = [];
			foreach ($plan['modules'] as $module)
				{
				foreach ($module['records'] as $record)
					{
					$affectedTables[] = $module['scope'] . '.' . $record['table'];
					}
				}

			(new AuditLogService($this->db))->logDelete('user', 'users', $userId, $plan['user'], [
				'tenant_id' => $plan['user']['tenant_id'],
				'affected_tables' => array_values(array_unique($affectedTables)),
				'deleted_person_id' => (int) $plan['user']['person_id'],
				'databases_preserved' => true,
				'source' => 'user_full_deletion',
			]);

			$stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
			$stmt->execute(['id' => $userId]);
			if ($stmt->rowCount() !== 1)
				{
				throw new \RuntimeException('O usuário não foi removido.');
				}

			$this->deleteIds('manager_main', 'addresses', $central['address_ids']);
			$this->deleteIds('manager_main', 'emails', $central['email_ids']);
			$this->deleteIds('manager_main', 'phone_numbers', $central['phone_number_ids']);

			$stmt = $this->db->prepare('DELETE FROM people WHERE id = :id');
			$stmt->execute(['id' => (int) $plan['user']['person_id']]);
			if ($stmt->rowCount() !== 1)
				{
				throw new \RuntimeException('A pessoa associada ao usuário não foi removida.');
				}

			if ($ownsTransaction)
				{
				$this->db->commit();
				}
			}
		catch (\Throwable $e)
			{
			if ($ownsTransaction && $this->db->inTransaction())
				{
				$this->db->rollBack();
				}
			throw $e;
			}
		}

	/** @return array<string, mixed> */
	private function buildPlan(int $userId): array
		{
		if (in_array($userId, User::PROTECTED_USER_IDS, true))
			{
			throw new \RuntimeException('Este usuário é protegido e não pode ser excluído.');
			}

		$stmt = $this->db->prepare("SELECT UserRow.id,
		                                      UserRow.person_id,
		                                      UserRow.tenant_id,
		                                      UserRow.username,
		                                      UserRow.type,
		                                      UserRow.active,
		                                      Person.name,
		                                      Person.document,
		                                      (SELECT Email.email
		                                       FROM emails AS Email
		                                       WHERE Email.person_id = UserRow.person_id
		                                       ORDER BY Email.is_login DESC, Email.id ASC
		                                       LIMIT 1) AS email
		                               FROM users AS UserRow
		                               INNER JOIN people AS Person ON Person.id = UserRow.person_id
		                               WHERE UserRow.id = :id");
		$stmt->execute(['id' => $userId]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$user)
			{
			throw new \RuntimeException('Usuário não encontrado.');
			}

		$personId = (int) $user['person_id'];
		if (in_array($personId, Person::PROTECTED_PERSON_IDS, true))
			{
			throw new \RuntimeException('A pessoa associada é protegida e não pode ser excluída.');
			}

		$stmt = $this->db->prepare('SELECT id FROM users WHERE person_id = :person_id AND id <> :user_id ORDER BY id');
		$stmt->execute(['person_id' => $personId, 'user_id' => $userId]);
		if ($stmt->fetchColumn() !== false)
			{
			throw new \RuntimeException('A pessoa associada possui outro usuário e não pode ser excluída por este fluxo.');
			}

		$central = $this->centralPlan($userId, $user);
		$modules = $central['modules'];
		$warnings = [];

		if ($central['preserved_support_chat_ids'] !== [])
			{
			$warnings[] = count($central['preserved_support_chat_ids'])
				. ' atendimento(s) de suporte serão preservados; apenas as referências de atendente/encerramento serão removidas.';
			}

		$tenantDatabases = [];
		foreach ($this->tenantSchemas() as $schema)
			{
			$databasePlan = $this->tenantDatabasePlan($schema, $userId, $personId);
			$tenantDatabases[] = $databasePlan;
			$modules = array_merge($modules, $databasePlan['modules']);

			if ($databasePlan['detached_schedule_event_ids'] !== [])
				{
				$warnings[] = count($databasePlan['detached_schedule_event_ids']) . ' evento(s) em ' . $schema
					. ' serão preservados e desvinculados das recorrências excluídas.';
				}

			if ($databasePlan['detached_task_ids'] !== [])
				{
				$warnings[] = count($databasePlan['detached_task_ids']) . ' tarefa(s) em ' . $schema
					. ' serão preservadas e terão a referência de terceiro removida.';
				}
			}

		return [
			'user' => $user,
			'central' => $central,
			'tenant_databases' => $tenantDatabases,
			'modules' => $modules,
			'warnings' => $warnings,
		];
		}

	/** @return array<string, mixed> */
	private function centralPlan(int $userId, array $user): array
		{
		$modules = [];

		$internalChats = $this->queryRows(
			"SELECT Chat.*
			 FROM internal_chats AS Chat
			 WHERE Chat.created_by_user_id = ?
			    OR (Chat.chat_type = 'direct' AND EXISTS (
			      SELECT 1 FROM internal_chat_participants AS Participant
			      WHERE Participant.internal_chat_id = Chat.id AND Participant.user_id = ?
			    ))
			 ORDER BY Chat.id",
			[$userId, $userId]
		);
		$internalChatIds = $this->ids($internalChats);
		$internalMessages = $this->queryRows(
			'SELECT * FROM internal_chat_messages WHERE sender_user_id = ?'
			. ($internalChatIds !== [] ? ' OR internal_chat_id IN (' . $this->placeholders($internalChatIds) . ')' : '')
			. ' ORDER BY id',
			array_merge([$userId], $internalChatIds)
		);
		$participantSql = 'SELECT Participant.*, UserRow.username FROM internal_chat_participants AS Participant '
			. 'INNER JOIN users AS UserRow ON UserRow.id = Participant.user_id WHERE Participant.user_id = ?';
		if ($internalChatIds !== [])
			{
			$participantSql .= ' OR Participant.internal_chat_id IN (' . $this->placeholders($internalChatIds) . ')';
			}
		$participantSql .= ' ORDER BY Participant.internal_chat_id, Participant.user_id';
		$internalParticipants = $this->queryRows($participantSql, array_merge([$userId], $internalChatIds));
		$internalRecords = [];
		foreach ($internalMessages as $row)
			{
			$internalRecords[] = $this->record('internal_chat_messages', $row['id'], 'Mensagem interna #' . $row['id'] . ' — chat #' . $row['internal_chat_id'] . ' | remetente: ' . $row['sender_name']);
			}
		foreach ($internalParticipants as $row)
			{
			$internalRecords[] = $this->record('internal_chat_participants', $row['internal_chat_id'] . ':' . $row['user_id'], 'Participante do chat #' . $row['internal_chat_id'] . ' — ' . $row['username']);
			}
		foreach ($internalChats as $row)
			{
			$internalRecords[] = $this->record('internal_chats', $row['id'], 'Chat interno #' . $row['id'] . ' — ' . ($row['title'] ?: $row['conversation_key']));
			}
		$this->appendModule($modules, 'manager_main', 'Chats internos', $internalRecords);

		$supportChats = $this->queryRows('SELECT * FROM support_chats WHERE customer_user_id = ? ORDER BY id', [$userId]);
		$supportChatIds = $this->ids($supportChats);
		$supportMessages = $this->queryRows(
			'SELECT * FROM support_chats_messages WHERE sender_user_id = ?'
			. ($supportChatIds !== [] ? ' OR support_chat_id IN (' . $this->placeholders($supportChatIds) . ')' : '')
			. ' ORDER BY id',
			array_merge([$userId], $supportChatIds)
		);
		$preservedSupportRows = $this->queryRows(
			'SELECT id FROM support_chats WHERE customer_user_id <> ? AND (attendant_user_id = ? OR closed_by_user_id = ?) ORDER BY id',
			[$userId, $userId, $userId]
		);
		$supportRecords = [];
		foreach ($supportMessages as $row)
			{
			$supportRecords[] = $this->record('support_chats_messages', $row['id'], 'Mensagem de suporte #' . $row['id'] . ' — atendimento #' . $row['support_chat_id'] . ' | remetente: ' . $row['sender_name']);
			}
		foreach ($supportChats as $row)
			{
			$supportRecords[] = $this->record('support_chats', $row['id'], 'Atendimento #' . $row['id'] . ' — tenant #' . $row['tenant_id'] . ' | status: ' . $row['status']);
			}
		$this->appendModule($modules, 'manager_main', 'Atendimento de suporte', $supportRecords);

		$operations = $this->queryRows('SELECT * FROM financial_operations WHERE user_id = ? ORDER BY id', [$userId]);
		$operationIds = $this->ids($operations);
		$payments = $operationIds === [] ? [] : $this->queryRows(
			'SELECT * FROM financial_operations_payments WHERE financial_operation_id IN (' . $this->placeholders($operationIds) . ') ORDER BY id',
			$operationIds
		);
		$financialRecords = [];
		foreach ($payments as $row)
			{
			$financialRecords[] = $this->record('financial_operations_payments', $row['id'], 'Pagamento #' . $row['id'] . ' — operação #' . $row['financial_operation_id'] . ' | valor: ' . $row['value']);
			}
		foreach ($operations as $row)
			{
			$financialRecords[] = $this->record('financial_operations', $row['id'], 'Operação financeira #' . $row['id'] . ' — ' . $row['type'] . ' | valor: ' . $row['total_value']);
			}
		$this->appendModule($modules, 'manager_main', 'Financeiro central', $financialRecords);

		$permissions = $this->queryRows("SELECT UserPermission.user_id, UserPermission.permission_id, PermissionCatalog.system, PermissionCatalog.ability
		                                      FROM user_permissions AS UserPermission
		                                      INNER JOIN permissions AS PermissionCatalog ON PermissionCatalog.id = UserPermission.permission_id
		                                      WHERE UserPermission.user_id = ?
		                                      ORDER BY PermissionCatalog.system, PermissionCatalog.ability", [$userId]);
		$tokens = $this->queryRows('SELECT * FROM user_remember_tokens WHERE user_id = ? ORDER BY id', [$userId]);
		$links = $this->queryRows('SELECT TenantUser.*, Tenant.database_number FROM tenant_users AS TenantUser INNER JOIN tenants AS Tenant ON Tenant.id = TenantUser.tenant_id WHERE TenantUser.user_id = ? ORDER BY TenantUser.tenant_id', [$userId]);
		$presences = $this->queryRows('SELECT * FROM user_presences WHERE user_id = ? ORDER BY id', [$userId]);
		$accessRecords = [];
		foreach ($permissions as $row)
			{
			$accessRecords[] = $this->record('user_permissions', $row['user_id'] . ':' . $row['permission_id'], 'Permissão ' . $row['system'] . '.' . $row['ability']);
			}
		foreach ($tokens as $row)
			{
			$accessRecords[] = $this->record('user_remember_tokens', $row['id'], 'Sessão persistente #' . $row['id'] . ' — expira: ' . $row['expires_at']);
			}
		foreach ($presences as $row)
			{
			$accessRecords[] = $this->record('user_presences', $row['id'], 'Presence #' . $row['id'] . ' - status: ' . $row['status']);
			}
		foreach ($links as $row)
			{
			$accessRecords[] = $this->record('tenant_users', $row['tenant_id'] . ':' . $userId, 'Vínculo com manager_' . $row['database_number'] . ' — perfil: ' . $row['role']);
			}
		$this->appendModule($modules, 'manager_main', 'Acessos, sessões e vínculos', $accessRecords);

		$logs = $this->queryRows('SELECT * FROM logs WHERE performed_by_user_id = ? ORDER BY id', [$userId]);
		$logRecords = [];
		foreach ($logs as $row)
			{
			$logRecords[] = $this->record('logs', $row['id'], 'Log #' . $row['id'] . ' — ' . $row['module'] . '.' . $row['action'] . ' | ' . $row['entity_name'] . ' #' . ($row['entity_id'] ?? '-'));
			}
		$this->appendModule($modules, 'manager_main', 'Logs executados pelo usuário', $logRecords);

		$this->appendModule($modules, 'manager_main', 'Cadastro do usuário', [
			$this->record('users', $userId, 'Usuário #' . $userId . ' — ' . $user['name'] . ' (' . $user['username'] . ')'),
		]);

		$addresses = $this->queryRows('SELECT * FROM addresses WHERE person_id = ? ORDER BY id', [(int) $user['person_id']]);
		$emails = $this->queryRows('SELECT * FROM emails WHERE person_id = ? ORDER BY id', [(int) $user['person_id']]);
		$phoneNumbers = $this->queryRows('SELECT * FROM phone_numbers WHERE person_id = ? ORDER BY id', [(int) $user['person_id']]);
		$personRecords = [];
		foreach ($addresses as $row)
			{
			$personRecords[] = $this->record('addresses', $row['id'], 'Endereço #' . $row['id'] . ' — ' . $row['street'] . ', ' . $row['number']);
			}
		foreach ($emails as $row)
			{
			$personRecords[] = $this->record('emails', $row['id'], 'E-mail #' . $row['id'] . ' — ' . $row['email']);
			}
		foreach ($phoneNumbers as $row)
			{
			$personRecords[] = $this->record('phone_numbers', $row['id'], 'Telefone #' . $row['id'] . ' — ' . $row['phone_number']);
			}
		$personRecords[] = $this->record('people', $user['person_id'], 'Pessoa #' . $user['person_id'] . ' — ' . $user['name'] . ' | documento: ' . $user['document']);
		$this->appendModule($modules, 'manager_main', 'Pessoa e contatos', $personRecords);

		$deletedInternalMessageIds = $this->ids($internalMessages);
		$deletedSupportMessageIds = $this->ids($supportMessages);
		$preservedInternalChatIds = $this->distinctIdsFromRows($internalMessages, 'internal_chat_id', $internalChatIds);
		$preservedSupportChatIds = $this->distinctIdsFromRows($supportMessages, 'support_chat_id', $supportChatIds);

		return [
			'modules' => $modules,
			'internal_chat_ids' => $internalChatIds,
			'internal_message_ids' => $deletedInternalMessageIds,
			'preserved_internal_chat_ids' => $preservedInternalChatIds,
			'support_chat_ids' => $supportChatIds,
			'support_message_ids' => $deletedSupportMessageIds,
			'preserved_support_chat_ids' => array_values(array_unique(array_merge(
				$this->ids($preservedSupportRows),
				$preservedSupportChatIds
			))),
			'financial_operation_ids' => $operationIds,
			'financial_payment_ids' => $this->ids($payments),
			'log_ids' => $this->ids($logs),
			'address_ids' => $this->ids($addresses),
			'email_ids' => $this->ids($emails),
			'phone_number_ids' => $this->ids($phoneNumbers),
		];
		}

	/** @return array<string, mixed> */
	private function tenantDatabasePlan(string $schema, int $userId, int $personId): array
		{
		$columns = $this->schemaColumns($schema);
		$modules = [];

		$products = $this->rowsWhenAvailable($schema, $columns, 'products', ['id', 'user_id'], 'user_id = ?', [$userId]);
		$productIds = $this->ids($products);
		$productImages = $this->rowsByParentIds($schema, $columns, 'product_images', 'product_id', $productIds);
		$productMovements = $this->rowsByParentIds($schema, $columns, 'product_stocks_movements', 'product_id', $productIds);
		$productRecords = [];
		foreach ($productMovements as $row)
			{
			$productRecords[] = $this->record('product_stocks_movements', $row['id'], 'Movimento de estoque #' . $row['id'] . ' — produto #' . $row['product_id'] . ' | ' . ($row['movement_type'] ?? ''));
			}
		foreach ($productImages as $row)
			{
			$productRecords[] = $this->record('product_images', $row['id'], 'Imagem #' . $row['id'] . ' — produto #' . $row['product_id'] . ' | ' . ($row['original_name'] ?? $row['file_path'] ?? ''));
			}
		foreach ($products as $row)
			{
			$productRecords[] = $this->record('products', $row['id'], 'Produto #' . $row['id'] . ' — ' . ($row['name'] ?? '') . (!empty($row['sku']) ? ' | SKU: ' . $row['sku'] : ''));
			}
		$this->appendModule($modules, $schema, 'Produtos e estoque', $productRecords);

		$tasks = $this->rowsWhenAvailable($schema, $columns, 'tasks', ['id', 'created_by_user_id'], 'created_by_user_id = ?', [$userId]);
		$taskIds = $this->ids($tasks);
		$assignees = $this->rowsByUserOrParent($schema, $columns, 'task_assignees', 'person_id', $personId, 'task_id', $taskIds);
		$assigneeIds = $this->ids($assignees);
		$comments = $this->rowsByUserOrParent($schema, $columns, 'task_comments', 'user_id', $userId, 'task_id', $taskIds);
		$executions = $this->rowsByExecutionAssociation($schema, $columns, $userId, $assigneeIds);
		$detachedTasks = [];
		if ($this->hasColumns($columns, 'tasks', ['id', 'third_party_person_id']))
			{
			$where = 'third_party_person_id = ?';
			$params = [$personId];
			if ($taskIds !== [])
				{
				$where .= ' AND id NOT IN (' . $this->placeholders($taskIds) . ')';
				$params = array_merge($params, $taskIds);
				}
			$detachedTasks = $this->schemaRows($schema, 'tasks', $where, $params);
			}
		$taskRecords = [];
		foreach ($executions as $row)
			{
			$taskRecords[] = $this->record('task_executions', $row['id'], 'Execução de tarefa #' . $row['id'] . ' — responsável #' . $row['task_assignee_id']);
			}
		foreach ($comments as $row)
			{
			$taskRecords[] = $this->record('task_comments', $row['id'], 'Comentário #' . $row['id'] . ' — tarefa #' . $row['task_id']);
			}
		foreach ($assignees as $row)
			{
			$taskRecords[] = $this->record('task_assignees', $row['id'], 'Responsável #' . $row['id'] . ' — tarefa #' . $row['task_id'] . ' | pessoa #' . $row['person_id']);
			}
		foreach ($tasks as $row)
			{
			$taskRecords[] = $this->record('tasks', $row['id'], 'Tarefa #' . $row['id'] . ' — ' . ($row['title'] ?? '') . ' | status: ' . ($row['status'] ?? ''));
			}
		$this->appendModule($modules, $schema, 'Tarefas', $taskRecords);

		$recurrences = $this->rowsWhenAvailable($schema, $columns, 'schedule_events_recurrences', ['id', 'created_by_user_id'], 'created_by_user_id = ?', [$userId]);
		$recurrenceIds = $this->ids($recurrences);
		$events = $this->rowsWhenAvailable($schema, $columns, 'schedule_events', ['id', 'created_by_user_id'], 'created_by_user_id = ?', [$userId]);
		$eventIds = $this->ids($events);
		$participants = $this->rowsByUserOrParent($schema, $columns, 'schedule_events_participants', 'person_id', $personId, 'schedule_event_id', $eventIds);
		$detachedEvents = [];
		if ($recurrenceIds !== [] && $this->hasColumns($columns, 'schedule_events', ['id', 'schedule_events_recurrence_id']))
			{
			$sql = 'schedule_events_recurrence_id IN (' . $this->placeholders($recurrenceIds) . ')';
			$params = $recurrenceIds;
			if ($eventIds !== [])
				{
				$sql .= ' AND id NOT IN (' . $this->placeholders($eventIds) . ')';
				$params = array_merge($params, $eventIds);
				}
			$detachedEvents = $this->schemaRows($schema, 'schedule_events', $sql, $params);
			}
		$scheduleRecords = [];
		foreach ($participants as $row)
			{
			$scheduleRecords[] = $this->record('schedule_events_participants', $row['id'], 'Participante #' . $row['id'] . ' — evento #' . $row['schedule_event_id'] . ' | pessoa #' . $row['person_id']);
			}
		foreach ($events as $row)
			{
			$scheduleRecords[] = $this->record('schedule_events', $row['id'], 'Evento #' . $row['id'] . ' — ' . ($row['title'] ?? '') . ' | início: ' . ($row['starts_at'] ?? ''));
			}
		foreach ($recurrences as $row)
			{
			$scheduleRecords[] = $this->record('schedule_events_recurrences', $row['id'], 'Recorrência #' . $row['id'] . ' — ' . ($row['title'] ?? '') . ' | frequência: ' . ($row['frequency'] ?? ''));
			}
		$this->appendModule($modules, $schema, 'Agenda', $scheduleRecords);

		return [
			'schema' => $schema,
			'modules' => $modules,
			'product_ids' => $productIds,
			'product_image_ids' => $this->ids($productImages),
			'product_movement_ids' => $this->ids($productMovements),
			'task_ids' => $taskIds,
			'task_assignee_ids' => $assigneeIds,
			'task_comment_ids' => $this->ids($comments),
			'task_execution_ids' => $this->ids($executions),
			'detached_task_ids' => $this->ids($detachedTasks),
			'schedule_recurrence_ids' => $recurrenceIds,
			'schedule_event_ids' => $eventIds,
			'schedule_participant_ids' => $this->ids($participants),
			'detached_schedule_event_ids' => $this->ids($detachedEvents),
		];
		}

	/** @return array<int, string> */
	private function tenantSchemas(): array
		{
		$stmt = $this->db->query("SELECT SCHEMA_NAME
		                          FROM information_schema.SCHEMATA
		                          WHERE SCHEMA_NAME REGEXP '^manager_[0-9]{4}$'
		                          ORDER BY SCHEMA_NAME");
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
		}

	/** @return array<string, array<string, bool>> */
	private function schemaColumns(string $schema): array
		{
		$stmt = $this->db->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema');
		$stmt->execute(['schema' => $schema]);
		$columns = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
			}
		return $columns;
		}

	private function hasColumns(array $columns, string $table, array $required): bool
		{
		foreach ($required as $column)
			{
			if (empty($columns[$table][$column]))
				{
				return false;
				}
			}
		return true;
		}

	/** @return array<int, array<string, mixed>> */
	private function rowsWhenAvailable(string $schema, array $columns, string $table, array $required, string $where, array $params): array
		{
		return $this->hasColumns($columns, $table, $required) ? $this->schemaRows($schema, $table, $where, $params) : [];
		}

	/** @return array<int, array<string, mixed>> */
	private function rowsByParentIds(string $schema, array $columns, string $table, string $column, array $ids): array
		{
		if ($ids === [] || !$this->hasColumns($columns, $table, ['id', $column]))
			{
			return [];
			}
		return $this->schemaRows($schema, $table, $this->quoteIdentifier($column) . ' IN (' . $this->placeholders($ids) . ')', $ids);
		}

	/** @return array<int, array<string, mixed>> */
	private function rowsByUserOrParent(string $schema, array $columns, string $table, string $userColumn, int $userId, string $parentColumn, array $parentIds): array
		{
		if (!$this->hasColumns($columns, $table, ['id', $userColumn, $parentColumn]))
			{
			return [];
			}
		$where = $this->quoteIdentifier($userColumn) . ' = ?';
		$params = [$userId];
		if ($parentIds !== [])
			{
			$where .= ' OR ' . $this->quoteIdentifier($parentColumn) . ' IN (' . $this->placeholders($parentIds) . ')';
			$params = array_merge($params, $parentIds);
			}
		return $this->schemaRows($schema, $table, $where, $params);
		}

	/** @return array<int, array<string, mixed>> */
	private function rowsByExecutionAssociation(string $schema, array $columns, int $userId, array $assigneeIds): array
		{
		if (!$this->hasColumns($columns, 'task_executions', ['id', 'task_assignee_id', 'started_by_user_id', 'paused_by_user_id']))
			{
			return [];
			}
		$where = '(started_by_user_id = ? OR paused_by_user_id = ?)';
		$params = [$userId, $userId];
		if ($assigneeIds !== [])
			{
			$where .= ' OR task_assignee_id IN (' . $this->placeholders($assigneeIds) . ')';
			$params = array_merge($params, $assigneeIds);
			}
		return $this->schemaRows($schema, 'task_executions', $where, $params);
		}

	/** @return array<int, array<string, mixed>> */
	private function schemaRows(string $schema, string $table, string $where, array $params): array
		{
		$this->assertTenantSchema($schema);
		$this->assertIdentifier($table);
		$sql = 'SELECT * FROM ' . $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($table)
			. ' WHERE ' . $where . ' ORDER BY id';
		return $this->queryRows($sql, $params);
		}

	/** @return array<int, array<string, mixed>> */
	private function queryRows(string $sql, array $params = []): array
		{
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	private function deleteInternalParticipants(int $userId, array $chatIds): void
		{
		$sql = 'DELETE FROM internal_chat_participants WHERE user_id = ?';
		$params = [$userId];
		if ($chatIds !== [])
			{
			$sql .= ' OR internal_chat_id IN (' . $this->placeholders($chatIds) . ')';
			$params = array_merge($params, $chatIds);
			}
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		}

	private function clearSupportChatUserReferences(int $userId): void
		{
		$stmt = $this->db->prepare('UPDATE support_chats SET attendant_user_id = NULL WHERE attendant_user_id = :id');
		$stmt->execute(['id' => $userId]);
		$stmt = $this->db->prepare('UPDATE support_chats SET closed_by_user_id = NULL WHERE closed_by_user_id = :id');
		$stmt->execute(['id' => $userId]);
		}

	private function refreshLastMessageAt(string $chatTable, string $messageTable, string $foreignKey, array $chatIds): void
		{
		if ($chatIds === [])
			{
			return;
			}
		foreach ([$chatTable, $messageTable, $foreignKey] as $identifier)
			{
			$this->assertIdentifier($identifier);
			}
		$sql = 'UPDATE ' . $this->quoteIdentifier($chatTable) . ' AS ChatRow SET ChatRow.last_message_at = ('
			. 'SELECT MAX(MessageRow.created) FROM ' . $this->quoteIdentifier($messageTable) . ' AS MessageRow '
			. 'WHERE MessageRow.' . $this->quoteIdentifier($foreignKey) . ' = ChatRow.id) '
			. 'WHERE ChatRow.id IN (' . $this->placeholders($chatIds) . ')';
		$stmt = $this->db->prepare($sql);
		$stmt->execute($chatIds);
		}

	private function deleteWhereUserId(string $table, int $userId): void
		{
		$this->assertIdentifier($table);
		$stmt = $this->db->prepare('DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE user_id = :id');
		$stmt->execute(['id' => $userId]);
		}

	private function deleteIds(string $schema, string $table, array $ids): void
		{
		if ($ids === [])
			{
			return;
			}
		if ($schema !== 'manager_main')
			{
			$this->assertTenantSchema($schema);
			}
		$this->assertIdentifier($table);
		$sql = 'DELETE FROM ' . $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($table)
			. ' WHERE id IN (' . $this->placeholders($ids) . ')';
		$stmt = $this->db->prepare($sql);
		$stmt->execute($ids);
		}

	private function updateIdsToNull(string $schema, string $table, string $column, array $ids): void
		{
		if ($ids === [])
			{
			return;
			}
		$this->assertTenantSchema($schema);
		$this->assertIdentifier($table);
		$this->assertIdentifier($column);
		$sql = 'UPDATE ' . $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($table)
			. ' SET ' . $this->quoteIdentifier($column) . ' = NULL WHERE id IN (' . $this->placeholders($ids) . ')';
		$stmt = $this->db->prepare($sql);
		$stmt->execute($ids);
		}

	/** @return array<int, int> */
	private function ids(array $rows): array
		{
		return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['id'], $rows)));
		}

	/** @return array<int, int> */
	private function distinctIdsFromRows(array $rows, string $column, array $excluded): array
		{
		$ids = array_values(array_unique(array_map(static fn (array $row): int => (int) $row[$column], $rows)));
		return array_values(array_diff($ids, $excluded));
		}

	private function appendModule(array &$modules, string $scope, string $label, array $records): void
		{
		if ($records === [])
			{
			return;
			}
		$modules[] = ['scope' => $scope, 'label' => $label, 'count' => count($records), 'records' => $records];
		}

	/** @return array<string, string> */
	private function record(string $table, $id, string $label): array
		{
		return ['table' => $table, 'id' => (string) $id, 'label' => trim($label)];
		}

	private function placeholders(array $values): string
		{
		return implode(', ', array_fill(0, count($values), '?'));
		}

	private function assertTenantSchema(string $schema): void
		{
		if (!preg_match('/^manager_[0-9]{4}$/', $schema))
			{
			throw new \InvalidArgumentException('Base de tenant inválida.');
			}
		}

	private function assertIdentifier(string $identifier): void
		{
		if (!preg_match('/^[a-z_]+$/', $identifier))
			{
			throw new \InvalidArgumentException('Identificador SQL inválido.');
			}
		}

	private function quoteIdentifier(string $identifier): string
		{
		return '`' . str_replace('`', '``', $identifier) . '`';
		}
	}
