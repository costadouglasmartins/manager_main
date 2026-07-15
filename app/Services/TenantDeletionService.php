<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Tenant;
use PDO;

/**
 * Builds the tenant deletion manifest and executes the destructive cleanup.
 *
 * Global geographic/banking catalogs are never part of the deletion plan.
 * Users shared with another tenant are retained, but their permission grants
 * are removed as explicitly required by the tenant deletion workflow.
 */
class TenantDeletionService
	{
	public const PROTECTED_TENANT_ID = 1;

	private PDO $db;
	private TenantProvisioner $provisioner;

	public function __construct(?PDO $db = null, ?TenantProvisioner $provisioner = null)
		{
		$this->db = $db ?? Database::getInstance();
		$this->provisioner = $provisioner ?? new TenantProvisioner();
		}

	/**
	 * Return every database row that the confirmed operation will remove.
	 *
	 * @param int $tenantId Tenant ID.
	 * @return array<string, mixed>
	 */
	public function impact(int $tenantId): array
		{
		$plan = $this->buildPlan($tenantId);
		$databaseImpact = $this->provisioner->deletionImpact((string) $plan['tenant']['database_number']);
		$modules = array_merge($databaseImpact['modules'], $this->centralImpactModules($plan));
		$warnings = [];

		if ($plan['shared_user_ids'] !== [])
			{
			$warnings[] = count($plan['shared_user_ids'])
				. ' usuário(s) também possuem vínculo com outro tenant. Seus cadastros serão preservados, '
				. 'mas todas as suas atribuições de permissões serão removidas.';
			}

		if ($plan['retained_exclusive_user_ids'] !== [])
			{
			$warnings[] = count($plan['retained_exclusive_user_ids'])
				. ' usuário(s) possuem referências em outra base ou módulo. Esses cadastros serão preservados e desativados; '
				. 'suas permissões e sessões serão removidas.';
			}

		if (!$databaseImpact['exists'])
			{
			$warnings[] = 'A base ' . $databaseImpact['database'] . ' não foi encontrada. A limpeza do manager_main ainda será executada.';
			}

		return [
			'tenant' => [
				'id' => (int) $plan['tenant']['id'],
				'name' => (string) ($plan['tenant']['associated_person_name'] ?? ('Tenant #' . $tenantId)),
				'database' => $databaseImpact['database'],
			],
			'database' => [
				'name' => $databaseImpact['database'],
				'exists' => $databaseImpact['exists'],
				'table_count' => $databaseImpact['table_count'],
				'record_count' => $databaseImpact['record_count'],
			],
			'modules' => $modules,
			'total_records' => array_sum(array_map(static fn (array $module): int => (int) $module['count'], $modules)),
			'preserved' => ['Cidades', 'Bairros', 'Bancos', 'Estados', 'Países'],
			'warnings' => $warnings,
		];
		}

	/**
	 * Drop the tenant database and remove its central records.
	 *
	 * The database is dropped first. DROP DATABASE IF EXISTS makes a retry safe
	 * if the transactional cleanup in manager_main fails afterwards.
	 *
	 * @param int $tenantId Tenant ID.
	 * @return void
	 */
	public function delete(int $tenantId): void
		{
		$plan = $this->buildPlan($tenantId);
		$tenant = $plan['tenant'];
		$databaseName = 'manager_' . (string) $tenant['database_number'];
		$tenantModel = new Tenant();
		$beforeData = $tenantModel->findAllByTenantId($tenantId);

		$this->provisioner->dropDatabase((string) $tenant['database_number']);

		try
			{
			$this->db->beginTransaction();
			$exclusiveUserIds = array_values(array_unique(array_merge(
				$plan['deletable_user_ids'],
				$plan['retained_exclusive_user_ids']
			)));

			// Tenant-owned conversations and their cascading children.
			$stmt = $this->db->prepare('DELETE FROM internal_chats WHERE tenant_id = :tenant_id');
			$stmt->execute(['tenant_id' => $tenantId]);

			$stmt = $this->db->prepare('DELETE FROM support_chats WHERE tenant_id = :tenant_id');
			$stmt->execute(['tenant_id' => $tenantId]);

			// Remove old audit rows for both the live FK and detached database snapshot.
			$logSql = 'DELETE FROM logs WHERE tenant_id = ? OR tenant_database = ?';
			$logParams = [$tenantId, $databaseName];
			if ($exclusiveUserIds !== [])
				{
				$logSql .= ' OR performed_by_user_id IN (' . $this->placeholders($exclusiveUserIds) . ')';
				$logParams = array_merge($logParams, $exclusiveUserIds);
				}
			$stmt = $this->db->prepare($logSql);
			$stmt->execute($logParams);

			// Keep one detached audit event describing the destructive operation.
			(new AuditLogService($this->db))->logDelete('tenant', 'tenants', $tenantId, $beforeData, [
				'tenant_id' => $tenantId,
				'tenant_database' => $databaseName,
				'affected_tables' => [
					'tenants', 'tenant_users', 'users', 'user_permissions', 'user_remember_tokens',
					'people', 'addresses', 'emails', 'phone_numbers', 'financial_operations',
					'financial_operations_payments', 'internal_chats', 'internal_chat_messages',
					'internal_chat_participants', 'support_chats', 'support_chats_messages', 'logs',
					'webcrawler_ip_range_executions', 'webcrawler_ip_range_results',
				],
				'preserved_tables' => ['cities', 'neighborhoods', 'banks', 'states', 'countries'],
				'source' => 'tenant_full_deletion',
			]);

			// Explicit requirement: remove permissions and remembered sessions from
			// every associated user, including users shared with another tenant.
			$this->deleteWhereIn('user_permissions', 'user_id', $plan['associated_user_ids']);
			$this->deleteWhereIn('user_remember_tokens', 'user_id', $plan['associated_user_ids']);

			if ($exclusiveUserIds !== [])
				{
				$placeholders = $this->placeholders($exclusiveUserIds);
				$stmt = $this->db->prepare(
					"DELETE Payment
					 FROM financial_operations_payments AS Payment
					 INNER JOIN financial_operations AS OperationRow
					         ON OperationRow.id = Payment.financial_operation_id
					 WHERE OperationRow.user_id IN ({$placeholders})"
				);
				$stmt->execute($exclusiveUserIds);

				$this->deleteWhereIn('financial_operations', 'user_id', $exclusiveUserIds);

				$stmt = $this->db->prepare(
					"DELETE ResultRow
					 FROM webcrawler_ip_range_results AS ResultRow
					 LEFT JOIN webcrawler_ip_range_executions AS ExecutionRow
					        ON ExecutionRow.id = ResultRow.execution_id
					 WHERE ResultRow.user_id IN ({$placeholders})
					    OR ExecutionRow.user_id IN ({$placeholders})"
				);
				$stmt->execute(array_merge($exclusiveUserIds, $exclusiveUserIds));
				$this->deleteWhereIn('webcrawler_ip_range_executions', 'user_id', $exclusiveUserIds);
				}

			// Remove the target link before selecting a possible remaining primary tenant.
			$stmt = $this->db->prepare('DELETE FROM tenant_users WHERE tenant_id = :tenant_id');
			$stmt->execute(['tenant_id' => $tenantId]);

			$stmt = $this->db->prepare("UPDATE users AS UserRow
			                            SET UserRow.tenant_id = (
			                              SELECT TenantUser.tenant_id
			                              FROM tenant_users AS TenantUser
			                              WHERE TenantUser.user_id = UserRow.id
			                              ORDER BY TenantUser.role = 'owner' DESC,
			                                       TenantUser.role = 'admin' DESC,
			                                       TenantUser.tenant_id ASC
			                              LIMIT 1
			                            ),
			                                UserRow.modified = :modified
			                            WHERE UserRow.tenant_id = :tenant_id");
			$stmt->execute(['modified' => date('Y-m-d H:i:s'), 'tenant_id' => $tenantId]);

			if ($plan['retained_exclusive_user_ids'] !== [])
				{
				$placeholders = $this->placeholders($plan['retained_exclusive_user_ids']);
				$stmt = $this->db->prepare(
					"UPDATE users SET active = 0, tenant_id = NULL, modified = ? WHERE id IN ({$placeholders})"
				);
				$stmt->execute(array_merge([date('Y-m-d H:i:s')], $plan['retained_exclusive_user_ids']));
				}

			$this->deleteWhereIn('users', 'id', $plan['deletable_user_ids']);
			$this->deleteWhereIn('addresses', 'person_id', $plan['deletable_person_ids']);
			$this->deleteWhereIn('emails', 'person_id', $plan['deletable_person_ids']);
			$this->deleteWhereIn('phone_numbers', 'person_id', $plan['deletable_person_ids']);
			$this->deleteWhereIn('people', 'id', $plan['deletable_person_ids']);

			$stmt = $this->db->prepare('DELETE FROM tenants WHERE id = :tenant_id');
			$stmt->execute(['tenant_id' => $tenantId]);
			if ($stmt->rowCount() !== 1)
				{
				throw new \RuntimeException('O tenant não foi removido do manager_main.');
				}

			$this->db->commit();
			}
		catch (\Throwable $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}
			throw $e;
			}
		}

	/**
	 * Resolve shared/exclusive users and people that can be deleted safely.
	 *
	 * @param int $tenantId Tenant ID.
	 * @return array<string, mixed>
	 */
	private function buildPlan(int $tenantId): array
		{
		if ($tenantId === self::PROTECTED_TENANT_ID)
			{
			throw new \RuntimeException('O tenant principal não pode ser excluído.');
			}

		$tenantModel = new Tenant();
		$tenant = $tenantModel->find($tenantId);
		if (!$tenant)
			{
			throw new \RuntimeException('Tenant não encontrado.');
			}

		$stmt = $this->db->prepare("SELECT DISTINCT UserRow.id,
			                                      UserRow.person_id,
			                                      UserRow.username,
			                                      UserRow.type,
			                                      UserRow.active,
			                                      UserRow.tenant_id,
			                                      Person.name AS person_name,
			                                      TargetLink.role AS tenant_role,
			                                      CASE
			                                        WHEN UserRow.type = 'root' THEN 1
			                                        WHEN UserRow.tenant_id IS NOT NULL AND UserRow.tenant_id <> :tenant_primary THEN 1
			                                        WHEN EXISTS (
			                                          SELECT 1 FROM tenant_users AS OtherLink
			                                          WHERE OtherLink.user_id = UserRow.id
			                                            AND OtherLink.tenant_id <> :tenant_other
			                                        ) THEN 1
			                                        ELSE 0
			                                      END AS is_shared
			                       FROM users AS UserRow
			                       INNER JOIN people AS Person ON Person.id = UserRow.person_id
			                       LEFT JOIN tenant_users AS TargetLink
			                              ON TargetLink.user_id = UserRow.id
			                             AND TargetLink.tenant_id = :tenant_link
			                       WHERE TargetLink.user_id IS NOT NULL
			                          OR UserRow.tenant_id = :tenant_user");
		$stmt->execute([
			'tenant_primary' => $tenantId,
			'tenant_other' => $tenantId,
			'tenant_link' => $tenantId,
			'tenant_user' => $tenantId,
		]);
		$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$associatedUserIds = array_map(static fn (array $user): int => (int) $user['id'], $users);
		$sharedUserIds = [];
		$exclusiveCandidateIds = [];
		foreach ($users as $user)
			{
			if ((int) $user['is_shared'] === 1)
				{
				$sharedUserIds[] = (int) $user['id'];
				}
			else
				{
				$exclusiveCandidateIds[] = (int) $user['id'];
				}
			}

		$databaseName = 'manager_' . (string) $tenant['database_number'];
		$externalUserIds = array_values(array_unique(array_merge(
			$this->externalReferenceIds('users', $exclusiveCandidateIds, $databaseName),
			$this->centralUserReferenceIds($exclusiveCandidateIds, $tenantId)
		)));
		$deletableUserIds = array_values(array_diff($exclusiveCandidateIds, $externalUserIds));
		$retainedExclusiveUserIds = array_values(array_intersect($exclusiveCandidateIds, $externalUserIds));

		$personIds = [];
		$userByPerson = [];
		foreach ($users as $user)
			{
			$personId = (int) $user['person_id'];
			$personIds[] = $personId;
			}

		if ($personIds !== [])
			{
			$personIds = array_values(array_unique($personIds));
			$stmt = $this->db->prepare('SELECT id, person_id FROM users WHERE person_id IN (' . $this->placeholders($personIds) . ')');
			$stmt->execute($personIds);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $personUser)
				{
				$userByPerson[(int) $personUser['person_id']][] = (int) $personUser['id'];
				}
			}

		$potentialPersonIds = [];
		foreach ($userByPerson as $personId => $personUserIds)
			{
			if (array_diff($personUserIds, $deletableUserIds) === [])
				{
				$potentialPersonIds[] = (int) $personId;
				}
			}

		$externallyReferencedPeople = $this->externalReferenceIds('people', $potentialPersonIds, $databaseName);
		$deletablePersonIds = array_values(array_diff($potentialPersonIds, $externallyReferencedPeople));

		return [
			'tenant' => $tenant,
			'users' => $users,
			'associated_user_ids' => array_values(array_unique($associatedUserIds)),
			'shared_user_ids' => array_values(array_unique($sharedUserIds)),
			'deletable_user_ids' => $deletableUserIds,
			'retained_exclusive_user_ids' => $retainedExclusiveUserIds,
			'deletable_person_ids' => $deletablePersonIds,
		];
		}

	/**
	 * Build the manager_main portion of the row-by-row manifest.
	 *
	 * @param array<string, mixed> $plan Deletion plan.
	 * @return array<int, array<string, mixed>>
	 */
	private function centralImpactModules(array $plan): array
		{
		$tenantId = (int) $plan['tenant']['id'];
		$databaseName = 'manager_' . (string) $plan['tenant']['database_number'];
		$exclusiveIds = array_values(array_unique(array_merge($plan['deletable_user_ids'], $plan['retained_exclusive_user_ids'])));
		$modules = [];

		$internalRecords = [];
		$stmt = $this->db->prepare('SELECT id, title, conversation_key FROM internal_chats WHERE tenant_id = :tenant ORDER BY id');
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$description = trim((string) ($row['title'] ?: $row['conversation_key']));
			$internalRecords[] = $this->record('internal_chats', $row['id'], 'Chat interno #' . $row['id'] . ($description !== '' ? ' — ' . $description : ''));
			}
		$stmt = $this->db->prepare("SELECT Message.id, Message.internal_chat_id, Message.sender_name
		                            FROM internal_chat_messages AS Message
		                            INNER JOIN internal_chats AS Chat ON Chat.id = Message.internal_chat_id
		                            WHERE Chat.tenant_id = :tenant ORDER BY Message.id");
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$internalRecords[] = $this->record('internal_chat_messages', $row['id'], 'Mensagem interna #' . $row['id'] . ' — chat #' . $row['internal_chat_id'] . ' | remetente: ' . $row['sender_name']);
			}
		$stmt = $this->db->prepare("SELECT Participant.internal_chat_id, Participant.user_id, UserRow.username
		                            FROM internal_chat_participants AS Participant
		                            INNER JOIN internal_chats AS Chat ON Chat.id = Participant.internal_chat_id
		                            INNER JOIN users AS UserRow ON UserRow.id = Participant.user_id
		                            WHERE Chat.tenant_id = :tenant
		                            ORDER BY Participant.internal_chat_id, Participant.user_id");
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$id = $row['internal_chat_id'] . ':' . $row['user_id'];
			$internalRecords[] = $this->record('internal_chat_participants', $id, 'Participante — chat #' . $row['internal_chat_id'] . ' | usuário: ' . $row['username']);
			}
		$this->appendModule($modules, 'manager_main', 'Chats internos', $internalRecords);

		$supportRecords = [];
		$stmt = $this->db->prepare('SELECT id, status, customer_user_id FROM support_chats WHERE tenant_id = :tenant ORDER BY id');
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$supportRecords[] = $this->record('support_chats', $row['id'], 'Atendimento #' . $row['id'] . ' — status: ' . $row['status'] . ' | cliente #' . $row['customer_user_id']);
			}
		$stmt = $this->db->prepare("SELECT Message.id, Message.support_chat_id, Message.sender_name
		                            FROM support_chats_messages AS Message
		                            INNER JOIN support_chats AS Chat ON Chat.id = Message.support_chat_id
		                            WHERE Chat.tenant_id = :tenant ORDER BY Message.id");
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$supportRecords[] = $this->record('support_chats_messages', $row['id'], 'Mensagem de suporte #' . $row['id'] . ' — atendimento #' . $row['support_chat_id'] . ' | remetente: ' . $row['sender_name']);
			}
		$this->appendModule($modules, 'manager_main', 'Atendimento de suporte', $supportRecords);

		$logRecords = [];
		$logSql = 'SELECT id, module, action, entity_name, entity_id FROM logs WHERE tenant_id = ? OR tenant_database = ?';
		$logParams = [$tenantId, $databaseName];
		if ($exclusiveIds !== [])
			{
			$logSql .= ' OR performed_by_user_id IN (' . $this->placeholders($exclusiveIds) . ')';
			$logParams = array_merge($logParams, $exclusiveIds);
			}
		$logSql .= ' ORDER BY id';
		$stmt = $this->db->prepare($logSql);
		$stmt->execute($logParams);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$logRecords[] = $this->record('logs', $row['id'], 'Log #' . $row['id'] . ' — ' . $row['module'] . '.' . $row['action'] . ' | ' . $row['entity_name'] . ' #' . ($row['entity_id'] ?? '-'));
			}
		$this->appendModule($modules, 'manager_main', 'Logs do tenant', $logRecords);

		$linkRecords = [];
		$stmt = $this->db->prepare("SELECT TenantUser.user_id, TenantUser.role, UserRow.username, Person.name
		                            FROM tenant_users AS TenantUser
		                            INNER JOIN users AS UserRow ON UserRow.id = TenantUser.user_id
		                            INNER JOIN people AS Person ON Person.id = UserRow.person_id
		                            WHERE TenantUser.tenant_id = :tenant ORDER BY TenantUser.user_id");
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$id = $tenantId . ':' . $row['user_id'];
			$linkRecords[] = $this->record('tenant_users', $id, 'Vínculo — ' . $row['name'] . ' (' . $row['username'] . ') | perfil: ' . $row['role']);
			}
		$this->appendModule($modules, 'manager_main', 'Vínculos de usuários', $linkRecords);

		$permissionRecords = [];
		if ($plan['associated_user_ids'] !== [])
			{
			$stmt = $this->db->prepare("SELECT UserPermission.user_id,
			                                  UserPermission.permission_id,
			                                  UserRow.username,
			                                  PermissionCatalog.system,
			                                  PermissionCatalog.ability,
			                                  PermissionCatalog.description
			                           FROM user_permissions AS UserPermission
			                           INNER JOIN users AS UserRow ON UserRow.id = UserPermission.user_id
			                           INNER JOIN permissions AS PermissionCatalog ON PermissionCatalog.id = UserPermission.permission_id
			                           WHERE UserPermission.user_id IN (" . $this->placeholders($plan['associated_user_ids']) . ")
			                           ORDER BY UserRow.username, PermissionCatalog.system, PermissionCatalog.ability");
			$stmt->execute($plan['associated_user_ids']);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$id = $row['user_id'] . ':' . $row['permission_id'];
				$permissionRecords[] = $this->record('user_permissions', $id, 'Permissão ' . $row['system'] . '.' . $row['ability'] . ' — usuário: ' . $row['username']);
				}
			}
		$this->appendModule($modules, 'manager_main', 'Permissões dos usuários associados', $permissionRecords, true);

		$sessionRecords = [];
		if ($plan['associated_user_ids'] !== [])
			{
			$stmt = $this->db->prepare("SELECT Token.id, Token.user_id, Token.expires_at, UserRow.username
			                           FROM user_remember_tokens AS Token
			                           INNER JOIN users AS UserRow ON UserRow.id = Token.user_id
			                           WHERE Token.user_id IN (" . $this->placeholders($plan['associated_user_ids']) . ")
			                           ORDER BY Token.id");
			$stmt->execute($plan['associated_user_ids']);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$sessionRecords[] = $this->record('user_remember_tokens', $row['id'], 'Sessão persistente #' . $row['id'] . ' — usuário: ' . $row['username'] . ' | expira: ' . $row['expires_at']);
				}
			}
		$this->appendModule($modules, 'manager_main', 'Sessões persistentes', $sessionRecords);

		$financialRecords = [];
		if ($exclusiveIds !== [])
			{
			$stmt = $this->db->prepare("SELECT Payment.id, Payment.financial_operation_id, Payment.value, OperationRow.user_id
			                           FROM financial_operations_payments AS Payment
			                           INNER JOIN financial_operations AS OperationRow ON OperationRow.id = Payment.financial_operation_id
			                           WHERE OperationRow.user_id IN (" . $this->placeholders($exclusiveIds) . ") ORDER BY Payment.id");
			$stmt->execute($exclusiveIds);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$financialRecords[] = $this->record('financial_operations_payments', $row['id'], 'Pagamento central #' . $row['id'] . ' — operação #' . $row['financial_operation_id'] . ' | valor: ' . $row['value']);
				}

			$stmt = $this->db->prepare("SELECT id, type, total_value, user_id FROM financial_operations
			                           WHERE user_id IN (" . $this->placeholders($exclusiveIds) . ") ORDER BY id");
			$stmt->execute($exclusiveIds);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$financialRecords[] = $this->record('financial_operations', $row['id'], 'Operação central #' . $row['id'] . ' — ' . $row['type'] . ' | valor: ' . $row['total_value'] . ' | usuário #' . $row['user_id']);
				}
			}
		$this->appendModule($modules, 'manager_main', 'Financeiro central de usuários exclusivos', $financialRecords);

		$webcrawlerRecords = [];
		if ($exclusiveIds !== [])
			{
			$placeholders = $this->placeholders($exclusiveIds);
			$stmt = $this->db->prepare("SELECT DISTINCT ResultRow.id,
			                                  ResultRow.execution_id,
			                                  ResultRow.ip_address,
			                                  ResultRow.protocol,
			                                  ResultRow.port,
			                                  ResultRow.scan_status
			                           FROM webcrawler_ip_range_results AS ResultRow
			                           LEFT JOIN webcrawler_ip_range_executions AS ExecutionRow
			                                  ON ExecutionRow.id = ResultRow.execution_id
			                           WHERE ResultRow.user_id IN ({$placeholders})
			                              OR ExecutionRow.user_id IN ({$placeholders})
			                           ORDER BY ResultRow.id");
			$stmt->execute(array_merge($exclusiveIds, $exclusiveIds));
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$webcrawlerRecords[] = $this->record(
					'webcrawler_ip_range_results',
					$row['id'],
					'Resultado do webcrawler #' . $row['id'] . ' — execução #' . ($row['execution_id'] ?? '-')
					. ' | ' . $row['protocol'] . '://' . $row['ip_address'] . ':' . $row['port']
					. ' | status: ' . $row['scan_status']
				);
				}

			$stmt = $this->db->prepare("SELECT id, start_ip, end_ip, status, processed_total
			                           FROM webcrawler_ip_range_executions
			                           WHERE user_id IN ({$placeholders}) ORDER BY id");
			$stmt->execute($exclusiveIds);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$webcrawlerRecords[] = $this->record(
					'webcrawler_ip_range_executions',
					$row['id'],
					'Execução do webcrawler #' . $row['id'] . ' — ' . $row['start_ip'] . ' até ' . $row['end_ip']
					. ' | status: ' . $row['status'] . ' | processados: ' . $row['processed_total']
				);
				}
			}
		$this->appendModule($modules, 'manager_main', 'Webcrawler de IPs de usuários exclusivos', $webcrawlerRecords);

		$userRecords = [];
		foreach ($plan['users'] as $user)
			{
			if (in_array((int) $user['id'], $plan['deletable_user_ids'], true))
				{
				$userRecords[] = $this->record('users', $user['id'], 'Usuário #' . $user['id'] . ' — ' . $user['person_name'] . ' (' . $user['username'] . ')');
				}
			}
		$this->appendModule($modules, 'manager_main', 'Usuários exclusivos', $userRecords);

		$personRecords = $this->personImpactRecords($plan['deletable_person_ids']);
		$this->appendModule($modules, 'manager_main', 'Pessoas e contatos exclusivos', $personRecords);

		$tenantLabel = 'Tenant #' . $tenantId . ' — base ' . $databaseName;
		$this->appendModule($modules, 'manager_main', 'Cadastro do tenant', [
			$this->record('tenants', $tenantId, $tenantLabel),
		]);

		return $modules;
		}

	/**
	 * List people and direct child rows selected for deletion.
	 *
	 * @param array<int, int> $personIds Person IDs.
	 * @return array<int, array<string, string>>
	 */
	private function personImpactRecords(array $personIds): array
		{
		if ($personIds === [])
			{
			return [];
			}

		$records = [];
		$stmt = $this->db->prepare('SELECT id, name, document FROM people WHERE id IN (' . $this->placeholders($personIds) . ') ORDER BY id');
		$stmt->execute($personIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$records[] = $this->record('people', $row['id'], 'Pessoa #' . $row['id'] . ' — ' . $row['name'] . ($row['document'] ? ' | documento: ' . $row['document'] : ''));
			}

		$stmt = $this->db->prepare('SELECT id, person_id, street, number FROM addresses WHERE person_id IN (' . $this->placeholders($personIds) . ') ORDER BY id');
		$stmt->execute($personIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$records[] = $this->record('addresses', $row['id'], 'Endereço #' . $row['id'] . ' — pessoa #' . $row['person_id'] . ' | ' . $row['street'] . ', ' . $row['number']);
			}

		$stmt = $this->db->prepare('SELECT id, person_id, email FROM emails WHERE person_id IN (' . $this->placeholders($personIds) . ') ORDER BY id');
		$stmt->execute($personIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$records[] = $this->record('emails', $row['id'], 'E-mail #' . $row['id'] . ' — pessoa #' . $row['person_id'] . ' | ' . $row['email']);
			}

		$stmt = $this->db->prepare('SELECT id, person_id, phone_number FROM phone_numbers WHERE person_id IN (' . $this->placeholders($personIds) . ') ORDER BY id');
		$stmt->execute($personIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$records[] = $this->record('phone_numbers', $row['id'], 'Telefone #' . $row['id'] . ' — pessoa #' . $row['person_id'] . ' | ' . $row['phone_number']);
			}

		return $records;
		}

	/**
	 * Find IDs referenced by another tenant database.
	 *
	 * @param string $referencedTable Central table name (users or people).
	 * @param array<int, int> $ids Candidate IDs.
	 * @param string $excludedDatabase Database being deleted.
	 * @return array<int, int>
	 */
	private function externalReferenceIds(string $referencedTable, array $ids, string $excludedDatabase): array
		{
		if ($ids === [])
			{
			return [];
			}

		$stmt = $this->db->prepare("SELECT DISTINCT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME
		                            FROM information_schema.KEY_COLUMN_USAGE
		                            WHERE REFERENCED_TABLE_SCHEMA = 'manager_main'
		                              AND REFERENCED_TABLE_NAME = :referenced_table
		                              AND TABLE_SCHEMA REGEXP '^manager_[0-9]{4}$'
		                              AND TABLE_SCHEMA <> :excluded_database");
		$stmt->execute(['referenced_table' => $referencedTable, 'excluded_database' => $excludedDatabase]);
		$references = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$found = [];

		foreach ($references as $reference)
			{
			try
				{
				$sql = 'SELECT DISTINCT ' . $this->quoteIdentifier($reference['COLUMN_NAME'])
					. ' FROM ' . $this->quoteIdentifier($reference['TABLE_SCHEMA']) . '.' . $this->quoteIdentifier($reference['TABLE_NAME'])
					. ' WHERE ' . $this->quoteIdentifier($reference['COLUMN_NAME']) . ' IN (' . $this->placeholders($ids) . ')';
				$referenceStmt = $this->db->prepare($sql);
				$referenceStmt->execute($ids);
				foreach ($referenceStmt->fetchAll(PDO::FETCH_COLUMN) as $id)
					{
					$found[] = (int) $id;
					}
				}
			catch (\Throwable $e)
				{
				// A metadata race must never make deletion less conservative.
				return $ids;
				}
			}

		return array_values(array_unique($found));
		}

	/**
	 * Find associated users referenced by central chats belonging to another tenant.
	 *
	 * @param array<int, int> $ids Candidate user IDs.
	 * @param int $tenantId Tenant being deleted.
	 * @return array<int, int>
	 */
	private function centralUserReferenceIds(array $ids, int $tenantId): array
		{
		if ($ids === [])
			{
			return [];
			}

		$placeholders = $this->placeholders($ids);
		$sql = "SELECT UserRow.id
		        FROM users AS UserRow
		        WHERE UserRow.id IN ({$placeholders})
		          AND (
		            EXISTS (SELECT 1 FROM internal_chats AS Chat WHERE Chat.tenant_id <> ? AND Chat.created_by_user_id = UserRow.id)
		            OR EXISTS (SELECT 1 FROM internal_chat_messages AS Message INNER JOIN internal_chats AS Chat ON Chat.id = Message.internal_chat_id WHERE Chat.tenant_id <> ? AND Message.sender_user_id = UserRow.id)
		            OR EXISTS (SELECT 1 FROM internal_chat_participants AS Participant INNER JOIN internal_chats AS Chat ON Chat.id = Participant.internal_chat_id WHERE Chat.tenant_id <> ? AND Participant.user_id = UserRow.id)
		            OR EXISTS (SELECT 1 FROM support_chats AS Chat WHERE Chat.tenant_id <> ? AND (Chat.customer_user_id = UserRow.id OR Chat.attendant_user_id = UserRow.id OR Chat.closed_by_user_id = UserRow.id))
		            OR EXISTS (SELECT 1 FROM support_chats_messages AS Message INNER JOIN support_chats AS Chat ON Chat.id = Message.support_chat_id WHERE Chat.tenant_id <> ? AND Message.sender_user_id = UserRow.id)
		          )";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_merge($ids, [$tenantId, $tenantId, $tenantId, $tenantId, $tenantId]));
		return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		}

	/**
	 * Append a non-empty module, or a required empty module such as permissions.
	 *
	 * @param array<int, array<string, mixed>> $modules Module accumulator.
	 * @param string $scope Database scope.
	 * @param string $label Module label.
	 * @param array<int, array<string, string>> $records Records.
	 * @param bool $includeWhenEmpty Whether the module must remain visible at zero.
	 * @return void
	 */
	private function appendModule(array &$modules, string $scope, string $label, array $records, bool $includeWhenEmpty = false): void
		{
		if ($records === [] && !$includeWhenEmpty)
			{
			return;
			}

		$modules[] = [
			'scope' => $scope,
			'label' => $label,
			'count' => count($records),
			'records' => $records,
		];
		}

	/**
	 * Normalize one manifest record.
	 *
	 * @param string $table Table name.
	 * @param mixed $id Record identifier.
	 * @param string $label User-facing row label.
	 * @return array<string, string>
	 */
	private function record(string $table, $id, string $label): array
		{
		return ['table' => $table, 'id' => (string) $id, 'label' => $label];
		}

	/**
	 * Delete rows using an integer IN list and validated identifiers.
	 *
	 * @param string $table Table name.
	 * @param string $column Column name.
	 * @param array<int, int> $ids IDs.
	 * @return void
	 */
	private function deleteWhereIn(string $table, string $column, array $ids): void
		{
		if ($ids === [])
			{
			return;
			}

		if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column))
			{
			throw new \InvalidArgumentException('Identificador SQL inválido.');
			}

		$sql = 'DELETE FROM ' . $this->quoteIdentifier($table)
			. ' WHERE ' . $this->quoteIdentifier($column) . ' IN (' . $this->placeholders($ids) . ')';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($ids));
		}

	/** @param array<int, mixed> $values */
	private function placeholders(array $values): string
		{
		return implode(', ', array_fill(0, count($values), '?'));
		}

	private function quoteIdentifier(string $identifier): string
		{
		return '`' . str_replace('`', '``', $identifier) . '`';
		}
	}

?>
