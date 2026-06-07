<?php

namespace App\SupportChat;

class SupportChatToken
	{
	/**
	 * Verifies a support chat token and returns its claims.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function verify(string $token, string $secret): ?array
		{
		$parts = explode('.', trim($token), 2);
		if (count($parts) !== 2)
			{
			return null;
			}

		$payloadEncoded = $parts[0];
		$signatureEncoded = $parts[1];
		$expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret, true);
		$providedSignature = self::base64UrlDecode($signatureEncoded);

		if ($providedSignature === null || !hash_equals($expectedSignature, $providedSignature))
			{
			return null;
			}

		$payloadJson = self::base64UrlDecode($payloadEncoded);
		if ($payloadJson === null)
			{
			return null;
			}

		$claims = json_decode($payloadJson, true);
		if (!is_array($claims))
			{
			return null;
			}

		if (!isset($claims['exp']) || (int) $claims['exp'] < time())
			{
			return null;
			}

		return $claims;
		}

	/**
	 * Decodes a base64url string.
	 */
	private static function base64UrlDecode(string $value): ?string
		{
		$padded = strtr($value, '-_', '+/');
		$padding = strlen($padded) % 4;
		if ($padding > 0)
			{
			$padded .= str_repeat('=', 4 - $padding);
			}

		$decoded = base64_decode($padded, true);
		return is_string($decoded) ? $decoded : null;
		}
	}

?>
