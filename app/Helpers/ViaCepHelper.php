<?php

namespace App\Helpers;

/**
 * Helper for ViaCEP postal code lookups.
 *
 * ViaCEP expects an eight-digit CEP and returns JSON address data. This helper
 * centralizes validation, transport and response normalization so controllers
 * can use the same lookup behavior across the application.
 */
class ViaCepHelper
	{
	private const BASE_URL = 'https://viacep.com.br/ws/';
	private const TIMEOUT_SECONDS = 5;

	/**
	 * Looks up a Brazilian postal code through ViaCEP.
	 *
	 * @param string $zipCode CEP value with or without mask.
	 * @return array<string, mixed>
	 */
	public static function lookup(string $zipCode): array
		{
		$digits = self::onlyDigits($zipCode);

		if (strlen($digits) !== 8)
			{
			return self::failure('invalid_zip_code', 'Informe um CEP válido com 8 dígitos.', 422);
			}

		$response = self::fetch(self::BASE_URL . $digits . '/json/');
		if (!$response['ok'])
			{
			return self::failure('lookup_failed', 'Não foi possível consultar o CEP agora.', 502);
			}

		$data = json_decode((string) $response['body'], true);
		if (!is_array($data))
			{
			return self::failure('invalid_response', 'A resposta do serviço de CEP é inválida.', 502);
			}

		if (!empty($data['erro']))
			{
			return self::failure('zip_code_not_found', 'CEP não encontrado.', 404);
			}

		return [
			'success' => true,
			'status'  => 200,
			'data'    => self::normalize($data),
		];
		}

	/**
	 * Keeps only digits from a value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function onlyDigits(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Fetches a URL with cURL when available, falling back to file_get_contents.
	 *
	 * @param string $url URL to fetch.
	 * @return array{ok: bool, status: int, body: string|null}
	 */
	private static function fetch(string $url): array
		{
		if (function_exists('curl_init'))
			{
			return self::fetchWithCurl($url);
			}

		return self::fetchWithStream($url);
		}

	/**
	 * Fetches a URL using cURL.
	 *
	 * @param string $url URL to fetch.
	 * @return array{ok: bool, status: int, body: string|null}
	 */
	private static function fetchWithCurl(string $url): array
		{
		$ch = curl_init($url);
		if ($ch === false)
			{
			return ['ok' => false, 'status' => 0, 'body' => null];
			}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
			CURLOPT_HTTPHEADER     => ['Accept: application/json'],
		]);

		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_errno($ch);
		curl_close($ch);

		return [
			'ok'     => ($error === 0 && $status >= 200 && $status < 300 && is_string($body)),
			'status' => $status,
			'body'   => is_string($body) ? $body : null,
		];
		}

	/**
	 * Fetches a URL using PHP streams.
	 *
	 * @param string $url URL to fetch.
	 * @return array{ok: bool, status: int, body: string|null}
	 */
	private static function fetchWithStream(string $url): array
		{
		$context = stream_context_create([
			'http' => [
				'ignore_errors' => true,
				'method'        => 'GET',
				'timeout'       => self::TIMEOUT_SECONDS,
				'header'        => "Accept: application/json\r\n",
			],
		]);

		$body = @file_get_contents($url, false, $context);
		$status = self::statusFromHeaders($http_response_header ?? []);

		return [
			'ok'     => ($status >= 200 && $status < 300 && is_string($body)),
			'status' => $status,
			'body'   => is_string($body) ? $body : null,
		];
		}

	/**
	 * Extracts the HTTP status code from response headers.
	 *
	 * @param array<int, string> $headers Response headers.
	 * @return int
	 */
	private static function statusFromHeaders(array $headers): int
		{
		$statusLine = $headers[0] ?? '';
		if (preg_match('/\s(\d{3})\s/', $statusLine, $matches))
			{
			return (int) $matches[1];
			}

		return 0;
		}

	/**
	 * Normalizes ViaCEP response keys to application-friendly names.
	 *
	 * @param array<string, mixed> $data ViaCEP response data.
	 * @return array<string, mixed>
	 */
	private static function normalize(array $data): array
		{
		return [
			'zip_code'     => (string) ($data['cep'] ?? ''),
			'street'       => (string) ($data['logradouro'] ?? ''),
			'complement'   => (string) ($data['complemento'] ?? ''),
			'neighborhood' => (string) ($data['bairro'] ?? ''),
			'city'         => (string) ($data['localidade'] ?? ''),
			'state'        => (string) ($data['uf'] ?? ''),
			'state_name'   => (string) ($data['estado'] ?? ''),
			'region'       => (string) ($data['regiao'] ?? ''),
			'ibge'         => (string) ($data['ibge'] ?? ''),
			'ddd'          => (string) ($data['ddd'] ?? ''),
			'siafi'        => (string) ($data['siafi'] ?? ''),
		];
		}

	/**
	 * Builds a normalized failure response.
	 *
	 * @param string $error Error code.
	 * @param string $message User-facing message.
	 * @param int $status HTTP status code.
	 * @return array<string, mixed>
	 */
	private static function failure(string $error, string $message, int $status): array
		{
		return [
			'success' => false,
			'status'  => $status,
			'error'   => $error,
			'message' => $message,
		];
		}
	}

?>
