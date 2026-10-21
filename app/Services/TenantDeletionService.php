<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
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

	/**
	 * Direct manager_main tenant associations handled by this service.
	 *
	 * The runtime metadata gate blocks deletion when a new tenant locator column
	 * is created without being added to this inventory and to the manifest flow.
	 *
	 * @var array<int, string>
	 */
	private const REGISTERED_DIRECT_TENANT_ASSOCIATIONS = [
		'internal_chats.tenant_database_number',
		'internal_chats.tenant_id',
		'logs.tenant_database',
		'logs.tenant_id',
		'support_chats.tenant_database_number',
		'support_chats.tenant_id',
		'tenant_people.tenant_id',
		'tenant_setting_values.tenant_id',
		'tenant_users.tenant_id',
		'user_presences.tenant_id',
		'users.tenant_id',
	];

	/**
	 * Indirect associations owned by a central tenant_people link.
	 *
	 * Tenant schemas use a normalized manager_* scope so the same CRUD is
	 * registered once and validated across every active dedicated database.
	 *
	 * @var array<int, string>
	 */
	private const REGISTERED_TENANT_PERSON_ASSOCIATIONS = [
		'manager_*.crm_activities.tenant_person_id',
		'manager_*.crm_opportunities.tenant_person_id',
		'manager_main.crm_opportunities.tenant_person_id',
	];

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

		if ($plan['protected_user_ids'] !== [])
			{
			$warnings[] = count($plan['protected_user_ids'])
				. ' usuário(s) protegido(s) possuem vínculo com este tenant. O vínculo será removido, '
				. 'mas cadastro, permissões e sessões serão preservados.';
			}

		if ($plan['retained_exclusive_user_ids'] !== [])
			{
			$warnings[] = count($plan['retained_exclusive_user_ids'])
				. ' usuário(s) possuem referências em outra base ou módulo. Esses cadastros serão preservados e desativados; '
				. 'suas permissões e sessões serão removidas.';
			}

		if ($plan['retained_person_ids'] !== [])
			{
			$warnings[] = count($plan['retained_person_ids'])
				. ' pessoa(s) possuem usuário, vínculo ou referência fora deste tenant. '
				. 'Os cadastros globais serão preservados e somente as associações deste tenant serão removidas.';
			}

		if (!$databaseImpact['exists'])
			{
			$warnings[] = 'A base ' . $databaseImpact['database'] . ' não foi encontrada. A limpeza do manager_main ainda será executada.';
			}

		if ($databaseImpact['uncatalogued_tables'] !== [])
			{
			$warnings[] = 'Há tabela(s) ainda não catalogada(s) na base do tenant: '
				. implode(', ', $databaseImpact['uncatalogued_tables'])
				. '. Os registros aparecem de forma genérica; atualize TenantProvisioner::DATABASE_MODULES.';
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
		if ($this->db->inTransaction())
			{
			throw new \RuntimeException('A exclusão de tenant não pode iniciar dentro de outra transação.');
			}

		$initialPlan = $this->buildPlan($tenantId);
		$tenant = $initialPlan['tenant'];
		$databaseName = 'manager_' . (string) $tenant['database_number'];

		$plan = $initialPlan;
		$quarantineBatches = [];
		try
			{
			$this->db->beginTransaction();
			$this->lockTenant($tenantId);

			// Lock candidate aggregates, then rebuild once more so no stale plan can delete a new cross-tenant link.
			$lockPlan = $this->buildPlan($tenantId);
			$this->lockDeletionCandidates($lockPlan);
			$plan = $this->buildPlan($tenantId);
			$tenant = $plan['tenant'];
			$databaseName = 'manager_' . (string) $tenant['database_number'];

			// Keep the tenant row locked while validating and dropping the exact dedicated database.
			$this->provisioner->deletionImpact((string) $tenant['database_number']);
			$this->provisioner->dropDatabase((string) $tenant['database_number']);

			$tenantModel = new Tenant();
			$beforeData = $tenantModel->findAllByTenantId($tenantId);
			$deletableUserIds = $plan['deletable_user_ids'];

			// Tenant-owned conversations and their cascading children.
			$stmt = $this->db->prepare('DELETE FROM internal_chats WHERE tenant_id = :tenant_id');
			$stmt->execute(['tenant_id' => $tenantId]);

			$stmt = $this->db->prepare('DELETE FROM support_chats WHERE tenant_id = :tenant_id');
			$stmt->execute(['tenant_id' => $tenantId]);

			// Remove rows outside the dedicated database that still reference this tenant's CRM links.
			foreach ($plan['tenant_person_references'] as $reference)
				{
				$this->deleteScopedAssociation(
					$reference['scope'],
					$reference['table'],
					$reference['column'],
					$plan['tenant_person_ids']
				);
				}
			$this->deleteIdsByOwner('tenant_people', 'id', $plan['tenant_person_ids'], 'tenant_id', $tenantId);

			// Delete audit rows scoped to this tenant/database; actor history from other tenants must survive.
			$stmt = $this->db->prepare(
				'DELETE FROM logs WHERE tenant_id = :tenant_id OR tenant_database = :tenant_database'
			);
			$stmt->execute([
				'tenant_id' => $tenantId,
				'tenant_database' => $databaseName,
			]);

			// Keep one detached audit event describing the destructive operation.
			(new AuditLogService($this->db))->logDelete('tenant', 'tenants', $tenantId, $beforeData, [
				'tenant_id' => $tenantId,
				'tenant_database' => $databaseName,
				'affected_tables' => [
					'tenants', 'tenant_users', 'users', 'user_permissions', 'user_remember_tokens',
					'people', 'addresses', 'emails', 'phone_numbers', 'financial_operations',
					'financial_operations_payments', 'internal_chats', 'internal_chat_messages',
					'internal_chat_participants', 'support_chats', 'support_chats_messages', 'logs',
					'tenant_people', 'crm_activities', 'crm_opportunities',
					'tenant_setting_values', 'user_presences', 'tenant_files',
					'tenant_temporary_files', 'support_chat_typing',
				],
				'preserved_tables' => ['cities', 'neighborhoods', 'banks', 'states', 'countries'],
				'source' => 'tenant_full_deletion',
			]);

			// Explicit requirement: remove permissions and remembered sessions from
			// every associated user, including users shared with another tenant.
			$this->deleteWhereIn('user_permissions', 'user_id', $plan['associated_user_ids']);
			$this->deleteWhereIn('user_remember_tokens', 'user_id', $plan['associated_user_ids']);
			$this->deleteWhere('tenant_setting_values', 'tenant_id', $tenantId);

			// Presence may be detached from the target tenant while still referencing a user selected for deletion.
			$presenceSql = 'DELETE FROM user_presences WHERE tenant_id = ?';
			// The tenant condition removes shared-user presence only when it belongs to the deleted tenant.
			$presenceParams = [$tenantId];
			if ($plan['deletable_user_ids'] !== [])
				{
				$presenceSql .= ' OR user_id IN (' . $this->placeholders($plan['deletable_user_ids']) . ')';
				$presenceParams = array_merge($presenceParams, $plan['deletable_user_ids']);
				}
			$stmt = $this->db->prepare($presenceSql);
			$stmt->execute($presenceParams);

			if ($deletableUserIds !== [])
				{
				$placeholders = $this->placeholders($deletableUserIds);
				$stmt = $this->db->prepare(
					"DELETE Payment
					 FROM financial_operations_payments AS Payment
					 INNER JOIN financial_operations AS OperationRow
					         ON OperationRow.id = Payment.financial_operation_id
					 WHERE OperationRow.user_id IN ({$placeholders})"
				);
				$stmt->execute($deletableUserIds);

				$this->deleteWhereIn('financial_operations', 'user_id', $deletableUserIds);
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

			$this->deleteDeletableUsers($plan['deletable_user_ids']);
			$this->deleteWhereIn('addresses', 'person_id', $plan['deletable_person_ids']);
			$this->deleteWhereIn('emails', 'person_id', $plan['deletable_person_ids']);
			$this->deleteWhereIn('phone_numbers', 'person_id', $plan['deletable_person_ids']);
			$this->deleteDeletablePeople($plan['deletable_person_ids']);

			// Atomically move filesystem data so a central rollback can restore it.
			$quarantineBatches[] = $this->quarantineTenantStorage($databaseName);

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

			try
				{
				$this->restoreQuarantineBatches($quarantineBatches);
				}
			catch (\Throwable $restoreError)
				{
				throw new \RuntimeException(
					'A limpeza central falhou e os arquivos do tenant não puderam ser restaurados integralmente.',
					0,
					$restoreError
				);
				}
			throw $e;
			}

		// Invalidate transient chat state and catch files created by a request already in flight.
		$postCommitError = null;
		try
			{
			SupportChatTypingService::clearChats($plan['support_chat_ids']);
			}
		catch (\Throwable $cleanupError)
			{
			$postCommitError = $cleanupError;
			}

		try
			{
			$quarantineBatches[] = $this->quarantineTenantStorage($databaseName);
			}
		catch (\Throwable $cleanupError)
			{
			$postCommitError ??= $cleanupError;
			}

		try
			{
			$this->purgeQuarantineBatches($quarantineBatches);
			}
		catch (\Throwable $cleanupError)
			{
			$postCommitError ??= $cleanupError;
			}

		if ($postCommitError !== null)
			{
			throw new \RuntimeException(
				'O tenant foi removido, mas uma limpeza transitória ficou pendente.',
				0,
				$postCommitError
			);
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

		$this->assertRegisteredTenantAssociations();

		// The validated database name is reused by all cross-schema reference checks.
		$databaseName = 'manager_' . (string) $tenant['database_number'];
		$tenantFiles = $this->tenantFileRecords($databaseName);
		$tenantTemporaryFiles = $this->tenantTemporaryFileRecords($databaseName);

		$stmt = $this->db->prepare('SELECT id FROM support_chats WHERE tenant_id = :tenant_id ORDER BY id');
		$stmt->execute(['tenant_id' => $tenantId]);
		$supportChatIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		$supportChatTypingRecords = $this->supportChatTypingRecords($supportChatIds);

		// CRM links are central tenant associations and may point to people without user accounts.
		$stmt = $this->db->prepare("SELECT TenantPerson.id,
		                                   TenantPerson.person_id,
		                                   TenantPerson.source,
		                                   TenantPerson.active,
		                                   Person.name AS person_name
		                            FROM tenant_people AS TenantPerson
		                            INNER JOIN people AS Person ON Person.id = TenantPerson.person_id
		                            WHERE TenantPerson.tenant_id = :tenant_id
		                            ORDER BY TenantPerson.id");
		$stmt->execute(['tenant_id' => $tenantId]);
		$tenantPeople = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$tenantPersonIds = array_map(static fn (array $row): int => (int) $row['id'], $tenantPeople);
		$tenantPersonReferences = $this->tenantPersonReferences($tenantPersonIds, $databaseName);

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

		$allAssociatedUserIds = array_map(static fn (array $user): int => (int) $user['id'], $users);
		$protectedUserIds = array_values(array_intersect($allAssociatedUserIds, User::PROTECTED_USER_IDS));
		$associatedUserIds = array_values(array_diff($allAssociatedUserIds, $protectedUserIds));
		$sharedUserIds = [];
		$exclusiveCandidateIds = [];
		foreach ($users as $user)
			{
			if ((int) $user['is_shared'] === 1)
				{
				if (!in_array((int) $user['id'], User::PROTECTED_USER_IDS, true))
					{
					$sharedUserIds[] = (int) $user['id'];
					}
				}
			elseif (!in_array((int) $user['id'], User::PROTECTED_USER_IDS, true))
				{
				$exclusiveCandidateIds[] = (int) $user['id'];
				}
			}

		$externalUserIds = array_values(array_unique(array_merge(
			$this->externalReferenceIds('users', $exclusiveCandidateIds, $databaseName, $tenantPersonReferences),
			$this->centralUserReferenceIds($exclusiveCandidateIds, $tenantId)
		)));
		$deletableUserIds = array_values(array_diff($exclusiveCandidateIds, $externalUserIds));
		$retainedExclusiveUserIds = array_values(array_intersect($exclusiveCandidateIds, $externalUserIds));

		// Candidate people include both tenant users and CRM-only contacts created for this tenant.
		$personIds = array_map(static fn (array $row): int => (int) $row['person_id'], $tenantPeople);
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
		foreach ($personIds as $personId)
			{
			if (in_array((int) $personId, Person::PROTECTED_PERSON_IDS, true))
				{
				continue;
				}
			$personUserIds = $userByPerson[$personId] ?? [];
			if (array_diff($personUserIds, $deletableUserIds) === [])
				{
				$potentialPersonIds[] = (int) $personId;
				}
			}

		// A person linked to another tenant must survive even when this tenant created the original record.
		$otherTenantPersonIds = [];
		if ($potentialPersonIds !== [])
			{
			$stmt = $this->db->prepare(
				'SELECT DISTINCT person_id FROM tenant_people'
				. ' WHERE tenant_id <> ? AND person_id IN (' . $this->placeholders($potentialPersonIds) . ')'
			);
			$stmt->execute(array_merge([$tenantId], $potentialPersonIds));
			$otherTenantPersonIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
			}

		$externallyReferencedPeople = array_values(array_unique(array_merge(
			$this->externalReferenceIds('people', $potentialPersonIds, $databaseName, $tenantPersonReferences),
			$this->centralPersonReferenceIds($potentialPersonIds, $tenantId),
			$otherTenantPersonIds
		)));
		$deletablePersonIds = array_values(array_diff($potentialPersonIds, $externallyReferencedPeople));
		$retainedPersonIds = array_values(array_diff($personIds, $deletablePersonIds));

		return [
			'tenant' => $tenant,
			'users' => $users,
			'tenant_people' => $tenantPeople,
			'tenant_person_ids' => $tenantPersonIds,
			'tenant_person_references' => $tenantPersonReferences,
			'tenant_files' => $tenantFiles,
			'tenant_temporary_files' => $tenantTemporaryFiles,
			'support_chat_ids' => $supportChatIds,
			'support_chat_typing_records' => $supportChatTypingRecords,
			'person_ids' => $personIds,
			'associated_user_ids' => array_values(array_unique($associatedUserIds)),
			'protected_user_ids' => $protectedUserIds,
			'shared_user_ids' => array_values(array_unique($sharedUserIds)),
			'deletable_user_ids' => $deletableUserIds,
			'retained_exclusive_user_ids' => $retainedExclusiveUserIds,
			'deletable_person_ids' => $deletablePersonIds,
			'retained_person_ids' => $retainedPersonIds,
		];
		}

	/**
	 * Check whether an optional manager_main table exists.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private function tableExists(string $table): bool
		{
		$this->assertIdentifier($table);
		$stmt = $this->db->prepare(
			"SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND TABLE_TYPE = 'BASE TABLE'"
		);
		$stmt->execute(['table' => $table]);
		return $stmt->fetchColumn() !== false;
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
		$deletableUserIds = $plan['deletable_user_ids'];
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
		$this->appendModule(
			$modules,
			'manager_main/storage/support_chats',
			'Estado transitório de digitação',
			$plan['support_chat_typing_records']
		);

		$logRecords = [];
		$stmt = $this->db->prepare(
			'SELECT id, module, action, entity_name, entity_id'
			. ' FROM logs'
			. ' WHERE tenant_id = :tenant_id OR tenant_database = :tenant_database'
			. ' ORDER BY id'
		);
		$stmt->execute([
			'tenant_id' => $tenantId,
			'tenant_database' => $databaseName,
		]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$logRecords[] = $this->record('logs', $row['id'], 'Log #' . $row['id'] . ' — ' . $row['module'] . '.' . $row['action'] . ' | ' . $row['entity_name'] . ' #' . ($row['entity_id'] ?? '-'));
			}
		$this->appendModule($modules, 'manager_main', 'Logs do tenant', $logRecords);

		$settingRecords = [];
		$stmt = $this->db->prepare("SELECT TenantSettingValue.id,
		                                   SystemSettingDefinition.key_name
		                            FROM tenant_setting_values AS TenantSettingValue
		                            INNER JOIN system_setting_definitions AS SystemSettingDefinition ON SystemSettingDefinition.id = TenantSettingValue.setting_definition_id
		                            WHERE TenantSettingValue.tenant_id = :tenant
		                            ORDER BY TenantSettingValue.id");
		$stmt->execute(['tenant' => $tenantId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$settingRecords[] = $this->record('tenant_setting_values', $row['id'], 'Tenant setting #' . $row['id'] . ' - ' . $row['key_name']);
			}

		// Keep the manifest aligned with every presence row removed by the deletion transaction.
		$presenceSql = "SELECT UserPresence.id,
		                       UserPresence.user_id,
		                       UserPresence.status
		                FROM user_presences AS UserPresence
		                WHERE UserPresence.tenant_id = ?";
		// Users selected for deletion may currently have a null or different tenant in their presence row.
		$presenceParams = [$tenantId];
		if ($plan['deletable_user_ids'] !== [])
			{
			$presenceSql .= ' OR UserPresence.user_id IN (' . $this->placeholders($plan['deletable_user_ids']) . ')';
			$presenceParams = array_merge($presenceParams, $plan['deletable_user_ids']);
			}
		$presenceSql .= ' ORDER BY UserPresence.id';
		$stmt = $this->db->prepare($presenceSql);
		$stmt->execute($presenceParams);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			$settingRecords[] = $this->record('user_presences', $row['id'], 'Presence #' . $row['id'] . ' - user #' . $row['user_id'] . ' | status: ' . $row['status']);
			}
		$this->appendModule($modules, 'manager_main', 'Tenant settings and presence', $settingRecords);

		foreach ($plan['tenant_person_references'] as $reference)
			{
			$this->appendModule(
				$modules,
				$reference['scope'],
				'Registros CRM vinculados ao tenant',
				$reference['records']
			);
			}

		$tenantPeopleRecords = [];
		foreach ($plan['tenant_people'] as $tenantPerson)
			{
			$source = trim((string) ($tenantPerson['source'] ?? ''));
			$label = 'Vínculo CRM #' . $tenantPerson['id']
				. ' — pessoa #' . $tenantPerson['person_id'] . ' | ' . $tenantPerson['person_name']
				. ($source !== '' ? ' | origem: ' . $source : '');
			$tenantPeopleRecords[] = $this->record('tenant_people', $tenantPerson['id'], $label);
			}
		$this->appendModule($modules, 'manager_main', 'Pessoas vinculadas ao CRM do tenant', $tenantPeopleRecords);

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
		if ($deletableUserIds !== [])
			{
			$stmt = $this->db->prepare("SELECT Payment.id, Payment.financial_operation_id, Payment.value, OperationRow.user_id
			                           FROM financial_operations_payments AS Payment
			                           INNER JOIN financial_operations AS OperationRow ON OperationRow.id = Payment.financial_operation_id
			                           WHERE OperationRow.user_id IN (" . $this->placeholders($deletableUserIds) . ") ORDER BY Payment.id");
			$stmt->execute($deletableUserIds);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$financialRecords[] = $this->record('financial_operations_payments', $row['id'], 'Pagamento central #' . $row['id'] . ' — operação #' . $row['financial_operation_id'] . ' | valor: ' . $row['value']);
				}

			$stmt = $this->db->prepare("SELECT id, type, total_value, user_id FROM financial_operations
			                           WHERE user_id IN (" . $this->placeholders($deletableUserIds) . ") ORDER BY id");
			$stmt->execute($deletableUserIds);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$financialRecords[] = $this->record('financial_operations', $row['id'], 'Operação central #' . $row['id'] . ' — ' . $row['type'] . ' | valor: ' . $row['total_value'] . ' | usuário #' . $row['user_id']);
				}
			}
		$this->appendModule($modules, 'manager_main', 'Financeiro central de usuários exclusivos', $financialRecords);

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

		$this->appendModule($modules, 'manager/tenant_files', 'Arquivos armazenados do tenant', $plan['tenant_files']);
		$this->appendModule(
			$modules,
			'manager/tmp/chunk_uploads',
			'Uploads temporários do tenant',
			$plan['tenant_temporary_files']
		);

		$tenantLabel = 'Tenant #' . $tenantId . ' — base ' . $databaseName;
		$this->appendModule($modules, 'manager_main', 'Cadastro do tenant', [
			$this->record('tenants', $tenantId, $tenantLabel),
		]);

		return $modules;
		}

	/**
	 * List active typing-cache entries associated with support chats.
	 *
	 * @param array<int, int> $supportChatIds Support chat IDs.
	 * @return array<int, array<string, string>>
	 */
	private function supportChatTypingRecords(array $supportChatIds): array
		{
		$records = [];
		foreach ($supportChatIds as $chatId)
			{
			foreach (SupportChatTypingService::getTypingUsers($chatId) as $typing)
				{
				$userId = (int) ($typing['user_id'] ?? 0);
				$role = (string) ($typing['role'] ?? 'customer');
				$userName = trim((string) ($typing['user_name'] ?? ''));
				$records[] = $this->record(
					'support_chat_typing',
					$chatId . ':' . $role . ':' . $userId,
					'Digitação no atendimento #' . $chatId
						. ' — ' . ($userName !== '' ? $userName : ('usuário #' . $userId))
						. ' | perfil: ' . $role
				);
				}
			}

		return $records;
		}

	/**
	 * List every persistent file stored under the tenant-specific public directory.
	 *
	 * @param string $databaseName Validated tenant database name.
	 * @return array<int, array<string, string>>
	 */
	private function tenantFileRecords(string $databaseName): array
		{
		$directory = $this->tenantFilesDirectory($databaseName);
		return $this->filesystemRecords(
			$directory,
			'tenant_files/' . $databaseName,
			'tenant_files'
		);
		}

	/**
	 * List temporary chunk uploads stored for the tenant.
	 *
	 * @param string $databaseName Validated tenant database name.
	 * @return array<int, array<string, string>>
	 */
	private function tenantTemporaryFileRecords(string $databaseName): array
		{
		$records = [];
		foreach ($this->tenantTemporaryDirectories($databaseName) as $storage)
			{
			$records = array_merge(
				$records,
				$this->filesystemRecords(
					$storage['directory'],
					$storage['relative_root'],
					'tenant_temporary_files'
				)
			);
			}

		usort($records, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
		return $records;
		}

	/**
	 * Build safe manifest rows for one filesystem directory.
	 *
	 * @param string $directory Absolute directory.
	 * @param string $relativeRoot Safe display path.
	 * @param string $table Manifest table identifier.
	 * @return array<int, array<string, string>>
	 */
	private function filesystemRecords(string $directory, string $relativeRoot, string $table): array
		{
		if (!file_exists($directory) && !is_link($directory))
			{
			return [];
			}
		if (is_link($directory) || !is_dir($directory))
			{
			throw new \RuntimeException('O diretório de arquivos do tenant é inválido.');
			}

		$records = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($iterator as $file)
			{
			$path = $file->getPathname();
			if ($file->isDir() && !$file->isLink())
				{
				continue;
				}

			$relativeSuffix = ltrim(substr($path, strlen($directory)), DIRECTORY_SEPARATOR);
			$relativePath = $relativeRoot . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativeSuffix);
			$size = $file->isFile() && !$file->isLink() ? (int) $file->getSize() : 0;
			$label = 'Arquivo ' . $relativePath . ($size > 0 ? ' — ' . $size . ' byte(s)' : '');
			$records[] = $this->record($table, $relativePath, $label);
			}

		usort($records, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
		return $records;
		}

	/**
	 * Move tenant storage to a same-filesystem quarantine for rollback safety.
	 *
	 * @param string $databaseName Validated tenant database name.
	 * @return array{root: string|null, moves: array<int, array{source: string, quarantine: string}>}
	 */
	private function quarantineTenantStorage(string $databaseName): array
		{
		$storageDirectories = $this->tenantStorageDirectories($databaseName);
		if ($storageDirectories === [])
			{
			return ['root' => null, 'moves' => []];
			}

		$quarantineParent = $this->managerRootDirectory() . '/tmp';
		$quarantineBase = $quarantineParent . '/tenant_deletion_quarantine';
		if (is_link($quarantineBase))
			{
			throw new \RuntimeException('O diretório de quarentena do tenant é inválido.');
			}
		if (!is_dir($quarantineBase)
			&& !mkdir($quarantineBase, 0775, true)
			&& !is_dir($quarantineBase))
			{
			throw new \RuntimeException('Não foi possível preparar a quarentena de arquivos do tenant.');
			}
		$this->assertStorageDirectory($quarantineBase, $quarantineParent);

		$quarantineRoot = $quarantineBase . '/' . $databaseName . '-' . bin2hex(random_bytes(12));
		if (!mkdir($quarantineRoot, 0775, false))
			{
			throw new \RuntimeException('Não foi possível criar a quarentena de arquivos do tenant.');
			}

		$moves = [];
		try
			{
			foreach ($storageDirectories as $index => $storage)
				{
				$quarantine = $quarantineRoot . '/item_' . $index;
				if (!@rename($storage['directory'], $quarantine))
					{
					throw new \RuntimeException('Não foi possível mover os arquivos do tenant para quarentena.');
					}
				$moves[] = [
					'source' => $storage['directory'],
					'quarantine' => $quarantine,
				];
				}
			}
		catch (\Throwable $e)
			{
			$this->restoreQuarantineBatches([['root' => $quarantineRoot, 'moves' => $moves]]);
			throw $e;
			}

		return ['root' => $quarantineRoot, 'moves' => $moves];
		}

	/**
	 * Restore every quarantined directory after a central rollback.
	 *
	 * @param array<int, array{root: string|null, moves: array<int, array{source: string, quarantine: string}>}> $batches
	 * @return void
	 */
	private function restoreQuarantineBatches(array $batches): void
		{
		foreach (array_reverse($batches) as $batch)
			{
			foreach (array_reverse($batch['moves']) as $move)
				{
				if (!file_exists($move['quarantine']) && !is_link($move['quarantine']))
					{
					continue;
					}
				if (file_exists($move['source']) || is_link($move['source'])
					|| !@rename($move['quarantine'], $move['source']))
					{
					throw new \RuntimeException('Não foi possível restaurar arquivos do tenant após rollback.');
					}
				}

			if ($batch['root'] !== null && is_dir($batch['root']) && !@rmdir($batch['root']))
				{
				throw new \RuntimeException('Não foi possível finalizar a restauração da quarentena do tenant.');
				}
			}
		}

	/**
	 * Permanently remove quarantined storage after the central commit.
	 *
	 * @param array<int, array{root: string|null, moves: array<int, array{source: string, quarantine: string}>}> $batches
	 * @return void
	 */
	private function purgeQuarantineBatches(array $batches): void
		{
		foreach ($batches as $batch)
			{
			foreach ($batch['moves'] as $move)
				{
				$this->deleteDirectory($move['quarantine']);
				}

			if ($batch['root'] !== null && is_dir($batch['root']) && !@rmdir($batch['root']))
				{
				throw new \RuntimeException('O tenant foi removido, mas a quarentena de arquivos não pôde ser eliminada.');
				}
			}
		}

	/**
	 * Remove one validated directory tree without following symlinks.
	 *
	 * @param string $directory Absolute directory.
	 * @return void
	 */
	private function deleteDirectory(string $directory): void
		{
		if (!file_exists($directory) && !is_link($directory))
			{
			return;
			}
		if (is_link($directory) || !is_dir($directory))
			{
			throw new \RuntimeException('O diretório de arquivos do tenant é inválido.');
			}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $item)
			{
			$path = $item->getPathname();
			if ($item->isLink() || $item->isFile())
				{
				if (!@unlink($path))
					{
					throw new \RuntimeException('Não foi possível excluir um arquivo armazenado do tenant.');
					}
				continue;
				}

			if ($item->isDir() && !@rmdir($path))
				{
				throw new \RuntimeException('Não foi possível excluir um diretório armazenado do tenant.');
				}
			}

		if (!@rmdir($directory))
			{
			throw new \RuntimeException('Não foi possível excluir o diretório de arquivos do tenant.');
			}
		}

	/**
	 * Resolve and validate the tenant-specific public storage directory.
	 *
	 * @param string $databaseName Tenant database name.
	 * @return string
	 */
	private function tenantFilesDirectory(string $databaseName): string
		{
		$this->assertTenantDatabaseName($databaseName);
		$baseDirectory = $this->managerRootDirectory() . '/public/tenant_files';
		$directory = $baseDirectory . '/' . $databaseName;
		if (file_exists($directory) || is_link($directory))
			{
			$this->assertStorageDirectory($directory, $baseDirectory);
			}

		return $directory;
		}

	/**
	 * Resolve temporary upload directories whose second path segment is the tenant database.
	 *
	 * @param string $databaseName Tenant database name.
	 * @return array<int, array{directory: string, relative_root: string}>
	 */
	private function tenantTemporaryDirectories(string $databaseName): array
		{
		$this->assertTenantDatabaseName($databaseName);
		$baseDirectory = $this->managerRootDirectory() . '/tmp/chunk_uploads';
		if (!file_exists($baseDirectory) && !is_link($baseDirectory))
			{
			return [];
			}
		if (is_link($baseDirectory) || !is_dir($baseDirectory))
			{
			throw new \RuntimeException('O diretório de uploads temporários é inválido.');
			}

		$directories = [];
		foreach (new \DirectoryIterator($baseDirectory) as $namespace)
			{
			if ($namespace->isDot() || $namespace->isLink() || !$namespace->isDir())
				{
				continue;
				}

			$directory = $namespace->getPathname() . '/' . $databaseName;
			if (!file_exists($directory) && !is_link($directory))
				{
				continue;
				}

			$this->assertStorageDirectory($directory, $baseDirectory);
			$directories[] = [
				'directory' => $directory,
				'relative_root' => 'chunk_uploads/' . $namespace->getFilename() . '/' . $databaseName,
			];
			}

		usort(
			$directories,
			static fn (array $left, array $right): int => strcmp($left['directory'], $right['directory'])
		);
		return $directories;
		}

	/**
	 * Return every existing persistent or temporary tenant storage directory.
	 *
	 * @param string $databaseName Tenant database name.
	 * @return array<int, array{directory: string, relative_root: string}>
	 */
	private function tenantStorageDirectories(string $databaseName): array
		{
		$directories = [];
		$tenantFilesDirectory = $this->tenantFilesDirectory($databaseName);
		if (file_exists($tenantFilesDirectory) || is_link($tenantFilesDirectory))
			{
			$directories[] = [
				'directory' => $tenantFilesDirectory,
				'relative_root' => 'tenant_files/' . $databaseName,
			];
			}

		return array_merge($directories, $this->tenantTemporaryDirectories($databaseName));
		}

	/**
	 * Validate that an existing storage directory is a real child of its expected base.
	 *
	 * @param string $directory Candidate directory.
	 * @param string $baseDirectory Expected base directory.
	 * @return void
	 */
	private function assertStorageDirectory(string $directory, string $baseDirectory): void
		{
		if (is_link($directory) || !is_dir($directory) || is_link($baseDirectory))
			{
			throw new \RuntimeException('O diretório de arquivos do tenant é inválido.');
			}

		$realManagerRoot = realpath($this->managerRootDirectory());
		$realBaseDirectory = realpath($baseDirectory);
		$realDirectory = realpath($directory);
		$managerPrefix = $realManagerRoot === false
			? ''
			: rtrim($realManagerRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$basePrefix = $realBaseDirectory === false
			? ''
			: rtrim($realBaseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if ($managerPrefix === ''
			|| $basePrefix === ''
			|| $realDirectory === false
			|| !str_starts_with($realBaseDirectory, $managerPrefix)
			|| !str_starts_with($realDirectory, $basePrefix))
			{
			throw new \RuntimeException('O diretório de arquivos do tenant está fora do escopo permitido.');
			}
		}

	/**
	 * Resolve the sibling manager application root used by tenant storage.
	 *
	 * @return string
	 */
	private function managerRootDirectory(): string
		{
		$managerMainRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
		return dirname($managerMainRoot) . '/manager';
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
	 * Block deletion when a direct or tenant-person association is missing from the inventory.
	 *
	 * @return void
	 */
	private function assertRegisteredTenantAssociations(): void
		{
		$stmt = $this->db->query("SELECT AssociationInventory.association
		                          FROM (
		                            SELECT CONCAT(ColumnMeta.TABLE_NAME, '.', ColumnMeta.COLUMN_NAME) AS association
		                            FROM information_schema.COLUMNS AS ColumnMeta
		                            INNER JOIN information_schema.TABLES AS TableMeta
		                                    ON TableMeta.TABLE_SCHEMA = ColumnMeta.TABLE_SCHEMA
		                                   AND TableMeta.TABLE_NAME = ColumnMeta.TABLE_NAME
		                                   AND TableMeta.TABLE_TYPE = 'BASE TABLE'
		                            WHERE ColumnMeta.TABLE_SCHEMA = DATABASE()
		                              AND ColumnMeta.COLUMN_NAME IN (
		                                'tenant_id',
		                                'tenant_database',
		                                'tenant_database_number'
		                              )
		                              AND ColumnMeta.TABLE_NAME <> 'tenants'

		                            UNION

		                            SELECT CONCAT(ForeignKeyMeta.TABLE_NAME, '.', ForeignKeyMeta.COLUMN_NAME)
		                            FROM information_schema.KEY_COLUMN_USAGE AS ForeignKeyMeta
		                            WHERE ForeignKeyMeta.TABLE_SCHEMA = DATABASE()
		                              AND ForeignKeyMeta.REFERENCED_TABLE_SCHEMA = DATABASE()
		                              AND ForeignKeyMeta.REFERENCED_TABLE_NAME = 'tenants'
		                              AND ForeignKeyMeta.TABLE_NAME <> 'tenants'
		                          ) AS AssociationInventory
		                          ORDER BY AssociationInventory.association");
		$actualAssociations = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		$unregisteredAssociations = array_values(array_diff(
			$actualAssociations,
			self::REGISTERED_DIRECT_TENANT_ASSOCIATIONS
		));

		if ($unregisteredAssociations !== [])
			{
			throw new \RuntimeException(
				'A exclusão foi bloqueada porque há associações de tenant não registradas: '
				. implode(', ', $unregisteredAssociations)
				. '. Atualize o manifesto e a execução do TenantDeletionService.'
			);
			}

		$stmt = $this->db->query("SELECT DISTINCT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME
		                          FROM information_schema.KEY_COLUMN_USAGE
		                          WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
		                            AND REFERENCED_TABLE_NAME = 'tenant_people'
		                            AND (
		                              TABLE_SCHEMA = DATABASE()
		                              OR TABLE_SCHEMA REGEXP '^manager_[0-9]{4}$'
		                            )
		                          ORDER BY TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME");
		$actualTenantPersonAssociations = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $association)
			{
			$scope = (string) $association['TABLE_SCHEMA'];
			if (preg_match('/^manager_[0-9]{4}$/', $scope))
				{
				$scope = 'manager_*';
				}
			$actualTenantPersonAssociations[] = $scope
				. '.' . (string) $association['TABLE_NAME']
				. '.' . (string) $association['COLUMN_NAME'];
			}
		$unregisteredTenantPersonAssociations = array_values(array_diff(
			array_values(array_unique($actualTenantPersonAssociations)),
			self::REGISTERED_TENANT_PERSON_ASSOCIATIONS
		));
		if ($unregisteredTenantPersonAssociations !== [])
			{
			throw new \RuntimeException(
				'A exclusão foi bloqueada porque há associações indiretas de tenant não registradas: '
				. implode(', ', $unregisteredTenantPersonAssociations)
				. '. Atualize o manifesto e a execução do TenantDeletionService.'
			);
			}
		}

	/**
	 * Find rows outside the target database that reference this tenant's CRM links.
	 *
	 * @param array<int, int> $tenantPersonIds Tenant-person link IDs.
	 * @param string $excludedDatabase Dedicated database that will be dropped.
	 * @return array<int, array<string, mixed>>
	 */
	private function tenantPersonReferences(array $tenantPersonIds, string $excludedDatabase): array
		{
		if ($tenantPersonIds === [])
			{
			return [];
			}

		$stmt = $this->db->prepare("SELECT DISTINCT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME
		                            FROM information_schema.KEY_COLUMN_USAGE
		                            WHERE REFERENCED_TABLE_SCHEMA = 'manager_main'
		                              AND REFERENCED_TABLE_NAME = 'tenant_people'
		                              AND TABLE_SCHEMA <> :excluded_database
		                              AND (
		                                TABLE_SCHEMA = 'manager_main'
		                                OR TABLE_SCHEMA REGEXP '^manager_[0-9]{4}$'
		                              )
		                            ORDER BY TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME");
		$stmt->execute(['excluded_database' => $excludedDatabase]);
		$references = [];

		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $reference)
			{
			$scope = (string) $reference['TABLE_SCHEMA'];
			$table = (string) $reference['TABLE_NAME'];
			$column = (string) $reference['COLUMN_NAME'];
			$this->assertScope($scope);
			$this->assertIdentifier($table);
			$this->assertIdentifier($column);

			$idColumnStmt = $this->db->prepare("SELECT 1
			                                    FROM information_schema.COLUMNS
			                                    WHERE TABLE_SCHEMA = :scope
			                                      AND TABLE_NAME = :table
			                                      AND COLUMN_NAME = 'id'");
			$idColumnStmt->execute(['scope' => $scope, 'table' => $table]);
			if ($idColumnStmt->fetchColumn() === false)
				{
				throw new \RuntimeException(
					'A associação ' . $scope . '.' . $table
					. ' precisa ser catalogada explicitamente no TenantDeletionService.'
				);
				}

			$sql = 'SELECT id, ' . $this->quoteIdentifier($column)
				. ' FROM ' . $this->quoteIdentifier($scope) . '.' . $this->quoteIdentifier($table)
				. ' WHERE ' . $this->quoteIdentifier($column) . ' IN (' . $this->placeholders($tenantPersonIds) . ')'
				. ' ORDER BY id';
			$referenceStmt = $this->db->prepare($sql);
			$referenceStmt->execute($tenantPersonIds);
			$rows = $referenceStmt->fetchAll(PDO::FETCH_ASSOC);
			if ($rows === [])
				{
				continue;
				}

			$entityLabel = match ($table)
				{
				'crm_activities' => 'Atividade CRM',
				'crm_opportunities' => 'Oportunidade CRM',
				default => 'Registro associado',
				};
			$records = [];
			foreach ($rows as $row)
				{
				$records[] = $this->record(
					$table,
					$row['id'],
					$entityLabel . ' #' . $row['id'] . ' — vínculo CRM #' . $row[$column]
				);
				}

			$references[] = [
				'scope' => $scope,
				'table' => $table,
				'column' => $column,
				'ids' => array_map(static fn (array $row): int => (int) $row['id'], $rows),
				'records' => $records,
			];
			}

		return $references;
		}

	/**
	 * Find IDs referenced by another tenant database.
	 *
	 * @param string $referencedTable Central table name (users or people).
	 * @param array<int, int> $ids Candidate IDs.
	 * @param string $excludedDatabase Database being deleted.
	 * @param array<int, array<string, mixed>> $ignoredReferences Rows already selected for tenant deletion.
	 * @return array<int, int>
	 */
	private function externalReferenceIds(
		string $referencedTable,
		array $ids,
		string $excludedDatabase,
		array $ignoredReferences = []
	): array
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
				$scope = (string) $reference['TABLE_SCHEMA'];
				$table = (string) $reference['TABLE_NAME'];
				$column = (string) $reference['COLUMN_NAME'];
				$this->assertScope($scope);
				$this->assertIdentifier($table);
				$this->assertIdentifier($column);

				$ignoredIds = $this->ignoredReferenceIds($ignoredReferences, $scope, $table);
				$sql = 'SELECT DISTINCT ' . $this->quoteIdentifier($column)
					. ' FROM ' . $this->quoteIdentifier($scope) . '.' . $this->quoteIdentifier($table)
					. ' WHERE ' . $this->quoteIdentifier($column) . ' IN (' . $this->placeholders($ids) . ')';
				$params = $ids;
				if ($ignoredIds !== [])
					{
					$sql .= ' AND id NOT IN (' . $this->placeholders($ignoredIds) . ')';
					$params = array_merge($params, $ignoredIds);
					}
				$referenceStmt = $this->db->prepare($sql);
				$referenceStmt->execute($params);
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
	 * Return IDs from rows that this same tenant deletion already removes.
	 *
	 * @param array<int, array<string, mixed>> $references Planned tenant-person references.
	 * @param string $scope Database schema.
	 * @param string $table Table name.
	 * @return array<int, int>
	 */
	private function ignoredReferenceIds(array $references, string $scope, string $table): array
		{
		$ids = [];
		foreach ($references as $reference)
			{
			if ($reference['scope'] === $scope && $reference['table'] === $table)
				{
				$ids = array_merge($ids, $reference['ids']);
				}
			}

		return array_values(array_unique(array_map('intval', $ids)));
		}

	/**
	 * Find associated users referenced by central records that survive this tenant.
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

		// CRM is tenant-database functionality in the current schema. Keep the
		// legacy central checks only when the complete legacy CRM schema exists.
		$centralCrmTables = [
			'crm_opportunities', 'crm_inactivity_reasons', 'crm_loss_reasons',
			'crm_opportunity_types', 'crm_pipeline_stages', 'crm_sources',
		];
		$existingCentralCrmTables = array_values(array_filter(
			$centralCrmTables,
			fn (string $table): bool => $this->tableExists($table)
		));
		if ($existingCentralCrmTables === [])
			{
			return [];
			}
		if (count($existingCentralCrmTables) !== count($centralCrmTables))
			{
			// A partially migrated legacy schema is unknown; preserve candidates.
			return $ids;
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
			            OR EXISTS (
			              SELECT 1
			              FROM tenant_people AS TenantPerson
				              WHERE TenantPerson.tenant_id <> ?
				                AND (
				                  TenantPerson.owner_user_id = UserRow.id
				                  OR TenantPerson.created_by_user_id = UserRow.id
				                  OR TenantPerson.updated_by_user_id = UserRow.id
				                )
				            )
				            OR EXISTS (
				              SELECT 1
				              FROM crm_opportunities AS Opportunity
				              LEFT JOIN tenant_people AS TenantPerson ON TenantPerson.id = Opportunity.tenant_person_id
				              WHERE (
				                  Opportunity.responsible_user_id = UserRow.id
				                  OR Opportunity.created_by_user_id = UserRow.id
				                  OR Opportunity.updated_by_user_id = UserRow.id
				                )
				                AND (
				                  Opportunity.tenant_person_id IS NULL
				                  OR TenantPerson.id IS NULL
				                  OR TenantPerson.tenant_id <> ?
				                )
				            )
				            OR EXISTS (SELECT 1 FROM crm_inactivity_reasons AS ReasonRow WHERE ReasonRow.created_by_user_id = UserRow.id OR ReasonRow.updated_by_user_id = UserRow.id)
				            OR EXISTS (SELECT 1 FROM crm_loss_reasons AS ReasonRow WHERE ReasonRow.created_by_user_id = UserRow.id OR ReasonRow.updated_by_user_id = UserRow.id)
				            OR EXISTS (SELECT 1 FROM crm_opportunity_types AS TypeRow WHERE TypeRow.created_by_user_id = UserRow.id OR TypeRow.updated_by_user_id = UserRow.id)
				            OR EXISTS (SELECT 1 FROM crm_pipeline_stages AS StageRow WHERE StageRow.created_by_user_id = UserRow.id OR StageRow.updated_by_user_id = UserRow.id)
				            OR EXISTS (SELECT 1 FROM crm_sources AS SourceRow WHERE SourceRow.created_by_user_id = UserRow.id OR SourceRow.updated_by_user_id = UserRow.id)
			          )";
		try
			{
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array_merge(
				$ids,
				[$tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId]
			));
			return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
			}
		catch (\Throwable $e)
			{
			// An incomplete legacy CRM schema must preserve candidates conservatively.
			return $ids;
			}
		}

	/**
	 * Find people referenced by central CRM rows that will survive this tenant.
	 *
	 * @param array<int, int> $ids Candidate person IDs.
	 * @param int $tenantId Tenant being deleted.
	 * @return array<int, int>
	 */
	private function centralPersonReferenceIds(array $ids, int $tenantId): array
		{
		if ($ids === [])
			{
			return [];
			}
		if (!$this->tableExists('crm_opportunities'))
			{
			return [];
			}

		$sql = "SELECT DISTINCT Opportunity.person_id
		        FROM crm_opportunities AS Opportunity
		        LEFT JOIN tenant_people AS TenantPerson ON TenantPerson.id = Opportunity.tenant_person_id
		        WHERE Opportunity.person_id IN (" . $this->placeholders($ids) . ")
		          AND (
		            Opportunity.tenant_person_id IS NULL
		            OR TenantPerson.id IS NULL
		            OR TenantPerson.tenant_id <> ?
		          )";
		try
			{
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array_merge($ids, [$tenantId]));
			return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
			}
		catch (\Throwable $e)
			{
			return $ids;
			}
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
	 * Lock the tenant row before rebuilding the final deletion plan.
	 *
	 * @param int $tenantId Tenant ID.
	 * @return void
	 */
	private function lockTenant(int $tenantId): void
		{
		$stmt = $this->db->prepare('SELECT id FROM tenants WHERE id = :id FOR UPDATE');
		$stmt->execute(['id' => $tenantId]);
		if ($stmt->fetchColumn() === false)
			{
			throw new \RuntimeException('Tenant não encontrado durante a confirmação da exclusão.');
			}
		}

	/**
	 * Lock users, people and CRM links before the final post-lock plan.
	 *
	 * @param array<string, mixed> $plan Preliminary locked-transaction plan.
	 * @return void
	 */
	private function lockDeletionCandidates(array $plan): void
		{
		$userIds = array_map(static fn (array $user): int => (int) $user['id'], $plan['users']);
		$this->lockRows('users', $userIds);
		$this->lockRows('people', $plan['person_ids']);
		$this->lockRows('tenant_people', $plan['tenant_person_ids']);
		}

	/**
	 * Lock existing rows from one validated manager_main table.
	 *
	 * @param string $table Table name.
	 * @param array<int, int> $ids Row IDs.
	 * @return void
	 */
	private function lockRows(string $table, array $ids): void
		{
		if ($ids === [])
			{
			return;
			}

		$this->assertIdentifier($table);
		$sql = 'SELECT id FROM ' . $this->quoteIdentifier($table)
			. ' WHERE id IN (' . $this->placeholders($ids) . ')'
			. ' ORDER BY id FOR UPDATE';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($ids));
		$stmt->fetchAll(PDO::FETCH_COLUMN);
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

		$this->assertIdentifier($table);
		$this->assertIdentifier($column);

		$sql = 'DELETE FROM ' . $this->quoteIdentifier($table)
			. ' WHERE ' . $this->quoteIdentifier($column) . ' IN (' . $this->placeholders($ids) . ')';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($ids));
		}

	/**
	 * Delete rows only while they still reference the planned tenant-person links.
	 *
	 * @param string $scope Database schema.
	 * @param string $table Table name.
	 * @param string $column Tenant-person foreign-key column.
	 * @param array<int, int> $tenantPersonIds Tenant-person IDs.
	 * @return void
	 */
	private function deleteScopedAssociation(
		string $scope,
		string $table,
		string $column,
		array $tenantPersonIds
	): void
		{
		if ($tenantPersonIds === [])
			{
			return;
			}

		$this->assertScope($scope);
		$this->assertIdentifier($table);
		$this->assertIdentifier($column);
		$sql = 'DELETE FROM ' . $this->quoteIdentifier($scope) . '.' . $this->quoteIdentifier($table)
			. ' WHERE ' . $this->quoteIdentifier($column)
			. ' IN (' . $this->placeholders($tenantPersonIds) . ')';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($tenantPersonIds));
		}

	/**
	 * Delete planned IDs only when they still belong to the expected owner.
	 *
	 * @param string $table Table name.
	 * @param string $idColumn ID column.
	 * @param array<int, int> $ids IDs.
	 * @param string $ownerColumn Owner column.
	 * @param int $ownerId Expected owner ID.
	 * @return void
	 */
	private function deleteIdsByOwner(
		string $table,
		string $idColumn,
		array $ids,
		string $ownerColumn,
		int $ownerId
	): void
		{
		if ($ids === [])
			{
			return;
			}

		$this->assertIdentifier($table);
		$this->assertIdentifier($idColumn);
		$this->assertIdentifier($ownerColumn);
		$sql = 'DELETE FROM ' . $this->quoteIdentifier($table)
			. ' WHERE ' . $this->quoteIdentifier($idColumn)
			. ' IN (' . $this->placeholders($ids) . ')'
			. ' AND ' . $this->quoteIdentifier($ownerColumn) . ' = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_merge(array_values($ids), [$ownerId]));
		if ($stmt->rowCount() !== count($ids))
			{
			throw new \RuntimeException('Uma associação do tenant mudou durante a exclusão.');
			}
		}

	/**
	 * Delete users only after confirming that no tenant link remains.
	 *
	 * @param array<int, int> $ids User IDs.
	 * @return void
	 */
	private function deleteDeletableUsers(array $ids): void
		{
		if ($ids === [])
			{
			return;
			}

		$sql = 'DELETE UserRow FROM users AS UserRow'
			. ' WHERE UserRow.id IN (' . $this->placeholders($ids) . ')'
			. ' AND UserRow.tenant_id IS NULL'
			. ' AND NOT EXISTS (SELECT 1 FROM tenant_users AS TenantUser WHERE TenantUser.user_id = UserRow.id)';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($ids));
		if ($stmt->rowCount() !== count($ids))
			{
			throw new \RuntimeException('Um usuário ganhou outro vínculo durante a exclusão do tenant.');
			}
		}

	/**
	 * Delete people only after confirming that no user or CRM link remains.
	 *
	 * @param array<int, int> $ids Person IDs.
	 * @return void
	 */
	private function deleteDeletablePeople(array $ids): void
		{
		if ($ids === [])
			{
			return;
			}

		$sql = 'DELETE PersonRow FROM people AS PersonRow'
			. ' WHERE PersonRow.id IN (' . $this->placeholders($ids) . ')'
			. ' AND NOT EXISTS (SELECT 1 FROM users AS UserRow WHERE UserRow.person_id = PersonRow.id)'
			. ' AND NOT EXISTS (SELECT 1 FROM tenant_people AS TenantPerson WHERE TenantPerson.person_id = PersonRow.id)';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($ids));
		if ($stmt->rowCount() !== count($ids))
			{
			throw new \RuntimeException('Uma pessoa ganhou outro vínculo durante a exclusão do tenant.');
			}
		}

	/**
	 * Delete rows by one integer equality condition.
	 *
	 * @param string $table Table name.
	 * @param string $column Column name.
	 * @param int $id Record owner identifier.
	 * @return void
	 */
	private function deleteWhere(string $table, string $column, int $id): void
		{
		$this->assertIdentifier($table);
		$this->assertIdentifier($column);

		$sql = 'DELETE FROM ' . $this->quoteIdentifier($table)
			. ' WHERE ' . $this->quoteIdentifier($column) . ' = :id';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['id' => $id]);
		}

	/**
	 * Validate a central or tenant database schema resolved from metadata.
	 *
	 * @param string $scope Database schema.
	 * @return void
	 */
	private function assertScope(string $scope): void
		{
		if ($scope !== 'manager_main')
			{
			$this->assertTenantDatabaseName($scope);
			}
		}

	/**
	 * Validate a tenant database name used in schema and filesystem operations.
	 *
	 * @param string $databaseName Database name.
	 * @return void
	 */
	private function assertTenantDatabaseName(string $databaseName): void
		{
		if (!preg_match('/^manager_[0-9]{4}$/', $databaseName))
			{
			throw new \InvalidArgumentException('Base de tenant inválida.');
			}
		}

	/**
	 * Validate a table or column identifier resolved from metadata.
	 *
	 * @param string $identifier SQL identifier.
	 * @return void
	 */
	private function assertIdentifier(string $identifier): void
		{
		if (!preg_match('/^[a-z_]+$/', $identifier))
			{
			throw new \InvalidArgumentException('Identificador SQL inválido.');
			}
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
