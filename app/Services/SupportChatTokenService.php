<?php

namespace App\Services;

class SupportChatTokenService
	{
	/**
	 * Returns the shared secret used by HTTP endpoints and the WebSocket server.
	 */
	private static function secret(): string
		{
		$secret = getenv('SUPPORT_CHAT_SECRET') ?: 'douglas201183mc695500..';
		return (string) $secret;
		}

	/**
	 * Creates a signed token for WebSocket authentication.
	 *
	 * @param array<string, mixed> $claims
	 */
	public static function issue(array $claims): string
		{
		$payload = $claims;
		$payload['iat'] = time();
		$payload['exp'] = time() + 3600;

		$encodedPayload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$signature = hash_hmac('sha256', $encodedPayload, self::secret(), true);

		return $encodedPayload . '.' . self::base64UrlEncode($signature);
		}

	/**
	 * Returns the configured WebSocket URL.
	 */
	public static function websocketUrl(): string
		{
		$configured = trim((string) (getenv('SUPPORT_CHAT_WS_URL') ?: ''));
		if ($configured !== '')
			{
			return $configured;
			}

		$scheme = 'ws';
		if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443))
			{
			$scheme = 'wss';
			}

		$host = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
		$host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';

		return $scheme . '://' . $host . ':8081';
		}

	/**
	 * Encodes binary/string payload as base64url.
	 */
	private static function base64UrlEncode(string $value): string
		{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
		}
	}

?>
