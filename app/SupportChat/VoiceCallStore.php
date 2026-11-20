<?php

namespace App\SupportChat;

/**
 * Persistence and authorization lookups needed by the voice call relay.
 */
interface VoiceCallStore
	{
	/**
	 * Returns one support chat row by id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findChatById(int $chatId): ?array;

	/**
	 * Returns whether the provided user is an active root marked as online.
	 */
	public function isOnlineRootUser(int $userId): bool;

	/**
	 * Returns whether an active user belongs to an active tenant.
	 */
	public function userBelongsToTenant(int $userId, int $tenantId): bool;

	/**
	 * Returns whether voice calls are enabled for a tenant.
	 */
	public function voiceCallsEnabled(int $tenantId): bool;

	/**
	 * Returns the display name of a user.
	 */
	public function userDisplayName(int $userId): string;

	/**
	 * Records a new voice call attempt.
	 *
	 * @param array<string, mixed> $call
	 */
	public function createVoiceCallLog(array $call): void;

	/**
	 * Updates a voice call record after a state change.
	 */
	public function updateVoiceCallLog(string $callUid, string $status, ?string $endReason, bool $answered, bool $ended, int $durationSeconds): void;
	}

?>
