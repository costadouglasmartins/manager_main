<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Person;
use App\Models\User;
use PDO;

/**
 * Authorizes short-lived edits of protected people, users and person-related records.
 **/
class ProtectedEditAuthorizationService
	{
	private const AUTHORIZER_USER_ID = 1;
	private const AUTHORIZATION_TTL_SECONDS = 600;
	private const ATTEMPT_WINDOW_SECONDS = 300;
	private const MAX_ATTEMPTS = 5;
	private const AUTHORIZATION_SESSION_KEY = 'protected_edit_authorizations';
	private const ATTEMPT_SESSION_KEY = 'protected_edit_attempts';
	private const PERSON_RELATED_RESOURCE_TABLES = [
		'address' => 'addresses',
		'email' => 'emails',
		'phone_number' => 'phone_numbers',
	];

	private PDO $db;

	/**
	 * Creates the authorization service using the central database connection.
	 * @param PDO|null $db Optional active database connection.
	 **/
	public function __construct(?PDO $db = null)
		{
		$this->db = $db ?? Database::getInstance();
		}

	/**
	 * Returns whether the resource ID requires password authorization.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Resource record ID.
	 * @return bool True when the record is protected.
	 **/
	public function isProtected(string $resource, int $recordId): bool
		{
		$resource = $this->normalizeResource($resource);
		if ($resource === 'user')
			{
			return in_array($recordId, User::PROTECTED_USER_IDS, true);
			}
		if ($resource === 'person')
			{
			return in_array($recordId, Person::PROTECTED_PERSON_IDS, true);
			}

		$table = self::PERSON_RELATED_RESOURCE_TABLES[$resource];
		$stmt = $this->db->prepare("SELECT person_id FROM {$table} WHERE id = :id LIMIT 1");
		$stmt->execute(['id' => $recordId]);
		$personId = $stmt->fetchColumn();

		return $personId !== false
			&& in_array((int) $personId, Person::PROTECTED_PERSON_IDS, true);
		}

	/**
	 * Verifies user 1's password and grants a short-lived edit authorization.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Protected record ID.
	 * @param string $password Password informed by the operator.
	 * @return bool True when authorization was granted.
	 **/
	public function authorize(string $resource, int $recordId, string $password): bool
		{
		$resource = $this->normalizeResource($resource);
		if (!$this->isProtected($resource, $recordId))
			{
			throw new \InvalidArgumentException('O registro informado não exige autorização especial.');
			}

		$this->ensureSession();
		if ($this->isRateLimited($resource, $recordId))
			{
			return false;
			}

		$stmt = $this->db->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
		$stmt->execute(['id' => self::AUTHORIZER_USER_ID]);
		$passwordHash = $stmt->fetchColumn();

		if (!is_string($passwordHash) || $passwordHash === '' || !password_verify($password, $passwordHash))
			{
			$this->registerFailedAttempt($resource, $recordId);
			return false;
			}

		$currentUserId = (int) ($_SESSION['manager_main']['user']['id'] ?? 0);
		$_SESSION['manager_main'][self::AUTHORIZATION_SESSION_KEY][$resource][(string) $recordId] = [
			'expires_at' => time() + self::AUTHORIZATION_TTL_SECONDS,
			'authorized_user_id' => $currentUserId,
		];
		unset($_SESSION['manager_main'][self::ATTEMPT_SESSION_KEY][$resource][(string) $recordId]);

		return true;
		}

	/**
	 * Checks a valid authorization for the current authenticated user.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Protected record ID.
	 * @return bool True when the edit is currently authorized.
	 **/
	public function isAuthorized(string $resource, int $recordId): bool
		{
		$resource = $this->normalizeResource($resource);
		if (!$this->isProtected($resource, $recordId))
			{
			return true;
			}

		$this->ensureSession();
		$entry = $_SESSION['manager_main'][self::AUTHORIZATION_SESSION_KEY][$resource][(string) $recordId] ?? null;
		$currentUserId = (int) ($_SESSION['manager_main']['user']['id'] ?? 0);

		if (!is_array($entry)
			|| (int) ($entry['expires_at'] ?? 0) < time()
			|| (int) ($entry['authorized_user_id'] ?? 0) !== $currentUserId)
			{
			unset($_SESSION['manager_main'][self::AUTHORIZATION_SESSION_KEY][$resource][(string) $recordId]);
			return false;
			}

		return true;
		}

	/**
	 * Revokes the authorization after a successful protected update.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Protected record ID.
	 * @return void
	 **/
	public function revoke(string $resource, int $recordId): void
		{
		$resource = $this->normalizeResource($resource);
		$this->ensureSession();
		unset($_SESSION['manager_main'][self::AUTHORIZATION_SESSION_KEY][$resource][(string) $recordId]);
		}

	/**
	 * Validates and normalizes a supported resource name.
	 * @param string $resource Resource name.
	 * @return string Normalized resource name.
	 **/
	private function normalizeResource(string $resource): string
		{
		$resource = strtolower(trim($resource));
		$supportedResources = array_merge(['user', 'person'], array_keys(self::PERSON_RELATED_RESOURCE_TABLES));
		if (!in_array($resource, $supportedResources, true))
			{
			throw new \InvalidArgumentException('Recurso de edição protegida inválido.');
			}
		return $resource;
		}

	/**
	 * Returns whether the session reached the failed-attempt limit.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Protected record ID.
	 * @return bool True when further attempts must be refused temporarily.
	 **/
	private function isRateLimited(string $resource, int $recordId): bool
		{
		$attempts = $this->recentAttempts($resource, $recordId);
		$_SESSION['manager_main'][self::ATTEMPT_SESSION_KEY][$resource][(string) $recordId] = $attempts;
		return count($attempts) >= self::MAX_ATTEMPTS;
		}

	/**
	 * Registers a failed password verification in the current session.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Protected record ID.
	 * @return void
	 **/
	private function registerFailedAttempt(string $resource, int $recordId): void
		{
		$attempts = $this->recentAttempts($resource, $recordId);
		$attempts[] = time();
		$_SESSION['manager_main'][self::ATTEMPT_SESSION_KEY][$resource][(string) $recordId] = $attempts;
		}

	/**
	 * Returns only failed attempts inside the active rate-limit window.
	 * @param string $resource Supported resource name.
	 * @param int $recordId Protected record ID.
	 * @return array<int, int> Recent attempt timestamps.
	 **/
	private function recentAttempts(string $resource, int $recordId): array
		{
		$attempts = $_SESSION['manager_main'][self::ATTEMPT_SESSION_KEY][$resource][(string) $recordId] ?? [];
		$minimumTimestamp = time() - self::ATTEMPT_WINDOW_SECONDS;
		return array_values(array_filter(
			is_array($attempts) ? $attempts : [],
			static fn ($timestamp): bool => (int) $timestamp >= $minimumTimestamp
		));
		}

	/**
	 * Ensures the manager session namespace exists.
	 * @return void
	 **/
	private function ensureSession(): void
		{
		if (session_status() === PHP_SESSION_NONE)
			{
			session_start();
			}
		if (!isset($_SESSION['manager_main']) || !is_array($_SESSION['manager_main']))
			{
			$_SESSION['manager_main'] = [];
			}
		}
	}
