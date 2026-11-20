<?php

namespace App\SupportChat;

/**
 * Keeps the state of one-to-one voice calls and decides which sockets receive
 * each event. Audio never passes through here: the relay only rings the callee
 * and forwards WebRTC signaling between the two participant sockets.
 *
 * Every method returns a list of deliveries ({client, payload}) instead of
 * writing to sockets, so the call rules can be tested without a network.
 */
class VoiceCallRelay
	{
	public const RING_TIMEOUT_SECONDS = 45;
	private const MAX_SIGNAL_BYTES = 16384;
	private const REJECT_REASONS = ['declined', 'busy', 'no_microphone'];

	private VoiceCallStore $store;
	/** @var array<string, array<string, mixed>> Active and ringing calls by id. */
	private array $calls = [];
	private ?\Throwable $storeError = null;

	public function __construct(VoiceCallStore $store)
		{
		$this->store = $store;
		}

	/**
	 * Handles one message sent by a voice socket.
	 *
	 * @param array<string, mixed> $claims Verified token claims of the sender.
	 * @param array<string, mixed> $message Decoded message.
	 * @param array<int, array<string, mixed>> $voiceClients Claims of every authenticated voice socket.
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	public function handle(int $clientId, array $claims, string $type, array $message, array $voiceClients): array
		{
		switch ($type)
			{
			case 'call_invite':
				return $this->invite($clientId, $claims, $message, $voiceClients);

			case 'call_accept':
				return $this->accept($clientId, $claims, $message, $voiceClients);

			case 'call_reject':
				return $this->reject($claims, $message, $voiceClients);

			case 'call_hangup':
				return $this->hangup($clientId, $message, $voiceClients);

			case 'rtc_signal':
				return $this->signal($clientId, $message);
			}

		return [$this->error($clientId, 'unsupported_type', 'Unsupported message type.')];
		}

	/**
	 * Rings again, on a socket that just connected, the calls still waiting for it.
	 *
	 * @param array<string, mixed> $claims
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	public function pendingRingsFor(int $clientId, array $claims): array
		{
		$identity = $this->identity($claims);
		$deliveries = [];

		foreach ($this->calls as $call)
			{
			if ($call['status'] === 'ringing' && $this->sameIdentity($identity, $call['callee']))
				{
				$deliveries[] = ['client' => $clientId, 'payload' => $this->ringingPayload($call)];
				}
			}

		return $deliveries;
		}

	/**
	 * Ends the calls in which a closed socket was a participant.
	 *
	 * @param array<int, array<string, mixed>> $voiceClients Remaining voice sockets.
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	public function clientDisconnected(int $clientId, array $voiceClients): array
		{
		$deliveries = [];

		foreach ($this->calls as $call)
			{
			if ($call['caller_client'] === $clientId || $call['callee_client'] === $clientId)
				{
				$deliveries = array_merge($deliveries, $this->leave($call, $clientId, 'disconnected', $voiceClients));
				}
			}

		return $deliveries;
		}

	/**
	 * Ends calls that rang longer than the timeout.
	 *
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	public function expire(int $now, array $voiceClients): array
		{
		$deliveries = [];

		foreach ($this->calls as $call)
			{
			if ($call['status'] === 'ringing' && ($now - (int) $call['created_at']) >= self::RING_TIMEOUT_SECONDS)
				{
				$deliveries = array_merge($deliveries, $this->finishRinging($call, 'missed', 'missed', $voiceClients));
				}
			}

		return $deliveries;
		}

	/**
	 * Returns and clears the last failure while writing the call log.
	 */
	public function takeStoreError(): ?\Throwable
		{
		$error = $this->storeError;
		$this->storeError = null;
		return $error;
		}

	/**
	 * Returns the calls currently ringing or active.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function calls(): array
		{
		return $this->calls;
		}

	/**
	 * Starts a call: validates who may call whom and rings every socket of the callee.
	 *
	 * @param array<string, mixed> $claims
	 * @param array<string, mixed> $message
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function invite(int $clientId, array $claims, array $message, array $voiceClients): array
		{
		$caller = $this->identity($claims);
		if ($caller['user_id'] <= 0)
			{
			return [$this->error($clientId, 'not_allowed', 'Caller is not identified.')];
			}

		if ($this->findCallOf($caller) !== null)
			{
			return [$this->error($clientId, 'already_in_call', 'You are already in a call.')];
			}

		$kind = (string) ($message['kind'] ?? '');
		$chatId = 0;

		if ($kind === 'internal')
			{
			$targetUserId = (int) ($message['target_user_id'] ?? 0);
			$tenantId = $caller['tenant_id'];

			if ($caller['system'] !== 'manager' || $tenantId <= 0)
				{
				return [$this->error($clientId, 'not_allowed', 'Internal calls are only available inside a tenant.')];
				}

			if ($targetUserId <= 0 || $targetUserId === $caller['user_id'])
				{
				return [$this->error($clientId, 'invalid_target', 'Invalid call recipient.')];
				}

			if (!$this->store->voiceCallsEnabled($tenantId))
				{
				return [$this->error($clientId, 'disabled', 'Voice calls are disabled for this tenant.')];
				}

			if (!$this->store->userBelongsToTenant($targetUserId, $tenantId))
				{
				return [$this->error($clientId, 'invalid_target', 'Call recipient not found in your tenant.')];
				}

			$callee = [
				'system' => 'manager',
				'user_id' => $targetUserId,
				'tenant_id' => $tenantId,
				'name' => $this->store->userDisplayName($targetUserId),
			];
			}
		elseif ($kind === 'support')
			{
			$chatId = (int) ($message['chat_id'] ?? 0);
			$chat = $chatId > 0 ? $this->store->findChatById($chatId) : null;

			if (!$chat || (string) ($chat['status'] ?? '') === 'closed')
				{
				return [$this->error($clientId, 'chat_unavailable', 'Support chat not found or closed.')];
				}

			$tenantId = (int) ($chat['tenant_id'] ?? 0);
			$attendantUserId = (int) ($chat['attendant_user_id'] ?? 0);

			if ($caller['system'] === 'manager')
				{
				if ((int) ($chat['customer_user_id'] ?? 0) !== $caller['user_id'] || $tenantId !== $caller['tenant_id'])
					{
					return [$this->error($clientId, 'chat_unavailable', 'Support chat not found or closed.')];
					}

				if ($attendantUserId <= 0 || !$this->store->isOnlineRootUser($attendantUserId))
					{
					return [$this->error($clientId, 'attendant_unavailable', 'The support attendant is not available.')];
					}

				$callee = [
					'system' => 'manager_main',
					'user_id' => $attendantUserId,
					'tenant_id' => 0,
					'name' => (string) ($chat['attendant_name'] ?? '') !== '' ? (string) $chat['attendant_name'] : $this->store->userDisplayName($attendantUserId),
				];
				}
			elseif ($caller['system'] === 'manager_main')
				{
				if ($attendantUserId !== $caller['user_id'])
					{
					return [$this->error($clientId, 'not_attendant', 'Only the assigned attendant can call this customer.')];
					}

				$customerUserId = (int) ($chat['customer_user_id'] ?? 0);
				$callee = [
					'system' => 'manager',
					'user_id' => $customerUserId,
					'tenant_id' => $tenantId,
					'name' => (string) ($chat['customer_name'] ?? '') !== '' ? (string) $chat['customer_name'] : $this->store->userDisplayName($customerUserId),
				];
				}
			else
				{
				return [$this->error($clientId, 'not_allowed', 'Unknown system.')];
				}

			if (!$this->store->voiceCallsEnabled($tenantId))
				{
				return [$this->error($clientId, 'disabled', 'Voice calls are disabled for this tenant.')];
				}
			}
		else
			{
			return [$this->error($clientId, 'invalid_kind', 'Unknown call kind.')];
			}

		if ($this->findCallOf($callee) !== null)
			{
			return [$this->error($clientId, 'busy', 'The recipient is in another call.')];
			}

		$calleeClients = $this->clientsOf($callee, $voiceClients);
		if (empty($calleeClients))
			{
			return [$this->error($clientId, 'unavailable', 'The recipient is not connected.')];
			}

		if ($caller['name'] === '')
			{
			$caller['name'] = $this->store->userDisplayName($caller['user_id']);
			}

		$call = [
			'id' => bin2hex(random_bytes(16)),
			'kind' => $kind,
			'chat_id' => $chatId,
			'tenant_id' => $tenantId,
			'context' => $this->callContext($kind, $claims),
			'caller' => $caller,
			'callee' => $callee,
			'caller_client' => $clientId,
			'callee_client' => null,
			'status' => 'ringing',
			'created_at' => time(),
			'answered_at' => null,
		];
		$this->calls[$call['id']] = $call;
		$this->logCreated($call);

		$deliveries = [[
			'client' => $clientId,
			'payload' => [
				'type' => 'call_created',
				'call_id' => $call['id'],
				'kind' => $kind,
				'chat_id' => $chatId,
				'peer' => $this->publicIdentity($callee),
				'ring_timeout' => self::RING_TIMEOUT_SECONDS,
			],
		]];

		foreach ($calleeClients as $calleeClientId)
			{
			$deliveries[] = ['client' => $calleeClientId, 'payload' => $this->ringingPayload($call)];
			}

		return $deliveries;
		}

	/**
	 * Binds the answering socket to a ringing call.
	 *
	 * @param array<string, mixed> $claims
	 * @param array<string, mixed> $message
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function accept(int $clientId, array $claims, array $message, array $voiceClients): array
		{
		$callId = (string) ($message['call_id'] ?? '');
		$call = $this->calls[$callId] ?? null;
		$identity = $this->identity($claims);

		if ($call === null || !$this->sameIdentity($identity, $call['callee']))
			{
			return [$this->error($clientId, 'call_not_found', 'Call not found or already finished.', $callId)];
			}

		if ($call['status'] !== 'ringing')
			{
			return [$this->error($clientId, 'call_not_ringing', 'This call was already answered.', $callId)];
			}

		$call['status'] = 'active';
		$call['callee_client'] = $clientId;
		$call['answered_at'] = time();
		$this->calls[$callId] = $call;
		$this->logUpdate($call, 'active', null, true, false);

		$deliveries = [
			[
				'client' => (int) $call['caller_client'],
				'payload' => ['type' => 'call_accepted', 'call_id' => $callId],
			],
			[
				'client' => $clientId,
				'payload' => [
					'type' => 'call_joined',
					'call_id' => $callId,
					'kind' => $call['kind'],
					'chat_id' => $call['chat_id'],
					'peer' => $this->publicIdentity($call['caller']),
				],
			],
		];

		foreach ($this->clientsOf($call['callee'], $voiceClients) as $otherClientId)
			{
			if ($otherClientId !== $clientId)
				{
				$deliveries[] = $this->ringStop($otherClientId, $callId, 'answered_elsewhere');
				}
			}

		return $deliveries;
		}

	/**
	 * Declines a ringing call from any socket of the callee.
	 *
	 * @param array<string, mixed> $claims
	 * @param array<string, mixed> $message
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function reject(array $claims, array $message, array $voiceClients): array
		{
		$callId = (string) ($message['call_id'] ?? '');
		$call = $this->calls[$callId] ?? null;

		if ($call === null || $call['status'] !== 'ringing' || !$this->sameIdentity($this->identity($claims), $call['callee']))
			{
			return [];
			}

		$reason = (string) ($message['reason'] ?? 'declined');
		if (!in_array($reason, self::REJECT_REASONS, true))
			{
			$reason = 'declined';
			}

		return $this->finishRinging($call, 'rejected', $reason, $voiceClients);
		}

	/**
	 * Ends or cancels a call from one of its participant sockets.
	 *
	 * @param array<string, mixed> $message
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function hangup(int $clientId, array $message, array $voiceClients): array
		{
		$callId = (string) ($message['call_id'] ?? '');
		$call = $this->calls[$callId] ?? null;

		if ($call === null || ($call['caller_client'] !== $clientId && $call['callee_client'] !== $clientId))
			{
			return [];
			}

		return $this->leave($call, $clientId, 'hangup', $voiceClients);
		}

	/**
	 * Forwards one WebRTC description or ICE candidate to the other participant.
	 *
	 * @param array<string, mixed> $message
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function signal(int $clientId, array $message): array
		{
		$callId = (string) ($message['call_id'] ?? '');
		$call = $this->calls[$callId] ?? null;

		if ($call === null || $call['status'] !== 'active')
			{
			return [$this->error($clientId, 'call_not_found', 'Call not found or not active.', $callId)];
			}

		if ($call['caller_client'] === $clientId)
			{
			$targetClientId = (int) $call['callee_client'];
			}
		elseif ($call['callee_client'] === $clientId)
			{
			$targetClientId = (int) $call['caller_client'];
			}
		else
			{
			return [$this->error($clientId, 'not_allowed', 'You are not a participant of this call.', $callId)];
			}

		$data = $message['data'] ?? null;
		$encoded = is_array($data) ? json_encode($data) : false;
		if ($encoded === false || strlen($encoded) > self::MAX_SIGNAL_BYTES)
			{
			return [$this->error($clientId, 'invalid_signal', 'Invalid signaling payload.', $callId)];
			}

		return [[
			'client' => $targetClientId,
			'payload' => ['type' => 'rtc_signal', 'call_id' => $callId, 'data' => $data],
		]];
		}

	/**
	 * Ends a call because one participant socket left it (hang up or disconnect).
	 *
	 * @param array<string, mixed> $call
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function leave(array $call, int $clientId, string $reason, array $voiceClients): array
		{
		if ($call['status'] === 'ringing')
			{
			// Only the caller holds a participant socket while ringing.
			return $this->finishRinging($call, 'cancelled', 'cancelled', $voiceClients);
			}

		$otherClientId = $call['caller_client'] === $clientId ? (int) $call['callee_client'] : (int) $call['caller_client'];
		$this->finish($call, 'completed', $reason);

		return [[
			'client' => $otherClientId,
			'payload' => ['type' => 'call_ended', 'call_id' => $call['id'], 'reason' => $reason],
		]];
		}

	/**
	 * Ends a call that was never answered and silences every callee socket.
	 *
	 * @param array<string, mixed> $call
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, array{client: int, payload: array<string, mixed>}>
	 */
	private function finishRinging(array $call, string $status, string $reason, array $voiceClients): array
		{
		$this->finish($call, $status, $reason);

		$deliveries = [[
			'client' => (int) $call['caller_client'],
			'payload' => ['type' => 'call_ended', 'call_id' => $call['id'], 'reason' => $reason],
		]];

		foreach ($this->clientsOf($call['callee'], $voiceClients) as $calleeClientId)
			{
			$deliveries[] = $this->ringStop($calleeClientId, $call['id'], $reason);
			}

		return $deliveries;
		}

	/**
	 * Removes a call from memory and records its outcome.
	 *
	 * @param array<string, mixed> $call
	 */
	private function finish(array $call, string $status, string $reason): void
		{
		unset($this->calls[$call['id']]);
		$this->logUpdate($call, $status, $reason, false, true);
		}

	/**
	 * Records a new call without letting a log failure break the call.
	 *
	 * @param array<string, mixed> $call
	 */
	private function logCreated(array $call): void
		{
		try
			{
			$this->store->createVoiceCallLog($call);
			}
		catch (\Throwable $exception)
			{
			$this->storeError = $exception;
			}
		}

	/**
	 * Records a call state change without letting a log failure break the call.
	 *
	 * @param array<string, mixed> $call
	 */
	private function logUpdate(array $call, string $status, ?string $reason, bool $answered, bool $ended): void
		{
		$duration = $call['answered_at'] !== null ? time() - (int) $call['answered_at'] : 0;

		try
			{
			$this->store->updateVoiceCallLog((string) $call['id'], $status, $reason, $answered, $ended, $ended ? $duration : 0);
			}
		catch (\Throwable $exception)
			{
			$this->storeError = $exception;
			}
		}

	/**
	 * Returns the event that makes a callee socket ring.
	 *
	 * @param array<string, mixed> $call
	 * @return array<string, mixed>
	 */
	private function ringingPayload(array $call): array
		{
		return [
			'type' => 'call_ringing',
			'call_id' => $call['id'],
			'kind' => $call['kind'],
			'chat_id' => $call['chat_id'],
			'context' => $call['context'],
			'from' => $this->publicIdentity($call['caller']),
			'expires_in' => max(0, self::RING_TIMEOUT_SECONDS - (time() - (int) $call['created_at'])),
		];
		}

	/**
	 * Returns the event that stops a callee socket from ringing.
	 *
	 * @return array{client: int, payload: array<string, mixed>}
	 */
	private function ringStop(int $clientId, string $callId, string $reason): array
		{
		return [
			'client' => $clientId,
			'payload' => ['type' => 'call_ring_stop', 'call_id' => $callId, 'reason' => $reason],
		];
		}

	/**
	 * Returns a protocol error for the sender.
	 *
	 * @return array{client: int, payload: array<string, mixed>}
	 */
	private function error(int $clientId, string $code, string $message, string $callId = ''): array
		{
		return [
			'client' => $clientId,
			'payload' => ['type' => 'call_error', 'code' => $code, 'message' => $message, 'call_id' => $callId],
		];
		}

	/**
	 * Describes the call for the person being called.
	 *
	 * @param array<string, mixed> $claims Caller claims.
	 */
	private function callContext(string $kind, array $claims): string
		{
		if ($kind === 'internal')
			{
			return 'Chamada interna';
			}

		$tenantName = trim((string) ($claims['tenant_name'] ?? ''));
		return $tenantName !== '' ? 'Suporte · ' . $tenantName : 'Suporte';
		}

	/**
	 * Builds the identity of a voice socket. Manager users are bound to the
	 * tenant they are logged in, so a call never rings in another tenant.
	 *
	 * @param array<string, mixed> $claims
	 * @return array{system: string, user_id: int, tenant_id: int, name: string}
	 */
	private function identity(array $claims): array
		{
		$system = (string) ($claims['system'] ?? '');

		return [
			'system' => $system,
			'user_id' => (int) ($claims['user_id'] ?? 0),
			'tenant_id' => $system === 'manager' ? (int) ($claims['tenant_id'] ?? 0) : 0,
			'name' => trim((string) ($claims['user_name'] ?? '')) !== '' ? trim((string) $claims['user_name']) : trim((string) ($claims['username'] ?? '')),
		];
		}

	/**
	 * Compares two identities, ignoring the display name.
	 *
	 * @param array<string, mixed> $first
	 * @param array<string, mixed> $second
	 */
	private function sameIdentity(array $first, array $second): bool
		{
		return $first['system'] === $second['system']
			&& (int) $first['user_id'] === (int) $second['user_id']
			&& (int) $first['tenant_id'] === (int) $second['tenant_id'];
		}

	/**
	 * Returns the fields of an identity that the other participant may see.
	 *
	 * @param array<string, mixed> $identity
	 * @return array{user_id: int, name: string}
	 */
	private function publicIdentity(array $identity): array
		{
		return ['user_id' => (int) $identity['user_id'], 'name' => (string) $identity['name']];
		}

	/**
	 * Returns the voice sockets that belong to an identity.
	 *
	 * @param array<string, mixed> $identity
	 * @param array<int, array<string, mixed>> $voiceClients
	 * @return array<int, int>
	 */
	private function clientsOf(array $identity, array $voiceClients): array
		{
		$clientIds = [];
		foreach ($voiceClients as $clientId => $claims)
			{
			if ($this->sameIdentity($this->identity($claims), $identity))
				{
				$clientIds[] = (int) $clientId;
				}
			}

		return $clientIds;
		}

	/**
	 * Returns the ringing or active call of a person, if any.
	 *
	 * @param array<string, mixed> $identity
	 * @return array<string, mixed>|null
	 */
	private function findCallOf(array $identity): ?array
		{
		foreach ($this->calls as $call)
			{
			if ($this->sameIdentity($identity, $call['caller']) || $this->sameIdentity($identity, $call['callee']))
				{
				return $call;
				}
			}

		return null;
		}
	}

?>
