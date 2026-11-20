<?php

namespace App\Services;

/**
 * Builds the ICE server list used by browsers to connect a voice call.
 *
 * STUN discovers public addresses; TURN relays the audio when a direct path
 * fails. TURN credentials are short-lived and follow the coturn
 * "use-auth-secret" scheme: username = "<expiry>:<user id>" and
 * credential = base64(HMAC-SHA1(secret, username)).
 *
 * Settings come from the environment:
 * - VOICE_CALL_STUN_URLS: comma-separated STUN URLs.
 * - VOICE_CALL_TURN_URLS: comma-separated TURN URLs (empty disables TURN).
 * - VOICE_CALL_TURN_SECRET: coturn static-auth-secret.
 */
class VoiceCallIceService
	{
	private const CREDENTIAL_TTL_SECONDS = 3600;
	private const DEFAULT_STUN_URLS = 'stun:stun.l.google.com:19302';

	/**
	 * Returns the RTCPeerConnection iceServers list for one user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function iceServers(int $userId): array
		{
		$servers = [];

		$stunUrls = self::urls('VOICE_CALL_STUN_URLS', self::DEFAULT_STUN_URLS);
		if ($stunUrls !== [])
			{
			$servers[] = ['urls' => $stunUrls];
			}

		$turnUrls = self::urls('VOICE_CALL_TURN_URLS', '');
		$secret = trim((string) (getenv('VOICE_CALL_TURN_SECRET') ?: ''));
		if ($turnUrls !== [] && $secret !== '')
			{
			$username = (time() + self::CREDENTIAL_TTL_SECONDS) . ':' . $userId;
			$servers[] = [
				'urls' => $turnUrls,
				'username' => $username,
				'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
			];
			}

		return $servers;
		}

	/**
	 * Reads a comma-separated URL list from the environment.
	 *
	 * @return array<int, string>
	 */
	private static function urls(string $name, string $default): array
		{
		$value = getenv($name);
		$value = $value === false ? $default : (string) $value;

		return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($url) => $url !== ''));
		}
	}

?>
