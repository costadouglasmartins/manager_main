<?php

namespace App\Services;

class SupportChatTypingService
	{
	private const TYPING_TTL_SECONDS = 4;

	/**
	 * Stores or clears the current typing state for one chat participant.
	 */
	public static function setTyping(int $chatId, int $userId, string $role, string $userName, bool $isTyping): void
		{
		if ($chatId <= 0 || $userId <= 0)
			{
			return;
			}

		self::mutate(function (array $data) use ($chatId, $userId, $role, $userName, $isTyping): array {
			$data = self::cleanupExpired($data);
			$chatKey = (string) $chatId;
			$userKey = self::typingKey($role, $userId);

			if (!isset($data[$chatKey]) || !is_array($data[$chatKey]))
				{
				$data[$chatKey] = [];
				}

			if (!$isTyping)
				{
				unset($data[$chatKey][$userKey]);
				if (empty($data[$chatKey]))
					{
					unset($data[$chatKey]);
					}

				return $data;
				}

			$data[$chatKey][$userKey] = [
				'user_id' => $userId,
				'role' => self::normalizeRole($role),
				'user_name' => trim($userName) !== '' ? trim($userName) : ('Usuário #' . $userId),
				'updated_at' => time(),
			];

			return $data;
		});
		}

	/**
	 * Returns active typing users for one support chat.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function getTypingUsers(int $chatId): array
		{
		if ($chatId <= 0)
			{
			return [];
			}

		$data = self::readData();
		$data = self::cleanupExpired($data);
		$chatTyping = $data[(string) $chatId] ?? [];

		if (!is_array($chatTyping))
			{
			return [];
			}

		$users = [];
		foreach ($chatTyping as $typing)
			{
			if (!is_array($typing))
				{
				continue;
				}

			$users[] = [
				'user_id' => (int) ($typing['user_id'] ?? 0),
				'role' => self::normalizeRole((string) ($typing['role'] ?? '')),
				'user_name' => (string) ($typing['user_name'] ?? ''),
				'updated_at' => (int) ($typing['updated_at'] ?? 0),
			];
			}

		usort($users, static function (array $a, array $b): int {
			return strcmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
		});

		return $users;
		}

	/**
	 * Clears every typing state associated with the provided support chats.
	 *
	 * @param array<int, mixed> $chatIds
	 */
	public static function clearChats(array $chatIds): void
		{
		$chatKeys = [];
		foreach ($chatIds as $chatId)
			{
			$chatId = (int) $chatId;
			if ($chatId > 0)
				{
				$chatKeys[(string) $chatId] = true;
				}
			}

		if (empty($chatKeys))
			{
			return;
			}

		self::mutate(static function (array $data) use ($chatKeys): array {
			$data = self::cleanupExpired($data);
			foreach (array_keys($chatKeys) as $chatKey)
				{
				unset($data[$chatKey]);
				}

			return $data;
		});
		}

	/**
	 * Mutates the typing state file using an exclusive lock.
	 */
	private static function mutate(callable $callback): void
		{
		self::ensureDirectory();
		$handle = @fopen(self::filePath(), 'c+');
		if (!$handle)
			{
			return;
			}

		if (!flock($handle, LOCK_EX))
			{
			fclose($handle);
			return;
			}

		rewind($handle);
		$contents = stream_get_contents($handle);
		$data = json_decode((string) $contents, true);
		if (!is_array($data))
			{
			$data = [];
			}

		$data = $callback($data);

		rewind($handle);
		ftruncate($handle, 0);
		fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		fflush($handle);
		flock($handle, LOCK_UN);
		fclose($handle);
		}

	/**
	 * Reads the typing state file using a shared lock.
	 *
	 * @return array<string, mixed>
	 */
	private static function readData(): array
		{
		$path = self::filePath();
		if (!is_file($path))
			{
			return [];
			}

		$handle = @fopen($path, 'r');
		if (!$handle)
			{
			return [];
			}

		if (!flock($handle, LOCK_SH))
			{
			fclose($handle);
			return [];
			}

		$contents = stream_get_contents($handle);
		flock($handle, LOCK_UN);
		fclose($handle);

		$data = json_decode((string) $contents, true);
		return is_array($data) ? $data : [];
		}

	/**
	 * Removes stale typing entries from a raw typing state array.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function cleanupExpired(array $data): array
		{
		$threshold = time() - self::TYPING_TTL_SECONDS;

		foreach ($data as $chatKey => $users)
			{
			if (!is_array($users))
				{
				unset($data[$chatKey]);
				continue;
				}

			foreach ($users as $userKey => $typing)
				{
				if (!is_array($typing) || (int) ($typing['updated_at'] ?? 0) < $threshold)
					{
					unset($data[$chatKey][$userKey]);
					}
				}

			if (empty($data[$chatKey]))
				{
				unset($data[$chatKey]);
				}
			}

		return $data;
		}

	/**
	 * Returns the on-disk typing state file path.
	 */
	private static function filePath(): string
		{
		$rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
		return rtrim($rootPath, '/\\') . '/storage/support_chats/typing.json';
		}

	/**
	 * Ensures the typing state directory exists.
	 */
	private static function ensureDirectory(): void
		{
		$directory = dirname(self::filePath());
		if (!is_dir($directory))
			{
			@mkdir($directory, 0775, true);
			}
		}

	/**
	 * Returns the stable key used for one typing participant.
	 */
	private static function typingKey(string $role, int $userId): string
		{
		return self::normalizeRole($role) . ':' . $userId;
		}

	/**
	 * Normalizes support chat participant roles.
	 */
	private static function normalizeRole(string $role): string
		{
		$role = strtolower(trim($role));
		return $role === 'root' ? 'attendant' : ($role !== '' ? $role : 'customer');
		}
	}

?>
