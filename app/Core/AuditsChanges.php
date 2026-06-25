<?php

namespace App\Core;

trait AuditsChanges
	{
	/**
	 * Keeps manager_main models compatible with audit method calls.
	 *
	 * The central system should only store logs produced by the tenant manager,
	 * so its own models intentionally do not persist audit rows.
	 *
	 * @return null
	 **/
	protected function auditLogger()
		{
		return null;
		}

	/**
	 * Writes a create event.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $afterData Snapshot after the change.
	 * @param array $context Additional context.
	 **/
	protected function auditCreate(string $module, string $entityName, $entityId, $afterData, array $context = []): void
		{
		}

	/**
	 * Writes an update event.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the change.
	 * @param mixed $afterData Snapshot after the change.
	 * @param array $context Additional context.
	 **/
	protected function auditUpdate(string $module, string $entityName, $entityId, $beforeData, $afterData, array $context = []): void
		{
		}

	/**
	 * Writes a delete event.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the delete.
	 * @param array $context Additional context.
	 **/
	protected function auditDelete(string $module, string $entityName, $entityId, $beforeData, array $context = []): void
		{
		}
	}
?>
