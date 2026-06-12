<?php

namespace App\Services;

/**
 * ViaCEP postal code lookup service.
 */
class ViaCepService
	{
	private const BASE_URL = 'https://viacep.com.br/ws/';
	private const TIMEOUT_SECONDS = 5;
	private const MAX_ATTEMPTS = 3;
	private const RETRY_DELAY_MICROSECONDS = 250000;
	private const USER_AGENT = 'Manager ZipCodeCrawler/1.0';

	/**
	 * Look up a postal code using ViaCEP and normalize the response payload.
	 * @param string $zipCode Postal code informed by the caller.
	 * @return array Lookup result with normalized address data or an error payload.
	 **/
	public function lookup(string $zipCode): array
		{
		$digits = $this->onlyDigits($zipCode);

		if (strlen($digits) !== 8)
			{
			return $this->failure('invalid_zip_code', 'Informe um CEP válido com 8 dígitos.', 422);
			}

		$response = [];
		for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++)
			{
			$response = $this->fetch(self::BASE_URL . $digits . '/json/');
			$response['attempts'] = $attempt;

			if (!empty($response['ok']) || !$this->shouldRetryResponse($response) || $attempt === self::MAX_ATTEMPTS)
				{
				break;
				}

			usleep(self::RETRY_DELAY_MICROSECONDS * $attempt);
			}

		if (!$response['ok'])
			{
			return $this->failure('lookup_failed', 'Não foi possível consultar o CEP no ViaCEP agora.', 502, $this->diagnosticsFromResponse($response, $digits));
			}

		$data = json_decode((string) $response['body'], true);
		if (!is_array($data))
			{
			return $this->failure('invalid_response', 'A resposta do ViaCEP é inválida.', 502);
			}

		if (!empty($data['erro']))
			{
			return $this->failure('zip_code_not_found', 'CEP não encontrado no ViaCEP.', 404);
			}

		return [
			'success' => true,
			'status'  => 200,
			'source'  => 'viacep',
			'data'    => $this->normalize($data),
		];
		}

	/**
	 * Remove every non-digit character from the provided value.
	 * @param string $value Raw value that may contain non-digit characters.
	 * @return string Value containing digits only.
	 **/
	private function onlyDigits(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Fetch the provided URL using the available HTTP transport.
	 * @param string $url Endpoint URL to be requested.
	 * @return array Transport result with request metadata and response body.
	 **/
	private function fetch(string $url): array
		{
		if (function_exists('curl_init'))
			{
			$response = $this->fetchWithCurl($url);
			if (!$response['ok'] && $this->shouldFallbackToStream($response))
				{
				$streamResponse = $this->fetchWithStream($url);
				$streamResponse['fallback_from_curl'] = [
					'status' => (int) ($response['status'] ?? 0),
					'curl_errno' => (int) ($response['curl_errno'] ?? 0),
					'curl_error' => (string) ($response['curl_error'] ?? ''),
				];
				return $streamResponse;
				}

			return $response;
			}

		return $this->fetchWithStream($url);
		}

	/**
	 * Fetch the provided URL using cURL when the extension is available.
	 * @param string $url Endpoint URL to be requested.
	 * @return array Transport result with cURL diagnostics and response body.
	 **/
	private function fetchWithCurl(string $url): array
		{
		$ch = curl_init($url);
		if ($ch === false)
			{
			return ['ok' => false, 'status' => 0, 'body' => null];
			}

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
			CURLOPT_USERAGENT      => self::USER_AGENT,
			CURLOPT_HTTPHEADER     => ['Accept: application/json'],
		];

		if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4'))
			{
			$options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
			}

		curl_setopt_array($ch, $options);

		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_errno($ch);
		$errorMessage = $error !== 0 ? curl_error($ch) : '';
		if (PHP_VERSION_ID < 80500)
			{
			curl_close($ch);
			}

		return [
			'ok'     => ($error === 0 && $status >= 200 && $status < 300 && is_string($body)),
			'transport' => 'curl',
			'url' => $url,
			'status' => $status,
			'curl_errno' => $error,
			'curl_error' => $errorMessage,
			'body'   => is_string($body) ? $body : null,
		];
		}

	/**
	 * Fetch the provided URL using the stream wrapper fallback.
	 * @param string $url Endpoint URL to be requested.
	 * @return array Transport result with HTTP status and response body.
	 **/
	private function fetchWithStream(string $url): array
		{
		$context = stream_context_create([
			'http' => [
				'ignore_errors' => true,
				'method'        => 'GET',
				'timeout'       => self::TIMEOUT_SECONDS,
				'header'        => "Accept: application/json\r\nUser-Agent: " . self::USER_AGENT . "\r\n",
			],
		]);

		$body = @file_get_contents($url, false, $context);
		$status = $this->statusFromHeaders($http_response_header ?? []);

		return [
			'ok'     => ($status >= 200 && $status < 300 && is_string($body)),
			'transport' => 'stream',
			'url' => $url,
			'status' => $status,
			'curl_errno' => 0,
			'curl_error' => '',
			'body'   => is_string($body) ? $body : null,
		];
		}

	/**
	 * Extract the HTTP status code from the response headers.
	 * @param array $headers Response headers returned by the stream wrapper.
	 * @return int Parsed HTTP status code or zero when unavailable.
	 **/
	private function statusFromHeaders(array $headers): int
		{
		$statusLine = $headers[0] ?? '';
		if (preg_match('/\s(\d{3})\s/', $statusLine, $matches))
			{
			return (int) $matches[1];
			}

		return 0;
		}

	/**
	 * Normalize the ViaCEP response into the project address structure.
	 * @param array $data Raw response data returned by ViaCEP.
	 * @return array Normalized address data expected by the application.
	 **/
	private function normalize(array $data): array
		{
		$state = strtoupper((string) ($data['uf'] ?? ''));

		return [
			'zip_code'     => (string) ($data['cep'] ?? ''),
			'street'       => (string) ($data['logradouro'] ?? ''),
			'complement'   => (string) ($data['complemento'] ?? ''),
			'neighborhood' => (string) ($data['bairro'] ?? ''),
			'city'         => (string) ($data['localidade'] ?? ''),
			'state'        => $state,
			'state_name'   => (string) ($data['estado'] ?? $state),
			'region'       => (string) ($data['regiao'] ?? ''),
			'ibge'         => (string) ($data['ibge'] ?? ''),
			'ddd'          => (string) ($data['ddd'] ?? ''),
			'siafi'        => (string) ($data['siafi'] ?? ''),
		];
		}

	/**
	 * Build diagnostic data based on the transport response.
	 * @param array $response Transport response metadata.
	 * @param string $digits Sanitized postal code used in the request.
	 * @return array Diagnostic payload for troubleshooting failed lookups.
	 **/
	private function diagnosticsFromResponse(array $response, string $digits): array
		{
		return [
			'request_zip_code' => $digits,
			'request_url' => (string) ($response['url'] ?? ''),
			'transport' => (string) ($response['transport'] ?? ''),
			'attempts' => (int) ($response['attempts'] ?? 1),
			'http_status' => (int) ($response['status'] ?? 0),
			'curl_errno' => (int) ($response['curl_errno'] ?? 0),
			'curl_error' => (string) ($response['curl_error'] ?? ''),
			'fallback_from_curl' => $response['fallback_from_curl'] ?? null,
		];
		}

	/**
	 * Determine whether the transport response should be retried.
	 * @param array $response Transport response metadata.
	 * @return bool True when the lookup should be retried.
	 **/
	private function shouldRetryResponse(array $response): bool
		{
		$curlErrorNumber = (int) ($response['curl_errno'] ?? 0);
		$status = (int) ($response['status'] ?? 0);

		return in_array($curlErrorNumber, [6, 7, 28, 35, 52, 56], true)
			|| $status === 0
			|| $status >= 500;
		}

	/**
	 * Determine whether the request should fallback from cURL to stream transport.
	 * @param array $response Transport response metadata.
	 * @return bool True when the fallback transport should be attempted.
	 **/
	private function shouldFallbackToStream(array $response): bool
		{
		$curlErrorNumber = (int) ($response['curl_errno'] ?? 0);
		$status = (int) ($response['status'] ?? 0);

		return $status === 0 || in_array($curlErrorNumber, [6, 7, 28, 35, 52, 56], true);
		}

	/**
	 * Build the standardized failure payload returned by the service.
	 * @param string $error Error identifier for the failure scenario.
	 * @param string $message User-facing error message.
	 * @param int $status HTTP status code associated with the failure.
	 * @param array $diagnostics Optional diagnostics included in the response.
	 * @return array Failure payload following the service response contract.
	 **/
	private function failure(string $error, string $message, int $status, array $diagnostics = []): array
		{
		$payload = [
			'success' => false,
			'status'  => $status,
			'source'  => 'viacep',
			'error'   => $error,
			'message' => $message,
		];

		if (!empty($diagnostics))
			{
			$payload['diagnostics'] = $diagnostics;
			}

		return $payload;
		}
	}

?>
