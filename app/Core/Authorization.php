<?php

namespace App\Core;

use App\Models\Permission;

/**
 * Authorization helper (user-only permissions).
 *
 * Permissions are cached in session at: $_SESSION['manager_main']['permissions'].
 *
 * Notes:
 * - Permissions are evaluated only from user_permissions (no groups/roles).
 * - To avoid lockout, users.type === 'root' is allowed to access the permissions manager
 *   (ability: permission.manage) even if not granted in user_permissions.
 */
class Authorization
	{
	/**
	 * Returns current authenticated user array from session, or null.
	 */
	public static function user(): ?array
		{
		return $_SESSION['manager_main']['user'] ?? null;
		}

	/**
	 * Checks if current authenticated user is root.
	 */
	public static function isRoot(): bool
		{
		$user = self::user();
		return (($user['type'] ?? '') === 'root');
		}

	/**
	 * Clears the permissions cache for the current session.
	 */
	public static function clearCache(): void
		{
		unset($_SESSION['manager_main']['permissions']);
		}

	/**
	 * Returns the current user's permissions (ability strings).
	 */
	public static function abilities(): array
		{
		$user = self::user();
		if (!$user)
			{
			return [];
			}

		$cached = $_SESSION['manager_main']['permissions'] ?? null;
		if (is_array($cached))
			{
			return $cached;
			}

		$permissionModel = new Permission();
		$abilities = $permissionModel->getAbilitiesForUser((int) ($user['id'] ?? 0));

		// Normalize
		$out = [];
		foreach ($abilities as $a)
			{
			if (is_string($a) && $a !== '')
				{
				$out[] = $a;
				}
			}

		$_SESSION['manager_main']['permissions'] = $out;
		return $out;
		}

	/**
	 * Checks if current user has an ability.
	 */
	public static function can(string $ability): bool
		{
		$user = self::user();
		if (!$user)
			{
			return false;
			}

		// Failsafe: allow root to access permission manager to prevent lockout.
		//if ($ability === 'permission.manage' && (($user['type'] ?? '') === 'root'))
		//	{
		//	return true;
		//	}

		$abilities = self::abilities();

		if (in_array('*', $abilities, true))
			{
			return true;
			}

		if (in_array($ability, $abilities, true))
			{
			return true;
			}

		// Optional wildcard support (e.g. "company.*")
		foreach ($abilities as $a)
			{
			if (!is_string($a))
				{
				continue;
				}
			if (str_ends_with($a, '.*'))
				{
				$prefix = substr($a, 0, -2);
				if ($prefix !== '' && str_starts_with($ability, $prefix . '.'))
					{
					return true;
					}
				}
			}

		return false;
		}
	}

?>
