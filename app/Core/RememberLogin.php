<?php

namespace App\Core;

use App\Models\User;

/**
 * Coordinates the persistent login lifecycle for this application.
 *
 * This class implements a "remember this browser" flow without storing
 * passwords, e-mail addresses, or full user data in the browser. The cookie
 * contains only a selector and validator pair. The selector locates the server
 * record, while the validator is hashed before it is stored in the database.
 * A valid cookie can recreate the PHP session after the browser is closed.
 */
class RememberLogin
	{
	/**
	 * Browser cookie used to carry the remember-login selector and validator.
	 */
	private const COOKIE_NAME = 'manager_main_remember';

	/**
	 * Number of days a persistent login should remain valid.
	 */
	private const COOKIE_DAYS = 30;

	/**
	 * Separator used between the selector and validator inside the cookie.
	 */
	private const COOKIE_SEPARATOR = ':';

	/**
	 * Restore the session from the remember-login cookie when possible.
	 *
	 * The method is intended to run early in the front controller, after
	 * session_start() and autoload registration but before routing. It exits
	 * quietly when the user already has a session or no remember cookie exists.
	 *
	 * Validation steps:
	 * - reject malformed cookie values;
	 * - delete expired database tokens;
	 * - match the selector to a database token;
	 * - compare the validator hash with hash_equals();
	 * - confirm that the referenced user can still log in;
	 * - rotate the token after a successful restore.
	 */
	public static function attemptFromCookie(): void
		{
		if (!empty($_SESSION['manager_main']['user']) || empty($_COOKIE[self::COOKIE_NAME]))
			{
			return;
			}

		$cookie = self::parseCookie((string) $_COOKIE[self::COOKIE_NAME]);
		if ($cookie === null)
			{
			self::clearCookie();
			return;
			}

		$userModel = new User();
		$userModel->deleteExpiredRememberTokens();

		$rememberToken = $userModel->findRememberTokenBySelector($cookie['selector']);
		if (!$rememberToken)
			{
			self::clearCookie();
			return;
			}

		if (strtotime((string) $rememberToken['expires_at']) < time())
			{
			$userModel->deleteRememberTokenById((int) $rememberToken['id']);
			self::clearCookie();
			return;
			}

		$cookieHash = hash('sha256', $cookie['validator']);
		if (!hash_equals((string) $rememberToken['token_hash'], $cookieHash))
			{
			$userModel->deleteRememberTokenBySelector($cookie['selector']);
			self::clearCookie();
			return;
			}

		$user = $userModel->findSessionUserById((int) $rememberToken['user_id']);
		if (!$user)
			{
			$userModel->deleteRememberTokenById((int) $rememberToken['id']);
			self::clearCookie();
			return;
			}

		self::storeUserInSession($user);
		self::rotateRememberToken($userModel, (int) $rememberToken['id'], (int) $user['id']);
		}

	/**
	 * Store the authenticated user payload in the PHP session.
	 *
	 * A successful password login and a successful cookie restore both end up
	 * here so the rest of the application can keep using the existing
	 * $_SESSION['manager_main']['user'] contract. The session ID is regenerated
	 * to reduce session fixation risk whenever a session is active.
	 *
	 * @param array<string, mixed> $user User data returned by the User model.
	 */
	public static function storeUserInSession(array $user): void
		{
		if (session_status() === PHP_SESSION_ACTIVE)
			{
			session_regenerate_id(true);
			}

		unset($_SESSION['manager_main']['permissions']);
		$_SESSION['manager_main']['user'] = [
			'id'       => $user['id'],
			'username' => $user['username'],
			'email'    => $user['email'],
			'type'     => $user['type'],
			'name'     => $user['name']
		];
		}

	/**
	 * Create and persist a remember-login token for the given user.
	 *
	 * The raw validator is sent only to the browser. The database receives a
	 * SHA-256 hash of that validator, which means a leaked token table is not
	 * directly enough to recreate browser cookies. Expired tokens are removed
	 * opportunistically before the new token is stored.
	 *
	 * @param array<string, mixed> $user Authenticated user data.
	 */
	public static function rememberUser(array $user): void
		{
		$userId = (int) ($user['id'] ?? 0);
		if ($userId <= 0)
			{
			return;
			}

		$userModel = new User();
		$userModel->deleteExpiredRememberTokens();

		$token = self::buildToken();
		$userModel->createRememberToken(
			$userId,
			$token['selector'],
			hash('sha256', $token['validator']),
			self::expiresAtDate($token['expires']),
			self::currentUserAgent(),
			self::currentIpAddress()
		);

		self::setCookie($token['selector'] . self::COOKIE_SEPARATOR . $token['validator'], $token['expires']);
		}

	/**
	 * Remove the remember-login token associated with the current browser.
	 *
	 * This is used when the user logs out or signs in without requesting a
	 * persistent login. If the cookie can be parsed, the matching database token
	 * is deleted first, then the browser cookie is expired.
	 */
	public static function forgetCurrentDevice(): void
		{
		if (!empty($_COOKIE[self::COOKIE_NAME]))
			{
			$cookie = self::parseCookie((string) $_COOKIE[self::COOKIE_NAME]);
			if ($cookie !== null)
				{
				$userModel = new User();
				$userModel->deleteRememberTokenBySelector($cookie['selector']);
				}
			}

		self::clearCookie();
		}

	/**
	 * Replace a consumed remember-login token with a fresh one.
	 *
	 * Token rotation limits replay opportunities: once a cookie has restored a
	 * session, the old selector/validator pair should stop working and the
	 * browser should receive a new pair with a renewed expiration date.
	 *
	 * @param User $userModel User model instance already used during restore.
	 * @param int $tokenId Existing token row id.
	 * @param int $userId Authenticated user id.
	 */
	private static function rotateRememberToken(User $userModel, int $tokenId, int $userId): void
		{
		$token = self::buildToken();
		$userModel->rotateRememberToken(
			$tokenId,
			$userId,
			$token['selector'],
			hash('sha256', $token['validator']),
			self::expiresAtDate($token['expires']),
			self::currentUserAgent(),
			self::currentIpAddress()
		);

		self::setCookie($token['selector'] . self::COOKIE_SEPARATOR . $token['validator'], $token['expires']);
		}

	/**
	 * Build a cryptographically random selector and validator pair.
	 *
	 * The selector is short enough to index efficiently and only identifies the
	 * database row. The validator is longer and acts as the secret proof that
	 * the browser owns the persistent login. Both values are hex encoded so they
	 * are safe to place in a cookie value.
	 *
	 * @return array{selector: string, validator: string, expires: int}
	 */
	private static function buildToken(): array
		{
		return [
			'selector'  => bin2hex(random_bytes(16)),
			'validator' => bin2hex(random_bytes(32)),
			'expires'   => time() + (self::COOKIE_DAYS * 24 * 60 * 60)
		];
		}

	/**
	 * Parse and strictly validate the remember-login cookie value.
	 *
	 * The cookie must contain exactly two hex-encoded components separated by
	 * COOKIE_SEPARATOR. Strict format checks prevent unexpected values from
	 * reaching database lookups or hash comparisons.
	 *
	 * @param string $value Raw cookie value received from the browser.
	 *
	 * @return array{selector: string, validator: string}|null
	 */
	private static function parseCookie(string $value): ?array
		{
		$parts = explode(self::COOKIE_SEPARATOR, $value, 2);
		if (count($parts) !== 2)
			{
			return null;
			}

		[$selector, $validator] = $parts;
		if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator))
			{
			return null;
			}

		return [
			'selector'  => $selector,
			'validator' => $validator
		];
		}

	/**
	 * Send the remember-login cookie to the browser.
	 *
	 * The cookie is scoped to the application path, marked HttpOnly so client
	 * scripts cannot read it, and uses SameSite=Lax to reduce cross-site request
	 * exposure while preserving regular navigation. The Secure flag is enabled
	 * automatically when the current request is HTTPS.
	 *
	 * @param string $value Selector and validator pair to store in the cookie.
	 * @param int $expires Unix timestamp used as the cookie expiration time.
	 */
	private static function setCookie(string $value, int $expires): void
		{
		if (headers_sent())
			{
			return;
			}

		setcookie(self::COOKIE_NAME, $value, [
			'expires'  => $expires,
			'path'     => self::cookiePath(),
			'secure'   => self::isSecureRequest(),
			'httponly' => true,
			'samesite' => 'Lax'
		]);
		$_COOKIE[self::COOKIE_NAME] = $value;
		}

	/**
	 * Expire the remember-login cookie in the browser and current request.
	 *
	 * setcookie() affects the response headers, while unsetting $_COOKIE keeps
	 * the current PHP request consistent after logout or invalid-cookie cleanup.
	 */
	private static function clearCookie(): void
		{
		if (!headers_sent())
			{
			setcookie(self::COOKIE_NAME, '', [
				'expires'  => time() - 3600,
				'path'     => self::cookiePath(),
				'secure'   => self::isSecureRequest(),
				'httponly' => true,
				'samesite' => 'Lax'
			]);
			}

		unset($_COOKIE[self::COOKIE_NAME]);
		}

	/**
	 * Resolve the application path that should receive the remember cookie.
	 *
	 * BASE_URL may include a subdirectory such as /manager_main/public/. Using
	 * that path keeps the cookie available to this application without leaking it
	 * unnecessarily to sibling applications on the same domain.
	 */
	private static function cookiePath(): string
		{
		$path = parse_url(BASE_URL, PHP_URL_PATH);
		if (!is_string($path) || $path === '')
			{
			return '/';
			}

		return rtrim($path, '/') . '/';
		}

	/**
	 * Determine whether the current request is being served over HTTPS.
	 *
	 * Direct HTTPS requests and common reverse-proxy setups are both supported.
	 * The result controls whether the remember cookie should receive the Secure
	 * attribute.
	 */
	private static function isSecureRequest(): bool
		{
		if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443))
			{
			return true;
			}

		$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
		return stripos((string) $forwardedProto, 'https') !== false;
		}

	/**
	 * Convert a Unix timestamp into the database datetime format.
	 *
	 * The token table stores expiration dates as DATETIME values, while the
	 * cookie API expects Unix timestamps.
	 *
	 * @param int $expires Unix timestamp.
	 */
	private static function expiresAtDate(int $expires): string
		{
		return date('Y-m-d H:i:s', $expires);
		}

	/**
	 * Return a normalized user-agent value for token audit metadata.
	 *
	 * The value is optional and is not used as an authentication factor. It is
	 * trimmed and capped to fit the database column.
	 */
	private static function currentUserAgent(): ?string
		{
		$userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
		if ($userAgent === '')
			{
			return null;
			}

		return substr($userAgent, 0, 255);
		}

	/**
	 * Return a normalized IP address value for token audit metadata.
	 *
	 * The value is optional and is not used as an authentication factor. It is
	 * capped to 45 characters so both IPv4 and IPv6 addresses fit the database
	 * column safely.
	 */
	private static function currentIpAddress(): ?string
		{
		$ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
		if ($ipAddress === '')
			{
			return null;
			}

		return substr($ipAddress, 0, 45);
		}
	}
?>