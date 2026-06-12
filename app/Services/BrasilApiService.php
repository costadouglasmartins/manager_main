<?php

namespace App\Services;

/**
 * BrasilAPI postal code lookup service.
 */
class BrasilApiService
	{
	private const BASE_URL = 'https://brasilapi.com.br/api/cep/v1/';
	private const TIMEOUT_SECONDS = 5;
	private const USER_AGENT = 'Manager ZipCodeCrawler/1.0';

	private const STATE_NAMES = [
		'AC' => 'Acre',
		'AL' => 'Alagoas',
		'AM' => 'Amazonas',
		'AP' => 'Amapá',
		'BA' => 'Bahia',
		'CE' => 'Ceará',
		'DF' => 'Distrito Federal',
		'ES' => 'Espírito Santo',
		'GO' => 'Goiás',
		'MA' => 'Maranhão',
		'MG' => 'Minas Gerais',
		'MS' => 'Mato Grosso do Sul',
		'MT' => 'Mato Grosso',
		'PA' => 'Pará',
		'PB' => 'Paraíba',
		'PE' => 'Pernambuco',
		'PI' => 'Piauí',
		'PR' => 'Paraná',
		'RJ' => 'Rio de Janeiro',
		'RN' => 'Rio Grande do Norte',
		'RO' => 'Rondônia',
		'RR' => 'Roraima',
		'RS' => 'Rio Grande do Sul',
		'SC' => 'Santa Catarina',
		'SE' => 'Sergipe',
		'SP' => 'São Paulo',
		'TO' => 'Tocantins',
	];

	/**
	 * Look up a postal code using BrasilAPI and normalize the response payload.
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

		$response = $this->fetch(self::BASE_URL . $digits);
		if (!$response['ok'])
			{
			if ((int) ($response['status'] ?? 0) === 404)
				{
				return $this->failure('zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
				}

			return $this->failure('lookup_failed', 'Não foi possível consultar o CEP na BrasilAPI agora.', 502, $this->diagnosticsFromResponse($response, $digits));
			}

		$data = json_decode((string) $response['body'], true);
		if (!is_array($data))
			{
			return $this->failure('invalid_response', 'A resposta da BrasilAPI é inválida.', 502);
			}

		$normalized = $this->normalize($data);
		if ($normalized['zip_code'] === '')
			{
			return $this->failure('zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
			}

		return [
			'success' => true,
			'status'  => 200,
			'source'  => 'brasilapi',
			'data'    => $normalized,
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
			return $this->fetchWithCurl($url);
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

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
			CURLOPT_USERAGENT      => self::USER_AGENT,
			CURLOPT_HTTPHEADER     => ['Accept: application/json'],
		]);

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
	 * Normalize the BrasilAPI response into the project address structure.
	 * @param array $data Raw response data returned by BrasilAPI.
	 * @return array Normalized address data expected by the application.
	 **/
	private function normalize(array $data): array
		{
		$state = strtoupper((string) ($data['state'] ?? ''));

		return [
			'zip_code'     => $this->formatZipCode((string) ($data['cep'] ?? '')),
			'street'       => (string) ($data['street'] ?? ''),
			'complement'   => '',
			'neighborhood' => (string) ($data['neighborhood'] ?? ''),
			'city'         => (string) ($data['city'] ?? ''),
			'state'        => $state,
			'state_name'   => self::STATE_NAMES[$state] ?? $state,
			'region'       => $this->regionFromState($state),
			'ibge'         => (string) ($data['city_ibge_code'] ?? ''),
			'ddd'          => (string) ($data['ddd'] ?? ''),
			'siafi'        => '',
		];
		}

	/**
	 * Format a postal code into the standard XXXXX-XXX representation.
	 * @param string $value Postal code value that may contain extra characters.
	 * @return string Formatted postal code or an empty string when invalid.
	 **/
	private function formatZipCode(string $value): string
		{
		$digits = $this->onlyDigits($value);
		if (strlen($digits) !== 8)
			{
			return '';
			}

		return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $digits;
		}

	/**
	 * Resolve the Brazilian region name from the state abbreviation.
	 * @param string $state Two-letter state abbreviation.
	 * @return string Region name associated with the state.
	 **/
	private function regionFromState(string $state): string
		{
		$regions = [
			'AC' => 'Norte',
			'AP' => 'Norte',
			'AM' => 'Norte',
			'PA' => 'Norte',
			'RO' => 'Norte',
			'RR' => 'Norte',
			'TO' => 'Norte',
			'AL' => 'Nordeste',
			'BA' => 'Nordeste',
			'CE' => 'Nordeste',
			'MA' => 'Nordeste',
			'PB' => 'Nordeste',
			'PE' => 'Nordeste',
			'PI' => 'Nordeste',
			'RN' => 'Nordeste',
			'SE' => 'Nordeste',
			'DF' => 'Centro-Oeste',
			'GO' => 'Centro-Oeste',
			'MT' => 'Centro-Oeste',
			'MS' => 'Centro-Oeste',
			'ES' => 'Sudeste',
			'MG' => 'Sudeste',
			'RJ' => 'Sudeste',
			'SP' => 'Sudeste',
			'PR' => 'Sul',
			'RS' => 'Sul',
			'SC' => 'Sul',
		];

		return $regions[$state] ?? '';
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
			'http_status' => (int) ($response['status'] ?? 0),
			'curl_errno' => (int) ($response['curl_errno'] ?? 0),
			'curl_error' => (string) ($response['curl_error'] ?? ''),
		];
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
			'source'  => 'brasilapi',
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
